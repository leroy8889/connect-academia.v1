<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Cache;

/**
 * Signature / cachet établissement → data URI PNG « encre » prêt à imprimer
 * sur l'attestation.
 *
 * Distinct de LogoDataUri (qui ne fait que redimensionner) : ici on traite le
 * scan pour qu'il ne ressemble PAS à une image collée —
 *   1. fond blanc/papier → réellement transparent (seuil de luminance) ;
 *   2. traits recolorés en encre bleu-violet, opacité proportionnelle à la
 *      noirceur du pixel → bords anti-aliasés qui « fondent » dans le papier
 *      (fin de trait = semi-transparent, comme de l'encre qui s'estompe).
 *
 * Résultat mis en cache Redis (6 h) : GD ne tourne qu'une fois par signature.
 */
class SignatureDataUri
{
    private const MAX_DIMENSION = 520;
    private const CACHE_TTL     = 21600; // 6 h
    private const CACHE_PREFIX  = 'orientation:signature_datauri:';

    /**
     * TTL d'un ÉCHEC. Volontairement court.
     *
     * Un résultat vide signifie « fichier momentanément illisible » : stockage
     * injoignable, plafond de transactions atteint, fichier pas encore
     * répliqué. Le mémoriser six heures ferait survivre la panne longtemps
     * après sa résolution — un plafond relevé à 9 h laisserait la plateforme
     * sans images jusqu'à 15 h. Une minute suffit à éviter le martèlement du
     * stockage, sans figer la panne.
     */
    private const CACHE_TTL_ECHEC = 60;

    /** Encre par défaut : bleu-violet stylo administratif. */
    private const INK = [0x18, 0x2F, 0x74];

    /** Au-dessus de ce seuil de luminance → papier → transparent. */
    private const WHITE_THRESHOLD = 232;

    /**
     * Mémoire du process, devant le cache partagé : même raison que
     * LogoDataUri — un document réclame plusieurs fois le même cachet, et
     * chaque occurrence retournait chercher le fichier sur Backblaze quand le
     * cache partagé était indisponible.
     *
     * @var array<string,string>
     */
    private static array $memoire = [];

    public static function pour(string $cle): string
    {
        if ($cle === '') {
            return '';
        }

        // La mémoire du process porte la décision FINALE de cette requête,
        // archive comprise : une fois tranché, on ne retranche pas.
        if (isset(self::$memoire[$cle])) {
            return self::$memoire[$cle];
        }

        $clefCache = self::CACHE_PREFIX . md5($cle);
        $absent    = new \stdClass();
        $connu     = Cache::get($clefCache, $absent);

        // Une valeur vide en cache n'est PAS une réponse : c'est la trace d'un
        // stockage muet il y a moins d'une minute. Elle évite de le marteler,
        // elle ne doit pas empêcher l'archive de répondre à sa place.
        if ($connu !== $absent && $connu !== '') {
            return self::$memoire[$cle] = (string)$connu;
        }

        if ($connu === $absent) {
            $valeur = self::construire($cle);

            if ($valeur !== '') {
                // Archivage local : ce cachet ne dépendra plus jamais du stockage
                // distant, même cache vidé (cf. MiroirMedia).
                MiroirMedia::ecrire($cle, $valeur);
                Cache::set($clefCache, $valeur, self::CACHE_TTL);

                return self::$memoire[$cle] = $valeur;
            }

            // Échec mémorisé brièvement, le temps d'éviter le martèlement.
            Cache::set($clefCache, '', self::CACHE_TTL_ECHEC);
        }

        // Stockage muet — plafond atteint, réseau coupé, fichier absent. La
        // copie archivée prend le relais : mieux vaut un cachet d'hier qu'une page
        // sans images.
        return self::$memoire[$cle] = MiroirMedia::lire($cle) ?? '';
    }

    /** Invalide le cache après remplacement/retrait de la signature. */
    public static function invalider(string $cle): void
    {
        if ($cle !== '') {
            unset(self::$memoire[$cle]);
            Cache::forget(self::CACHE_PREFIX . md5($cle));
            MiroirMedia::oublier($cle);
        }
    }

    private static function construire(string $cle): string
    {
        try {
            $contenu = (new DocumentStorage())->getContent($cle);
        } catch (\Throwable $e) {
            error_log('[SignatureDataUri] Indisponible (' . $cle . ') : ' . $e->getMessage());
            return '';
        }

        if ($contenu === '' || !function_exists('imagecreatefromstring')) {
            // GD absent : repli data URI brut (comportement « image collée »,
            // jamais pire que rien).
            return $contenu !== '' ? self::brut($contenu) : '';
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenu) ?: '';
        if (!str_starts_with($mime, 'image/')) {
            return '';
        }

        $traite = self::traiter($contenu);
        if ($traite === null) {
            return self::brut($contenu);
        }

        return 'data:image/png;base64,' . base64_encode($traite);
    }

    private static function brut(string $contenu): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenu) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($contenu);
    }

    /** Nettoyage encre : fond transparent + recolorisation. Null si GD échoue. */
    private static function traiter(string $contenu): ?string
    {
        try {
            $src = @imagecreatefromstring($contenu);
            if ($src === false) {
                return null;
            }

            $w = imagesx($src);
            $h = imagesy($src);
            $ratio = min(1.0, self::MAX_DIMENSION / max($w, $h, 1));
            $nw = max(1, (int)round($w * $ratio));
            $nh = max(1, (int)round($h * $ratio));

            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);

            [$ir, $ig, $ib] = self::INK;

            // Palette d'encre indexée par niveau d'alpha (128 nuances) —
            // évite d'allouer une couleur par pixel.
            $encre = [];

            for ($y = 0; $y < $nh; $y++) {
                for ($x = 0; $x < $nw; $x++) {
                    $rgba = imagecolorat($dst, $x, $y);
                    $a = ($rgba >> 24) & 0x7F;
                    if ($a >= 110) {
                        continue; // déjà transparent : on laisse
                    }
                    $r = ($rgba >> 16) & 0xFF;
                    $g = ($rgba >> 8) & 0xFF;
                    $b = $rgba & 0xFF;
                    $lum = (int)round(0.299 * $r + 0.587 * $g + 0.114 * $b);

                    if ($lum >= self::WHITE_THRESHOLD) {
                        // Papier → transparent
                        imagesetpixel($dst, $x, $y, imagecolorallocatealpha($dst, 0, 0, 0, 127));
                        continue;
                    }

                    // Trait : plus c'est sombre, plus c'est opaque (encre dense).
                    $darkness = (self::WHITE_THRESHOLD - $lum) / self::WHITE_THRESHOLD; // 0..1
                    $alpha    = 127 - (int)round($darkness * 127); // 0 = opaque
                    $alpha    = max(0, min(127, $alpha));

                    if (!isset($encre[$alpha])) {
                        $encre[$alpha] = imagecolorallocatealpha($dst, $ir, $ig, $ib, $alpha);
                    }
                    imagesetpixel($dst, $x, $y, $encre[$alpha]);
                }
            }

            ob_start();
            imagepng($dst, null, 8);
            $sortie = (string)ob_get_clean();
            imagedestroy($dst);

            return $sortie !== '' ? $sortie : null;
        } catch (\Throwable $e) {
            error_log('[SignatureDataUri] Traitement échoué : ' . $e->getMessage());
            return null;
        }
    }
}
