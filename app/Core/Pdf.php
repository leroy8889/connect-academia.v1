<?php
declare(strict_types=1);

namespace Core;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Fabrique unique des documents PDF de la plateforme.
 *
 * Ces documents partent chez des candidats et des établissements partenaires :
 * un fichier blanc est inacceptable. Le rendu est donc VÉRIFIÉ avant d'être
 * rendu à l'appelant, et deux replis se déclenchent tout seuls si le moteur
 * défaille.
 *
 * ── Pourquoi cette méfiance ────────────────────────────────────────────────
 * Pour écrire du texte, dompdf lit le fichier de police octet par octet
 * (php-font-lib) afin d'en extraire chaque glyphe. C'est du code chaud et
 * répétitif — exactement ce que le JIT d'opcache décide de recompiler. Une
 * recompilation fautive ne provoque aucune erreur : la fonction rend des
 * données fausses en silence, dompdf produit un PDF structurellement valide,
 * et plus une seule lettre n'est dessinée. Les images (QR code, filigrane)
 * n'empruntent pas ce chemin et continuent de s'afficher — signature exacte de
 * la panne observée en production le 4 août 2026.
 *
 * ── Les trois lignes de défense ────────────────────────────────────────────
 *  1. `opcache.jit` coupé pour la durée de la requête, quand le serveur
 *     l'autorise.
 *  2. Rendu vérifié. Si le document ne contient aucune police, second essai
 *     avec Helvetica : c'est une police NATIVE du format PDF, présente dans
 *     tous les lecteurs, qui ne passe par aucun analyseur de fichier `.ttf`.
 *     Le document sort dans une autre typographie, mais il sort lisible.
 *  3. Si même ce repli échoue, on retourne null. À l'appelant de servir sa
 *     version HTML imprimable — jamais un PDF vide.
 *
 * Le correctif durable reste `opcache.jit_buffer_size=0` dans le php.ini de
 * PHP-FPM. Ce fichier est le filet, pas la réparation.
 */
final class Pdf
{
    /** Cache de polices + fichiers temporaires, relatif à la racine projet. */
    private const CACHE_DIR = '/storage/dompdf';

    /** Police native du format PDF, sans analyse de fichier de police. */
    private const POLICE_SECOURS = 'Helvetica';

    /**
     * Rend un document et garantit qu'il est exploitable, ou retourne null.
     *
     * @param string        $html        Document complet (UTF-8).
     * @param callable|null $apresRendu  Reçoit le Dompdf après `render()`, pour
     *                                   les pieds de page numérotés.
     * @param string        $orientation 'portrait' ou 'landscape'.
     * @return string|null Octets du PDF, ou null si aucun rendu n'est utilisable.
     */
    public static function rendre(
        string $html,
        ?callable $apresRendu = null,
        string $orientation = 'portrait'
    ): ?string {
        // 1re ligne : rendu normal, polices DejaVu (accents complets).
        $pdf = self::tenter($html, self::options(), $apresRendu, $orientation);
        if ($pdf !== null && self::estExploitable($pdf)) {
            return $pdf;
        }

        error_log('[Pdf] Rendu principal inexploitable — repli sur ' . self::POLICE_SECOURS
            . '. Cause probable : JIT opcache actif (opcache.jit='
            . var_export(ini_get('opcache.jit'), true) . '). '
            . 'Correctif serveur : opcache.jit_buffer_size=0 dans le php.ini PHP-FPM.');

        // 2e ligne : police native du format PDF, aucun `.ttf` à analyser.
        $secours = self::options();
        $secours->set('defaultFont', self::POLICE_SECOURS);
        $pdf = self::tenter(self::forcerPoliceNative($html), $secours, $apresRendu, $orientation);
        if ($pdf !== null && self::estExploitable($pdf)) {
            return $pdf;
        }

        error_log('[Pdf] Repli ' . self::POLICE_SECOURS . ' inexploitable lui aussi — '
            . 'l\'appelant doit servir sa version HTML imprimable.');

        return null;
    }

