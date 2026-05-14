<?php
declare(strict_types=1);

namespace Controllers;

use Core\{Response, Session, RateLimiter, Cache};
use Models\{User, Transaction, Abonnement};

class PaiementController
{
    public function initier(): void
    {
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['success' => false, 'error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        $rlKey = "paiement_initier:{$userId}";
        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            Response::json(['success' => false, 'error' => ['code' => 'RATE_LIMIT', 'message' => 'Trop de tentatives. Attendez quelques minutes.']], 429);
        }
        RateLimiter::hit($rlKey, 600);

        $user = (new User())->findById($userId);
        if (!$user) {
            Response::json(['success' => false, 'error' => ['code' => 'USER_NOT_FOUND']], 404);
        }

        $redis = \Core\Redis::getInstance();

        // Anti-doublon : si payment_pending actif → retourner le même lien
        if ($redis->isAvailable() && $redis->exists("payment_pending:{$userId}")) {
            $cached = json_decode($redis->get("payment_pending:{$userId}"), true);
            $cachedUrl  = $cached['payment_url'] ?? $_ENV['MONEYFUSION_PAYMENT_LINK'];
            $cachedMfTk = $cached['mf_token']    ?? null;
            if (!empty($cached['tx_id'])) {
                $this->setTxCookie((int) $cached['tx_id']);
            }
            Response::json(['success' => true, 'payment_url' => $cachedUrl, 'mf_token' => $cachedMfTk]);
        }

        $reference = sprintf('CA-%d-%d-%s', $userId, time(), strtoupper(bin2hex(random_bytes(2))));

        // Lien statique MoneyFusion (le token de session est créé par MF au moment de la visite,
        // puis passé dans ?token= lors du redirect vers /paiement/retour)
        $paymentUrl = $_ENV['MONEYFUSION_PAYMENT_LINK'];
        $mfToken    = null;

        // Créer transaction en BDD
        $txId = (new Transaction())->creer($userId, 'mensuel', 2000.00, $reference, $mfToken);

        // Cookie signé pour identifier la transaction même si session expire
        $this->setTxCookie($txId);

        if ($redis->isAvailable()) {
            $redis->set("payment_pending:{$userId}", json_encode([
                'reference'   => $reference,
                'tx_id'       => $txId,
                'mf_token'    => $mfToken,
                'payment_url' => $paymentUrl,
                'email'       => $user['email'],
                'plan'        => 'mensuel',
                'ts'          => time(),
            ]), 1800);
        }

        error_log("[Paiement] Initié — user:{$userId} ref:{$reference} tx:{$txId} mf_token:{$mfToken} url:{$paymentUrl}");

        Response::json(['success' => true, 'payment_url' => $paymentUrl, 'mf_token' => $mfToken]);
    }

    /**
     * Crée une session de paiement via l'API MoneyFusion.
     * Retourne ['url' => ..., 'token' => ...] ou [] si erreur (fallback lien statique).
     */
    private function creerSessionMF(array $user, string $reference): array
    {
        $apiUrl     = rtrim($_ENV['MONEYFUSION_API_URL'] ?? 'https://www.pay.moneyfusion.net/paiement/paiement_api/', '/') . '/';
        $mfToken    = $_ENV['MONEYFUSION_TOKEN'] ?? '';
        $appUrl     = rtrim($_ENV['APP_URL'] ?? '', '/');
        $webhookUrl = $appUrl . '/api/paiement/callback';
        $returnUrl  = $appUrl . '/paiement/retour';
        $nom        = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?: 'Client';

        if (empty($mfToken)) {
            error_log('[Paiement] MONEYFUSION_TOKEN non configuré — fallback lien statique');
            return [];
        }

        $postFields = [
            'token_yoo'        => $mfToken,
            'montant'          => '2000',
            'nom_client'       => $nom,
            'client_telephone' => '000000000',
            'webhook_url'      => $webhookUrl,
            'success_url'      => $returnUrl,
            'error_url'        => $returnUrl . '?error=1',
            'personal_id'      => $reference,
        ];

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$raw) {
            error_log("[Paiement] cURL MF API error: {$err}");
            return [];
        }

        $resp = json_decode($raw, true);
        error_log("[Paiement] MF API response: {$raw}");

        if (!$resp || !($resp['statut'] ?? false) || empty($resp['url'])) {
            error_log("[Paiement] MF API réponse invalide: {$raw}");
            return [];
        }

