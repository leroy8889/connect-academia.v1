<?php
declare(strict_types=1);

namespace Models\Orientation;

/**
 * Machine à états du dossier d'inscription (spec §4).
 *
 * Cette classe est la SEULE source de vérité sur les états et les transitions.
 * Elle ne touche pas la base : c'est Dossier::transition() qui écrit, en
 * s'appuyant sur les règles définies ici.
 *
 * Règle absolue de la spec §4.2 : aucun autre chemin que ceux décrits ici.
 */
final class DossierStatut
{
    // ── États (spec §4.1) ────────────────────────────────────────────────────
    public const BROUILLON       = 'brouillon';
    public const SOUMIS          = 'soumis';
    public const PIECE_MANQUANTE = 'piece_manquante';
    public const VALIDE_50       = 'valide_50';
    public const REJETE          = 'rejete';
    public const VALIDE_100      = 'valide_100';
    public const EXPIRE          = 'expire';
    public const ANNULE          = 'annule';

    /**
     * Statuts hérités de l'ancien modèle, encore présents dans l'ENUM MySQL
     * pour que les lignes non migrées restent lisibles. Plus aucune écriture ne
     * les produit — ils sont normalisés à la lecture par self::normaliser().
     */
    private const HERITES = [
        'paye'    => self::SOUMIS,
        'accepte' => self::VALIDE_50,
        'inscrit' => self::VALIDE_100,
        'refuse'  => self::REJETE,
    ];

    /** États terminaux : plus aucune transition, sauf super administrateur. */
    private const TERMINAUX = [self::REJETE, self::VALIDE_100, self::EXPIRE, self::ANNULE];

    /**
     * Retours arrière autorisés à l'établissement : état actuel => état rendu.
     *
     * C'est le droit à l'erreur du guichet. Un agent qui valide le mauvais
     * dossier à 50 %, demande une pièce déjà fournie, rejette par méprise ou
     * enregistre un règlement sur le mauvais candidat doit pouvoir défaire son
     * geste — sinon la correction passe par un appel au support, et la donnée
     * reste fausse en attendant.
     *
     * Volontairement HORS de self::TRANSITIONS : la zone décision se construit
     * à partir de TRANSITIONS, et un retour arrière n'est pas une décision. Il
     * s'affiche à part, avec sa propre confirmation, et il est journalisé comme
     * une annulation (Journal::DECISION_ANNULEE), jamais comme une décision.
     *
     * Chaque retour rend l'état IMMÉDIATEMENT ANTÉRIEUR, pas le début du
     * parcours : annuler les 100 % ramène à 50 %, pas à « soumis ».
     */
    private const RETOURS = [
        self::VALIDE_50       => self::SOUMIS,
        self::PIECE_MANQUANTE => self::SOUMIS,
        self::REJETE          => self::SOUMIS,
        self::VALIDE_100      => self::VALIDE_50,
    ];

    /** Ce que l'agent lit sur le bouton, et ce qu'il doit comprendre. */
    private const RETOUR_LIBELLES = [
        self::VALIDE_50       => 'Annuler la validation à 50 %',
        self::PIECE_MANQUANTE => 'Annuler la demande de pièce',
        self::REJETE          => 'Annuler le rejet',
        self::VALIDE_100      => 'Annuler l’inscription à 100 %',
    ];

    /** Conséquence annoncée avant de cliquer : rien ne doit surprendre. */
    private const RETOUR_EFFETS = [
        self::VALIDE_50       => 'Le dossier redevient « soumis », la date limite de présentation est effacée, et le candidat est prévenu.',
        self::PIECE_MANQUANTE => 'Le dossier redevient « soumis » et la liste des pièces attendues est effacée.',
        self::REJETE          => 'Le dossier redevient « soumis » : le motif de refus est effacé et l’analyse reprend.',
        self::VALIDE_100      => 'Le dossier repasse à 50 %, la commission est annulée, l’attestation d’inscription est retirée et les montants saisis sont effacés.',
    ];

