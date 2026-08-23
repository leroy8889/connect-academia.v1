<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Cache;

/**
 * Logo établissement → data URI léger, prêt à embarquer dans un document HTML
 * autonome (récépissé, attestation).
 *
 * Un logo uploadé par un établissement peut peser plusieurs Mo : embarqué tel
 * quel en base64 (et répété en-tête + filigrane + signature), le document
 * dépassait 25 Mo — uploads B2 interminables et téléchargements perçus comme
 * des erreurs côté candidat. Ici : redimensionnement GD (côté max 480 px),
 * ré-encodage compressé, puis cache Redis — B2 n'est sollicité qu'une fois
 * par logo toutes les 6 h.
 */
class LogoDataUri
{
    private const MAX_DIMENSION = 480;
    private const CACHE_TTL     = 21600; // 6 h
    private const CACHE_PREFIX  = 'orientation:logo_datauri:';

    /**
     * Data URI compressé du logo, ou chaîne vide si indisponible
     * (les gabarits basculent alors sur l'initiale de l'établissement).
     */
    public static function pour(string $cle): string
    {
        if ($cle === '') {
            return '';
        }

        return (string)Cache::remember(
            self::CACHE_PREFIX . md5($cle),
            self::CACHE_TTL,
            static fn(): string => self::construire($cle)
        );
    }

    /** Invalide le cache après remplacement du logo. */
    public static function invalider(string $cle): void
    {
        if ($cle !== '') {
            Cache::forget(self::CACHE_PREFIX . md5($cle));
        }
    }

    private static function construire(string $cle): string
    {
        try {
            $contenu = (new DocumentStorage())->getContent($cle);
        } catch (\Throwable $e) {
            error_log('[LogoDataUri] Logo indisponible (' . $cle . ') : ' . $e->getMessage());
            return '';
        }

        if ($contenu === '') {
            return '';
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenu) ?: '';
        if (!str_starts_with($mime, 'image/')) {
            return '';
        }

        $optimise = self::redimensionner($contenu, $mime);
        if ($optimise !== null) {
            [$contenu, $mime] = $optimise;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contenu);
    }

    /**
     * Réduit l'image au côté max autorisé et la ré-encode (PNG si transparence,
     * JPEG sinon). Retourne null si GD échoue — l'original est alors embarqué
     * tel quel (comportement d'avant, jamais pire).
     *
     * @return array{0: string, 1: string}|null [contenu, mime]
     */
    private static function redimensionner(string $contenu, string $mime): ?array
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

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
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $transparent);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);

            $avecAlpha = self::contientTransparence($dst, $nw, $nh);

            ob_start();
            if ($avecAlpha) {
                imagepng($dst, null, 8);
                $mimeSortie = 'image/png';
            } else {
                imagejpeg($dst, null, 82);
                $mimeSortie = 'image/jpeg';
            }
            $sortie = (string)ob_get_clean();
            imagedestroy($dst);

            if ($sortie === '') {
                return null;
            }

            // Garde le plus léger des deux (petit PNG déjà optimisé, par ex.)
            if (strlen($sortie) >= strlen($contenu)) {
                return [$contenu, $mime];
            }

            return [$sortie, $mimeSortie];
        } catch (\Throwable $e) {
            error_log('[LogoDataUri] Redimensionnement échoué : ' . $e->getMessage());
            return null;
        }
    }

    /** Échantillonne l'image pour détecter un canal alpha réellement utilisé. */
    private static function contientTransparence(\GdImage $img, int $w, int $h): bool
    {
        $pasX = max(1, intdiv($w, 24));
        $pasY = max(1, intdiv($h, 24));
        for ($x = 0; $x < $w; $x += $pasX) {
            for ($y = 0; $y < $h; $y += $pasY) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }
        return false;
    }
}
