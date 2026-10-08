<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Core\Session;
use PDO;

/**
 * Journal d'activité — EN AJOUT SEUL (spec §11.3).
 *
 * Trace unique et opposable de tout ce qui arrive à un utilisateur et à un
 * dossier. Cette classe n'expose délibérément AUCUNE méthode d'écriture autre
 * que l'insertion : pas d'update(), pas de delete(), pas de purge(). Un journal
 * modifiable ne prouve rien.
 *
 * Complémentaire de AuditService (audit_log), qui reste en place pour les
 * actions back-office historiques : Journal est la frise *utilisateur*,
 * audit_log est la trace *administrative*. Les deux coexistent sans doublon
 * fonctionnel — Journal est le seul consulté par la frise de la fiche §11.1.
 *
 * Aucune écriture ne doit pouvoir faire échouer une opération métier :
 * toute exception est absorbée et écrite dans les logs applicatifs.
 */
final class Journal
{
    // ── Types d'événements (spec §11.2) ──────────────────────────────────────
    public const COMPTE_CREE             = 'compte_cree';
    public const CONNEXION               = 'connexion';
    public const PREINSCRIPTION_CREEE    = 'preinscription_creee';
    public const DOSSIER_DEMARRE         = 'dossier_demarre';
    public const DOSSIER_SOUMIS          = 'dossier_soumis';
    public const RECEPISSE_GENERE        = 'recepisse_genere';
    public const RECEPISSE_TELECHARGE    = 'recepisse_telecharge';
    public const PIECE_DEMANDEE          = 'piece_demandee';
    public const PIECE_TELEVERSEE        = 'piece_televersee';
    public const DOSSIER_VALIDE_50       = 'dossier_valide_50';
    public const DOSSIER_REJETE          = 'dossier_rejete';
    public const QR_SCANNE               = 'qr_scanne';
    public const DOSSIER_VALIDE_100      = 'dossier_valide_100';
    public const ATTESTATION_GENEREE     = 'attestation_generee';
    public const COMMISSION_GENEREE      = 'commission_generee';
    public const STATUT_BOURSE_MODIFIE   = 'statut_bourse_modifie';
    public const DOSSIER_EXPIRE          = 'dossier_expire';
    public const TAUX_COMMISSION_MODIFIE = 'taux_commission_modifie';

    // Événements complémentaires nécessaires au fonctionnement réel
    public const DOSSIER_ANNULE          = 'dossier_annule';
    public const DOSSIER_PROLONGE        = 'dossier_prolonge';
    public const ATTESTATION_TELECHARGEE = 'attestation_telechargee';
    public const JETON_REVOQUE           = 'jeton_revoque';
    public const PIECE_DECIDEE           = 'piece_decidee';
    public const PIECE_SUPPRIMEE         = 'piece_supprimee';
    public const DOSSIER_RELANCE         = 'dossier_relance';
    /**
     * @deprecated Plus aucune tâche n'en écrit : la relance des « dossiers
     *             ANBG en cours » a disparu avec la situation de bourse du
     *             même nom. La constante reste pour relire le journal, qui est
     *             en ajout seul et conserve les lignes déjà écrites.
     */
    public const RELANCE_BOURSE          = 'relance_bourse';
    public const COMMISSION_ANNULEE      = 'commission_annulee';
    public const COMMISSION_STATUT       = 'commission_statut_modifie';
    public const ALERTE_TAUX_BOURSIERS   = 'alerte_taux_boursiers';
    public const ALERTE_INSC_MULTIPLES   = 'alerte_inscriptions_multiples';

    /**
     * Annulation d'une décision par l'établissement (droit à l'erreur du
     * guichet). Type d'événement DISTINCT de la décision elle-même : une
     * validation annulée doit se lire comme telle dans la frise, sinon un
     * dossier corrigé raconte une histoire fausse.
     */
    public const DECISION_ANNULEE        = 'decision_annulee';

    /** Établissement au-delà du délai d'analyse réglementaire (spec §14). */
    public const ALERTE_RETARD_ANALYSE   = 'alerte_retard_analyse';

    /** Reçu de règlement de scolarité édité (par le candidat ou au guichet). */
    public const RECU_TELECHARGE         = 'recu_telecharge';

    /**
     * Pièce refusée remplacée par le candidat depuis son espace.
     * Distinct de PIECE_TELEVERSEE : un remplacement raconte un aller-retour
     * avec l'établissement, pas un premier dépôt.
     */
    public const PIECE_REMPLACEE         = 'piece_remplacee';

