<?php
declare(strict_types=1);

namespace Services\Orientation;

/**
 * Envoi d'un document officiel au navigateur.
 *
 * Les attestations sont servies en PDF et non plus en page HTML à imprimer :
 * le navigateur n'ajoute alors plus son en-tête / pied de page (URL de la page,
 * date, titre) en marge du document.
 *
 * Si le rendu PDF échoue (dompdf indisponible, mémoire), on retombe sur la
 * version HTML imprimable : le candidat obtient toujours son document.
 */
final class PdfStream
{
    /**
     * @param string|null $pdf          Octets du PDF, ou null si le rendu a échoué.
     * @param callable    $htmlFallback Rend la version HTML (appelée seulement en repli).
     * @param string      $nomFichier   Nom proposé, sans extension (assaini).
     */
    public static function emettre(?string $pdf, callable $htmlFallback, string $nomFichier): void
    {
        // Un PDF ne tolère aucun octet parasite : on vide les tampons ouverts.
        // Le repli HTML plus bas a besoin d'un tampon vide lui aussi.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Un PDF tronqué ou mal formé s'ouvre sur une page blanche : on ne sert
        // que des octets qui commencent bien par la signature du format.
        if ($pdf !== null && $pdf !== '' && !str_starts_with($pdf, '%PDF')) {
            error_log('[PdfStream] Sortie PDF invalide (' . strlen($pdf) . ' octets) — repli HTML.');
            $pdf = null;
        }

        if ($pdf !== null && $pdf !== '') {
            // Nom assaini : jamais de saut de ligne ni de guillemet dans un en-tête.
            $nom = preg_replace('/[^A-Za-z0-9._-]/', '', $nomFichier);
            $nom = ($nom === null || $nom === '') ? 'document' : $nom;

            // La compression et les politiques de sécurité posées par le
            // front-controller n'ont pas de sens sur un flux binaire : `object-src
            // 'none'` empêche certains navigateurs d'afficher la visionneuse PDF,
            // et la page reste blanche alors que le document est correct.
            //
            // Cette règle est désormais portée par Core\FichierBinaire, qui la
            // partage avec les pièces justificatives déposées par le candidat :
            // elles souffraient exactement du même défaut sans jamais avoir reçu
            // le correctif, faute de passer par ici.
            \Core\FichierBinaire::preparerReponse();

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $nom . '.pdf"');
            header('X-Content-Type-Options: nosniff');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo (string)$htmlFallback();
    }
}
