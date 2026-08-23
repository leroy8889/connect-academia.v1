<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;

class SlugService
{
    public static function fromNom(string $nom): string
    {
        if (function_exists('transliterator_transliterate')) {
            // intl : "École d'Économie" → "ecole d'economie" (accents proprement retirés)
            $slug = (string)transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', trim($nom));
        } else {
            // Fallback iconv : retirer les apostrophes de translittération ("é" → "'e")
            $slug = mb_strtolower(trim($nom), 'UTF-8');
            $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
            $slug = str_replace(["'", '`', '^', '"', '~'], '', $slug);
        }
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'etablissement';
        }

        $db   = Database::getInstance()->getConnection();
        $base = $slug;
        $i    = 2;

        while (true) {
            $st = $db->prepare('SELECT id FROM etablissements WHERE slug = ?');
            $st->execute([$slug]);
            if (!$st->fetch()) {
                break;
            }
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
