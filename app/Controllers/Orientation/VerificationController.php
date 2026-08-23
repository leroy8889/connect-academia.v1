<?php
declare(strict_types=1);

namespace Controllers\Orientation;

use Models\Orientation\Dossier;

/**
 * Vérification publique d'authenticité des documents officiels (scan QR code).
 *
 * Chaque récépissé de préinscription et chaque attestation d'inscription porte
 * un QR code encodant /verifier/{token} — token unique de 64 hex (256 bits
 * aléatoires, imprévisible : impossible à forger ou énumérer). La page affiche
 * les informations officielles du dossier depuis la base : toute divergence
 * avec le document papier révèle une falsification.
 *
 * Route publique sans authentification : le porteur du document (établissement,
 * employeur, administration) doit pouvoir vérifier sans compte. Les données
 * affichées sont celles déjà imprimées sur le document lui-même.
 */
class VerificationController
{
    private const FILIERES = [
        'socle_l1_l2'   => 'Socle L1-L2',
        'licence_l3'    => 'Licence L3',
        'master_m1_m2'  => 'Master M1-M2',
        'certification' => 'Certification',
    ];

    private const STATUTS = [
        'brouillon' => ['Brouillon',               'neutre'],
        'soumis'    => ['Soumis — en cours d\'examen', 'attente'],
        'paye'      => ['Payé — en cours d\'examen',   'attente'],
        'accepte'   => ['Accepté',                 'valide'],
        'inscrit'   => ['Inscrit',                 'valide'],
        'refuse'    => ['Refusé',                  'refuse'],
    ];

    public function show(string $token): void
    {
        $token = strtolower(trim($token));

        $doc  = null;
        $type = null;

        // Format strict avant toute requête : 64 hex exactement
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            $model = new Dossier();
            if ($row = $model->findRecepisseByToken($token)) {
                $doc  = $row;
                $type = 'recepisse';
            } elseif ($row = $model->findAttestationByToken($token)) {
                $doc  = $row;
                $type = 'attestation';
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');

        if (!$doc) {
            http_response_code(404);
            include BASE_PATH . '/app/Views/orientation/verification.php';
            return;
        }

        // ── Données d'affichage ───────────────────────────────────────────────
        $typeLibelle    = $type === 'attestation'
            ? 'Attestation d\'inscription'
            : 'Attestation de préinscription';
        $delivreAccord = 'délivrée';

        $numero = (string)($doc['numero'] ?? '—');

        $dateEmission = '—';
        try {
            $dateEmission = (new \DateTime((string)$doc['date_emission']))->format('d/m/Y');
        } catch (\Throwable) {
        }

        $dateNaissance = '—';
        if (!empty($doc['date_naissance'])) {
            try {
                $dateNaissance = (new \DateTime((string)$doc['date_naissance']))->format('d/m/Y');
            } catch (\Throwable) {
            }
        }

        $dateDepot = '—';
        if (!empty($doc['date_creation'])) {
            try {
                $dateDepot = (new \DateTime((string)$doc['date_creation']))->format('d/m/Y à H\hi');
            } catch (\Throwable) {
            }
        }

        $filiereCle     = (string)($doc['filiere_visee'] ?? '');
        $filiereLibelle = self::FILIERES[$filiereCle] ?? ($filiereCle !== '' ? $filiereCle : '—');
        $specialite     = trim((string)($doc['specialite_visee'] ?? ''));
        if ($specialite !== '' && $filiereLibelle !== '—') {
            $filiereLibelle .= ' — ' . $specialite;
        }

        $telephoneBrut = trim((string)($doc['telephone'] ?? ''));
        $telephone     = '—';
        if ($telephoneBrut !== '') {
            $telephone = str_starts_with($telephoneBrut, '+')
                ? $telephoneBrut
                : trim((string)($doc['indicatif_tel'] ?? '+241')) . ' ' . $telephoneBrut;
        }

        $statutCle = (string)($doc['statut'] ?? '');
        [$statutLibelle, $statutTon] = self::STATUTS[$statutCle] ?? ['—', 'neutre'];

        // Attestation dont le dossier n'est plus validé : divergence à signaler
        $avertissement = null;
        if ($type === 'attestation' && !in_array($statutCle, ['inscrit', 'accepte'], true)) {
            $avertissement = 'Le statut actuel du dossier ne correspond plus à cette attestation. '
                . 'Contactez l\'établissement pour confirmation.';
        }

        $origineLibelle = ($doc['origine'] ?? '') === 'etablissement'
            ? 'Dépôt au guichet de l\'établissement'
            : 'Dépôt en ligne sur Connect\'Academia';

        // Compte élève lié (absent pour un dossier déposé au guichet sans compte)
        $compte = null;
        if (!empty($doc['eleve_id']) && (($doc['compte_nom'] ?? '') !== '' || ($doc['compte_email'] ?? '') !== '')) {
            $compteCreeLe = '—';
            try {
                $compteCreeLe = (new \DateTime((string)$doc['compte_cree_le']))->format('d/m/Y');
            } catch (\Throwable) {
            }
            $compte = [
                'nom'      => trim(($doc['compte_prenom'] ?? '') . ' ' . ($doc['compte_nom'] ?? '')) ?: '—',
                'email'    => (string)($doc['compte_email'] ?? '—'),
                'verifie'  => (int)($doc['compte_verifie'] ?? 0) === 1,
                'cree_le'  => $compteCreeLe,
            ];
        }

        include BASE_PATH . '/app/Views/orientation/verification.php';
    }
}
