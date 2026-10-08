<?php
declare(strict_types=1);

namespace Services\Orientation;

/**
 * Facture proforma jointe à l'attestation de préinscription.
 *
 * ── À qui elle s'adresse, et ce que ça change ────────────────────────────────
 *
 * Cette proforma n'est pas un devis commercial générique : c'est la pièce que
 * le candidat dépose à l'ANBG pour demander une bourse. Son lecteur est donc
 * l'agence, pas l'établissement, et ce qu'elle doit y lire est le coût d'un
 * étudiant BOURSIER — c'est-à-dire ce que l'ANBG aura à financer si elle
 * accorde la bourse.
 *
 * Elle affiche donc systématiquement le TARIF BOURSIER de la formation et du
 * niveau choisis. Y faire figurer le tarif non boursier reviendrait à faire
 * demander à l'ANBG un montant qu'elle ne réglera jamais — un écart qui se
 * paie en dossier rejeté, des mois plus tard, sans que personne ne comprenne
 * pourquoi.
 *
 * Quand l'établissement n'a pas de tarif boursier distinct (tarif unique),
 * TarifCycle retombe de lui-même sur le tarif non boursier : la proforma reste
 * juste, et l'établissement n'a rien de plus à saisir.
 *
 * ── Ce que la préinscription connaît ─────────────────────────────────────────
 *
 * À ce stade le candidat a choisi sa formation et son niveau, mais il n'a pas
 * encore déclaré sa situation de bourse — ce champ appartient au parcours
 * d'inscription. On force donc `boursier` dans la résolution : ce n'est pas une
 * supposition sur le candidat, c'est l'hypothèse de travail du document.
 *
 * Rendu en fragment (pas de <html>/<body>) : assemblé par
 * RecepisseService::renderPdfComplet() dans le document final.
 */
class FactureProformaService
{
    public function render(array $d, array $etab, string $numeroFacture, ?\DateTimeInterface $dateEmission = null, ?string $tokenVerification = null): string
    {
        $date = $dateEmission ?? new \DateTime();

        $etabNom      = (string)($etab['nom'] ?? '');
        $etabVille    = (string)($etab['ville'] ?? '');
        $couleur      = (string)($etab['couleur_principale'] ?? '#1E3A5F');
        $etabInitiale = $etabNom !== '' ? mb_strtoupper(mb_substr($etabNom, 0, 1)) : '?';
        $logoDataUri  = LogoDataUri::pour((string)($etab['logo_cle'] ?? ''));
        $cachetDataUri = LogoDataUri::pour((string)($etab['cachet_cle'] ?? ''));
        $qrDataUri     = QrCodeVerification::dataUri($tokenVerification);

        $anneeUniv = \Core\AnneeUniversitaire::courante($date);

        $nomComplet = trim((string)($d['nom_complet'] ?? '')) ?: '—';

        $filiereLibelle = trim((string)($d['filiere_visee'] ?? '')) ?: '—';
        $specialite     = trim((string)($d['specialite_visee'] ?? ''));
        if ($specialite !== '' && $filiereLibelle !== '—') {
            $filiereLibelle .= ' — ' . $specialite;
        }

        $tarif = self::tarifBoursier($d, $etab);

        $fraisInscription = (int)round($tarif['frais_inscription'] ?? 0);
        $fraisScolarite   = (int)round($tarif['montant'] ?? 0);
        $total            = $fraisInscription + $fraisScolarite;

        // Le niveau figure sur la facture : sans lui, l'ANBG ne peut pas
        // vérifier que le montant correspond bien à l'année demandée, alors
        // que deux années d'un même cycle peuvent avoir deux tarifs.
        $niveauLibelle = trim((string)($d['niveau_entree'] ?? ''));
        $cycleLibelle  = (string)($tarif['cycle_libelle'] ?? '');

        $dateStr = $date->format('d/m/Y');

        // Pièces du dossier d'inscription applicables à CE cycle. L'ANBG lit
        // cette proforma pour instruire une demande de bourse : lui dire ce que
        // l'établissement exigera évite l'aller-retour qui coûte une rentrée.
        $piecesDossier = CompositionDossier::pourDossier($etab, $d);

        // Titre du bloc : le cycle, seul déterminant de la liste.
        $cycleDossierLibelle = TarifCycle::libelle(TarifCycle::detecter($d));

        ob_start();
        include BASE_PATH . '/app/Views/orientation/_facture-proforma-pdf.php';
        return (string)ob_get_clean();
    }

    /**
     * Vrai si un tarif exploitable existe — sinon la proforma est omise du PDF
     * plutôt que d'être imprimée à zéro franc, ce qu'aucune agence n'accepte.
     */
    public static function etabAConfigureTarifs(array $etab, array $d): bool
    {
        $tarif = self::tarifBoursier($d, $etab);

        return ($tarif['montant'] ?? null) !== null
            || ($tarif['frais_inscription'] ?? null) !== null;
    }

    /**
     * Tarif applicable en faisant l'hypothèse d'un candidat boursier.
     *
     * L'établissement du dossier est forcé à celui de la fiche : une
     * préinscription porte parfois un `etablissement_id` absent du tableau
     * transmis, et la résolution par filière a besoin des deux pour ne pas
     * aller chercher une formation homonyme chez un autre partenaire.
     *
     * @return array<string,mixed> Cf. TarifCycle::detail()
     */
    private static function tarifBoursier(array $d, array $etab): array
    {
        $d['etablissement_id'] = (int)($d['etablissement_id'] ?? ($etab['id'] ?? 0));

        return TarifCycle::detail($d, $etab, SituationBourse::BOURSIER);
    }
}