    /**
     * Options dompdf : ressources distantes coupées (data URI uniquement),
     * police gérant les accents, cache inscriptible, JIT neutralisé.
     *
     * @param bool $couperJit Passer false uniquement pour observer le moteur tel
     *                        qu'il tourne réellement (page de diagnostic).
     */
    public static function options(bool $couperJit = true): Options
    {
        if ($couperJit) {
            self::desactiverJit();
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);   // data URI uniquement
        $options->set('defaultFont', 'DejaVu Sans');

        $cache = self::cacheDir();
        if ($cache !== null) {
            $options->set('fontDir', $cache);
            $options->set('fontCache', $cache);
            $options->set('tempDir', $cache);
        }

        return $options;
    }

    /**
     * Un document est exploitable s'il est un PDF, qu'il porte au moins une
     * page, et qu'au moins une police y est déclarée.
     *
     * C'est ce dernier point qui attrape la panne : un PDF sans `/BaseFont` ne
     * peut dessiner aucune lettre. Il s'ouvre normalement et reste blanc.
     */
    public static function estExploitable(string $pdf): bool
    {
        if ($pdf === '' || !str_starts_with($pdf, '%PDF')) {
            return false;
        }
        if (!preg_match('/\/Type\s*\/Page[^s]/', $pdf)) {
            return false;
        }

        return (bool)preg_match('/\/BaseFont\s*\//', $pdf);
    }

    /** Un essai de rendu. null si dompdf lève. */
    private static function tenter(
        string $html,
        Options $options,
        ?callable $apresRendu,
        string $orientation
    ): ?string {
        try {
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', $orientation);
            $dompdf->render();

            if ($apresRendu !== null) {
                $apresRendu($dompdf);
            }

            return (string)$dompdf->output();
        } catch (\Throwable $e) {
            error_log('[Pdf] Rendu échoué : ' . $e->getMessage()
                . ' @' . $e->getFile() . ':' . $e->getLine());
            return null;
        }
    }

    /**
     * Impose la police native à tout le document.
     *
     * Les gabarits déclarent « DejaVu Sans » un peu partout ; sans cette
     * surcharge, le repli retomberait sur la police qui vient d'échouer. La
     * règle est injectée en fin de `<head>` pour passer après les autres.
     */
    private static function forcerPoliceNative(string $html): string
    {
        $regle = '<style>*{font-family:' . self::POLICE_SECOURS . ',sans-serif !important;}</style>';

        $pos = stripos($html, '</head>');
        if ($pos !== false) {
            return substr($html, 0, $pos) . $regle . substr($html, $pos);
        }

        return $regle . $html;
    }

    /**
     * Coupe le JIT opcache le temps de la requête, s'il est actif.
     *
     * Journalise le refus : sur un serveur où la bascule à chaud est interdite,
     * seul le php.ini compte, et le problème doit rester visible.
     */
    private static function desactiverJit(): void
    {
        $jit = (string)ini_get('opcache.jit');

        if ($jit === '' || $jit === 'off' || $jit === 'disable'
            || (int)ini_get('opcache.jit_buffer_size') === 0) {
            return;
        }

        if (@ini_set('opcache.jit', 'off') === false) {
            error_log('[Pdf] JIT opcache actif (' . $jit . ') et non désactivable à chaud — '
                . 'poser opcache.jit_buffer_size=0 dans le php.ini de PHP-FPM.');
        }
    }

    /** Chemin du cache, créé au besoin. null si le disque refuse. */
    private static function cacheDir(): ?string
    {
        $dir = BASE_PATH . self::CACHE_DIR;

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log('[Pdf] Cache de polices non créable : ' . $dir);
            return null;
        }

        if (!is_writable($dir)) {
            error_log('[Pdf] Cache de polices non inscriptible : ' . $dir);
            return null;
        }

        return $dir;
    }
}
