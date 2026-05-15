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
    private const DEFAULT_CHECK_URL = "https://www.pay.moneyfusion.net/paiementNotif";

    private static function getApiUrl(): string
    {
        $url = $_ENV['MONEY_FUSION_PAYMENT_URL'] ?? $_ENV['MONEY_FUSION_API_URL'] ?? '';
        return rtrim($url, '/');
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
     * Vérifie le statut d'un paiement.
     */
    public static function checkStatus(string $token): array
    {
        $url = self::DEFAULT_CHECK_URL . '/' . $token;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data ?: ['statut' => false, 'message' => 'Failed to check payment status'];
    }
}
