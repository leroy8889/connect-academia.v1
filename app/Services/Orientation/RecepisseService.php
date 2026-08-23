<?php
declare(strict_types=1);

namespace Services\Orientation;

/**
 * Génération du récépissé officiel de préinscription (gabarit maquette).
 *
 * Rendu HTML autonome : le logo de l'établissement est embarqué en data URI
 * (en-tête, filigrane, bloc signature) — aucun lien signé périssable.
 * Utilisé par le flux en ligne, le guichet et le téléchargement gestionnaire.
 */
class RecepisseService
{
    private const FILIERES = [
        'socle_l1_l2'   => 'Socle L1-L2',
        'licence_l3'    => 'Licence L3',
        'master_m1_m2'  => 'Master M1-M2',
        'certification' => 'Certification',
    ];

    /**
     * Rend le récépissé en HTML.
     *
     * @param array $d    Données candidat — mêmes clés côté formulaire ($_POST)
     *                    et côté base (row dossiers_inscription) : nom_complet,
     *                    date_naissance, lieu_naissance, nationalite,
     *                    pays_provenance, indicatif_tel, telephone, email,
     *                    filiere_visee, niveau_entree, dernier_etablissement.
     * @param array       $etab  Row établissement (nom, ville, couleur_principale, logo_cle).
     * @param string|null $tokenVerification Token d'authenticité (64 hex) — imprime
     *                    le QR code de vérification /verifier/{token} sur le document.
     */
    public function render(array $d, array $etab, string $numero, ?\DateTimeInterface $dateEmission = null, ?string $tokenVerification = null): string
    {
        return $this->rendre($d, $etab, $numero, $dateEmission, $tokenVerification, false);
    }

    /**
     * Rend l'attestation de préinscription en PDF (dompdf).
     *
     * Un vrai PDF plutôt qu'une page HTML à imprimer : le navigateur n'ajoute
     * plus son en-tête / pied de page (URL de la page, date, titre) en marge du
     * document officiel. Retourne null si le rendu échoue — l'appelant retombe
     * alors sur la version HTML imprimable.
     */
    public function renderPdf(array $d, array $etab, string $numero, ?\DateTimeInterface $dateEmission = null, ?string $tokenVerification = null): ?string
    {
        $html = $this->rendre($d, $etab, $numero, $dateEmission, $tokenVerification, true);

        // Core\Pdf vérifie le document produit et bascule seul sur une police
        // native si le moteur de glyphes défaille ; null = aucun rendu
        // exploitable, l'appelant sert alors la version HTML imprimable.
        $pdf = \Core\Pdf::rendre($html);
        if ($pdf === null) {
            error_log('[RecepisseService] Aucun rendu PDF exploitable — repli HTML.');
        }

        return $pdf;
    }

    /**
     * Rend l'attestation ET, si l'établissement a configuré des tarifs
     * (Etablissement::prix_inscription / frais_scolarite), une facture
     * proforma indicative en page 2 du même PDF (voir FactureProformaService).
     * Même contrat que renderPdf() : null si aucun rendu n'est exploitable.
     */
    public function renderPdfComplet(array $d, array $etab, string $numero, string $numeroFacture, ?\DateTimeInterface $dateEmission = null, ?string $tokenVerification = null): ?string
    {
        $html = $this->rendre($d, $etab, $numero, $dateEmission, $tokenVerification, true);

        if (FactureProformaService::etabAConfigureTarifs($etab, $d)) {
            $fragment = (new FactureProformaService())->render($d, $etab, $numeroFacture, $dateEmission, $tokenVerification);
            $html     = self::injecterPageSupplementaire($html, $fragment);
        }

        $pdf = \Core\Pdf::rendre($html);
        if ($pdf === null) {
            error_log('[RecepisseService] Aucun rendu PDF exploitable (avec facture) — repli HTML.');
        }

        return $pdf;
    }

