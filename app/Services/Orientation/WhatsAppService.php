<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;

/**
 * Envoi de messages WhatsApp aux candidats (espace gestionnaire).
 *
 * Les candidats gabonais sont bien plus joignables sur WhatsApp que par SMS :
 * quand l'établissement écrit à un candidat depuis « Dossiers reçus », le message
 * part à la fois dans les notifications de son compte ET sur son WhatsApp.
 *
 * Pilotes via WHATSAPP_DRIVER (.env) :
 *   - "log"    : aucun envoi réel — tracé dans error_log, journalisé statut "simule"
 *                (mode développement, valeur par défaut).
 *   - "twilio" : API Twilio WhatsApp — réutilise SMS_TWILIO_SID / SMS_TWILIO_TOKEN,
 *                expéditeur WHATSAPP_TWILIO_FROM (ex. "whatsapp:+14155238886").
 *   - "meta"   : WhatsApp Cloud API (Meta) — WHATSAPP_META_TOKEN + WHATSAPP_META_PHONE_ID.
 *                Rappel Meta : hors fenêtre de 24 h, seuls les modèles approuvés
 *                (templates) passent en message texte libre.
 *
 * Chaque tentative est journalisée dans whatsapp_orientation
 * (migration : Database/migration_whatsapp_orientation.sql). Si la table est
 * absente, l'envoi reste fonctionnel — seule la trace est ignorée.
 *
 * La normalisation E.164 réutilise SmsService::normaliserNumero.
 */
class WhatsAppService
{
    private string $driver;

    public function __construct()
    {
        $this->driver = strtolower(trim($_ENV['WHATSAPP_DRIVER'] ?? 'log'));
    }

    /** Le service est-il prêt à envoyer de vrais messages WhatsApp ? */
    public function estConfigure(): bool
    {
        return match ($this->driver) {
            'twilio' => ($_ENV['SMS_TWILIO_SID'] ?? '') !== ''
                && ($_ENV['SMS_TWILIO_TOKEN'] ?? '') !== ''
                && ($_ENV['WHATSAPP_TWILIO_FROM'] ?? '') !== '',
            'meta'   => ($_ENV['WHATSAPP_META_TOKEN'] ?? '') !== ''
                && ($_ENV['WHATSAPP_META_PHONE_ID'] ?? '') !== '',
            'log'    => true,
            default  => false,
        };
    }

    /** Mode simulation (aucun message réel ne part) ? */
    public function estSimulation(): bool
    {
        return $this->driver === 'log';
    }

    /**
     * Envoie un message WhatsApp et journalise la tentative.
     *
     * @return array{success: bool, statut: string, erreur: ?string}
     */
    public function envoyer(
        string $numeroE164,
        string $message,
        int $dossierId,
        int $etablissementId,
        int $envoyePar
    ): array {
        $message = trim($message);

        if (($err = $this->valider($numeroE164, $message)) !== null) {
            return ['success' => false, 'statut' => 'echec', 'erreur' => $err];
        }

        [$success, $statut, $reference, $erreur] = $this->dispatch($numeroE164, $message);

        $this->journaliser($dossierId, $etablissementId, $envoyePar, $numeroE164, $message, $statut, $reference, $erreur);

        return ['success' => $success, 'statut' => $statut, 'erreur' => $erreur];
    }

    /**
     * Envoi WhatsApp « brut » — sans contexte dossier ni journalisation
     * orientation. Utilisé par les alertes système (ex. Core\SecurityMonitor)
     * qui doivent prévenir un numéro d'administration, hors flux candidat.
     *
     * Réutilise exactement les mêmes pilotes (twilio/meta/log) que envoyer().
     *
     * @return array{success: bool, statut: string, erreur: ?string}
     */
    public function envoyerBrut(string $numeroE164, string $message): array
    {
        $message = trim($message);

        if (($err = $this->valider($numeroE164, $message)) !== null) {
            return ['success' => false, 'statut' => 'echec', 'erreur' => $err];
        }

        [$success, $statut, , $erreur] = $this->dispatch($numeroE164, $message);

        return ['success' => $success, 'statut' => $statut, 'erreur' => $erreur];
    }

    /** Contrôles communs. Retourne le message d'erreur, ou null si tout est bon. */
    private function valider(string $numeroE164, string $message): ?string
    {
        if ($message === '') {
            return 'Message vide.';
        }
        if (mb_strlen($message) > 1000) {
            return 'Message trop long (1000 caractères max).';
        }
        if (!preg_match('/^\+\d{8,15}$/', $numeroE164)) {
            return 'Numéro de téléphone invalide.';
        }
        return null;
    }