    /**
     * Transitions autorisées (spec §4.2) : depuis => [vers => acteurs autorisés].
     *
     * Acteurs : 'etudiant', 'etablissement', 'systeme'.
     * Le super administrateur est traité à part (self::AUTORISE_SUPER_ADMIN).
     */
    private const TRANSITIONS = [
        self::BROUILLON => [
            self::SOUMIS => ['etudiant'],
            self::ANNULE => ['etudiant'],
        ],
        self::SOUMIS => [
            self::VALIDE_50       => ['etablissement'],
            self::PIECE_MANQUANTE => ['etablissement'],
            self::REJETE          => ['etablissement'],
        ],
        self::PIECE_MANQUANTE => [
            self::SOUMIS => ['etudiant'],
            self::REJETE => ['etablissement'],
        ],
        self::VALIDE_50 => [
            self::VALIDE_100 => ['etablissement'],
            self::EXPIRE     => ['systeme'],
            self::ANNULE     => ['etudiant', 'etablissement'],
        ],
        // États terminaux : aucune sortie ordinaire.
        self::REJETE     => [],
        self::VALIDE_100 => [],
        self::EXPIRE     => [],
        self::ANNULE     => [],
    ];

    /** Progression affichée par état (spec §4.1). */
    private const PROGRESSION = [
        self::BROUILLON       => 0,
        self::SOUMIS          => 0,
        self::PIECE_MANQUANTE => 0,
        self::VALIDE_50       => 50,
        self::REJETE          => 0,
        self::VALIDE_100      => 100,
        self::EXPIRE          => 0,
        self::ANNULE          => 0,
    ];

    /**
     * Libellé court d'une PRÉINSCRIPTION, côté back-office.
     *
     * Même machine à états, deux vocabulaires. Une préinscription est REÇUE, et
     * c'est tout : elle ne se valide pas, ne se refuse pas, n'attend aucune
     * décision de l'établissement. Le parcours d'inscription — validation sur
     * pièces, passage au guichet, commission — ne la concerne pas, et le lui
     * appliquer à l'écran fait croire à l'agent qu'il a un dossier à trancher.
     *
     * Les états autres que « reçue » ne sont plus produits : ils traduisent des
     * lignes héritées d'un ancien parcours où l'établissement se prononçait.
     * On les lit pour ne pas afficher un nom technique, on n'en crée plus.
     */
    private const LIBELLES_PREINSCRIPTION = [
        self::BROUILLON       => 'Brouillon',
        self::SOUMIS          => 'Préinscription reçue',
        self::PIECE_MANQUANTE => 'Pièce manquante',
        self::VALIDE_50       => 'Acceptée',
        self::REJETE          => 'Refusée',
        self::VALIDE_100      => 'Acceptée',
        self::EXPIRE          => 'Expirée',
        self::ANNULE          => 'Annulée',
    ];


    /** Libellé court, côté back-office. */
    private const LIBELLES = [
        self::BROUILLON       => 'Brouillon',
        self::SOUMIS          => 'Soumis',
        self::PIECE_MANQUANTE => 'Pièce manquante',
        self::VALIDE_50       => 'Validé à 50 %',
        self::REJETE          => 'Rejeté',
        self::VALIDE_100      => 'Inscrit (100 %)',
        self::EXPIRE          => 'Expiré',
        self::ANNULE          => 'Annulé',
    ];

    /**
     * Message affiché à l'étudiant (spec §9.2). Jamais le nom technique.
     * {motif} est substitué par self::messageEtudiant().
     */
    private const MESSAGES_ETUDIANT = [
        self::BROUILLON       => 'Votre dossier est commencé mais pas encore envoyé. Reprenez-le pour le finaliser.',
        self::SOUMIS          => 'Votre dossier a été reçu. L’établissement l’analyse. Vous serez notifié.',
        self::PIECE_MANQUANTE => 'L’établissement demande une pièce complémentaire. Téléversez-la pour poursuivre.',
        self::VALIDE_50       => 'L’établissement a accepté votre dossier. Vous êtes enregistré '
                                 . 'auprès de l’établissement et votre place est retenue. Il ne reste '
                                 . 'qu’une seule étape, le règlement de la scolarité, qui se fait '
                                 . 'uniquement sur place. Présentez-vous à l’établissement avec votre '
                                 . 'récépissé avant le début des cours, vous pouvez y aller au moment '
                                 . 'qui vous arrange, même à l’approche de la rentrée. Votre place '
                                 . 'reste retenue jusqu’à cette date.',
        self::REJETE          => 'Votre dossier n’a pas été retenu. Motif : {motif}.',
        self::VALIDE_100      => 'Vous êtes inscrit. Votre attestation d’inscription est disponible.',
        self::EXPIRE          => 'Le délai de finalisation est dépassé. Contactez l’établissement.',
        self::ANNULE          => 'Ce dossier a été annulé.',
    ];

