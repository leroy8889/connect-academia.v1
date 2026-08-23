<?php
declare(strict_types=1);

namespace Services\Orientation;

/**
 * Génération de l'attestation d'inscription officielle (gabarit maquette).
 *
 * À ne pas confondre avec le récépissé de préinscription (RecepisseService),
 * délivré immédiatement au dépôt : l'attestation n'est délivrée qu'APRÈS
 * validation du dossier par l'établissement (statut "accepte").
 *
 * Rendu HTML autonome : logo établissement embarqué en data URI compressé
 * (LogoDataUri) — en-tête, filigrane et bloc signature.
 */
class AttestationService
{
    private const FILIERES = [
        'socle_l1_l2'   => 'Socle L1-L2',
        'licence_l3'    => 'Licence L3',
        'master_m1_m2'  => 'Master M1-M2',
        'certification' => 'Certification',
    ];

    private const GENRES = [
        'homme' => 'Homme',
        'femme' => 'Femme',
        'autre' => 'Autre',
    ];

    /**
     * Rend l'attestation en HTML.
     *
     * @param array $d    Row `dossiers_inscription` : nom_complet, genre,
     *                    date_naissance, lieu_naissance, adresse, ville,
     *                    indicatif_tel, telephone, email, filiere_visee,
     *                    niveau_entree.
     * @param array       $etab  Row établissement (nom, ville, couleur_principale, logo_cle).
     * @param string|null $tokenVerification Token d'authenticité (64 hex) — imprime
     *                    le QR code de vérification /verifier/{token} sur le document.
     */
    public function render(array $d, array $etab, string $numero, ?\DateTimeInterface $dateEmission = null, ?string $tokenVerification = null): string
    {
        return $this->rendre($d, $etab, $numero, $dateEmission, $tokenVerification, false);
    }

    /**
     * Rend l'attestation d'inscription en PDF (dompdf).
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
            error_log('[AttestationService] Aucun rendu PDF exploitable — repli HTML.');
        }

        return $pdf;
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
        // Signature / cachet officiel (facultatif) — traité « encre » (fond
        // transparent + recolorisation) puis imprimé dans le bloc « La Direction
        // Académique », à la place du logo lorsqu'il est défini.
        $signatureDataUri = SignatureDataUri::pour((string)($etab['signature_cle'] ?? ''));

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

        $adresseParts = array_filter([
            trim((string)($d['adresse'] ?? '')),
            trim((string)($d['ville'] ?? '')),
        ]);

        $nomComplet      = trim((string)($d['nom_complet'] ?? '')) ?: '—';
        $lieuNaissance   = trim((string)($d['lieu_naissance'] ?? '')) ?: '—';
        $genreLibelle    = self::GENRES[(string)($d['genre'] ?? '')] ?? '—';
        $adresse         = $adresseParts !== [] ? implode(', ', $adresseParts) : '—';
        $email           = trim((string)($d['email'] ?? '')) ?: '—';
        $niveauEntree    = trim((string)($d['niveau_entree'] ?? '')) ?: '—';
        $dateEmissionStr = $date->format('d/m/Y');
        $qrDataUri       = QrCodeVerification::dataUri($tokenVerification);

        ob_start();
        include BASE_PATH . ($pourPdf
            ? '/app/Views/orientation/_attestation-pdf.php'
            : '/app/Views/orientation/_attestation.php');
        return (string)ob_get_clean();
    }
}