    /**
     * Dispatch vers le pilote actif (numéro + message déjà validés).
     *
     * @return array{0: bool, 1: string, 2: ?string, 3: ?string}
     *         [success, statut, reference_externe, erreur]
     */
    private function dispatch(string $numeroE164, string $message): array
    {
        return match ($this->driver) {
            'twilio' => $this->envoyerTwilio($numeroE164, $message),
            'meta'   => $this->envoyerMeta($numeroE164, $message),
            'log'    => $this->envoyerLog($numeroE164, $message),
            default  => [false, 'echec', null, 'Pilote WhatsApp inconnu : ' . $this->driver],
        };
    }

    // ── Pilotes ──────────────────────────────────────────────────────────────

    /** @return array{0: bool, 1: string, 2: ?string, 3: ?string} */
    private function envoyerTwilio(string $to, string $message): array
    {
        $sid   = $_ENV['SMS_TWILIO_SID']       ?? '';
        $token = $_ENV['SMS_TWILIO_TOKEN']     ?? '';
        $from  = $_ENV['WHATSAPP_TWILIO_FROM'] ?? '';

        if ($sid === '' || $token === '' || $from === '') {
            return [false, 'echec', null, 'Passerelle WhatsApp non configurée (WHATSAPP_TWILIO_FROM / SMS_TWILIO_* manquants).'];
        }

        // Twilio attend le préfixe "whatsapp:" sur From et To.
        if (!str_starts_with($from, 'whatsapp:')) {
            $from = 'whatsapp:' . $from;
        }

        $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => $sid . ':' . $token,
            CURLOPT_POSTFIELDS     => http_build_query([
                'To'   => 'whatsapp:' . $to,
                'From' => $from,
                'Body' => $message,
            ]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return [false, 'echec', null, 'Connexion à la passerelle WhatsApp impossible : ' . $err];
        }

        $json = json_decode((string)$body, true) ?: [];
        if ($code >= 200 && $code < 300) {
            return [true, 'envoye', $json['sid'] ?? null, null];
        }
        $detail = $json['message'] ?? ('HTTP ' . $code);
        return [false, 'echec', null, 'Envoi refusé par la passerelle : ' . $detail];
    }

    /** @return array{0: bool, 1: string, 2: ?string, 3: ?string} */
    private function envoyerMeta(string $to, string $message): array
    {
        $token   = $_ENV['WHATSAPP_META_TOKEN']    ?? '';
        $phoneId = $_ENV['WHATSAPP_META_PHONE_ID'] ?? '';
        $version = $_ENV['WHATSAPP_META_VERSION']  ?? 'v20.0';

        if ($token === '' || $phoneId === '') {
            return [false, 'echec', null, 'Passerelle WhatsApp Cloud non configurée (WHATSAPP_META_* manquants).'];
        }

        // Meta veut le numéro sans le "+" de tête.
        $toDigits = ltrim($to, '+');

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toDigits,
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $message],
        ]);

        $ch = curl_init('https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneId) . '/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return [false, 'echec', null, 'Connexion à WhatsApp Cloud impossible : ' . $err];
        }

        $json = json_decode((string)$body, true) ?: [];
        if ($code >= 200 && $code < 300) {
            $ref = $json['messages'][0]['id'] ?? null;
            return [true, 'envoye', $ref, null];
        }
        $detail = $json['error']['message'] ?? ('HTTP ' . $code);
        return [false, 'echec', null, 'Envoi refusé par WhatsApp Cloud : ' . $detail];
    }

    /** @return array{0: bool, 1: string, 2: ?string, 3: ?string} */
    private function envoyerLog(string $to, string $message): array
    {
        error_log(sprintf('[WhatsApp simulé] → %s : %s', $to, $message));
        return [true, 'simule', null, null];
    }

    // ── Journal ──────────────────────────────────────────────────────────────

    private function journaliser(
        int $dossierId,
        int $etablissementId,
        int $envoyePar,
        string $destinataire,
        string $message,
        string $statut,
        ?string $reference,
        ?string $erreur
    ): void {
        try {
            Database::getInstance()->getConnection()->prepare('
                INSERT INTO whatsapp_orientation
                  (dossier_id, etablissement_id, envoye_par, destinataire, message, statut, reference_externe, erreur)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ')->execute([
                $dossierId, $etablissementId, $envoyePar,
                $destinataire, $message, $statut, $reference,
                $erreur !== null ? mb_substr($erreur, 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            // Table absente (migration non appliquée) : l'envoi reste fonctionnel.
            error_log('[WhatsApp] Journalisation impossible : ' . $e->getMessage());
        }
    }
}
