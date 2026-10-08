<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Core\Queue;
use Core\Settings;
use Models\Orientation\Dossier;
use Models\Orientation\DossierStatut;
use Models\Orientation\NotificationOrientation;
use PDO;

/**
 * Tâches planifiées du module inscription (spec §14).
 *
 * Chaque tâche est une méthode statique qui retourne un compte rendu. Elles
 * sont appelées par bin/taches-orientation.php, lui-même déclenché par cron.
 *
 * ── Trois règles communes ─────────────────────────────────────────────────
 *
 *  1. **Idempotentes.** Rejouer une tâche ne double jamais ses effets : les
 *     relances sont bornées par une trace de la dernière relance, l'expiration
 *     passe par la machine à états qui refuse une transition déjà faite.
 *
 *  2. **Bornées.** Chaque passage traite au plus un lot. Une tâche qui
 *     découvre dix mille dossiers en retard ne doit pas saturer la base ni
 *     inonder les candidats de notifications ; elle en traite un lot et
 *     repasse au prochain déclenchement.
 *
 *  3. **Une erreur sur un dossier n'arrête pas le lot.** Chaque élément est
 *     traité dans son propre try/catch : un dossier corrompu ne doit pas
 *     empêcher les 500 autres d'être traités.
 */
final class TachesPlanifiees
{
    /** Dossiers traités au maximum par passage. */
    private const LOT = 200;

    /** Relances : on ne réécrit pas au même candidat avant ce délai. */
    private const ANTI_REPETITION_JOURS = 14;

    // ── Quotidienne — expiration des dossiers (spec §14) ────────────────────

    /**
     * Passe à `expire` les dossiers validés à 50 % dont la date limite est
     * dépassée, et prévient l'étudiant comme l'établissement.
     *
     * L'acteur est le SYSTÈME : c'est la seule transition que la machine à
     * états réserve à 'systeme' (spec §4.2). Un agent ne peut pas expirer un
     * dossier à la main — il l'annule, ce qui n'est pas la même chose.
     *
     * @return array{traites:int, erreurs:int, details:list<string>}
     */
    public static function expirerDossiers(): array
    {
        $rapport = ['traites' => 0, 'erreurs' => 0, 'details' => []];
        $modele  = new Dossier();

        foreach (self::dossiersExpires() as $dossier) {
            try {
                $resultat = $modele->transition(
                    (int)$dossier['id'],
                    DossierStatut::EXPIRE,
                    'systeme',
                    ['commentaire' => 'Délai de présentation dépassé (tâche automatique).']
                );

                if (!$resultat['ok']) {
                    $rapport['erreurs']++;
                    $rapport['details'][] = 'Dossier #' . $dossier['id'] . ' : ' . $resultat['erreur'];
                    continue;
                }

                self::notifierCandidat(
                    $dossier,
                    'dossier_expire',
                    'Le délai de finalisation est dépassé',
                    'Votre dossier auprès de ' . ($dossier['etab_nom'] ?? 'l’établissement')
                    . ' a expiré faute de présentation dans le délai imparti. '
                    . 'Contactez l’établissement si vous souhaitez le réactiver.'
                );

                self::notifierEtablissement(
                    (int)$dossier['etablissement_id'],
                    'dossier_expire',
                    'Dossier expiré — ' . ($dossier['reference'] ?? '#' . $dossier['id']),
                    ($dossier['nom_complet'] ?? 'Un candidat')
                    . ' ne s’est pas présenté dans le délai. Le dossier est passé en « expiré ».'
                );

                $rapport['traites']++;
            } catch (\Throwable $e) {
                $rapport['erreurs']++;
                $rapport['details'][] = 'Dossier #' . $dossier['id'] . ' : ' . $e->getMessage();
                error_log('[Taches] Expiration #' . $dossier['id'] . ' : ' . $e->getMessage());
            }
        }

        return $rapport;
    }

    // ── Toutes les 15 minutes — rattrapage des documents ────────────────────

