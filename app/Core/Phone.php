<?php
declare(strict_types=1);

namespace Core;

/**
 * Numéros de téléphone gabonais (plan de numérotation 2020, +241).
 *
 * Format national stocké en base : 9 chiffres commençant par 0.
 *   - 01XXXXXXX : fixe
 *   - 06XXXXXXX / 07XXXXXXX : mobile (Gabon Télécom, Airtel, Moov)
 *
 * En entrée on tolère : espaces, tirets, points, +241 / 00241 / 241,
 * et l'ancien format 8 chiffres sans le 0 initial.
 */
final class Phone
{
    /**
     * Normalise vers le format national 0XXXXXXXX (9 chiffres).
     * Retourne null si le numéro n'est pas un numéro gabonais valide.
     */
    public static function normalizeGabon(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00241')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '241')) {
            $digits = substr($digits, 3);
        }

        // Ancien format 8 chiffres (sans le 0 national ajouté en 2020)
        if (strlen($digits) === 8 && $digits[0] !== '0') {
            $digits = '0' . $digits;
        }

        return preg_match('/^0[167]\d{7}$/', $digits) === 1 ? $digits : null;
    }

    /** Affichage lisible : "+241 074 12 34 56". Chaîne vide si null/invalide. */
    public static function formatGabon(?string $national): string
    {
        $n = self::normalizeGabon($national);
        if ($n === null) {
            return '';
        }
        return '+241 ' . substr($n, 0, 3) . ' ' . substr($n, 3, 2)
             . ' ' . substr($n, 5, 2) . ' ' . substr($n, 7, 2);
    }
}
