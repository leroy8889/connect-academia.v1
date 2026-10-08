<?php
declare(strict_types=1);

namespace Core;

/**
 * Envoi d'un fichier binaire déjà en mémoire au navigateur (PDF, image).
 *
 * ── Le problème que cette classe résout ──────────────────────────────────────
 *
 * Le front-controller pose sur CHAQUE réponse une politique de sécurité de
 * contenu qui contient `object-src 'none'`. C'est la bonne règle pour une page
 * HTML. Sur une réponse dont le corps EST un PDF, elle a un effet de bord
 * dévastateur : les navigateurs fondés sur Chromium affichent un PDF en ligne
 * au moyen d'une visionneuse interne traitée comme un plugin, et `object-src`
 * la bloque. L'utilisateur ne voit pas une erreur : il voit une **page
 * parfaitement blanche**, alors que les octets envoyés sont valides.
 *
 * Le symptôme est plus visible sur téléphone. Sur ordinateur, un PDF bloqué en
 * ligne se rabat souvent sur un téléchargement — l'utilisateur récupère quand
 * même son fichier et ne signale rien. Sur mobile, le navigateur ouvre un
 * nouvel onglet, la visionneuse est bloquée, et il ne reste rien à l'écran.
 *
 * Ce diagnostic n'est pas nouveau dans ce projet : `PdfStream` le corrige
 * depuis l'épisode des « PDF blancs en production » pour les documents
 * ENGENDRÉS (attestation, récépissé, reçu). Mais chaque endroit qui sert un
 * fichier le réécrivait à la main, et les pièces justificatives DÉPOSÉES par le
 * candidat — le bouton « Voir » de son espace — n'avaient jamais reçu le
 * correctif. D'où cette classe : la règle vit à UN seul endroit.
 *
 * ── Pourquoi retirer l'en-tête et non l'assouplir ────────────────────────────
 *
 * Un `object-src 'self'` suffirait en théorie. En pratique la valeur exacte qui
 * débloque la visionneuse varie d'un navigateur et d'une version à l'autre, et
 * une politique « presque bonne » redonne la page blanche sans rien dire. On
 * retire donc l'en-tête, comme le fait déjà `PdfStream` en production.
 *
 * Ce retrait n'ouvre aucune brèche, parce que la protection ne vient pas de la
 * CSP ici mais de trois verrous en amont, tous conservés :
 *
 *   1. le type MIME est validé au DÉPÔT (liste blanche, type réel lu sur le
 *      contenu et non déclaré par le client — cf. PieceDossier / DocumentStorage) ;
 *   2. `X-Content-Type-Options: nosniff` interdit au navigateur de requalifier
 *      le fichier en HTML ;
 *   3. l'affichage EN LIGNE est réservé à une liste blanche de types inoffensifs ;
 *      tout le reste part en pièce jointe, donc sans exécution possible.
 *
 * Le SVG est absent de la liste blanche, et doit le rester : c'est du XML
 * exécutable, servi en ligne depuis notre propre domaine il vaudrait un XSS
 * stocké.
 */
final class FichierBinaire
{
    /**
     * Types affichables EN LIGNE sans risque. Tout ce qui n'est pas ici est
     * forcé en téléchargement, quel que soit ce que demande l'appelant.
     */
    public const TYPES_EN_LIGNE = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/bmp',
        'image/x-ms-bmp',
        'image/avif',
    ];

    /**
     * Extension de fichier attendue pour chaque type servi.
     *
     * Couvre tous les formats déposables (cf. PieceDossier::typesAcceptes) et
     * pas seulement ceux affichables en ligne : un HEIC ou un TIFF part en
     * téléchargement, mais il doit arriver avec la bonne extension, sans quoi
     * le téléphone du candidat ne sait pas avec quoi l'ouvrir.
     */
    private const EXTENSIONS = [
        'application/pdf'          => 'pdf',
        'image/jpeg'               => 'jpg',
        'image/png'                => 'png',
        'image/webp'               => 'webp',
        'image/gif'                => 'gif',
        'image/bmp'                => 'bmp',
        'image/x-ms-bmp'           => 'bmp',
        'image/tiff'               => 'tif',
        'image/avif'               => 'avif',
        'image/heic'               => 'heic',
        'image/heif'               => 'heif',
        'image/x-icon'             => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];

    /**
     * Prépare la réponse à recevoir un corps binaire.
     *
     * Trois gestes, tous nécessaires :
     *  · vider les tampons de sortie — un seul octet parasite (un espace avant
     *    `<?php`, un avertissement) suffit à corrompre un PDF ;
     *  · couper la compression — un flux gzipé dont la longueur annoncée est
     *    celle du contenu brut est tronqué à l'arrivée ;
     *  · retirer les en-têtes de politique HTML (voir l'en-tête de classe).
     */
    public static function preparerReponse(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');

        if (!headers_sent()) {
            header_remove('Content-Security-Policy');
            header_remove('X-Frame-Options');
        }
    }

    /**
     * Nom de fichier sûr et OUVRABLE.
     *
     * L'extension n'est pas cosmétique : un fichier nommé « Attestation_du_Bac »
     * sans extension est refusé par la plupart des applications mobiles, qui
     * choisissent l'application d'ouverture d'après le nom et non d'après le
     * type MIME. Le candidat téléchargeait un fichier qu'il ne pouvait plus
     * ouvrir.
     */
    public static function nomSur(string $nomPropose, string $mime): string
    {
        // Jamais de guillemet ni de saut de ligne : l'en-tête Content-Disposition
        // s'injecte comme n'importe quel en-tête HTTP.
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nomPropose) ?? '';
        $nom = trim($nom, '_.');
        if ($nom === '') {
            $nom = 'document';
        }
        $nom = mb_substr($nom, 0, 80);

        $ext = self::EXTENSIONS[strtolower(trim($mime))] ?? null;
        if ($ext !== null && !preg_match('/\.' . $ext . '$/i', $nom)) {
            $nom .= '.' . $ext;
        }

        return $nom;
    }

    /**
     * Émet le fichier. `$inline` n'est qu'un souhait : un type absent de
     * TYPES_EN_LIGNE part toujours en pièce jointe.
     */
    public static function emettre(
        string $contenu,
        string $mime,
        string $nomPropose,
        bool $inline = true
    ): void {
        $mime   = strtolower(trim($mime));
        $inline = $inline && in_array($mime, self::TYPES_EN_LIGNE, true);

        self::preparerReponse();

        header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
             . '; filename="' . self::nomSur($nomPropose, $mime) . '"');
        header('Content-Length: ' . strlen($contenu));
        header('X-Content-Type-Options: nosniff');
        // Pièce d'identité, relevé de notes : rien de tout cela n'a sa place
        // dans le cache d'un navigateur partagé (cybercafé, téléphone prêté).
        header('Cache-Control: private, no-store');

        echo $contenu;
    }
}
