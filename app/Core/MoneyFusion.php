<?php
declare(strict_types=1);

namespace Core;

/**
 * Helper pour l'intégration de l'API MoneyFusion.
 * Version alignée sur l'implémentation Next.js.
 */
class MoneyFusion
{
    public const FEE_RATE = 1.08;
    private const DEFAULT_CHECK_URL = "https://pay.moneyfusion.net/paiementNotif";

    private static function getApiUrl(): string
    {
        $url = $_ENV['MONEY_FUSION_PAYMENT_URL'] ?? $_ENV['MONEY_FUSION_API_URL'] ?? '';
        return rtrim($url, '/');
    }

    /**
     * Tronque un token de paiement destiné aux logs.
     *
     * Un token complet permet d'interroger l'API MoneyFusion : il n'a rien à
     * faire en clair dans `storage/logs/app.log`. Les 8 premiers caractères
     * suffisent à corréler plusieurs lignes pendant un diagnostic.
     */
    public static function maskToken(?string $token): string
    {
        $t = (string) $token;
        return $t === '' ? 'n/a' : substr($t, 0, 8) . '…(' . strlen($t) . ')';
    }

    /**
     * Calcule le montant à envoyer à l'API MoneyFusion.
     * Aligné sur Math.round(displayedPrice / 1.08)
     */
    public static function calculateAmountToSend(float $displayedPrice): int
    {
        return (int) round($displayedPrice / self::FEE_RATE);
    }

    /**
     * Initie un paiement via l'API MoneyFusion.
     * 
     * @param array $paymentData {
     *   totalPrice: number,
     *   article: Array<Record<string, number>>,
     *   personal_Info: Array<Record<string, any>>,
     *   numeroSend: string,
     *   nomclient: string,
     *   return_url?: string,
     *   webhook_url?: string
     * }
     * @return array
     */
    public static function initiate(array $paymentData): array
    {
        $url = self::getApiUrl();
        if (empty($url)) {
            return ['statut' => false, 'message' => 'MONEY_FUSION_PAYMENT_URL is not set'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($paymentData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['statut' => false, 'message' => 'Failed to initiate payment: ' . $err];
        }

        $data = json_decode($response, true);
        return $data ?: ['statut' => false, 'message' => 'Invalid API response'];
    }

    /**
     * Vérifie le statut d'un paiement (endpoint officiel paiementNotif).
     * Réponse: { statut: bool, data: { statut: paid|pending|failure|no paid, Montant, frais, ... } }
     */
    public static function checkStatus(string $token): array
    {
        $base = rtrim($_ENV['MONEY_FUSION_CHECK_URL'] ?? self::DEFAULT_CHECK_URL, '/');
        $url  = $base . '/' . rawurlencode($token);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$response) {
            error_log("[MoneyFusion] checkStatus cURL error: {$err} token:" . self::maskToken($token));
            return ['statut' => false, 'message' => 'cURL error: ' . $err];
        }

        $data = json_decode($response, true);
        if (!$data) {
            error_log("[MoneyFusion] checkStatus réponse non-JSON: " . substr($response, 0, 200));
            return ['statut' => false, 'message' => 'Invalid JSON response'];
        }

        // Normalisation: certains retours utilisent data.status au lieu de data.statut
        if (isset($data['data']['status']) && !isset($data['data']['statut'])) {
            $data['data']['statut'] = strtolower((string)$data['data']['status']);
        }
        if (isset($data['data']['statut'])) {
            $data['data']['statut'] = strtolower((string)$data['data']['statut']);
        }

        error_log("[MoneyFusion] checkStatus token:" . self::maskToken($token) . ' statut:' . ($data['data']['statut'] ?? 'n/a'));
        return $data;
    }

    /**
     * Initie un retrait (payout) vers un numéro mobile money.
     * Doc: POST https://pay.moneyfusion.net/api/v1/withdraw
     * Header: moneyfusion-private-key
     * Réponse succès: { tokenPay, statut: true, message }
     */
    public static function payout(
        string $countryCode,
        string $phone,
        int    $amount,
        string $withdrawMode,
        string $webhookUrl = ''
    ): array {
        $privateKey = $_ENV['MONEY_FUSION_PRIVATE_API_KEY'] ?? '';
        if ($privateKey === '') {
            return ['statut' => false, 'message' => 'MONEY_FUSION_PRIVATE_API_KEY is not set'];
        }

        $payload = [
            'countryCode'   => $countryCode,
            'phone'         => $phone,
            'amount'        => $amount,
            'withdraw_mode' => $withdrawMode,
        ];
        if ($webhookUrl !== '') {
            $payload['webhook_url'] = $webhookUrl;
        }

        $ch = curl_init('https://pay.moneyfusion.net/api/v1/withdraw');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'moneyfusion-private-key: ' . $privateKey,
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$response) {
            error_log("[MoneyFusion] payout cURL error: {$err}");
            return ['statut' => false, 'message' => 'cURL error: ' . ($err ?: 'empty response')];
        }

        $data = json_decode($response, true);
        if (!$data) {
            error_log('[MoneyFusion] payout réponse non-JSON: ' . substr($response, 0, 200));
            return ['statut' => false, 'message' => 'Invalid JSON response: ' . substr($response, 0, 120)];
        }

        return $data;
    }

    /**
     * Liste dynamique des pays / opérateurs de retrait supportés.
     */
    public static function withdrawMethods(): array
    {
        $ch = curl_init('https://pay.moneyfusion.net/api/v1/withdraw/methods');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = $response ? json_decode($response, true) : null;
        return $data ?: ['success' => false, 'data' => []];
    }
}
