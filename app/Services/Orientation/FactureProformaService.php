<?php
declare(strict_types=1);

namespace Services\Orientation;

/**
 * Facture proforma indicative jointe à l'attestation de préinscription —
 * reprend les frais d'inscription et de scolarité standard configurés sur
 * l'établissement (Etablissement::prix_inscription / frais_scolarite).
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

        $filiere          = self::resoudreFiliere($etab, $d);
        $fraisInscription = (int)($filiere['frais_inscription'] ?? 0) > 0
            ? (int)$filiere['frais_inscription']
            : (int)($etab['prix_inscription'] ?? 0);
        $fraisScolarite   = (int)($filiere['frais_scolarite'] ?? 0) > 0
            ? (int)$filiere['frais_scolarite']
            : (int)($etab['frais_scolarite'] ?? 0);
        $total            = $fraisInscription + $fraisScolarite;

        $dateStr = $date->format('d/m/Y');

        ob_start();
        include BASE_PATH . '/app/Views/orientation/_facture-proforma-pdf.php';
        return (string)ob_get_clean();
    }

    /** Vrai si un tarif exploitable existe (filière spécifique, ou repli établissement). */
    public static function etabAConfigureTarifs(array $etab, array $d): bool
    {
        $filiere = self::resoudreFiliere($etab, $d);
        if ((int)($filiere['frais_inscription'] ?? 0) > 0 || (int)($filiere['frais_scolarite'] ?? 0) > 0) {
            return true;
        }
        return (int)($etab['prix_inscription'] ?? 0) > 0 || (int)($etab['frais_scolarite'] ?? 0) > 0;
    }

    /** Filière du dossier, si elle existe et appartient bien à cet établissement — sinon null (repli établissement). */
    private static function resoudreFiliere(array $etab, array $d): ?array
    {
        $nom = trim((string)($d['filiere_visee'] ?? ''));
        if ($nom === '' || empty($etab['id'])) {
            return null;
        }
        return (new \Models\Orientation\Filiere())->findByNom($nom, (int)$etab['id']);
    }
}