    /**
     * Fusionne un fragment (<style>…</style> + markup) dans un document HTML
     * complet, en page suivante : les règles du fragment rejoignent le <style>
     * principal, son markup est inséré juste avant </body>.
     */
    private static function injecterPageSupplementaire(string $htmlPrincipal, string $fragment): string
    {
        if (preg_match('/<style>(.*)<\/style>/s', $fragment, $m)) {
            $fragment      = trim(str_replace($m[0], '', $fragment));
            $htmlPrincipal = preg_replace('/<\/style>/i', $m[1] . '</style>', $htmlPrincipal, 1);
        }

        return str_ireplace('</body>', $fragment . '</body>', $htmlPrincipal);
    }

    private function rendre(array $d, array $etab, string $numero, ?\DateTimeInterface $dateEmission, ?string $tokenVerification, bool $pourPdf): string
    {
        $date = $dateEmission ?? new \DateTime();

        // Année universitaire courante de la plateforme (bascule juin — préinscriptions rentrée)
        $anneeUniv = \Core\AnneeUniversitaire::courante($date);

        $etabNom      = (string)($etab['nom'] ?? '');
        $etabVille    = (string)($etab['ville'] ?? '');
        $couleur      = (string)($etab['couleur_principale'] ?? '#1E3A5F');
        $etabInitiale = $etabNom !== '' ? mb_strtoupper(mb_substr($etabNom, 0, 1)) : '?';
        $logoDataUri  = LogoDataUri::pour((string)($etab['logo_cle'] ?? ''));
        $cachetDataUri = LogoDataUri::pour((string)($etab['cachet_cle'] ?? ''));

        $dateNaissance = '—';
        if (!empty($d['date_naissance'])) {
            try {
                $dateNaissance = (new \DateTime((string)$d['date_naissance']))->format('d/m/Y');
            } catch (\Throwable) {
                // Date illisible : le tiret reste
            }
        }

        $telephoneBrut = trim((string)($d['telephone'] ?? ''));
        $telephone     = '—';
        if ($telephoneBrut !== '') {
            // Ne pas doubler l'indicatif si le numéro saisi le contient déjà
            $telephone = str_starts_with($telephoneBrut, '+')
                ? $telephoneBrut
                : trim((string)($d['indicatif_tel'] ?? '+241')) . ' ' . $telephoneBrut;
        }

        $filiereCle     = (string)($d['filiere_visee'] ?? '');
        $filiereLibelle = self::FILIERES[$filiereCle] ?? ($filiereCle !== '' ? $filiereCle : '—');
        $specialite     = trim((string)($d['specialite_visee'] ?? ''));
        if ($specialite !== '' && $filiereLibelle !== '—') {
            $filiereLibelle .= ' — ' . $specialite;
        }

        $nomComplet      = trim((string)($d['nom_complet'] ?? '')) ?: '—';
        $lieuNaissance   = trim((string)($d['lieu_naissance'] ?? '')) ?: '—';
        $nationalite     = trim((string)($d['nationalite'] ?? '')) ?: '—';
        $paysProvenance  = trim((string)($d['pays_provenance'] ?? '')) ?: '—';
        $email           = trim((string)($d['email'] ?? '')) ?: '—';
        $niveauEntree    = trim((string)($d['niveau_entree'] ?? '')) ?: '—';
        $dernierEtab     = trim((string)($d['dernier_etablissement'] ?? '')) ?: '—';
        $serieBac        = trim((string)($d['serie_bac'] ?? '')) ?: '—';
        $anneeBac        = trim((string)($d['annee_bac'] ?? '')) ?: '—';
        $dateEmissionStr = $date->format('d/m/Y');
        $qrDataUri       = QrCodeVerification::dataUri($tokenVerification);

        ob_start();
        include BASE_PATH . ($pourPdf
            ? '/app/Views/orientation/_recepisse-pdf.php'
            : '/app/Views/orientation/_recepisse.php');
        return (string)ob_get_clean();
    }

}
