<?php
declare(strict_types=1);

namespace Controllers;

use Core\{Response, Session, RateLimiter, Cache, MoneyFusion};
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
            $cachedUrl  = $cached['payment_url'] ?? null;
            $cachedMfTk = $cached['mf_token']    ?? null;
            if ($cachedUrl) {
                if (!empty($cached['tx_id'])) {
                    $this->setTxCookie((int) $cached['tx_id']);
                }
                Response::json(['success' => true, 'payment_url' => $cachedUrl, 'mf_token' => $cachedMfTk]);
            }
        }

        $reference = sprintf('CA-%d-%d-%s', $userId, time(), strtoupper(bin2hex(random_bytes(2))));
        
        // Préparation des données pour MoneyFusion
        $appUrl     = rtrim($_ENV['APP_URL'] ?? '', '/');
        $webhookUrl = $appUrl . '/api/paiement/callback';
        $returnUrl  = $appUrl . '/paiement/retour';
        $nom        = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?: 'Étudiant Connect Academia';
        $plan           = $_GET['plan'] ?? 'mensuel';
        $montantAffiche = $plan === 'annuel' ? (float)($_ENV['PRIX_ANNUEL_XAF'] ?? 15000) : (float)($_ENV['PRIX_MENSUEL_XAF'] ?? 2000);
        $montantAEnvoyer = MoneyFusion::calculateAmountToSend($montantAffiche);

        $paymentData = [
            'totalPrice'    => $montantAEnvoyer,
            'article'       => [
                ['Abonnement Connect\'Academia' => $montantAEnvoyer]
            ],
            'personal_Info' => [
                [
                    'userId'      => $userId,
                    'reference'   => $reference,
                    'plan'        => $plan,
                    'email'       => $user['email'] ?? '',
                    'phoneNumber' => $user['telephone'] ?? '000000000'
                ]
            ],
            'numeroSend'    => '000000000', // Optionnel ou à demander au client
            'nomclient'     => $nom,
            'return_url'    => $returnUrl,
            'webhook_url'   => $webhookUrl
        ];

        $mfResponse = MoneyFusion::initiate($paymentData);

        if (!($mfResponse['statut'] ?? false) || empty($mfResponse['url'])) {
            error_log("[Paiement] Échec initiation MoneyFusion: " . json_encode($mfResponse));
            Response::json(['success' => false, 'message' => 'Impossible d\'initier le paiement pour le moment.'], 500);
        }

        $paymentUrl = $mfResponse['url'];
        $mfToken    = $mfResponse['token'] ?? null;

        // Créer transaction en BDD avec le montant réel (affiché)
        $txId = (new Transaction())->creer($userId, $plan, $montantAffiche, $reference, $mfToken);

        // Cookie signé pour identifier la transaction même si session expire
        $this->setTxCookie($txId);

        if ($redis->isAvailable()) {
            $redis->set("payment_pending:{$userId}", json_encode([
                'reference'   => $reference,
                'tx_id'       => $txId,
                'mf_token'    => $mfToken,
                'payment_url' => $paymentUrl,
                'email'       => $user['email'],
                'plan'        => $plan,
                'ts'          => time(),
            ]), 1800);
        }

        error_log("[Paiement] Initié — user:{$userId} ref:{$reference} tx:{$txId} mf_token:{$mfToken} url:{$paymentUrl}");

        Response::json(['success' => true, 'payment_url' => $paymentUrl, 'mf_token' => $mfToken]);
    }


    public function callback(): void
    {
        $rawBody = file_get_contents('php://input');
        error_log('[MF Webhook] Payload reçu: ' . $rawBody);

        $payload = json_decode($rawBody, true);
        if (!$payload) {
            error_log('[MF Webhook] JSON invalide ou body vide');
            http_response_code(200);
            echo 'OK';
            exit;
        }

        // Mapping des champs basés sur la nouvelle doc
        $event       = $payload['event'] ?? '';
        $mfToken     = $payload['tokenPay'] ?? '';
        $statut      = strtolower($payload['statut'] ?? '');
        $montantRecu = (float) ($payload['Montant'] ?? 0);
        $personalInfo = $payload['personal_Info'][0] ?? [];
        $personalId  = $personalInfo['reference'] ?? '';
        $userId      = (int) ($personalInfo['userId'] ?? 0);

        if ($event === 'payin.session.pending') {
            error_log("[MF Webhook] Paiement initié (pending) — token: $mfToken");
            http_response_code(200);
            echo 'OK';
            exit;
        }

        $txModel = new Transaction();
        $transaction = !empty($mfToken) ? $txModel->findByAgregateurRef($mfToken) : null;
        if (!$transaction && !empty($personalId)) {
            $transaction = $txModel->findByReference($personalId);
        }

        if (!$transaction) {
            error_log("[MF Webhook] Transaction introuvable — ref:$personalId token:$mfToken");
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

        if ($event === 'payin.session.completed' && ($statut === 'paid' || $payload['statut'] === true)) {
            // Activation
            $txModel->mettreAJour($txId, 'succes', $mfToken, $rawBody);
            (new Abonnement())->creer($userId, $transaction['plan'] ?? 'mensuel', 30);

            if ($redis->isAvailable()) $redis->del("payment_pending:{$userId}");
            Cache::forget("abonnement:{$userId}");

            error_log("[MF Webhook] Abonnement activé pour user {$userId} — tx:{$txId}");
        } elseif ($event === 'payin.session.cancelled' || $statut === 'failure' || $statut === 'no paid') {
            $txModel->mettreAJour($txId, 'echec', $mfToken, $rawBody);
            if ($redis->isAvailable()) $redis->del("payment_pending:{$userId}");
            error_log("[MF Webhook] Paiement échoué/annulé pour user {$userId}");
        }

        http_response_code(200);
        echo 'OK';
    }

    public function retour(): void
    {
        $getToken = $_GET['token'] ?? null;
        $getRef   = $_GET['personal_id'] ?? $_GET['reference'] ?? null;

        error_log("[Retour] Redirect reçu — token:{$getToken} ref:{$getRef}");

        $txModel = new Transaction();
        $userId  = Session::userId();
        $tx      = null;

        // 1. Déjà identifié par session ?
        if ($userId) {
            $abonnement = (new Abonnement())->getActif($userId);
            if ($abonnement) {
                $this->clearTxCookie();
                Response::redirect('/abonnement/confirmation');
            }
            $tx = $txModel->findEnAttente($userId);
        }

        // 2. Par token MF
        if (!$tx && $getToken) {
            $candidate = $txModel->findByAgregateurRef($getToken);
            if ($candidate && $candidate['statut'] === 'en_attente') {
                $tx     = $candidate;
                $userId = (int) $tx['user_id'];
            }
        }

        // 3. Par référence
        if (!$tx && $getRef) {
            $candidate = $txModel->findByReference($getRef);
            if ($candidate && $candidate['statut'] === 'en_attente') {
                $tx     = $candidate;
                $userId = (int) $tx['user_id'];
            }
        }

        if (!$tx || !$userId) {
            error_log("[Retour] Transaction introuvable ou user non identifié");
            Response::redirect('/abonnement/choisir?status=pending');
        }

        $txId = (int) $tx['id'];

        // Vérification proactive via l'API
        $mfToken = $getToken ?: ($tx['aggregateur_ref'] ?? null);
        if ($mfToken) {
            $verified = MoneyFusion::checkStatus($mfToken);
            if ($verified && ($verified['statut'] ?? false)) {
                $statusData = $verified['data'] ?? [];
                if (($statusData['statut'] ?? '') === 'paid') {
                    $txModel->mettreAJour($txId, 'succes', $mfToken, json_encode($verified));
                    (new Abonnement())->creer($userId, $tx['plan'] ?? 'mensuel', 30);
                    
                    $redis = \Core\Redis::getInstance();
                    if ($redis->isAvailable()) $redis->del("payment_pending:{$userId}");
                    Cache::forget("abonnement:{$userId}");
                    $this->clearTxCookie();

                    error_log("[Retour] Abonnement activé via checkStatus — user:{$userId} tx:{$txId}");
                    Response::redirect('/abonnement/confirmation');
                }
            }
        }

        // Si pas encore "paid", on laisse le webhook ou le polling gérer
        Response::redirect('/abonnement/choisir?status=pending');
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

        if ($tx) {
            $mfToken = $_GET['mf_token'] ?? ($tx['aggregateur_ref'] ?? null);
            if ($mfToken) {
                $verified = MoneyFusion::checkStatus($mfToken);
                if ($verified && ($verified['statut'] ?? false)) {
                    $statusData = $verified['data'] ?? [];
                    if (($statusData['statut'] ?? '') === 'paid') {
                        $txId = (int) $tx['id'];
                        $txModel->mettreAJour($txId, 'succes', $mfToken, json_encode($verified));
                        (new Abonnement())->creer($userId, $tx['plan'] ?? 'mensuel', 30);

                        $redis = \Core\Redis::getInstance();
                        if ($redis->isAvailable()) $redis->del("payment_pending:{$userId}");
                        Cache::forget("abonnement:{$userId}");
                        $this->clearTxCookie();

                        Response::json(['success' => true, 'abonne' => true, 'redirect' => url('/abonnement/confirmation')]);
                    }
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

}
