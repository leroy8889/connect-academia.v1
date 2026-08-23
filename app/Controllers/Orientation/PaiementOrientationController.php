<?php
declare(strict_types=1);

namespace Controllers\Orientation;

use Core\MoneyFusion;
use Models\Orientation\{Dossier, Reversement};
use Services\Orientation\{AuditService, ConfirmationInscriptionService};

/**
 * Webhooks MoneyFusion (payin + payout) du module Orientation.
 *
 * Sécurité : MoneyFusion ne signe pas ses webhooks. Le payload entrant ne
 * sert donc que de déclencheur — AUCUNE décision n'est prise sur son contenu.
 *
 *  - Payin  : le statut réel est revérifié côté serveur via l'API officielle
 *    paiementNotif/{token} (Core\MoneyFusion::checkStatus). Seule la réponse
 *    de l'API MoneyFusion fait foi ; un webhook forgé ne peut rien confirmer.
 *  - Payout : le tokenPay reçu doit correspondre à un reversement initié par
 *    nos soins (token imprévisible, connu uniquement de MoneyFusion et de
 *    notre base) ; traitement idempotent.
 */
class PaiementOrientationController
{
    private Dossier $dossierModel;

    public function __construct()
    {
        $this->dossierModel = new Dossier();
    }

    // ── Webhook pay-in ────────────────────────────────────────────────────────

    public function webhookPayin(): void
    {
        $rawBody = (string)file_get_contents('php://input');
        error_log('[OrientationWebhook] Payload: ' . substr($rawBody, 0, 1000));

        $payload = json_decode($rawBody, true);
        if (!$payload) {
            http_response_code(200);
            echo 'OK';
            exit;
        }

        $event   = (string)($payload['event'] ?? '');
        $mfToken = (string)($payload['tokenPay'] ?? $payload['token'] ?? '');

        // Session en attente — on ignore, on attend le completed
        if ($event === 'payin.session.pending' || $mfToken === '') {
            http_response_code(200);
            echo 'OK';
            exit;
        }

        // ── Vérification serveur : seule l'API MoneyFusion fait foi ──────────
        $verified = MoneyFusion::checkStatus($mfToken);
        $data     = $verified['data'] ?? null;

        if (!is_array($data) || empty($data)) {
            error_log("[OrientationWebhook] Vérification API impossible — token:" . MoneyFusion::maskToken($mfToken));
            http_response_code(200);
            echo 'OK';
            exit;
        }

        $statutApi    = strtolower((string)($data['statut'] ?? ''));
        $personalInfo = $data['personal_Info'][0] ?? [];
        $paiementId   = (int)($personalInfo['paiementId'] ?? 0);

        // Trouver le paiement : d'abord via l'ID vérifié par l'API, sinon token
        $paiement = $paiementId ? $this->dossierModel->findPaiementById($paiementId) : null;
        if (!$paiement) {
            $paiement = $this->dossierModel->findPaiementByToken($mfToken);
        }

        if (!$paiement) {
            error_log("[OrientationWebhook] Paiement introuvable — paiementId:{$paiementId} token:" . MoneyFusion::maskToken($mfToken));
            http_response_code(200);
            echo 'OK';
            exit;
        }

        // Le token vérifié doit correspondre à celui enregistré à l'initiation
        if (!empty($paiement['token_pay']) && $paiement['token_pay'] !== $mfToken) {
            error_log("[OrientationWebhook] Token discordant — paiement:{$paiement['id']} attendu:" . MoneyFusion::maskToken($paiement['token_pay']) . ' reçu:' . MoneyFusion::maskToken($mfToken));
            http_response_code(200);
            echo 'OK';
            exit;
        }

        $pId = (int)$paiement['id'];

        if ($statutApi === 'paid') {
            ConfirmationInscriptionService::confirmer($pId, $mfToken);
        } elseif (in_array($statutApi, ['failure', 'no paid', 'failed', 'cancelled'], true)) {
            if ($paiement['statut'] !== 'reussi') {
                $this->dossierModel->updatePaiementStatut($pId, 'echoue');
                error_log("[OrientationWebhook] Paiement échoué — paiement:{$pId} statut:{$statutApi}");
            }
        }
        // pending → rien, on attend le prochain événement

        http_response_code(200);
        echo 'OK';
    }

    // ── Webhook pay-out ───────────────────────────────────────────────────────

    public function webhookPayout(): void
    {
        $rawBody = (string)file_get_contents('php://input');
        error_log('[OrientationWebhookPayout] Payload: ' . substr($rawBody, 0, 1000));

        $payload = json_decode($rawBody, true);
        if (!$payload) {
            http_response_code(200);
            echo 'OK';
            exit;
        }

        $event   = (string)($payload['event'] ?? '');
        $mfToken = (string)($payload['tokenPay'] ?? $payload['token'] ?? '');

        if ($mfToken === '') {
            http_response_code(200);
            echo 'OK';
            exit;
        }

        $rev = new Reversement();

        // Authentification implicite : le tokenPay doit correspondre à un
        // reversement que NOUS avons initié (token imprévisible).
        $reversement = $rev->findByToken($mfToken);
        if (!$reversement) {
            error_log("[OrientationWebhookPayout] Reversement introuvable — token:" . MoneyFusion::maskToken($mfToken));
            http_response_code(200);
            echo 'OK';
            exit;
        }

        if ($reversement['statut'] === 'reussi') {
            http_response_code(200);
            echo 'OK';
            exit;
        }

        $revId = (int)$reversement['id'];

        if ($event === 'payout.session.completed') {
            $rev->updateStatut($revId, 'reussi', $mfToken, null);
            AuditService::log('reversement.reussi', 'reversement', $revId, null, ['token' => $mfToken]);
            error_log("[OrientationWebhookPayout] Reversement réussi — rev:{$revId}");
        } elseif ($event === 'payout.session.cancelled') {
            $rev->updateStatut($revId, 'a_rejouer', $mfToken, 'Payout annulé/échoué (webhook MoneyFusion)');
            AuditService::log('reversement.echoue', 'reversement', $revId, null, ['event' => $event]);
            error_log("[OrientationWebhookPayout] Reversement échoué — rev:{$revId}");
        }

        http_response_code(200);
        echo 'OK';
    }
}