    /**
     * Régénère les récépissés et attestations manquants (spec §14).
     *
     * C'est le filet de la règle §5.4 : « si la génération du PDF échoue, elle
     * ne doit pas faire échouer la soumission ». Le dossier reste soumis, et
     * c'est cette tâche qui rattrape — sans quoi la promesse de ne jamais
     * bloquer le candidat se paierait en documents manquants.
     *
     * @return array{recepisses:int, attestations:int, erreurs:int, details:list<string>}
     */
    public static function rattraperDocuments(int $lot = 25): array
    {
        $lot     = max(1, min($lot, 100));
        $rapport = ['recepisses' => 0, 'attestations' => 0, 'erreurs' => 0, 'details' => []];

        foreach (self::recepissesManquants($lot) as $dossierId) {
            try {
                if (RecepisseDossierService::generer($dossierId) !== null) {
                    $rapport['recepisses']++;
                } else {
                    $rapport['erreurs']++;
                    $rapport['details'][] = 'Récépissé #' . $dossierId . ' : génération sans résultat';
                }
            } catch (\Throwable $e) {
                $rapport['erreurs']++;
                $rapport['details'][] = 'Récépissé #' . $dossierId . ' : ' . $e->getMessage();
                error_log('[Taches] Rattrapage récépissé #' . $dossierId . ' : ' . $e->getMessage());
            }
        }

        foreach (AttestationDossierService::enAttente($lot) as $dossierId) {
            try {
                if (AttestationDossierService::generer($dossierId) !== null) {
                    $rapport['attestations']++;
                }
                // generer() rend null quand l'établissement a désactivé la
                // délivrance : ce n'est pas une erreur, on ne compte rien.
            } catch (\Throwable $e) {
                $rapport['erreurs']++;
                $rapport['details'][] = 'Attestation #' . $dossierId . ' : ' . $e->getMessage();
                error_log('[Taches] Rattrapage attestation #' . $dossierId . ' : ' . $e->getMessage());
            }
        }

        return $rapport;
    }

    // ── Hebdomadaire — dossiers dormants ────────────────────────────────────

    /**
     * Signale à chaque établissement ses dossiers soumis depuis plus de N jours
     * sans décision (spec §14).
     *
     * Une notification PAR ÉTABLISSEMENT, pas par dossier : recevoir quarante
     * alertes le lundi matin garantit qu'aucune ne sera lue.
     *
     * @return array{etablissements:int, dossiers:int, erreurs:int, details:list<string>}
     */
    public static function signalerDossiersDormants(): array
    {
        $jours   = max(1, (int)Settings::get('relance_dossier_dormant_jours', 7));
        $rapport = ['etablissements' => 0, 'dossiers' => 0, 'erreurs' => 0, 'details' => []];

        foreach (self::dormantsParEtablissement($jours) as $ligne) {
            try {
                $nb = (int)$ligne['nb'];

                self::notifierEtablissement(
                    (int)$ligne['etablissement_id'],
                    'dossiers_dormants',
                    $nb . ' dossier' . ($nb > 1 ? 's' : '') . ' en attente depuis plus de ' . $jours . ' jours',
                    'Le plus ancien attend depuis ' . (int)$ligne['plus_ancien'] . ' jours. '
                    . 'Analysez les pièces pour valider à 50 % ou demander un complément.'
                );

                $rapport['etablissements']++;
                $rapport['dossiers'] += $nb;
            } catch (\Throwable $e) {
                $rapport['erreurs']++;
                $rapport['details'][] = 'Établissement #' . $ligne['etablissement_id'] . ' : ' . $e->getMessage();
                error_log('[Taches] Dossiers dormants : ' . $e->getMessage());
            }
        }

        return $rapport;
    }

    // ── Quotidienne — délai d'analyse dépassé (alerte super administrateur) ─