        return [
            'url'   => $resp['url'],
            'token' => $resp['token'] ?? null,
        ];
    }

    public function callback(): void
    {
        $rawBody = file_get_contents('php://input');
        error_log('[MF Webhook] Payload reçu: ' . $rawBody);

        // Vérification HMAC si secret configuré
        $secret = $_ENV['MONEYFUSION_WEBHOOK_SECRET'] ?? '';
        if (!empty($secret)) {
            $receivedSig = $_SERVER['HTTP_X_MONEYFUSION_SIGNATURE']
                        ?? $_SERVER['HTTP_X_SIGNATURE']
                        ?? '';
            $expectedSig = hash_hmac('sha256', $rawBody, $secret);
            if (!hash_equals($expectedSig, $receivedSig)) {
                error_log('[MF Webhook] Signature invalide');
                http_response_code(403);
                exit;
            }
        }

        $payload = json_decode($rawBody, true);
        if (!$payload) {
            error_log('[MF Webhook] JSON invalide ou body vide');
            http_response_code(200);
            echo 'OK';
            exit;
        }

        $statut      = strtolower($payload['statut'] ?? $payload['status'] ?? '');
        $personalId  = $payload['personal_id'] ?? $payload['reference'] ?? '';
        $mfToken     = $payload['token']        ?? $payload['transaction_id'] ?? '';
        $emailPayeur = $payload['email']         ?? $payload['customer_email'] ?? '';
        $methode     = $payload['operateur']     ?? $payload['payment_method'] ?? $payload['method'] ?? null;
        $montantRecu = (float) ($payload['montant'] ?? $payload['amount'] ?? 0);

        $txModel = new Transaction();

        // Stratégie 1 : trouver par personal_id (notre référence — fiable)
        $transaction = !empty($personalId) ? $txModel->findByReference($personalId) : null;

        // Stratégie 2 : trouver par token MF dans aggregateur_ref
        if (!$transaction && !empty($mfToken)) {
            $transaction = $txModel->findByAgregateurRef($mfToken);
        }

        // Stratégie 3 : fallback par email (ancien comportement)
        if (!$transaction && !empty($emailPayeur)) {
            $user = (new User())->findByEmail($emailPayeur);
            if ($user) {
                $transaction = $txModel->findEnAttente((int) $user['id']);
            }
        }

        if (!$transaction) {
            error_log("[MF Webhook] Transaction introuvable — personal_id:{$personalId} token:{$mfToken} email:{$emailPayeur}");
            http_response_code(200);
            echo 'OK';
            exit;
        }

        $txId   = (int) $transaction['id'];
        $userId = (int) $transaction['user_id'];
        $redis  = \Core\Redis::getInstance();

        if ($transaction['statut'] === 'succes') {
            error_log("[MF Webhook] Transaction {$txId} déjà traitée (idempotence)");
            http_response_code(200);
            echo 'OK';
            exit;
        }

        $estSucces = in_array($statut, ['success', 'succes', 'paid', 'completed', 'successful'], true);

        if ($estSucces) {
            $montantAttendu = (float) ($_ENV['PRIX_MENSUEL_XAF'] ?? 2000);
            if ($montantRecu > 0 && $montantRecu < $montantAttendu) {
                error_log("[MF Webhook] Montant insuffisant: reçu {$montantRecu}, attendu {$montantAttendu}");
                $txModel->mettreAJour($txId, 'echec', $mfToken ?: $personalId, $rawBody);
                if ($redis->isAvailable()) $redis->del("payment_pending:{$userId}");
                http_response_code(200);
                echo 'OK';
                exit;
            }

            $txModel->mettreAJour($txId, 'succes', $mfToken ?: $personalId, $rawBody, $methode);
            (new Abonnement())->creer($userId, $transaction['plan'] ?? 'mensuel', 30);

            if ($redis->isAvailable()) $redis->del("payment_pending:{$userId}");
            Cache::forget("abonnement:{$userId}");

            error_log("[MF Webhook] Abonnement activé pour user {$userId} — tx:{$txId}");

        } else {
            $txModel->mettreAJour($txId, 'echec', $mfToken ?: $personalId, $rawBody);
            if ($redis->isAvailable()) $redis->del("payment_pending:{$userId}");
            error_log("[MF Webhook] Paiement échoué pour user {$userId}, statut: {$statut}");
        }

        http_response_code(200);
        echo 'OK';
    }

    public function retour(): void
    {
        // GET params que MoneyFusion ajoute au redirect (success_url)
        $getToken  = $_GET['token']       ?? $_GET['transaction_id'] ?? null;
        $getStatut = strtolower($_GET['statut'] ?? $_GET['status'] ?? '');
        $getRef    = $_GET['personal_id'] ?? $_GET['reference']     ?? null;

        error_log("[Retour] GET params — token:{$getToken} statut:{$getStatut} ref:{$getRef}");

        $txModel = new Transaction();
        $userId  = Session::userId();
        $tx      = null;

        // Stratégie 1 : session active → chercher tx par user
        if ($userId) {
            $abonnement = (new Abonnement())->getActif($userId);
            if ($abonnement) {
                $this->clearTxCookie();
                Response::redirect('/abonnement/confirmation');
            }
            $tx = $txModel->findEnAttente($userId);
        }

        // Stratégie 2 : token MF dans GET → trouver la tx par aggregateur_ref
        if (!$tx && $getToken) {
            $candidate = $txModel->findByAgregateurRef($getToken);
            if ($candidate && $candidate['statut'] === 'en_attente') {
                $tx     = $candidate;
                $userId = (int) $tx['user_id'];
            }
        }

        // Stratégie 3 : personal_id dans GET → trouver par référence
        if (!$tx && $getRef) {
            $candidate = $txModel->findByReference($getRef);
            if ($candidate && $candidate['statut'] === 'en_attente') {
                $tx     = $candidate;
                $userId = (int) $tx['user_id'];
            }
        }

        // Stratégie 4 : cookie signé ca_tx → trouver par ID
        if (!$tx) {
            $cookieTxId = $this->readSignedCookie();
            if ($cookieTxId) {
                $candidate = $txModel->findById($cookieTxId);
                if ($candidate && $candidate['statut'] === 'en_attente') {
                    $tx     = $candidate;
                    $userId = (int) $tx['user_id'];
                }
            }
        }

        // Aucune transaction identifiable
        if (!$tx || !$userId) {
            error_log("[Retour] Transaction introuvable — token:{$getToken} ref:{$getRef}");
            $this->clearTxCookie();
            if (Session::userId()) {
                Response::redirect('/abonnement/choisir?status=pending');
            }
            Response::redirect('/auth/connexion?redirect=' . urlencode('/abonnement/choisir?status=pending'));
        }

        $txId = (int) $tx['id'];
        error_log("[Retour] Transaction trouvée — tx:{$txId} user:{$userId}");

        // Déjà traitée (idempotence)
        if ($tx['statut'] === 'succes') {
            $this->clearTxCookie();
            if (Session::userId() == $userId) {
                Response::redirect('/abonnement/confirmation');
            }
            Response::redirect('/auth/connexion?payment_ok=1&redirect=' . urlencode('/abonnement/confirmation'));
        }

        // Tentative de vérification MF par ordre de fiabilité
        $tokensToTry = array_values(array_unique(array_filter([
            $getToken,
            $tx['aggregateur_ref'] ?? null,
            $getRef,
        ])));

        foreach ($tokensToTry as $token) {
            $verified = $this->verifierPaiementMF($token);
            if ($verified) {
                $methode = $verified['operateur'] ?? $verified['payment_method'] ?? null;
                $txModel->mettreAJour($txId, 'succes', $token, json_encode($verified), $methode);
                (new Abonnement())->creer($userId, $tx['plan'] ?? 'mensuel', 30);

                $redis = \Core\Redis::getInstance();
                if ($redis->isAvailable()) $redis->del("payment_pending:{$userId}");
                Cache::forget("abonnement:{$userId}");
                $this->clearTxCookie();

                error_log("[Retour] Abonnement activé — user:{$userId} tx:{$txId} token:{$token}");

                if (Session::userId() == $userId) {
                    Response::redirect('/abonnement/confirmation');
                }
                Response::redirect('/auth/connexion?payment_ok=1&redirect=' . urlencode('/abonnement/confirmation'));
            }
        }

        // MF confirme succès via GET statut mais vérification API indisponible
        $mfSaysOk = in_array($getStatut, ['success', 'succes', 'paid', 'completed', 'successful'], true);
        if ($mfSaysOk && !empty($getToken)) {
            $txModel->mettreAJour($txId, 'succes', $getToken, json_encode($_GET), null);
            (new Abonnement())->creer($userId, $tx['plan'] ?? 'mensuel', 30);

            $redis = \Core\Redis::getInstance();
            if ($redis->isAvailable()) $redis->del("payment_pending:{$userId}");
            Cache::forget("abonnement:{$userId}");
            $this->clearTxCookie();

            error_log("[Retour] Abonnement activé via statut GET — user:{$userId} tx:{$txId}");

            if (Session::userId() == $userId) {
                Response::redirect('/abonnement/confirmation');
            }
            Response::redirect('/auth/connexion?payment_ok=1&redirect=' . urlencode('/abonnement/confirmation'));
        }

        $this->clearTxCookie();
        if (Session::userId()) {
            Response::redirect('/abonnement/choisir?status=pending');
        }
        Response::redirect('/auth/connexion?redirect=' . urlencode('/abonnement/choisir?status=pending'));
    }

    public function statut(): void
    {
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['success' => false, 'abonne' => false], 401);
        }

        $abonnement = (new Abonnement())->getActif($userId);
        if ($abonnement) {
            Response::json(['success' => true, 'abonne' => true, 'redirect' => url('/abonnement/confirmation')]);
        }

        $txModel = new Transaction();
        $tx      = $txModel->findEnAttente($userId);

        // Tenter vérification MF proactive si token fourni ou stocké dans la tx
        if ($tx) {
            $mfToken = $_GET['mf_token'] ?? ($tx['aggregateur_ref'] ?? null);
            if ($mfToken) {
                $verified = $this->verifierPaiementMF($mfToken);
                if ($verified) {
                    $txId    = (int) $tx['id'];
                    $methode = $verified['operateur'] ?? $verified['payment_method'] ?? null;
                    $txModel->mettreAJour($txId, 'succes', $mfToken, json_encode($verified), $methode);
                    (new Abonnement())->creer($userId, $tx['plan'] ?? 'mensuel', 30);

                    $redis = \Core\Redis::getInstance();
                    if ($redis->isAvailable()) $redis->del("payment_pending:{$userId}");
                    Cache::forget("abonnement:{$userId}");
                    $this->clearTxCookie();

                    error_log("[Statut] Abonnement activé via polling — user:{$userId} tx:{$txId} token:{$mfToken}");
                    Response::json(['success' => true, 'abonne' => true, 'redirect' => url('/abonnement/confirmation')]);
                }
            }
        }

        Response::json(['success' => true, 'abonne' => false, 'pending' => (bool) $tx]);
    }

    private function setTxCookie(int $txId): void
    {
        $sig = hash_hmac('sha256', (string) $txId, $_ENV['APP_SECRET'] ?? 'secret');
        setcookie('ca_tx', $txId . ':' . $sig, [
            'expires'  => time() + 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => str_starts_with($_ENV['APP_URL'] ?? '', 'https'),
        ]);
    }

    private function readSignedCookie(): ?int
    {
        $val = $_COOKIE['ca_tx'] ?? '';
        if (!$val) return null;
        $parts = explode(':', $val, 2);
        if (count($parts) !== 2) return null;
        [$txId, $sig] = $parts;
        $expected = hash_hmac('sha256', $txId, $_ENV['APP_SECRET'] ?? 'secret');
        return hash_equals($expected, $sig) ? (int) $txId : null;
    }

    private function clearTxCookie(): void
    {
        setcookie('ca_tx', '', ['expires' => time() - 3600, 'path' => '/']);
    }

    /**
     * Vérifie le statut d'un paiement via l'API MoneyFusion v3.
     * GET https://pay.moneyfusion.net/api/v3/payments/status/{token}
     * Réponse: {"statut": true, "data": {"status": "paid", ...}}
     */
    private function verifierPaiementMF(string $mfPaymentToken): array|false
    {
        if (empty($mfPaymentToken)) return false;

        $url = 'https://pay.moneyfusion.net/api/v3/payments/status/' . rawurlencode($mfPaymentToken);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$raw) {
            error_log("[MF Verify] cURL error: {$err} token:{$mfPaymentToken}");
            return false;
        }

        error_log("[MF Verify] response token:{$mfPaymentToken} — {$raw}");
        $resp = json_decode($raw, true);
        if (!$resp || empty($resp['data'])) return false;

        $status = strtolower($resp['data']['status'] ?? '');
        return $status === 'paid' ? $resp['data'] : false;
    }
}