    /**
     * Message affiché au candidat d'une PRÉINSCRIPTION.
     *
     * Elle n'engage à rien : ni pièce à fournir, ni scolarité à régler, ni
     * décision à attendre. Lui servir le texte de l'inscription
     * (« présentez-vous avec votre récépissé pour régler votre scolarité »)
     * l'envoie au guichet pour rien.
     */
    private const MESSAGES_ETUDIANT_PREINSCRIPTION = [
        self::BROUILLON       => 'Votre préinscription est commencée mais pas encore envoyée.',
        self::SOUMIS          => 'Votre préinscription est enregistrée. Votre attestation de préinscription est disponible dans votre espace.',
        self::PIECE_MANQUANTE => 'L’établissement demande une pièce complémentaire.',
        self::VALIDE_50       => 'Votre préinscription est enregistrée. Vous pouvez poursuivre vers l’inscription.',
        self::REJETE          => 'Votre préinscription n’a pas été retenue. Motif : {motif}.',
        self::VALIDE_100      => 'Votre préinscription est enregistrée.',
        self::EXPIRE          => 'Cette préinscription n’est plus valable. Contactez l’établissement.',
        self::ANNULE          => 'Cette préinscription a été annulée.',
    ];

    /**
     * Tonalité visuelle, consommée par les vues (badges, bandeaux).
     * Volontairement sémantique — la couleur exacte vient du design system.
     */
    private const TONS = [
        self::BROUILLON       => 'neutre',
        self::SOUMIS          => 'attente',
        self::PIECE_MANQUANTE => 'attente',
        self::VALIDE_50       => 'succes',
        self::REJETE          => 'danger',
        self::VALIDE_100      => 'info',
        self::EXPIRE          => 'danger',
        self::ANNULE          => 'neutre',
    ];

