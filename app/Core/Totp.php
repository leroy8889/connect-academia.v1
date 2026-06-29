<?php
declare(strict_types=1);

namespace Core;

/**
 * TOTP (RFC 6238) — Google Authenticator compatible
 * Aucune dépendance externe. Algorithme : HMAC-SHA1, pas de 30 s, 6 chiffres.
 */
class Totp
{
    private const CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Génère un secret Base32 aléatoire (20 octets → 32 chars). */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /**
     * Calcule le code TOTP pour un instant donné.
     * @param int $timestamp  Timestamp Unix (0 = maintenant)
     */
    public static function getCode(string $secret, int $timeStep = 30, int $timestamp = 0): string
    {
        $time = (int) floor(($timestamp ?: time()) / $timeStep);
        $key  = self::base32Decode($secret);
        // Counter en Big-Endian 64 bits
        $data = pack('NN', 0, $time);
        $hash = hash_hmac('sha1', $data, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            ((ord($hash[$offset + 3]) & 0xFF))
        );
        return str_pad((string) ($code % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Vérifie un code OTP avec fenêtre de ±$window pas (tolérance horloge).
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::getCode($secret, 30, $now + $i * 30), $code)) {
                return true;
            }
        }
        return false;
    }

    /** URL otpauth:// pour QR code (Google Authenticator). */
    public static function getOtpauthUrl(string $label, string $secret, string $issuer = "Connect'Academia"): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    /** URL du QR code via api.qrserver.com (pas d'upload de secret). */
    public static function getQrCodeImageUrl(string $label, string $secret, string $issuer = "Connect'Academia"): string
    {
        $otpauth = self::getOtpauthUrl($label, $secret, $issuer);
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($otpauth);
    }

    // ── Encodage / Décodage Base32 ────────────────────────────────────────

    private static function base32Encode(string $data): string
    {
        $encoded = '';
        $n       = strlen($data);
        $buffer  = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < $n; $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $encoded .= self::CHARS[($buffer >> $bitsLeft) & 0x1F];
            }
        }
        if ($bitsLeft > 0) {
            $buffer <<= (5 - $bitsLeft);
            $encoded .= self::CHARS[$buffer & 0x1F];
        }
        return $encoded;
    }

    private static function base32Decode(string $data): string
    {
        $data    = strtoupper(str_replace('=', '', $data));
        $decoded = '';
        $buffer  = 0;
        $bitsLeft = 0;

        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $pos = strpos(self::CHARS, $data[$i]);
            if ($pos === false) continue;
            $buffer = ($buffer << 5) | $pos;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $decoded .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $decoded;
    }
}