    /**
     * Signale au SUPER ADMINISTRATEUR les dossiers qu'un établissement laisse
     * sans décision au-delà du délai d'analyse (défaut : 3 jours).
     *
     * ── Pourquoi le super administrateur, et pas l'établissement ───────────
     * L'établissement est déjà relancé par signalerDossiersDormants(). Ici, le
     * destinataire est celui qui peut agir SUR l'établissement : c'est un
     * manquement à l'engagement de service pris envers le candidat, pas un
     * simple oubli à rappeler. D'où le journal — la trace est datée, nominative
     * et opposable — doublé d'un e-mail, parce qu'un tableau de bord que
     * personne n'ouvre n'alerte personne.
     *
     * Idempotente. Chaque dossier n'est journalisé qu'une fois par fenêtre
     * d'anti-répétition : sans cela, un dossier oublié pendant deux mois
     * produirait soixante entrées identiques et noierait le journal.
     *
     * @return array{dossiers:int, etablissements:int, erreurs:int, details:list<string>}
     */
    public static function alerterRetardAnalyse(): array
    {
        $jours   = Tracabilite::delaiAnalyse();
        $rapport = ['dossiers' => 0, 'etablissements' => 0, 'erreurs' => 0, 'details' => []];

        // L'anti-répétition est appliquée EN BASE, avant la limite de lot :
        // sinon un arriéré de dossiers déjà signalés remplit le lot à lui
        // seul et les nouveaux retards ne sont jamais alertés.
        $enRetard = Tracabilite::analysesEnRetard($jours, self::LOT, self::ANTI_REPETITION_JOURS);
        if ($enRetard === []) {
            return $rapport;
        }

        $nouveaux = [];   // dossiers non encore signalés dans la fenêtre
        $parEtab  = [];   // synthèse pour l'e-mail : un bloc par établissement

        foreach ($enRetard as $dossier) {
            $dossierId = (int)$dossier['id'];

            try {
                if (self::dejaSignale($dossierId, Journal::ALERTE_RETARD_ANALYSE)) {
                    continue;
                }

                Journal::ecrire(Journal::ALERTE_RETARD_ANALYSE, [
                    'objet_type'       => 'dossier',
                    'objet_id'         => $dossierId,
                    'etablissement_id' => (int)$dossier['etablissement_id'],
                    'acteur_role'      => 'systeme',
                    'donnees'          => [
                        'reference'     => $dossier['reference'] ?? null,
                        'candidat'      => $dossier['nom_complet'] ?? null,
                        'etablissement' => $dossier['etab_nom'] ?? null,
                        'depuis_le'     => $dossier['depuis_le'] ?? null,
                        'jours_attente' => (int)$dossier['jours_attente'],
                        'delai_maximal' => $jours,
                    ],
                ]);

                $nouveaux[] = $dossier;

                $cle = (int)$dossier['etablissement_id'];
                $parEtab[$cle] ??= ['nom' => (string)$dossier['etab_nom'], 'nb' => 0, 'max' => 0];
                $parEtab[$cle]['nb']++;
                $parEtab[$cle]['max'] = max($parEtab[$cle]['max'], (int)$dossier['jours_attente']);

                $rapport['dossiers']++;
            } catch (\Throwable $e) {
                $rapport['erreurs']++;
                $rapport['details'][] = 'Dossier #' . $dossierId . ' : ' . $e->getMessage();
                error_log('[Taches] Retard d’analyse #' . $dossierId . ' : ' . $e->getMessage());
            }
        }

        if ($nouveaux === []) {
            return $rapport;
        }

        $rapport['etablissements'] = count($parEtab);

        foreach ($parEtab as $bloc) {
            $rapport['details'][] = sprintf(
                '%s : %d dossier%s sans décision, le plus ancien depuis %d jours',
                $bloc['nom'],
                $bloc['nb'],
                $bloc['nb'] > 1 ? 's' : '',
                $bloc['max']
            );
        }

        // L'e-mail ne doit jamais faire échouer la tâche : le journal, lui, est
        // déjà écrit, et c'est lui qui fait foi.
        try {
            self::previenirSuperAdmins($parEtab, $nouveaux, $jours);
        } catch (\Throwable $e) {
            $rapport['erreurs']++;
            $rapport['details'][] = 'Envoi aux super administrateurs : ' . $e->getMessage();
            error_log('[Taches] Alerte retard d’analyse, e-mail : ' . $e->getMessage());
        }

        return $rapport;
    }

