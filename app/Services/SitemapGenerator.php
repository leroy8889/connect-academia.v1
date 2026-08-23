<?php
declare(strict_types=1);

namespace Services;

use Models\Orientation\Etablissement;

/**
 * Génère le sitemap XML de Connect'Academia.
 *
 * Source unique partagée par :
 *   - le fichier physique /sitemap.xml (écrit sur disque, servi tel quel par
 *     Apache — rapide, pas d'accès base à chaque robot) ;
 *   - le contrôleur SitemapController (repli dynamique si le fichier manque).
 *
 * Le fichier est réécrit automatiquement à chaque création ou modification
 * d'un établissement par l'administration (cf. Admin\OrientationController) :
 * tout établissement passé au statut « actif » est donc référencé sans
 * intervention manuelle. Un script CLI (bin/generate-sitemap.php) permet aussi
 * une régénération forcée.
 */
class SitemapGenerator
{
    /** Pages publiques stables (sans authentification) — [chemin, priorité, fréquence]. */
    private const PAGES_STATIQUES = [
        ['/',                           '1.0', 'weekly'],
        ['/orientation',                '0.9', 'weekly'],
        ['/orientation/universites',    '0.7', 'weekly'],
        ['/orientation/grandes-ecoles', '0.7', 'weekly'],
        ['/documentation',              '0.6', 'monthly'],
        ['/contact',                    '0.5', 'yearly'],
        ['/auth/inscription',           '0.8', 'monthly'],
        ['/auth/connexion',             '0.6', 'monthly'],
    ];

    /** Construit le XML complet (pages fixes + chaque établissement actif). */
    public function build(): string
    {
        $base  = rtrim((string)($_ENV['APP_URL'] ?? 'https://connectacademia.io'), '/');
        $today = date('Y-m-d');
        $etabs = (new Etablissement())->sitemapEntries();

        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach (self::PAGES_STATIQUES as [$path, $priority, $freq]) {
            $xml .= $this->url($base . $path, $today, $freq, $priority, $esc);
        }

        foreach ($etabs as $e) {
            $slug = trim((string)($e['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $lastmod = $today;
            if (!empty($e['lastmod'])) {
                try {
                    $lastmod = (new \DateTime((string)$e['lastmod']))->format('Y-m-d');
                } catch (\Throwable) {
                    // Date illisible : on garde la date du jour
                }
            }
            $xml .= $this->url($base . '/orientation/' . $slug, $lastmod, 'weekly', '0.8', $esc);
        }

        $xml .= '</urlset>' . "\n";
        return $xml;
    }

    /** Chemin absolu du fichier sitemap physique (racine du projet). */
    public function filePath(): string
    {
        return BASE_PATH . '/sitemap.xml';
    }

    /**
     * Réécrit le fichier physique de façon atomique (écriture dans un fichier
     * temporaire puis rename). Ne lève jamais : renvoie false en cas d'échec
     * pour ne pas interrompre le flux appelant (une création d'établissement
     * ne doit pas casser parce que le sitemap n'a pas pu être écrit).
     */
    public function writeToDisk(): bool
    {
        try {
            $xml  = $this->build();
            $dest = $this->filePath();
            $tmp  = $dest . '.' . bin2hex(random_bytes(4)) . '.tmp';

            if (@file_put_contents($tmp, $xml, LOCK_EX) === false) {
                return false;
            }
            if (!@rename($tmp, $dest)) {
                @unlink($tmp);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            error_log('[SitemapGenerator] écriture échouée : ' . $e->getMessage());
            return false;
        }
    }

    /** Fabrique une entrée <url> du sitemap. */
    private function url(string $loc, string $lastmod, string $freq, string $priority, callable $esc): string
    {
        return "  <url>\n"
            . '    <loc>' . $esc($loc) . "</loc>\n"
            . '    <lastmod>' . $esc($lastmod) . "</lastmod>\n"
            . '    <changefreq>' . $esc($freq) . "</changefreq>\n"
            . '    <priority>' . $esc($priority) . "</priority>\n"
            . "  </url>\n";
    }
}
