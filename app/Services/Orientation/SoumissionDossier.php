<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Core\Queue;
use Models\Orientation\Dossier;
use Models\Orientation\DossierStatut;
use Models\Orientation\NotificationOrientation;
use PDO;

/**
 * Soumission finale du dossier (spec §5.4).
 *
 * Tout ce qui doit être vrai ensemble se fait dans UNE transaction :
 *
 *   1. passage de `brouillon` à `soumis` par la machine à états ;
 *   2. attribution de la référence définitive (compteur verrouillé) ;
 *   3. émission du jeton de vérification et du code court.
 *
 * Ce qui peut échouer sans invalider le dépôt reste DEHORS de la transaction :
 * notifications, e-mails, et surtout génération du récépissé PDF. La spec est
 * explicite : « si la génération du PDF échoue, elle ne doit pas faire échouer
 * la soumission ». Le dossier reste soumis, l'espace étudiant affiche
 * « Récépissé en cours de génération », et la tâche de rattrapage repasse.
 *
 * La méthode est idempotente : un rafraîchissement ou un double POST ne crée
 * ni seconde référence, ni second jeton.
 */
final class SoumissionDossier
{
    /**
     * @param array<string,mixed> $dossier Ligne du dossier (état avant soumission).
     * @param array<string,mixed> $etab    Ligne de l'établissement.
     *
     * @return array{
     *   ok:bool, erreur:?string, dossier:?array,
     *   reference:?string, jeton:?array, deja_soumis:bool
     * }
     */
    public static function executer(array $dossier, array $etab, ?int $acteurId = null): array
    {
        $dossierId = (int)($dossier['id'] ?? 0);
        if ($dossierId <= 0) {
            return self::echec('Dossier invalide.');
        }

        $db      = Database::getInstance()->getConnection();
        $modele  = new Dossier();
        $externe = $db->inTransaction();

        if (!$externe) {
            $db->beginTransaction();
        }

        try {
            // Verrou sur la ligne pour la durée de la transaction : deux POST
            // simultanés ne peuvent pas réserver deux références.
            $st = $db->prepare('SELECT * FROM dossiers_inscription WHERE id = ? FOR UPDATE');
            $st->execute([$dossierId]);
            $courant = $st->fetch(PDO::FETCH_ASSOC);

            if ($courant === false) {
                throw new \RuntimeException('Dossier introuvable au moment de la soumission.');
            }

            $statutCourant = DossierStatut::normaliser((string)$courant['statut']);
            $dejaSoumis    = $statutCourant !== DossierStatut::BROUILLON;

            // 1. Référence définitive — une seule fois dans la vie du dossier.
            $reference = (string)($courant['reference'] ?? '');
            if ($reference === '') {
                $reference = ReferenceDossier::reserver($db, (int)$courant['etablissement_id']);

                $db->prepare(
                    'UPDATE dossiers_inscription SET reference = ?
                       WHERE id = ? AND (reference IS NULL OR reference = \'\')'
                )->execute([$reference, $dossierId]);
            }

            // 2. Jeton de vérification + code court (idempotent).
            $jeton = JetonDossier::pour($dossierId, $db);

            // 3. Transition d'état. Se greffe sur la transaction ouverte ici.
            //    Couvre les deux chemins autorisés vers `soumis` (spec §4.2) :
            //    première soumission depuis `brouillon`, et retour depuis
            //    `piece_manquante` après nouveau téléversement. Un rejeu sur un
            //    dossier déjà soumis est accepté sans effet.
            $resultat = $modele->transition(
                $dossierId,
                DossierStatut::SOUMIS,
                'etudiant',
                [
                    'acteur_id' => $acteurId,
                    'donnees'   => ['reference' => $reference],
                ]
            );

            if (!$resultat['ok']) {
                throw new \RuntimeException((string)$resultat['erreur']);
            }

            if (!$externe) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if (!$externe && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[SoumissionDossier] #' . $dossierId . ' : ' . $e->getMessage());

            return self::echec('Votre dossier n’a pas pu être enregistré. Réessayez dans un instant.');
        }

        // ── Hors transaction : rien ici ne peut invalider le dépôt ───────────

        $dossierFinal = $modele->findById($dossierId) ?? $dossier;

        self::notifier($dossierFinal, $etab, $reference);
        self::planifierRecepisse($dossierId, (int)$etab['id']);

        return [
            'ok'          => true,
            'erreur'      => null,
            'dossier'     => $dossierFinal,
            'reference'   => $reference,
            'jeton'       => $jeton,
            'deja_soumis' => $dejaSoumis,
        ];
    }

    /**
     * Les prochaines étapes affichées sur l'écran de confirmation (spec §5.4).
     *
     * @return list<string>
     */
    public static function prochainesEtapes(array $etab): array
    {
        return [
            'L’établissement analyse vos pièces et vous notifie de sa décision.',
            'Une fois votre dossier validé à 50 %, présentez-vous à '
                . ($etab['nom'] ?? 'l’établissement') . ' avec votre récépissé.',
            'Le règlement de la scolarité se fait sur place. Votre inscription devient alors définitive.',
        ];
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /**
     * Notifications in-app : au candidat, puis à chaque gestionnaire de
     * l'établissement. Aucune ne peut faire échouer la soumission.
     */
    private static function notifier(array $dossier, array $etab, string $reference): void
    {
        try {
            $notifications = new NotificationOrientation();
            $etabId        = (int)$etab['id'];
            $etabNom       = (string)($etab['nom'] ?? 'l’établissement');

            if (!empty($dossier['eleve_id'])) {
                $notifications->create([
                    'user_id'          => (int)$dossier['eleve_id'],
                    'etablissement_id' => $etabId,
                    'type'             => 'dossier_soumis',
                    'titre'            => 'Dossier envoyé — ' . $reference,
                    'message'          => 'Votre dossier d’inscription à ' . $etabNom
                        . ' a bien été reçu. Il est en cours d’analyse : vous n’êtes pas encore inscrit. '
                        . 'Votre récépissé de dépôt est disponible dans votre espace.',
                ]);
            }

            $candidat = trim((string)($dossier['nom_complet'] ?? 'Un candidat'));

            foreach ($notifications->findGestionnaireIds($etabId) as $gestionnaireId) {
                $notifications->create([
                    'user_id'          => (int)$gestionnaireId,
                    'etablissement_id' => $etabId,
                    'type'             => 'dossier_a_analyser',
                    'titre'            => 'Nouveau dossier — ' . $reference,
                    'message'          => $candidat . ' a déposé un dossier d’inscription. '
                        . 'Analysez les pièces pour le valider à 50 %.',
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[SoumissionDossier] Notifications échouées : ' . $e->getMessage());
        }
    }

    /**
     * Met la génération du récépissé en file d'attente. Le PDF n'est pas
     * fabriqué pendant la requête : il fait plusieurs centaines de
     * millisecondes et un aller-retour vers B2, que le candidat n'a pas à
     * attendre. Si la file est indisponible, Queue bascule en exécution
     * immédiate ; si même cela échoue, la tâche de rattrapage repassera.
     */
    private static function planifierRecepisse(int $dossierId, int $etablissementId): void
    {
        try {
            Queue::push('generate_recepisse_dossier', [
                'dossierId' => $dossierId,
                'etabId'    => $etablissementId,
            ]);
        } catch (\Throwable $e) {
            error_log('[SoumissionDossier] Mise en file du récépissé échouée : ' . $e->getMessage());
        }
    }

    /** @return array{ok:false,erreur:string,dossier:null,reference:null,jeton:null,deja_soumis:false} */
    private static function echec(string $message): array
    {
        return [
            'ok'          => false,
            'erreur'      => $message,
            'dossier'     => null,
            'reference'   => null,
            'jeton'       => null,
            'deja_soumis' => false,
        ];
    }
}
