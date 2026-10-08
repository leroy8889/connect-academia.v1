<?php
declare(strict_types=1);

namespace Services\Orientation;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
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

    /** Vérification d'authenticité d'un document (récépissé, attestation). */
    public const CHEMIN_DOCUMENT = '/verifier/';

    /** Vérification de l'état réel d'un dossier, réservée aux agents. */
    public const CHEMIN_DOSSIER  = '/v/';

    /**
     * URL publique de vérification encodée dans le QR code.
     * Construite depuis la requête courante (même schéma que les liens
     * d'e-mails — AuthController) ; repli sur le domaine de production.
     */
    public static function url(string $token, string $chemin = self::CHEMIN_DOCUMENT): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? self::DOMAINE_DEFAUT);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || $host === self::DOMAINE_DEFAUT
            ? 'https' : 'http';

        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

        return $scheme . '://' . $host . $base . $chemin . $token;
    }

    /**
     * URL de vérification d'un DOSSIER (spec §7.1). Chemin court : le QR code
     * en contient moins de caractères, donc moins de modules, donc des modules
     * plus gros à taille de papier égale — c'est ce qui le rend lisible après
     * pliage et photocopie.
     */
    public static function urlDossier(string $token): string
    {
        return self::url($token, self::CHEMIN_DOSSIER);
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

    /**
     * QR code PNG en data URI, pour les documents destinés au PAPIER.
     *
     * Deux différences avec dataUri() (SVG) :
     *
     *  - Correction d'erreur **Q (25 %)** au lieu de M (spec §6.4). Le récépissé
     *    circule imprimé sur une imprimante bas de gamme, plié dans une poche,
     *    photocopié, puis photographié depuis un écran de téléphone. Un quart
     *    du code peut être détruit sans que la lecture échoue.
     *  - Rendu matriciel PNG. dompdf trace le SVG lui-même, et le moindre écart
     *    d'anticrénelage sur des modules de 1 pt casse la lecture ; un PNG à
     *    résolution maîtrisée est reproduit tel quel.
     *
     * Chaîne vide si le token est absent ou si la génération échoue : le
     * gabarit masque alors le bloc, le document reste délivrable.
     */
    public static function dataUriPng(
        ?string $token,
        int $tailleModule = 8,
        string $chemin = self::CHEMIN_DOCUMENT
    ): string {
        if ($token === null || $token === '') {
            return '';
        }

        try {
            $options = new QROptions([
                'outputInterface'   => QRGdImagePNG::class,
                'eccLevel'          => EccLevel::Q,
                'outputBase64'      => true,
                'addQuietzone'      => true,
                'quietzoneSize'     => 2,
                // Modules généreux : le document est destiné à l'impression,
                // pas à l'écran. 8 px/module donne un QR d'environ 300 px de
                // côté, soit ~30 mm à 250 dpi (taille minimale de la spec §6.3).
                'scale'             => max(4, min($tailleModule, 16)),
                'imageTransparent'  => false,
                'bgColor'           => [255, 255, 255],
            ]);

            return (new QRCode($options))->render(self::url($token, $chemin));
        } catch (\Throwable $e) {
            error_log('[QrCodeVerification] Génération QR PNG échouée : ' . $e->getMessage());

            // Repli SVG : un QR dans une autre technologie vaut mieux qu'aucun.
            return self::dataUri($token);
        }
    }
}
