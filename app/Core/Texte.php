<?php
declare(strict_types=1);

namespace Core;

/**
 * Normalisation de texte pour les usages où seul l'ASCII est admis :
 * bande de lecture optique du récépissé, codes d'établissement, segments de
 * chemin de stockage, clés de comparaison.
 *
 * ── Pourquoi ne pas se contenter d'iconv //TRANSLIT ────────────────────────
 * Le résultat de `iconv('UTF-8', 'ASCII//TRANSLIT')` dépend de l'implémentation
 * de libiconv du système. Sur glibc (Linux, la production), « Léa » devient
 * « Lea ». Sur la libiconv de macOS (les postes de développement), la même
 * fonction rend « L'ea » : l'accent est transformé en apostrophe séparée.
 *
 * Sur une bande MRZ, cette différence est visible et fausse le document :
 * « ARIANE<LEA » devient « ARIANE<L<EA ». Deux environnements produiraient
 * deux récépissés différents pour le même dossier — inacceptable pour une
 * pièce officielle.
 *
 * On applique donc une table explicite, identique partout, et on ne se sert
 * d'iconv qu'en dernier recours pour les caractères non listés.
 */
final class Texte
{
    /**
     * Lettres accentuées et ligatures des langues qui nous concernent :
     * français, et les usages courants au Gabon et dans la sous-région.
     */
    private const REMPLACEMENTS = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'Ç' => 'C', 'ç' => 'c',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'Ñ' => 'N', 'ñ' => 'n',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ý' => 'Y', 'ý' => 'y', 'ÿ' => 'y',
        'Æ' => 'AE', 'æ' => 'ae', 'Œ' => 'OE', 'œ' => 'oe',
        'ß' => 'ss', 'Ð' => 'D', 'ð' => 'd', 'Þ' => 'TH', 'þ' => 'th',
        // Apostrophes et tirets typographiques : ils traversent souvent les
        // copier-coller depuis un traitement de texte.
        '’' => "'", '‘' => "'", '“' => '"', '”' => '"',
        '–' => '-', '—' => '-', '…' => '...',
        "\u{00A0}" => ' ', "\u{202F}" => ' ', "\u{2009}" => ' ',
    ];

    /**
     * Retire les accents et les caractères typographiques, sans changer la
     * casse ni la ponctuation restante.
     */
    public static function sansAccents(string $valeur): string
    {
        $valeur = strtr($valeur, self::REMPLACEMENTS);

        // Tout ce qui est déjà ASCII sort tel quel.
        if (preg_match('//u', $valeur) !== 1 || preg_match('/[^\x20-\x7E]/', $valeur) !== 1) {
            return $valeur;
        }

        // Reste des caractères exotiques : iconv en dernier recours, puis
        // nettoyage des artefacts qu'il peut produire (`'e`, `^o`, `"u`).
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valeur);
        if ($translit === false) {
            return preg_replace('/[^\x20-\x7E]/', '', $valeur) ?? '';
        }

        return preg_replace('/[`\'"^~]([A-Za-z])/', '$1', $translit) ?? $translit;
    }

    /**
     * Forme canonique pour comparer deux libellés saisis à la main :
     * sans accents, en minuscules, espaces normalisés, ponctuation retirée.
     * « Côte d'Ivoire » et « cote d ivoire » donnent la même clé.
     */
    public static function cle(string $valeur): string
    {
        $valeur = strtolower(self::sansAccents($valeur));
        $valeur = preg_replace('/[^a-z0-9]+/', ' ', $valeur) ?? '';

        return trim(preg_replace('/\s+/', ' ', $valeur) ?? '');
    }

    /**
     * Réduit une chaîne à l'alphabet d'une bande de lecture optique :
     * A-Z et 0-9, tout séparateur devenant « < ».
     */
    public static function mrz(string $valeur): string
    {
        $valeur = strtoupper(self::sansAccents($valeur));
        $valeur = preg_replace('/[^A-Z0-9]+/', '<', $valeur) ?? '';

        return trim($valeur, '<');
    }
}
