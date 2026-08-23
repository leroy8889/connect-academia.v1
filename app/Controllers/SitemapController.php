<?php
declare(strict_types=1);

namespace Controllers;

use Services\SitemapGenerator;

/**
 * Sitemap XML — repli dynamique (/sitemap.xml).
 *
 * En temps normal le fichier physique /sitemap.xml (réécrit à chaque
 * changement d'établissement, cf. Services\SitemapGenerator) est servi
 * directement par Apache. Cette route ne s'exécute donc que si le fichier
 * est absent : elle régénère alors le XML à la volée et tente de le
 * persister sur disque pour les prochaines requêtes.
 */
class SitemapController
{
    public function index(): void
    {
        $gen = new SitemapGenerator();
        $xml = $gen->build();

        // Recrée le fichier physique manquant (best-effort, silencieux).
        $gen->writeToDisk();

        if (!headers_sent()) {
            header('Content-Type: application/xml; charset=utf-8');
            header('X-Robots-Tag: noindex');
        }
        echo $xml;
    }
}
