<?php
declare(strict_types=1);

namespace Services\Orientation;

use Dompdf\Dompdf;
use Models\Orientation\Dossier;

/**
 * Reçu de paiement d'inscription — génération PDF (dompdf) + stockage B2.
 *
 * Point d'entrée unique pour les deux chemins de confirmation (webhook
 * MoneyFusion et retour navigateur) : réserve un numéro REC-AAAA-NNNN
 * atomique, rend le gabarit _recu-pdf.php, convertit en PDF A4 et pousse
 * `recus/{dossierId}/{numero}.pdf` sur B2. Idempotent : si un reçu avec
 * clé existe déjà pour ce paiement, ne fait rien.
 */
class RecuService
{
    private const FILIERES = [
        'socle_l1_l2'   => 'Socle L1-L2',
        'licence_l3'    => 'Licence L3',
        'master_m1_m2'  => 'Master M1-M2',
        'certification' => 'Certification',
    ];

    /**
     * @param array $dossier Row dossier joint établissement (Dossier::findById)
     * @param array $etab    Row établissement complet (prix_inscription, frais_dossier, logo_cle…)
     * @return string|null   Clé B2 du PDF, ou null si la génération a échoué
     */
    public static function generer(int $dossierId, int $paiementId, array $dossier, array $etab, int $montant): ?string
    {
        $dossierModel = new Dossier();

        $existant = $dossierModel->getRecu($paiementId);
        if ($existant && !empty($existant['cle_pdf'])) {
            return (string)$existant['cle_pdf'];
        }

        try {
            $numero = $dossierModel->reserveRecu($paiementId);
        } catch (\Throwable $e) {
            error_log('[RecuService] Réservation numéro échouée : ' . $e->getMessage());
            return null;
        }

        $filiereCle     = (string)($dossier['filiere_visee'] ?? '');
        $filiereLibelle = self::FILIERES[$filiereCle] ?? ($filiereCle !== '' ? $filiereCle : '—');
        $specialite     = trim((string)($dossier['specialite_visee'] ?? ''));
        if ($specialite !== '' && $filiereLibelle !== '—') {
            $filiereLibelle .= ' — ' . $specialite;
        }

        $telephoneBrut = trim((string)($dossier['telephone'] ?? ''));
        $telephone     = $telephoneBrut === '' ? '—'
            : (str_starts_with($telephoneBrut, '+')
                ? $telephoneBrut
                : trim((string)($dossier['indicatif_tel'] ?? '+241')) . ' ' . $telephoneBrut);

        $prixInscription = (int)($etab['prix_inscription'] ?? 0);
        $fraisDossier    = (int)($etab['frais_dossier'] ?? 0);
        // Cohérence : si le total stocké diffère du détail (tarifs modifiés
        // entre-temps), on n'affiche que le total réellement payé.
        if ($prixInscription + $fraisDossier !== $montant) {
            $prixInscription = $montant;
            $fraisDossier    = 0;
        }

        $etabNom      = (string)($etab['nom'] ?? $dossier['etab_nom'] ?? '—');
        $couleur      = (string)($etab['couleur_principale'] ?? $dossier['couleur_principale'] ?? '#8B52FA');
        $etabVille    = (string)($etab['ville'] ?? '');
        $etabInitiale = $etabNom !== '' ? mb_strtoupper(mb_substr($etabNom, 0, 1)) : '?';
        $logoDataUri  = '';
        try {
            $logoDataUri = LogoDataUri::pour((string)($etab['logo_cle'] ?? ''));
        } catch (\Throwable) {
            // Sans logo : initiale affichée
        }

        $nomComplet   = (string)($dossier['nom_complet'] ?? '');
        $email        = (string)($dossier['email'] ?? '');
        $niveauEntree = (string)($dossier['niveau_entree'] ?? '—');
        $dateEmission = date('d/m/Y à H:i');
        $reference    = sprintf('ORI-%d-%s', $paiementId, date('Ymd'));
        $modePaiement = 'Mobile Money — MoneyFusion';

        ob_start();
        include BASE_PATH . '/app/Views/orientation/_recu-pdf.php';
        $html = (string)ob_get_clean();

        // Core\Pdf vérifie le document et bascule seul sur une police native si
        // le moteur de glyphes défaille. Un reçu vide n'est jamais archivé.
        $pdf = \Core\Pdf::rendre($html);
        if ($pdf === null) {
            error_log('[RecuService] Aucun rendu PDF exploitable — reçu non généré.');
            return null;
        }

        $cle = sprintf('recus/%d/%s.pdf', $dossierId, $numero);

        try {
            (new DocumentStorage())->uploadContent($pdf, $cle, 'application/pdf');
            $dossierModel->updateRecuCle($paiementId, $cle);
        } catch (\Throwable $e) {
            error_log('[RecuService] Upload B2 échoué : ' . $e->getMessage());
            return null;
        }

        return $cle;
    }
}