    /** Dossier renvoyé à l'analyse après complément de pièces (spec §9.3). */
    public const DOSSIER_RENVOYE         = 'dossier_renvoye';

    /**
     * Abandon d'année constaté par le SUPER ADMINISTRATEUR (spec §10.5).
     * L'inscription n'est pas défaite : c'est la créance qui se ferme.
     */
    public const ABANDON_DECLARE         = 'abandon_declare';
    public const ABANDON_LEVE            = 'abandon_leve';

    /** Libellés lisibles pour la frise chronologique (spec §11.1). */
    private const LIBELLES = [
        self::COMPTE_CREE             => 'Compte créé',
        self::CONNEXION               => 'Connexion',
        self::PREINSCRIPTION_CREEE    => 'Préinscription déposée',
        self::DOSSIER_DEMARRE         => 'Dossier d’inscription démarré',
        self::DOSSIER_SOUMIS          => 'Dossier soumis',
        self::RECEPISSE_GENERE        => 'Récépissé généré',
        self::RECEPISSE_TELECHARGE    => 'Récépissé téléchargé',
        self::PIECE_DEMANDEE          => 'Pièce complémentaire demandée',
        self::PIECE_TELEVERSEE        => 'Pièce téléversée',
        self::DOSSIER_VALIDE_50       => 'Dossier validé à 50 %',
        self::DOSSIER_REJETE          => 'Dossier rejeté',
        self::QR_SCANNE               => 'QR code scanné',
        self::DOSSIER_VALIDE_100      => 'Inscription définitive (100 %)',
        self::ATTESTATION_GENEREE     => 'Attestation d’inscription générée',
        self::COMMISSION_GENEREE      => 'Commission générée',
        self::STATUT_BOURSE_MODIFIE   => 'Situation de bourse modifiée',
        self::DOSSIER_EXPIRE          => 'Dossier expiré',
        self::TAUX_COMMISSION_MODIFIE => 'Taux de commission modifié',
        self::DOSSIER_ANNULE          => 'Dossier annulé',
        self::DOSSIER_PROLONGE        => 'Délai prolongé',
        self::ATTESTATION_TELECHARGEE => 'Attestation téléchargée',
        self::JETON_REVOQUE           => 'Jeton de vérification révoqué',
        self::PIECE_DECIDEE           => 'Pièce acceptée ou refusée',
        self::PIECE_SUPPRIMEE         => 'Pièce supprimée',
        self::DOSSIER_RELANCE         => 'Candidat relancé',
        self::RELANCE_BOURSE          => 'Relance — situation de bourse',
        self::COMMISSION_ANNULEE      => 'Commission annulée',
        self::COMMISSION_STATUT       => 'Statut de commission modifié',
        self::ALERTE_TAUX_BOURSIERS   => 'Alerte — taux de boursiers anormal',
        self::ALERTE_INSC_MULTIPLES   => 'Alerte — inscriptions multiples',
        self::DECISION_ANNULEE        => 'Décision annulée par l’établissement',
        self::ALERTE_RETARD_ANALYSE   => 'Alerte — dossier non analysé dans le délai',
        self::RECU_TELECHARGE         => 'Reçu de scolarité téléchargé',
        self::PIECE_REMPLACEE         => 'Pièce remplacée par le candidat',
        self::DOSSIER_RENVOYE         => 'Dossier renvoyé à l’analyse',
        self::ABANDON_DECLARE         => 'Abandon d’année constaté',
        self::ABANDON_LEVE            => 'Abandon d’année levé',
    ];

    /** Longueur maximale d'un bloc JSON de contexte, pour borner la table. */
    private const MAX_DONNEES_OCTETS = 8000;

    /**
     * Écrit une entrée. Ne lève jamais.
     *
     * @param string      $type    Une des constantes de classe.
     * @param array{
     *   utilisateur_id?:int|null, acteur_id?:int|null, acteur_role?:string|null,
     *   objet_type?:string|null, objet_id?:int|null, etablissement_id?:int|null,
     *   donnees?:array
     * } $ctx
     */
    public static function ecrire(string $type, array $ctx = []): void
    {
        try {
            [$acteurId, $acteurRole] = self::acteurCourant($ctx);

            $donnees = $ctx['donnees'] ?? [];
            $json    = $donnees === [] ? null : self::encoderDonnees($donnees);

            $st = Database::getInstance()->getConnection()->prepare(
                'INSERT INTO journal_activite
                   (utilisateur_id, acteur_id, acteur_role, type_evenement,
                    objet_type, objet_id, etablissement_id, donnees, adresse_ip, cree_le)
                 VALUES (:uid, :aid, :role, :type, :otype, :oid, :etab, :donnees, :ip, NOW())'
            );

            $st->execute([
                ':uid'     => self::entierOuNull($ctx['utilisateur_id'] ?? null),
                ':aid'     => $acteurId,
                ':role'    => $acteurRole,
                ':type'    => $type,
                ':otype'   => $ctx['objet_type'] ?? null,
                ':oid'     => self::entierOuNull($ctx['objet_id'] ?? null),
                ':etab'    => self::entierOuNull($ctx['etablissement_id'] ?? null),
                ':donnees' => $json,
                ':ip'      => self::ip(),
            ]);
        } catch (\Throwable $e) {
            error_log('[Journal] Écriture échouée (' . $type . ') : ' . $e->getMessage());
        }
    }