    /** @return list<string> Tous les états de la machine. */
    public static function tous(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    /**
     * Ramène un statut lu en base à un état de la machine.
     * Les valeurs héritées sont traduites ; une valeur inconnue retombe sur
     * BROUILLON plutôt que de propager une donnée hors domaine.
     */
    public static function normaliser(?string $statut): string
    {
        $statut = (string)$statut;

        if (isset(self::HERITES[$statut])) {
            return self::HERITES[$statut];
        }

        return isset(self::TRANSITIONS[$statut]) ? $statut : self::BROUILLON;
    }

    public static function existe(?string $statut): bool
    {
        return $statut !== null && isset(self::TRANSITIONS[$statut]);
    }

    public static function estTerminal(string $statut): bool
    {
        return in_array(self::normaliser($statut), self::TERMINAUX, true);
    }

    /**
     * État rendu par l'annulation de la dernière décision, ou null si le
     * dossier n'est dans aucun état annulable par l'établissement.
     */
    public static function retour(?string $statut): ?string
    {
        return self::RETOURS[self::normaliser($statut)] ?? null;
    }

    /** Libellé du bouton d'annulation, ou null si aucun retour possible. */
    public static function retourLibelle(?string $statut): ?string
    {
        return self::RETOUR_LIBELLES[self::normaliser($statut)] ?? null;
    }

    /** Conséquences annoncées du retour arrière, ou null. */
    public static function retourEffet(?string $statut): ?string
    {
        return self::RETOUR_EFFETS[self::normaliser($statut)] ?? null;
    }

    /**
     * Une transition est-elle autorisée pour cet acteur ?
     *
     * @param string $acteur 'etudiant' | 'etablissement' | 'systeme' | 'super_admin'
     * @param bool   $retour Vrai pour une annulation de décision : la table
     *                       consultée est alors self::RETOURS, jamais
     *                       self::TRANSITIONS. Réservé à l'établissement et au
     *                       super administrateur.
     */
    public static function peut(string $depuis, string $vers, string $acteur, bool $retour = false): bool
    {
        $depuis = self::normaliser($depuis);

        if (!self::existe($vers)) {
            return false;
        }

        // Retour arrière : seul le chemin inverse exact est permis. Un agent ne
        // peut pas « annuler » un rejet en inscrivant le candidat à 100 %.
        if ($retour) {
            return in_array($acteur, ['etablissement', 'super_admin'], true)
                && self::retour($depuis) === $vers;
        }

        // Le super administrateur peut forcer une sortie d'état terminal
        // (spec §4.2, dernière règle). L'action est journalisée avec son identité
        // par l'appelant : Dossier::transition().
        if ($acteur === 'super_admin') {
            return $depuis !== $vers;
        }

        $acteurs = self::TRANSITIONS[$depuis][$vers] ?? null;

        return $acteurs !== null && in_array($acteur, $acteurs, true);
    }

    /**
     * États atteignables depuis un état donné pour un acteur donné.
     *
     * @return list<string>
     */
    public static function transitionsPossibles(string $depuis, string $acteur): array
    {
        $depuis = self::normaliser($depuis);

        if ($acteur === 'super_admin') {
            return array_values(array_diff(self::tous(), [$depuis]));
        }

        $sorties = [];
        foreach (self::TRANSITIONS[$depuis] ?? [] as $vers => $acteurs) {
            if (in_array($acteur, $acteurs, true)) {
                $sorties[] = $vers;
            }
        }

        return $sorties;
    }

    public static function progression(?string $statut): int
    {
        return self::PROGRESSION[self::normaliser($statut)] ?? 0;
    }

    /**
     * Libellé de l'état. Le vocabulaire suit la nature du dossier : une
     * préinscription est « reçue » puis « acceptée », jamais « validée à 50 % ».
     */
    public static function libelle(?string $statut, bool $estInscription = true): string
    {
        $table = $estInscription ? self::LIBELLES : self::LIBELLES_PREINSCRIPTION;

        return $table[self::normaliser($statut)] ?? 'Inconnu';
    }

    public static function ton(?string $statut): string
    {
        return self::TONS[self::normaliser($statut)] ?? 'neutre';
    }

    /**
     * Message destiné à l'étudiant, motif de rejet substitué le cas échéant.
     * Le motif est renvoyé BRUT : l'échappement est de la responsabilité de la
     * vue (spec §15.4), qui seule connaît son contexte de sortie.
     */
    public static function messageEtudiant(?string $statut, ?string $motif = null, bool $estInscription = true): string
    {
        $table   = $estInscription ? self::MESSAGES_ETUDIANT : self::MESSAGES_ETUDIANT_PREINSCRIPTION;
        $message = $table[self::normaliser($statut)] ?? '';
        $motif   = trim((string)$motif);

        return str_replace('{motif}', $motif !== '' ? $motif : 'non précisé', $message);
    }

    /**
     * Les trois paliers de la barre de progression étudiante (spec §9.1).
     *
     * @return list<array{cle:string,libelle:string,atteint:bool,courant:bool}>
     */
    public static function paliers(?string $statut): array
    {
        $progression = self::progression($statut);
        $normalise   = self::normaliser($statut);
        $depose      = $normalise !== self::BROUILLON;

        $definition = [
            ['cle' => 'depose',  'libelle' => 'Déposé',        'seuil' => 0],
            ['cle' => 'valide',  'libelle' => 'Validé à 50 %', 'seuil' => 50],
            ['cle' => 'inscrit', 'libelle' => 'Inscrit',       'seuil' => 100],
        ];

        $paliers = [];
        foreach ($definition as $palier) {
            $atteint = $palier['seuil'] === 0
                ? $depose
                : $progression >= $palier['seuil'];

            $paliers[] = [
                'cle'     => $palier['cle'],
                'libelle' => $palier['libelle'],
                'atteint' => $atteint,
                'courant' => $atteint && $progression === $palier['seuil'],
            ];
        }

        return $paliers;
    }

    /**
     * Action attendue de l'étudiant, s'il y en a une (spec §9.1).
     *
     * @return array{libelle:string,url_suffixe:string}|null
     */
    public static function actionEtudiant(?string $statut): ?array
    {
        return match (self::normaliser($statut)) {
            self::BROUILLON       => ['libelle' => 'Reprendre mon dossier',      'url_suffixe' => ''],
            // Ancre, pas route : le dépôt vit dans la fiche du dossier, sous
            // « Pièces justificatives ». Un suffixe de chemin renverrait vers
            // une URL qui n'existe pas.
            self::PIECE_MANQUANTE => ['libelle' => 'Téléverser la pièce manquante', 'url_suffixe' => '#pieces'],
            self::VALIDE_50       => ['libelle' => 'Télécharger mon récépissé',  'url_suffixe' => '/recepisse'],
            self::VALIDE_100      => ['libelle' => 'Télécharger mon attestation', 'url_suffixe' => '/attestation'],
            default               => null,
        };
    }
}
