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

    /**
     * Data URI compressé du logo, ou chaîne vide si indisponible
     * (les gabarits basculent alors sur l'initiale de l'établissement).
     */
    /**
     * Mémoire du process, devant le cache partagé.
     *
     * Une page de l'espace élève affiche dix dossiers du même établissement, et
     * un document PDF réclame le même logo pour l'en-tête et le filigrane. Sans
     * ce garde, chaque occurrence repassait par le cache — et, quand celui-ci
     * était indisponible, par un téléchargement Backblaze. C'est ce qui a fini
     * par épuiser le plafond de transactions du compte.
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
                // Archivage local : ce logo ne dépendra plus jamais du stockage
                // distant, même cache vidé (cf. MiroirMedia).
                MiroirMedia::ecrire($cle, $valeur);
                Cache::set($clefCache, $valeur, self::CACHE_TTL);

                return self::$memoire[$cle] = $valeur;
            }

            // Échec mémorisé brièvement, le temps d'éviter le martèlement.
            Cache::set($clefCache, '', self::CACHE_TTL_ECHEC);
        }

        // Stockage muet — plafond atteint, réseau coupé, fichier absent. La
        // copie archivée prend le relais : mieux vaut un logo d'hier qu'une page
        // sans images.
        return self::$memoire[$cle] = MiroirMedia::lire($cle) ?? '';
    }

    /** Invalide le cache après remplacement du logo. */
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