    /**
     * Raccourci pour les événements portant sur un dossier : renseigne
     * automatiquement objet_type, l'établissement et l'utilisateur concerné.
     *
     * @param array{id:int,eleve_id?:int|null,etablissement_id?:int|null} $dossier
     */
    public static function dossier(string $type, array $dossier, array $donnees = [], array $ctx = []): void
    {
        self::ecrire($type, $ctx + [
            'utilisateur_id'   => $dossier['eleve_id']         ?? null,
            'objet_type'       => 'dossier',
            'objet_id'         => (int)($dossier['id']         ?? 0),
            'etablissement_id' => $dossier['etablissement_id'] ?? null,
            'donnees'          => $donnees,
        ]);
    }

    // ── Lecture (frise, écrans super admin) ──────────────────────────────────

    /**
     * Frise chronologique d'un utilisateur, du plus récent au plus ancien.
     *
     * @return list<array<string,mixed>>
     */
    public static function friseUtilisateur(int $utilisateurId, int $limite = 300): array
    {
        return self::lire(
            'WHERE j.utilisateur_id = :uid',
            [':uid' => $utilisateurId],
            $limite
        );
    }

    /**
     * Frise d'un objet précis (un dossier, par exemple).
     *
     * @return list<array<string,mixed>>
     */
    public static function friseObjet(string $objetType, int $objetId, int $limite = 200): array
    {
        return self::lire(
            'WHERE j.objet_type = :otype AND j.objet_id = :oid',
            [':otype' => $objetType, ':oid' => $objetId],
            $limite
        );
    }

    /**
     * Consultation filtrée du journal (écran /admin/journal).
     *
     * @param array{type?:string,etablissement_id?:int,utilisateur_id?:int,du?:string,au?:string} $filtres
     * @return list<array<string,mixed>>
     */
    public static function rechercher(array $filtres = [], int $limite = 200, int $offset = 0): array
    {
        $where  = [];
        $params = [];

        if (!empty($filtres['type'])) {
            $where[]         = 'j.type_evenement = :type';
            $params[':type'] = (string)$filtres['type'];
        }
        if (!empty($filtres['etablissement_id'])) {
            $where[]         = 'j.etablissement_id = :etab';
            $params[':etab'] = (int)$filtres['etablissement_id'];
        }
        if (!empty($filtres['utilisateur_id'])) {
            $where[]        = 'j.utilisateur_id = :uid';
            $params[':uid'] = (int)$filtres['utilisateur_id'];
        }
        if (!empty($filtres['du'])) {
            $where[]       = 'j.cree_le >= :du';
            $params[':du'] = $filtres['du'] . ' 00:00:00';
        }
        if (!empty($filtres['au'])) {
            $where[]       = 'j.cree_le <= :au';
            $params[':au'] = $filtres['au'] . ' 23:59:59';
        }

        return self::lire(
            $where === [] ? '' : 'WHERE ' . implode(' AND ', $where),
            $params,
            $limite,
            $offset
        );
    }

    /** Nombre total d'entrées correspondant aux mêmes filtres (pagination). */
    public static function compter(array $filtres = []): int
    {
        // Réutilise rechercher() sans LIMIT serait coûteux : on refait le WHERE.
        $where  = [];
        $params = [];
        foreach ([
            'type'             => ['j.type_evenement = :type',  ':type'],
            'etablissement_id' => ['j.etablissement_id = :etab', ':etab'],
            'utilisateur_id'   => ['j.utilisateur_id = :uid',    ':uid'],
        ] as $cle => [$sql, $bind]) {
            if (!empty($filtres[$cle])) {
                $where[]        = $sql;
                $params[$bind]  = $filtres[$cle];
            }
        }
        if (!empty($filtres['du'])) {
            $where[]       = 'j.cree_le >= :du';
            $params[':du'] = $filtres['du'] . ' 00:00:00';
        }
        if (!empty($filtres['au'])) {
            $where[]       = 'j.cree_le <= :au';
            $params[':au'] = $filtres['au'] . ' 23:59:59';
        }

        try {
            $sql = 'SELECT COUNT(*) FROM journal_activite j'
                 . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where));
            $st  = Database::getInstance()->getConnection()->prepare($sql);
            $st->execute($params);

