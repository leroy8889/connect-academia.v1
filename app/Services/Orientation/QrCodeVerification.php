<?php
declare(strict_types=1);

namespace Services\Orientation;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;

/**
 * QR code d'authenticité des documents officiels (récépissé, attestation).
 *
 * Chaque document porte un token de vérification unique (64 hex, 256 bits
 * aléatoires — voir Dossier::tokenVerification*). Le QR code encode l'URL
 * publique /verifier/{token} : scanner le document affiche les informations
 * officielles du dossier depuis la base, rendant toute falsification
 * détectable. Rendu SVG en data URI — le document reste 100 % autonome
 * (aucune ressource externe), comme le logo (LogoDataUri).
 */
class QrCodeVerification
{
    /** Domaine public par défaut (contexte CLI/webhook sans HTTP_HOST). */
    private const DOMAINE_DEFAUT = 'connectacademia.io';

    /**
     * URL publique de vérification encodée dans le QR code.
     * Construite depuis la requête courante (même schéma que les liens
     * d'e-mails — AuthController) ; repli sur le domaine de production.
     */
    public static function url(string $token): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? self::DOMAINE_DEFAUT);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || $host === self::DOMAINE_DEFAUT
            ? 'https' : 'http';

        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

        return $scheme . '://' . $host . $base . '/verifier/' . $token;
    }

    /**
     * QR code SVG en data URI, prêt pour un <img> embarqué dans le document.
     * Chaîne vide si le token est absent (le gabarit masque alors le bloc QR)
     * ou si la génération échoue — un document sans QR reste téléchargeable.
     */
    public static function dataUri(?string $token): string
    {
        if ($token === null || $token === '') {
            return '';
        }

        try {
            $options = new QROptions([
                'outputInterface' => QRMarkupSVG::class,
                'eccLevel'        => EccLevel::M,
                'outputBase64'    => true,
                'addQuietzone'    => true,
                'connectPaths'    => true,
                'drawLightModules'=> false,
            ]);

            return (new QRCode($options))->render(self::url($token));
        } catch (\Throwable $e) {
            error_log('[QrCodeVerification] Génération QR échouée : ' . $e->getMessage());
            return '';
        }
    }
}
