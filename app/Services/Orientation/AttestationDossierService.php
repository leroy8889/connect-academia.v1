<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Models\Orientation\Dossier;
use Models\Orientation\DossierStatut;
use PDO;

/**
 * Attestation d'inscription — le document délivré après les 100 % (spec §8.4).
 *
 * À ne pas confondre avec le récépissé : le récépissé atteste d'un DÉPÔT et
 * sort à 0 % ; l'attestation prouve une INSCRIPTION DÉFINITIVE et ne sort
 * qu'après règlement et validation. C'est toute la distinction que la refonte
 * cherche à rendre claire (spec §2).
 *
 * Le rendu réutilise AttestationService, déjà en place et déjà aux couleurs de
 * chaque établissement (logo, cachet, signature du gestionnaire). Ce service
 * n'y ajoute que ce qui manquait : idempotence, rendu PDF plutôt que HTML,
 * empreinte, et écriture de la ligne `inscriptions` dont dépendent les écrans
 * existants.
 */
final class AttestationDossierService
{
    /**
     * Génère l'attestation si le dossier y a droit et qu'elle n'existe pas.
     *
     * Idempotente : rappelée sur un dossier déjà pourvu, elle renvoie la clé
     * existante sans rien régénérer. C'est ce qui permet à la tâche de
     * rattrapage de repasser sans créer de doublon ni invalider un document
     * déjà transmis au candidat.
     *
     * @return string|null Clé B2 du document, ou null si non délivrable.
     */
    public static function generer(int $dossierId, bool $forcer = false): ?string
    {
        $modele  = new Dossier();
        $dossier = $modele->findById($dossierId);

        if ($dossier === null) {
            return null;
        }

        // Verrou métier : une attestation ne s'émet QUE pour une inscription
        // définitive. Émettre plus tôt reviendrait à certifier une inscription
        // qui n'existe pas.
        if (DossierStatut::normaliser((string)$dossier['statut']) !== DossierStatut::VALIDE_100) {
            return null;
        }

        $existante = $modele->getAttestation($dossierId);
        if ($existante !== null && !$forcer && !empty($existante['cle_pdf'])) {
            return (string)$existante['cle_pdf'];
        }

        $etab = self::etablissement((int)$dossier['etablissement_id']);
        if ($etab === null) {
            return null;
        }

        // L'établissement peut désactiver la délivrance automatique : certains
        // préfèrent remettre leur propre document au guichet.
        if ((int)($etab['genere_attestation'] ?? 1) !== 1) {
            return null;
        }

        // La ligne `inscriptions` porte le lien dossier ↔ inscription dont
        // dépendent les écrans déjà en place (espace élève, bilans).
        try {
            if (!$modele->hasInscription($dossierId)) {
                $modele->createInscription($dossierId, (int)$dossier['etablissement_id']);
            }
        } catch (\Throwable $e) {
            error_log('[AttestationDossier] Ligne inscription #' . $dossierId . ' : ' . $e->getMessage());
        }

        try {
            $numero = $modele->reserveAttestation($dossierId);
        } catch (\Throwable $e) {
            error_log('[AttestationDossier] Réservation numéro #' . $dossierId . ' : ' . $e->getMessage());

            return null;
        }

        $token   = $modele->tokenVerificationAttestation($dossierId);
        $service = new AttestationService();

        // PDF d'abord : c'est le format que le candidat imprimera et qu'un
        // employeur acceptera. Repli HTML seulement si le moteur défaille,
        // pour ne jamais laisser le dossier sans document.
        $pdf = $service->renderPdf($dossier, $etab, $numero, null, $token);

        if ($pdf !== null) {
            $contenu   = $pdf;
            $mime      = 'application/pdf';
            $extension = 'pdf';
        } else {
            error_log('[AttestationDossier] Repli HTML #' . $dossierId);
            $contenu   = $service->render($dossier, $etab, $numero, null, $token);
            $mime      = 'text/html; charset=utf-8';
            $extension = 'html';
        }

        $cle = sprintf(
            'attestations/%s/%d/%s.%s',
            self::segment((string)($dossier['annee_academique'] ?? \Core\AnneeUniversitaire::code())),
            (int)$dossier['etablissement_id'],
            self::segment($numero),
            $extension
        );

        try {
            (new DocumentStorage())->uploadContent($contenu, $cle, $mime);
        } catch (\Throwable $e) {
            error_log('[AttestationDossier] Dépôt B2 échoué (' . $cle . ') : ' . $e->getMessage());

            // Numéro conservé : la tâche de rattrapage réessaiera le dépôt sans
            // changer le numéro déjà annoncé au candidat.
            return null;
        }

        $hash = hash('sha256', $contenu);

        try {
            $modele->updateAttestationCle($dossierId, $cle);

            Database::getInstance()->getConnection()->prepare(
                'UPDATE attestations_inscription
                 SET hash_sha256 = ?, version = version + ?
                 WHERE dossier_id = ?'
            )->execute([$hash, $forcer ? 1 : 0, $dossierId]);
        } catch (\Throwable $e) {
            error_log('[AttestationDossier] Mise à jour base #' . $dossierId . ' : ' . $e->getMessage());
        }

        Journal::dossier(
            Journal::ATTESTATION_GENEREE,
            $dossier,
            ['numero' => $numero, 'cle' => $cle, 'empreinte' => substr($hash, 0, 12)],
            ['acteur_role' => 'systeme']
        );

        return $cle;
    }

    /**
     * Dossiers à 100 % dont l'attestation manque encore — alimente la tâche de
     * rattrapage (spec §14).
     *
     * @return list<int>
     */
    public static function enAttente(int $limite = 50): array
    {
        $limite = max(1, min($limite, 500));

        try {
            $st = Database::getInstance()->getConnection()->query(
                "SELECT d.id
                 FROM dossiers_inscription d
                 JOIN etablissements e ON e.id = d.etablissement_id AND e.genere_attestation = 1
                 LEFT JOIN attestations_inscription a ON a.dossier_id = d.id
                 WHERE d.statut IN ('valide_100', 'inscrit')
                   AND (a.id IS NULL OR a.cle_pdf IS NULL OR a.cle_pdf = '')
                 ORDER BY d.valide_100_le ASC
                 LIMIT {$limite}"
            );

            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
        } catch (\Throwable $e) {
            error_log('[AttestationDossier] Liste d’attente illisible : ' . $e->getMessage());

            return [];
        }
    }

    /** @return array<string,mixed>|null */
    private static function etablissement(int $etablissementId): ?array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT * FROM etablissements WHERE id = ? LIMIT 1'
            );
            $st->execute([$etablissementId]);
            $etab = $st->fetch(PDO::FETCH_ASSOC);

            return $etab === false ? null : $etab;
        } catch (\Throwable $e) {
            error_log('[AttestationDossier] Établissement illisible : ' . $e->getMessage());

            return null;
        }
    }

    private static function segment(string $valeur): string
    {
        $propre = preg_replace('/[^A-Za-z0-9_-]/', '-', $valeur) ?? '';

        return trim(preg_replace('/-+/', '-', $propre) ?? '', '-') ?: 'x';
    }
}