            return (int)$st->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[Journal] Comptage échoué : ' . $e->getMessage());
            return 0;
        }
    }

    /** Libellé lisible d'un type d'événement. */
    public static function libelle(string $type): string
    {
        return self::LIBELLES[$type] ?? $type;
    }

    /** @return array<string,string> type => libellé, pour les filtres d'écran. */
    public static function typesDisponibles(): array
    {
        return self::LIBELLES;
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $params
     * @return list<array<string,mixed>>
     */
    private static function lire(string $where, array $params, int $limite, int $offset = 0): array
    {
        // LIMIT/OFFSET interpolés après cast entier borné : PDO en mode
        // EMULATE_PREPARES=false refuse les placeholders sur LIMIT.
        $limite = max(1, min($limite, 1000));
        $offset = max(0, $offset);

        try {
            $sql = "SELECT j.*,
                           u.nom  AS sujet_nom,  u.prenom AS sujet_prenom,
                           e.nom  AS etab_nom
                    FROM journal_activite j
                    LEFT JOIN users          u ON u.id = j.utilisateur_id
                    LEFT JOIN etablissements e ON e.id = j.etablissement_id
                    {$where}
                    ORDER BY j.cree_le DESC, j.id DESC
                    LIMIT {$limite} OFFSET {$offset}";

            $st = Database::getInstance()->getConnection()->prepare($sql);
            $st->execute($params);
            $lignes = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Journal] Lecture échouée : ' . $e->getMessage());
            return [];
        }

        foreach ($lignes as &$ligne) {
            $ligne['libelle'] = self::libelle((string)$ligne['type_evenement']);
            $ligne['donnees'] = self::decoderDonnees($ligne['donnees'] ?? null);
        }

        return $lignes;
    }

    /**
     * Détermine l'auteur de l'action : valeur explicite si fournie, sinon
     * déduite de la session (élève/gestionnaire, ou administrateur).
     *
     * @return array{0:int|null,1:string|null}
     */
    private static function acteurCourant(array $ctx): array
    {
        if (array_key_exists('acteur_id', $ctx) || array_key_exists('acteur_role', $ctx)) {
            return [
                self::entierOuNull($ctx['acteur_id'] ?? null),
                isset($ctx['acteur_role']) ? (string)$ctx['acteur_role'] : null,
            ];
        }

        if (Session::isAdmin()) {
            return [Session::adminId(), Session::adminRole() ?? 'admin'];
        }
        if (Session::isGestionnaire()) {
            return [Session::gestionnaireId(), 'gestionnaire'];
        }
        if (Session::isLoggedIn()) {
            return [Session::userId(), Session::userRole() ?? 'eleve'];
        }

        return [null, PHP_SAPI === 'cli' ? 'systeme' : null];
    }

    private static function encoderDonnees(array $donnees): ?string
    {
        $json = json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false) {
            return null;
        }

        if (strlen($json) > self::MAX_DONNEES_OCTETS) {
            $json = json_encode(
                ['_tronque' => true, 'apercu' => mb_substr($json, 0, 500)],
                JSON_UNESCAPED_UNICODE
            ) ?: null;
        }

        return $json;
    }

    /** @return array<string,mixed> */
    private static function decoderDonnees(mixed $brut): array
    {
        if (!is_string($brut) || $brut === '') {
            return [];
        }
        $decode = json_decode($brut, true);

        return is_array($decode) ? $decode : [];
    }

    private static function entierOuNull(mixed $valeur): ?int
    {
        if ($valeur === null || $valeur === '' || $valeur === 0 || $valeur === '0') {
            return null;
        }

        return (int)$valeur;
    }

    /**
     * Adresse IP du client. En production derrière nginx, REMOTE_ADDR est déjà
     * l'IP réelle (proxy_set_header). On ne lit JAMAIS X-Forwarded-For sans
     * liste blanche de proxies : c'est un en-tête falsifiable par le client,
     * et le journal doit rester opposable.
     */
    private static function ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }
}
