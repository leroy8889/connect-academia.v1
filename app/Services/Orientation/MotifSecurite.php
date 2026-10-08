<?php
declare(strict_types=1);

namespace Services\Orientation;

/**
 * Fond guilloché des documents officiels.
 *
 * Une trame diagonale très pâle, répétée derrière le bloc d'identification du
 * récépissé. Rôle double : signaler visuellement qu'on a affaire à un document
 * officiel, et rendre une photocopie ou une retouche grossière détectables —
 * la trame se dégrade de façon caractéristique.
 *
 * Le motif est une tuile PNG minuscule (24 px) produite par GD et embarquée en
 * data URI. Pourquoi pas un SVG : dompdf ne trace pas les SVG en
 * `background-image`, seulement dans un `<img>`. Une tuile matricielle est
 * reproduite telle quelle par tous les moteurs.
 *
 * La tuile pèse quelques centaines d'octets et est mémorisée pour la durée du
 * processus : générer un récépissé n'appelle GD qu'une fois.
 */
final class MotifSecurite
{
    private const TAILLE = 24;

    /** @var array<string,string> Tuiles déjà produites, par couleur. */
    private static array $cache = [];

    /**
     * Tuile guillochée en data URI, prête pour `background-image: url(…)`.
     *
     * @param string $couleurHex Couleur des traits (#RRGGBB).
     * @param float  $opacite    0–1. Au-delà de 0,10 la trame devient lisible
     *                           à l'œil et gêne la lecture du texte posé dessus.
     */
    public static function tuile(string $couleurHex = '#8B52FA', float $opacite = 0.055): string
    {
        $cle = $couleurHex . '|' . $opacite;
        if (isset(self::$cache[$cle])) {
            return self::$cache[$cle];
        }

        $data = self::produire($couleurHex, $opacite);
        if ($data === null) {
            // GD absent ou en échec : pas de fond. Un document sans guilloché
            // reste parfaitement valide — c'est un ornement, pas une preuve.
            return self::$cache[$cle] = '';
        }

        return self::$cache[$cle] = $data;
    }

    private static function produire(string $couleurHex, float $opacite): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        try {
            [$r, $v, $b] = self::composantes($couleurHex);

            // Alpha GD : 0 = opaque, 127 = transparent (échelle inversée).
            $alpha = (int)round(127 - (max(0.0, min($opacite, 1.0)) * 127));

            $image = imagecreatetruecolor(self::TAILLE, self::TAILLE);
            if ($image === false) {
                return null;
            }

            imagealphablending($image, false);
            imagesavealpha($image, true);

            $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
            imagefill($image, 0, 0, $transparent);

            imagealphablending($image, true);
            $trait = imagecolorallocatealpha($image, $r, $v, $b, $alpha);

            // Deux familles de diagonales croisées, décalées d'une demi-tuile
            // pour que le raccord soit invisible une fois le motif répété.
            $t = self::TAILLE;
            imageline($image, 0, 0, $t, $t, $trait);
            imageline($image, 0, $t, $t, 0, $trait);
            imageline($image, -1, (int)($t / 2), (int)($t / 2), -1, $trait);
            imageline($image, (int)($t / 2), $t + 1, $t + 1, (int)($t / 2), $trait);

            ob_start();
            imagepng($image, null, 9);
            $png = (string)ob_get_clean();
            imagedestroy($image);

            if ($png === '') {
                return null;
            }

            return 'data:image/png;base64,' . base64_encode($png);
        } catch (\Throwable $e) {
            error_log('[MotifSecurite] Génération échouée : ' . $e->getMessage());

            return null;
        }
    }

    /** @return array{0:int,1:int,2:int} */
    private static function composantes(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            $hex = '8B52FA';
        }

        return [
            (int)hexdec(substr($hex, 0, 2)),
            (int)hexdec(substr($hex, 2, 2)),
            (int)hexdec(substr($hex, 4, 2)),
        ];
    }
}