    /**
     * Un événement de ce type a-t-il déjà été écrit pour ce dossier dans la
     * fenêtre d'anti-répétition ? Le journal étant en ajout seul, il constitue
     * une mémoire fiable sans ajouter de colonne au dossier.
     */
    private static function dejaSignale(int $dossierId, string $evenement): bool
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT 1 FROM journal_activite
                  WHERE objet_type = \'dossier\' AND objet_id = ?
                    AND type_evenement = ?
                    AND cree_le > DATE_SUB(NOW(), INTERVAL ? DAY)
                  LIMIT 1'
            );
            $st->execute([$dossierId, $evenement, self::ANTI_REPETITION_JOURS]);

            return $st->fetchColumn() !== false;
        } catch (\Throwable $e) {
            error_log('[Taches] Lecture anti-répétition : ' . $e->getMessage());

            // En cas de doute, on considère le dossier déjà signalé : mieux
            // vaut une alerte manquante qu'une inondation du journal.
            return true;
        }
    }

    /**
     * E-mail de synthèse aux super administrateurs actifs.
     *
     * Une seule synthèse, jamais un message par dossier : une alerte qui arrive
     * quarante fois le matin n'est plus lue au bout de trois.
     *
     * @param array<int,array{nom:string,nb:int,max:int}> $parEtab
     * @param list<array<string,mixed>>                   $dossiers
     */
    private static function previenirSuperAdmins(array $parEtab, array $dossiers, int $jours): void
    {
        $destinataires = self::superAdmins();
        if ($destinataires === []) {
            return;
        }

        $h     = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $total = count($dossiers);

        $lignes = '';
        foreach ($parEtab as $bloc) {
            $lignes .= '<tr>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #E8E8ED;font-size:14px;">' . $h($bloc['nom']) . '</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #E8E8ED;font-size:14px;text-align:right;">' . (int)$bloc['nb'] . '</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #E8E8ED;font-size:14px;text-align:right;color:#B45309;font-weight:700;">'
                . (int)$bloc['max'] . ' j</td>'
                . '</tr>';
        }

        $sujet = sprintf(
            '[Connect\'Academia] %d dossier%s non analysé%s au-delà de %d jours',
            $total,
            $total > 1 ? 's' : '',
            $total > 1 ? 's' : '',
            $jours
        );

        $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;padding:24px;background:#FAF9F5;color:#1A1A1A;">
  <p style="margin:0 0 4px;font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:#8B52FA;font-weight:700;">Alerte de traitement</p>
  <h1 style="margin:0 0 12px;font-size:21px;font-weight:700;">Délai d'analyse dépassé</h1>
  <p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#4A4A4A;">
    {$total} dossier(s) attendent une décision depuis plus de {$jours} jours.
    Le candidat n'a reçu ni validation, ni demande de pièce, ni refus.
  </p>
  <table style="width:100%;border-collapse:collapse;background:#FFFFFF;border:1px solid #E8E8ED;border-radius:8px;">
    <thead>
      <tr>
        <th style="padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#9E9E9E;border-bottom:1px solid #E8E8ED;">Établissement</th>
        <th style="padding:8px 12px;text-align:right;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#9E9E9E;border-bottom:1px solid #E8E8ED;">Dossiers</th>
        <th style="padding:8px 12px;text-align:right;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#9E9E9E;border-bottom:1px solid #E8E8ED;">Plus ancien</th>
      </tr>
    </thead>
    <tbody>{$lignes}</tbody>
  </table>
  <p style="margin:18px 0 0;font-size:13px;line-height:1.6;color:#4A4A4A;">
    Le détail dossier par dossier est disponible dans les tableaux de bord de la console
    d'administration, section « Délai d'analyse dépassé ».
  </p>
  <p style="margin:16px 0 0;font-size:11px;color:#9E9E9E;">
    Message automatique. Chaque dossier n'est signalé qu'une fois par quinzaine.
  </p>
</div>
HTML;

        foreach ($destinataires as $admin) {
            Queue::push('send_email', [
                'to'      => (string)$admin['email'],
                'toName'  => trim((string)($admin['prenom'] ?? '') . ' ' . (string)($admin['nom'] ?? '')),
                'subject' => $sujet,
                'html'    => $html,
            ]);
        }
    }

    /**
     * Super administrateurs actifs. Les administrateurs ordinaires et les
     * modérateurs ne sont pas destinataires : cette alerte porte sur la
     * relation contractuelle avec un partenaire.
     *
     * @return list<array{email:string, nom:?string, prenom:?string}>
     */
    private static function superAdmins(): array
    {
        try {
            $st = Database::getInstance()->getConnection()->query(
                "SELECT email, nom, prenom
                   FROM admins
                  WHERE role = 'super_admin' AND is_active = 1 AND email <> ''"
            );

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Taches] Liste des super administrateurs illisible : ' . $e->getMessage());

            return [];
        }
    }

    // ── Hebdomadaire — alerte taux de boursiers ─────────────────────────────

    /**
     * Compare le taux de boursiers de chaque établissement à la moyenne de la
     * plateforme et journalise les écarts anormaux (spec §11.4, §14).
     *
     * Aucune notification n'est envoyée à l'établissement concerné : un écart
     * n'est pas une accusation, et l'annoncer au partenaire avant vérification
     * serait à la fois injuste et contre-productif. L'écart part dans le
     * journal, que le super administrateur consulte.
     *
     * @return array{signales:int, moyenne:float, details:list<string>}
     */
    public static function alerterTauxBoursiers(): array
    {
        $analyse  = Tracabilite::alerteBoursiers();
        $rapport  = ['signales' => 0, 'moyenne' => $analyse['moyenne'], 'details' => []];

        foreach ($analyse['etablissements'] as $etab) {
            if (!$etab['anormal']) {
                continue;
            }

            Journal::ecrire(Journal::ALERTE_TAUX_BOURSIERS, [
                'objet_type'       => 'etablissement',
                'objet_id'         => $etab['etablissement_id'],
                'etablissement_id' => $etab['etablissement_id'],
                'acteur_role'      => 'systeme',
                'donnees'          => [
                    'etablissement'      => $etab['nom'],
                    'taux_etablissement' => $etab['taux'],
                    'moyenne_plateforme' => $analyse['moyenne'],
                    'ecart_pourcent'     => $etab['ecart'],
                    'dossiers'           => $etab['dossiers'],
                    'boursiers'          => $etab['boursiers'],
                ],
            ]);

            $rapport['signales']++;
            $rapport['details'][] = sprintf(
                '%s : %s %% de boursiers sur %d dossiers (moyenne %s %%, écart %s%s %%)',
                $etab['nom'],
                number_format($etab['taux'], 1, ',', ' '),
                $etab['dossiers'],
                number_format($analyse['moyenne'], 1, ',', ' '),
                $etab['ecart'] > 0 ? '+' : '',
                number_format($etab['ecart'], 1, ',', ' ')
            );
        }

        return $rapport;
    }

    /**
     * Prévient le super administrateur des étudiants inscrits à 100 % dans
     * plusieurs établissements pour la même année (spec §17).
     *
     * @return array{signales:int, details:list<string>}
     */
    public static function signalerInscriptionsMultiples(): array
    {
        $rapport = ['signales' => 0, 'details' => []];

        foreach (Tracabilite::inscriptionsMultiples() as $cas) {
            Journal::ecrire(Journal::ALERTE_INSC_MULTIPLES, [
                'utilisateur_id' => (int)$cas['eleve_id'],
                'objet_type'     => 'utilisateur',
                'objet_id'       => (int)$cas['eleve_id'],
                'acteur_role'    => 'systeme',
                'donnees'        => [
                    'annee'          => $cas['annee_academique'],
                    'nb_etablissements' => (int)$cas['nb_etablissements'],
                    'etablissements' => $cas['etablissements'],
                ],
            ]);

            $rapport['signales']++;
            $rapport['details'][] = sprintf(
                '%s — %d établissements en %s : %s',
                $cas['nom_complet'] ?? ('#' . $cas['eleve_id']),
                (int)$cas['nb_etablissements'],
                $cas['annee_academique'],
                $cas['etablissements']
            );
        }

        return $rapport;
    }

    // ── Requêtes ─────────────────────────────────────────────────────────────

    /** @return list<array<string,mixed>> */
    private static function dossiersExpires(): array
    {
        try {
            $st = Database::getInstance()->getConnection()->query(
                "SELECT d.*, e.nom AS etab_nom
                 FROM dossiers_inscription d
                 JOIN etablissements e ON e.id = d.etablissement_id
                 WHERE d.statut IN ('valide_50', 'accepte')
                   AND d.expire_le IS NOT NULL
                   AND d.expire_le < NOW()
                 ORDER BY d.expire_le ASC
                 LIMIT " . self::LOT
            );

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Taches] Lecture des dossiers expirés : ' . $e->getMessage());

            return [];
        }
    }

    /** @return list<int> */
    private static function recepissesManquants(int $lot): array
    {
        try {
            $st = Database::getInstance()->getConnection()->query(
                "SELECT d.id
                 FROM dossiers_inscription d
                 LEFT JOIN recepisses r ON r.dossier_id = d.id
                 WHERE d.reference IS NOT NULL
                   AND d.statut NOT IN ('brouillon', 'annule')
                   AND (r.id IS NULL OR r.cle_pdf IS NULL OR r.cle_pdf = '')
                 ORDER BY d.soumis_le ASC
                 LIMIT {$lot}"
            );

            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
        } catch (\Throwable $e) {
            error_log('[Taches] Lecture des récépissés manquants : ' . $e->getMessage());

            return [];
        }
    }

    /** @return list<array{etablissement_id:int, nb:int, plus_ancien:int}> */
    private static function dormantsParEtablissement(int $jours): array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                "SELECT d.etablissement_id,
                        COUNT(*) AS nb,
                        MAX(DATEDIFF(NOW(), d.soumis_le)) AS plus_ancien
                 FROM dossiers_inscription d
                 -- `preinscriptions` porte une ligne pour CHAQUE dossier dès la
                 -- réservation du récépissé : filtrer dessus ne renvoyait jamais
                 -- rien, et aucune relance ne partait.
                 WHERE d.statut IN ('soumis', 'paye', 'piece_manquante')
                   AND d.soumis_le IS NOT NULL
                   AND d.soumis_le < DATE_SUB(NOW(), INTERVAL :jours DAY)
                 GROUP BY d.etablissement_id
                 ORDER BY nb DESC"
            );
            $st->execute([':jours' => $jours]);

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Taches] Lecture des dossiers dormants : ' . $e->getMessage());

            return [];
        }
    }

    // ── Notifications ────────────────────────────────────────────────────────

    private static function notifierCandidat(array $dossier, string $type, string $titre, string $message): void
    {
        if (empty($dossier['eleve_id'])) {
            return;
        }

        (new NotificationOrientation())->create([
            'user_id'          => (int)$dossier['eleve_id'],
            'etablissement_id' => (int)$dossier['etablissement_id'],
            'type'             => $type,
            'titre'            => $titre,
            'message'          => $message,
        ]);
    }

    private static function notifierEtablissement(int $etablissementId, string $type, string $titre, string $message): void
    {
        $notifications = new NotificationOrientation();

        foreach ($notifications->findGestionnaireIds($etablissementId) as $gestionnaireId) {
            $notifications->create([
                'user_id'          => (int)$gestionnaireId,
                'etablissement_id' => $etablissementId,
                'type'             => $type,
                'titre'            => $titre,
                'message'          => $message,
            ]);
        }
    }

}
