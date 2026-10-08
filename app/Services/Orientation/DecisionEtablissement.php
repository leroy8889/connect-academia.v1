<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Database;
use Models\Orientation\Dossier;
use Models\Orientation\DossierStatut;
use Models\Orientation\NotificationOrientation;
use PDO;

/**
 * Décisions de l'établissement sur un dossier (spec §8.3, §8.4).
 *
 * Toutes les décisions passent par ici — le contrôleur ne fait que valider le
 * formulaire et afficher le résultat. Deux raisons :
 *
 *  1. Chaque décision est un enchaînement indissociable : transition d'état,
 *     effets (délai d'expiration, commission, attestation), notification,
 *     journal. Éclaté dans un contrôleur, il finit par exister une branche qui
 *     oublie une étape.
 *  2. Le back-office établissement n'est pas le seul appelant : l'écran de
 *     vérification au guichet valide aussi à 100 %, et le super administrateur
 *     agit sur les mêmes dossiers.
 *
 * La machine à états reste seule juge de ce qui est permis : ce service ne
 * contourne jamais Dossier::transition().
 */
final class DecisionEtablissement
{
    /** Prolongation maximale accordable en une fois, en jours. */
    private const PROLONGATION_MAX_JOURS = 90;

    /**
     * Ce dossier relève-t-il du parcours d'inscription ?
     *
     * La règle est celle du modèle (Dossier::estInscription), jamais réécrite
     * ici. Ce qui en dépend : la validation sur pièces, le passage à 100 %, la
     * commission, le délai de présentation — tout le parcours d'inscription.
     * Une PRÉINSCRIPTION n'en fait pas partie : elle est reçue, puis acceptée
     * ou refusée, et rien d'autre ne lui arrive.
     */
    private static function estInscription(array $dossier): bool
    {
        return Dossier::estInscription($dossier);
    }

    /**
     * Refus opposé à une décision d'inscription demandée sur une préinscription.
     *
     * Ces gestes sont déjà absents de l'écran, mais l'écran n'est pas une
     * barrière : un POST forgé, un onglet resté ouvert sur une fiche devenue
     * obsolète, et la préinscription partirait en validation à 50 % avec une
     * date limite, puis en commission. Le refus est ici, au seul endroit qui
     * décide.
     */
    private static function refusPreinscription(string $geste): array
    {
        return self::echec(
            'Ce dossier est une préinscription : ' . $geste . ' ne s’applique qu’à une inscription. '
            . 'Une préinscription ne se décide pas — le candidat doit d’abord déposer '
            . 'son dossier d’inscription.'
        );
    }

    /**
     * Validation sur pièces — le dossier devient recevable (spec §8.3).
     *
     * @return array{ok:bool, message:string, dossier:?array}
     */
    public static function valider50(
        int $dossierId,
        int $etablissementId,
        ?int $agentId,
        ?string $commentaire = null
    ): array {
        $modele  = new Dossier();
        $dossier = self::dossierDeLEtablissement($modele, $dossierId, $etablissementId);

        if ($dossier === null) {
            return self::echec('Dossier introuvable.');
        }

        // Une préinscription ne se valide pas : elle n'engage rien, ne demande
        // rien et donne droit à son attestation dès le dépôt. Il n'y a donc
        // aucune décision à prendre dessus.
        if (!self::estInscription($dossier)) {
            return self::refusPreinscription('la validation sur pièces');
        }

        $resultat = $modele->transition($dossierId, DossierStatut::VALIDE_50, 'etablissement', [
            'acteur_id'   => $agentId,
            'commentaire' => self::texteCourt($commentaire, 2000),
        ]);

        if (!$resultat['ok']) {
            return self::echec((string)$resultat['erreur']);
        }

        $apres  = $resultat['dossier'];
        $limite = !empty($apres['expire_le'])
            ? date('d/m/Y', strtotime((string)$apres['expire_le']))
            : null;

        // Le texte ne parle plus de « 50 % » : côté candidat, cette décision veut
        // dire que sa place est retenue et qu'il ne lui reste que le règlement
        // sur place. La date limite reste rappelée quand elle existe — la tâche
        // planifiée expire réellement le dossier passé ce délai.
        self::notifierCandidat($apres, 'dossier_valide_50', 'Votre dossier a été accepté',
            'L’établissement a accepté votre dossier. '
            . 'Vous êtes enregistré auprès de l’établissement et votre place est retenue. '
            . 'Il ne reste qu’une seule étape, le règlement de la scolarité, qui se fait '
            . 'uniquement sur place. Présentez-vous à l’établissement avec votre récépissé '
            . 'avant le début des cours, vous pouvez y aller au moment qui vous arrange, '
            . 'même à l’approche de la rentrée. Votre place reste retenue jusqu’à cette date.'
            . ($limite !== null ? ' Soit avant le ' . $limite . '.' : '')
            . self::suffixeCommentaire($commentaire)
        );

        return [
            'ok'      => true,
            'message' => 'Dossier validé à 50 %. Le candidat est invité à se présenter'
                . ($limite !== null ? ' avant le ' . $limite : '') . '.',
            'dossier' => $apres,
        ];
    }

    /**
     * Demande de pièce complémentaire (spec §8.2).
     * Le libellé des pièces attendues est obligatoire : « pièce manquante »
     * sans dire laquelle laisse le candidat sans action possible.
     *
     * @return array{ok:bool, message:string, dossier:?array}
     */
    public static function demanderPiece(
        int $dossierId,
        int $etablissementId,
        ?int $agentId,
        string $piecesAttendues
    ): array {
        $piecesAttendues = self::texteCourt($piecesAttendues, 1000) ?? '';

        if ($piecesAttendues === '') {
            return self::echec('Indiquez les pièces attendues : sans précision, le candidat ne peut rien faire.');
        }

        $modele  = new Dossier();
        $dossier = self::dossierDeLEtablissement($modele, $dossierId, $etablissementId);

        if ($dossier === null) {
            return self::echec('Dossier introuvable.');
        }

        // Une préinscription ne comporte aucune pièce justificative : le
        // formulaire n'en demande pas et le candidat n'a nulle part où en
        // déposer. Lui en réclamer une le laisserait bloqué.
        if (!self::estInscription($dossier)) {
            return self::refusPreinscription('la demande de pièce complémentaire');
        }

        $resultat = $modele->transition($dossierId, DossierStatut::PIECE_MANQUANTE, 'etablissement', [
            'acteur_id'        => $agentId,
            'pieces_attendues' => $piecesAttendues,
            'commentaire'      => $piecesAttendues,
            'evenement'        => Journal::PIECE_DEMANDEE,
        ]);

        if (!$resultat['ok']) {
            return self::echec((string)$resultat['erreur']);
        }

        self::notifierCandidat($resultat['dossier'], 'piece_demandee', 'Pièce complémentaire demandée',
            self::nomEtablissement($etablissementId) . ' demande une pièce complémentaire : '
            . $piecesAttendues . '. Téléversez-la depuis votre espace pour poursuivre.'
        );

        return ['ok' => true, 'message' => 'Demande envoyée au candidat.', 'dossier' => $resultat['dossier']];
    }

    /**
     * Rejet motivé. Le motif est obligatoire (spec §4.1) : un refus sans motif
     * est indéfendable en cas de litige, et inutilisable par le candidat.
     *
     * @return array{ok:bool, message:string, dossier:?array}
     */
    public static function rejeter(
        int $dossierId,
        int $etablissementId,
        ?int $agentId,
        string $motif
    ): array {
        $motif = self::texteCourt($motif, 2000) ?? '';

        if (mb_strlen($motif) < 10) {
            return self::echec('Le motif du refus est obligatoire et doit être explicite (10 caractères minimum).');
        }

        $modele  = new Dossier();
        $dossier = self::dossierDeLEtablissement($modele, $dossierId, $etablissementId);

        if ($dossier === null) {
            return self::echec('Dossier introuvable.');
        }

        // Refuser suppose d'avoir examiné : une préinscription n'est pas
        // examinée. Elle ne se refuse pas plus qu'elle ne se valide.
        if (!self::estInscription($dossier)) {
            return self::refusPreinscription('le rejet');
        }

        $resultat = $modele->transition($dossierId, DossierStatut::REJETE, 'etablissement', [
            'acteur_id' => $agentId,
            'motif'     => $motif,
        ]);

        if (!$resultat['ok']) {
            return self::echec((string)$resultat['erreur']);
        }

        self::notifierCandidat($resultat['dossier'], 'dossier_rejete', 'Votre dossier n’a pas été retenu',
            'Motif : ' . $motif
        );

        return ['ok' => true, 'message' => 'Dossier rejeté. Le candidat a été informé du motif.', 'dossier' => $resultat['dossier']];
    }

    /**
     * Validation à 100 % — inscription définitive (spec §8.4).
     *
     * C'est le point le plus sensible du système : depuis que la scolarité se
     * règle au guichet, c'est le SEUL endroit où la plateforme capte encore
     * l'information financière. Sans les montants, la commission ne peut plus
     * être calculée, et la trace de l'opération est perdue définitivement.
     * D'où le refus catégorique de la transition sans données financières
     * (garde-fou porté par Dossier::transition()).
     *
     * L'ensemble — transition, gel du taux, commission — tient dans une seule
     * transaction : un dossier à 100 % sans sa commission serait une perte
     * comptable silencieuse.
     *
     * @param array{
     *   montant_scolarite_annuelle:mixed, montant_encaisse_initial:mixed,
     *   mode_paiement:string, reference_paiement?:?string, date_paiement:string
     * } $finances
     *
     * @return array{ok:bool, message:string, dossier:?array, erreurs:array<string,string>}
     */
    public static function valider100(
        int $dossierId,
        int $etablissementId,
        ?int $agentId,
        array $finances,
        ?string $commentaire = null
    ): array {
        $modele  = new Dossier();
        $dossier = self::dossierDeLEtablissement($modele, $dossierId, $etablissementId);

        if ($dossier === null) {
            return self::echec('Dossier introuvable.') + ['erreurs' => []];
        }

        // Verrou de nature. Une préinscription validée à 100 % créerait une
        // commission sur une scolarité que personne n'a réglée, et délivrerait
        // une attestation d'inscription à un candidat qui n'a déposé aucun
        // dossier. C'est la seule barrière : l'écran ne montre pas ce bouton,
        // mais l'écran ne décide de rien.
        if (!self::estInscription($dossier)) {
            return self::refusPreinscription('la validation à 100 %') + ['erreurs' => []];
        }

        // ── Les montants ne se saisissent plus au guichet ────────────────────
        // Ils sont fixés par le super administrateur : grille tarifaire de la
        // formation, puis fiche de l'établissement. Le tarif retenu dépend de
        // TROIS choses lues sur le dossier — la formation visée, le niveau
        // d'entrée et la situation de bourse déclarée. L'agent constate un
        // règlement, il ne fixe pas un tarif : ce qu'il tape ici est donc
        // ÉCRASÉ par la configuration.
        //
        // Le repli sur la saisie n'existe que si aucun tarif n'est configuré
        // nulle part — un guichet bloqué serait pire qu'une saisie manuelle.
        $etab   = TarifCycle::etablissement($etablissementId);
        $tarif  = TarifCycle::detail($dossier, $etab);

        if ($tarif['montant'] !== null) {
            $finances['montant_scolarite_annuelle'] = $tarif['montant'];
        }

        // Frais d'inscription : tarif du cycle ET de la situation de bourse,
        // avec repli sur le tarif public. Nature distincte de la scolarité —
        // ils ne sont jamais imputés dessus (cf. validerFinances). Pour un
        // BOURSIER ils portent en revanche la commission : les écraser par la
        // configuration n'est donc plus seulement une commodité, c'est ce qui
        // garantit que la base de facturation ne dépend pas d'une frappe.
        if ($tarif['frais_inscription'] !== null) {
            $finances['montant_frais_inscription'] = $tarif['frais_inscription'];
        }

        $controle = self::validerFinances($finances);
        if ($controle['erreurs'] !== []) {
            return [
                'ok'      => false,
                'message' => 'Les informations de paiement sont incomplètes.',
                'dossier' => null,
                'erreurs' => $controle['erreurs'],
            ];
        }

        $db      = Database::getInstance()->getConnection();
        $externe = $db->inTransaction();

        if (!$externe) {
            $db->beginTransaction();
        }

        try {
            $resultat = $modele->transition($dossierId, DossierStatut::VALIDE_100, 'etablissement', [
                'acteur_id'   => $agentId,
                'commentaire' => self::texteCourt($commentaire, 2000),
                'finances'    => $controle['valeurs'],
            ]);

            if (!$resultat['ok']) {
                throw new \RuntimeException((string)$resultat['erreur']);
            }

            $apres = $resultat['dossier'];

            // Commission calculée sur le taux GELÉ dans le dossier au moment
            // même de la transition (spec §10.2) — jamais sur le taux courant
            // de l'établissement, qui peut changer demain.
            CommissionDossier::generer($apres, $agentId, $db);

            // Provenance du tarif retenu : sans cette trace, un établissement
            // qui conteste sa commission n'a rien à opposer, et le support non
            // plus. Hors transaction critique : une trace ne bloque jamais.
            Journal::dossier(
                Journal::DOSSIER_VALIDE_100,
                $apres,
                [
                    'cycle'            => $tarif['cycle'],
                    'niveau'           => $tarif['niveau'],
                    'tarif_source'     => $tarif['source'],
                    'scolarite'        => $controle['valeurs']['montant_scolarite_annuelle'],
                    'frais_inscription'=> $controle['valeurs']['montant_frais_inscription'],
                    'frais_source'     => $tarif['frais_inscription_source'],
                    // Nature ET montant : le montant seul ne dit pas si la
                    // commission porte sur la scolarité ou sur l'inscription,
                    // et c'est précisément la question qu'on posera au support.
                    'base_nature'      => $tarif['base_commission'],
                    'base_commission'  => $tarif['montant_base_commission'],
                    'statut_bourse'    => $apres['statut_bourse'] ?? null,
                ],
                ['acteur_id' => $agentId, 'acteur_role' => 'etablissement']
            );

            if (!$externe) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if (!$externe && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[DecisionEtablissement] Validation 100 % #' . $dossierId . ' : ' . $e->getMessage());

            return [
                'ok'      => false,
                'message' => $e instanceof \RuntimeException
                    ? $e->getMessage()
                    : 'L’inscription n’a pas pu être validée. Réessayez.',
                'dossier' => null,
                'erreurs' => [],
            ];
        }

        // Hors transaction : l'attestation ne doit pas pouvoir annuler
        // l'inscription. Si elle échoue, la tâche de rattrapage repassera.
        $attestationDelivree = self::delivrerAttestation($dossierId);

        self::notifierCandidat($apres, 'inscription_validee', 'Vous êtes inscrit',
            'Félicitations ! Votre inscription à ' . self::nomEtablissement($etablissementId)
            . ' est définitive.'
            . ($attestationDelivree
                ? ' Votre attestation d’inscription est disponible dans votre espace.'
                : ' Votre attestation d’inscription sera disponible dans quelques minutes.')
            // Le reçu est rendu à la demande : il est disponible dès maintenant,
            // sans attendre le moindre traitement différé.
            . ' Votre reçu de scolarité y est également téléchargeable.'
            . self::suffixeCommentaire($commentaire)
        );

        return [
            'ok'      => true,
            'message' => 'Inscription validée à 100 %.'
                . ($attestationDelivree ? ' Attestation délivrée au candidat.' : ' Attestation en cours de génération.'),
            'dossier' => $apres,
            'erreurs' => [],
        ];
    }

    /**
     * Prolongation du délai de présentation (spec §8.2).
     * Ne change pas le statut : le dossier reste à 50 %, seule la date limite
     * recule. Utile quand un candidat prévient qu'il ne peut pas venir à temps.
     *
     * @return array{ok:bool, message:string, dossier:?array}
     */
    public static function prolonger(
        int $dossierId,
        int $etablissementId,
        ?int $agentId,
        int $jours
    ): array {
        if ($jours < 1 || $jours > self::PROLONGATION_MAX_JOURS) {
            return self::echec('La prolongation doit être comprise entre 1 et ' . self::PROLONGATION_MAX_JOURS . ' jours.');
        }

        $modele  = new Dossier();
        $dossier = self::dossierDeLEtablissement($modele, $dossierId, $etablissementId);

        if ($dossier === null) {
            return self::echec('Dossier introuvable.');
        }

        if (!self::estInscription($dossier)) {
            return self::refusPreinscription('la prolongation du délai de présentation');
        }

        if (DossierStatut::normaliser((string)$dossier['statut']) !== DossierStatut::VALIDE_50) {
            return self::echec('Seul un dossier validé à 50 % peut être prolongé.');
        }

        // La nouvelle date part de la limite courante si elle est encore à
        // venir, sinon d'aujourd'hui : prolonger un dossier déjà en retard doit
        // laisser un délai réel, pas une date déjà passée.
        $base = !empty($dossier['expire_le']) && strtotime((string)$dossier['expire_le']) > time()
            ? new \DateTimeImmutable((string)$dossier['expire_le'])
            : new \DateTimeImmutable();

        $nouvelle = $base->modify('+' . $jours . ' days');

        try {
            Database::getInstance()->getConnection()->prepare(
                'UPDATE dossiers_inscription SET expire_le = ? WHERE id = ? AND etablissement_id = ?'
            )->execute([$nouvelle->format('Y-m-d H:i:s'), $dossierId, $etablissementId]);
        } catch (\Throwable $e) {
            error_log('[DecisionEtablissement] Prolongation échouée #' . $dossierId . ' : ' . $e->getMessage());

            return self::echec('La prolongation n’a pas pu être enregistrée.');
        }

        Journal::dossier(
            Journal::DOSSIER_PROLONGE,
            $dossier,
            [
                'jours'          => $jours,
                'ancienne_date'  => $dossier['expire_le'] ?? null,
                'nouvelle_date'  => $nouvelle->format('Y-m-d H:i:s'),
            ],
            ['acteur_id' => $agentId, 'acteur_role' => 'etablissement']
        );

        self::notifierCandidat($dossier, 'delai_prolonge', 'Votre délai a été prolongé',
            'Vous avez jusqu’au ' . $nouvelle->format('d/m/Y')
            . ' pour vous présenter à ' . self::nomEtablissement($etablissementId) . '.'
        );

        return [
            'ok'      => true,
            'message' => 'Délai prolongé jusqu’au ' . $nouvelle->format('d/m/Y') . '.',
            'dossier' => $modele->findById($dossierId),
        ];
    }

    /**
     * Annulation de la dernière décision — le droit à l'erreur du guichet.
     *
     * Un agent qui valide le mauvais dossier à 50 %, rejette par méprise ou
     * enregistre un règlement sur le mauvais candidat doit pouvoir défaire son
     * geste lui-même. Sans cela la correction passe par le support, et la
     * donnée reste fausse en attendant — y compris la commission.
     *
     * Ce n'est PAS une transition ordinaire : la machine à états la traite par
     * sa table de retours (DossierStatut::RETOURS), et le modèle efface les
     * jalons posés par la décision annulée (Dossier::construireRetour).
     *
     * L'annulation d'une inscription à 100 % enchaîne trois effets
     * indissociables, dans une seule transaction :
     *   1. retour du dossier à 50 % ;
     *   2. annulation de la commission — sinon la plateforme facture une
     *      inscription qui n'existe plus ;
     *   3. retrait de l'attestation d'inscription — un document qui certifie
     *      une inscription annulée est un faux en circulation.
     *
     * Une commission déjà RÉGLÉE bloque l'annulation : on ne défait pas une
     * opération comptable close depuis le back-office d'un établissement.
     *
     * @return array{ok:bool, message:string, dossier:?array}
     */
    public static function annuler(
        int $dossierId,
        int $etablissementId,
        ?int $agentId,
        ?string $motif = null
    ): array {
        $modele  = new Dossier();
        $dossier = self::dossierDeLEtablissement($modele, $dossierId, $etablissementId);

        if ($dossier === null) {
            return self::echec('Dossier introuvable.');
        }

        // Aucune décision n'est prise sur une préinscription : il n'y a donc
        // rien à annuler. Les lignes héritées d'un ancien parcours restent
        // lisibles, mais l'établissement n'y revient pas.
        if (!self::estInscription($dossier)) {
            return self::refusPreinscription('l’annulation d’une décision');
        }

        $etat   = DossierStatut::normaliser((string)$dossier['statut']);
        $retour = DossierStatut::retour($etat);

        if ($retour === null) {
            return self::echec('Aucune décision annulable sur ce dossier (' . DossierStatut::libelle($etat) . ').');
        }

        $motif = self::texteCourt($motif, 2000);

        if ($etat === DossierStatut::VALIDE_100) {
            $commission = CommissionDossier::pourDossier($dossierId);

            if ($commission !== null) {
                $statutCommission = (string)$commission['statut'];

                if ($statutCommission === CommissionDossier::ABANDONNEE) {
                    return self::echec(
                        'Cet étudiant est déclaré en abandon d’année par Connect’Academia : '
                        . 'son inscription ne peut plus être annulée ici. Contactez l’administrateur '
                        . 'de la plateforme pour faire lever l’abandon.'
                    );
                }

                // Une tranche encaissée est une opération comptable close, même
                // si les deux suivantes ne le sont pas.
                if ($statutCommission === CommissionDossier::REGLEE
                    || CommissionDossier::montantEncaisse((int)$commission['id']) > 0) {
                    return self::echec(
                        'Une partie au moins de la commission de ce dossier est déjà encaissée : '
                        . 'l’inscription ne peut plus être annulée depuis le back-office. '
                        . 'Contactez l’administrateur de la plateforme.'
                    );
                }
            }
        }

        $db      = Database::getInstance()->getConnection();
        $externe = $db->inTransaction();

        if (!$externe) {
            $db->beginTransaction();
        }

        try {
            $resultat = $modele->transition($dossierId, $retour, 'etablissement', [
                'retour'      => true,
                'acteur_id'   => $agentId,
                'commentaire' => $motif,
                'evenement'   => Journal::DECISION_ANNULEE,
                'donnees'     => ['decision_annulee' => $etat],
            ]);

            if (!$resultat['ok']) {
                throw new \RuntimeException((string)$resultat['erreur']);
            }

            if ($etat === DossierStatut::VALIDE_100) {
                CommissionDossier::annuler($dossierId, $agentId);
                self::retirerAttestation($dossierId, $db);
            }

            if (!$externe) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if (!$externe && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[DecisionEtablissement] Annulation #' . $dossierId . ' : ' . $e->getMessage());

            return self::echec(
                $e instanceof \RuntimeException
                    ? $e->getMessage()
                    : 'L’annulation n’a pas pu être enregistrée. Réessayez.'
            );
        }

        $apres = $resultat['dossier'] ?? $modele->findById($dossierId);

        // Le candidat a été notifié de la décision : il doit l'être de son
        // annulation. Un espace qui affiche « vous êtes inscrit » puis change
        // d'avis sans un mot est le pire des deux mondes.
        self::notifierCandidat(
            $apres,
            'decision_annulee',
            self::TITRES_ANNULATION[$etat] ?? 'Votre dossier a été mis à jour',
            self::messageAnnulation($etat, self::nomEtablissement($etablissementId))
            . self::suffixeCommentaire($motif)
        );

        Journal::dossier(
            Journal::DECISION_ANNULEE,
            $apres ?? $dossier,
            ['decision_annulee' => $etat, 'statut_rendu' => $retour, 'motif' => $motif],
            ['acteur_id' => $agentId, 'acteur_role' => 'etablissement']
        );

        return [
            'ok'      => true,
            'message' => self::MESSAGES_ANNULATION[$etat] ?? 'Décision annulée.',
            'dossier' => $apres,
        ];
    }

    /**
     * Retire l'attestation d'inscription d'un dossier qui n'est plus inscrit,
     * ainsi que la ligne `inscriptions` qui la sous-tend.
     *
     * La ligne est SUPPRIMÉE et non marquée : le numéro d'attestation est
     * réservé par une contrainte d'unicité, et le laisser en place ferait
     * échouer la réémission le jour où le dossier repasse à 100 %. Le fichier
     * déposé sur B2 n'est pas effacé — il n'est plus référencé, et la trace de
     * son existence reste dans le journal.
     */
    private static function retirerAttestation(int $dossierId, PDO $db): void
    {
        $db->prepare('DELETE FROM attestations_inscription WHERE dossier_id = ?')->execute([$dossierId]);
        $db->prepare('DELETE FROM inscriptions WHERE dossier_id = ?')->execute([$dossierId]);
    }

    /** Titre de la notification envoyée au candidat, par décision annulée. */
    private const TITRES_ANNULATION = [
        DossierStatut::VALIDE_50       => 'Votre dossier est de nouveau en cours d’analyse',
        DossierStatut::PIECE_MANQUANTE => 'La demande de pièce a été retirée',
        DossierStatut::REJETE          => 'Votre dossier est réexaminé',
        DossierStatut::VALIDE_100      => 'Votre inscription a été suspendue',
    ];

    /** Message de retour affiché à l'agent, par décision annulée. */
    private const MESSAGES_ANNULATION = [
        DossierStatut::VALIDE_50       => 'Validation à 50 % annulée. Le dossier est de nouveau à analyser.',
        DossierStatut::PIECE_MANQUANTE => 'Demande de pièce annulée. Le dossier est de nouveau à analyser.',
        DossierStatut::REJETE          => 'Rejet annulé. Le dossier est de nouveau à analyser.',
        DossierStatut::VALIDE_100      => 'Inscription à 100 % annulée. Commission annulée et attestation retirée.',
    ];

    private static function messageAnnulation(string $etat, string $etablissement): string
    {
        return match ($etat) {
            DossierStatut::VALIDE_50       => $etablissement . ' a annulé la validation de votre dossier. '
                . 'Il est de nouveau en cours d’analyse : inutile de vous déplacer pour le moment.',
            DossierStatut::PIECE_MANQUANTE => $etablissement . ' a retiré sa demande de pièce complémentaire. '
                . 'Vous n’avez rien à fournir.',
            DossierStatut::REJETE          => $etablissement . ' a annulé le refus de votre dossier. '
                . 'Il est de nouveau en cours d’analyse.',
            DossierStatut::VALIDE_100      => $etablissement . ' a annulé votre inscription définitive. '
                . 'Votre dossier revient à l’étape précédente et votre attestation n’est plus valable. '
                . 'Rapprochez-vous de l’établissement.',
            default                        => $etablissement . ' a modifié l’état de votre dossier.',
        };
    }

    /**
     * Relance d'un candidat qui n'a pas fourni la pièce demandée (spec §8.2).
     * N'entraîne aucune transition : c'est un rappel.
     *
     * @return array{ok:bool, message:string, dossier:?array}
     */
    public static function relancer(int $dossierId, int $etablissementId, ?int $agentId): array
    {
        $modele  = new Dossier();
        $dossier = self::dossierDeLEtablissement($modele, $dossierId, $etablissementId);

        if ($dossier === null) {
            return self::echec('Dossier introuvable.');
        }

        // Aucune pièce n'est jamais demandée sur une préinscription : la
        // relancer reviendrait à réclamer un document qui n'existe pas.
        if (!self::estInscription($dossier)) {
            return self::refusPreinscription('la relance pour pièce manquante');
        }

        $attendues = trim((string)($dossier['pieces_attendues'] ?? ''));

        self::notifierCandidat($dossier, 'relance_piece', 'Rappel : pièce en attente',
            self::nomEtablissement($etablissementId) . ' attend toujours '
            . ($attendues !== '' ? '« ' . $attendues . ' »' : 'une pièce complémentaire')
            . ' pour poursuivre l’examen de votre dossier.'
        );

        Journal::dossier(
            'dossier_relance',
            $dossier,
            ['pieces_attendues' => $attendues],
            ['acteur_id' => $agentId, 'acteur_role' => 'etablissement']
        );

        return ['ok' => true, 'message' => 'Relance envoyée au candidat.', 'dossier' => $dossier];
    }

    // ── Validation du formulaire financier ───────────────────────────────────

    /**
     * Contrôle des montants et du mode de paiement (spec §8.4).
     *
     * Le formulaire désactive son bouton tant que les champs obligatoires sont
     * vides, mais cela ne vaut rien : la validation est refaite ici, et la
     * machine à états refuse de son côté une transition sans finances.
     *
     * @return array{erreurs:array<string,string>, valeurs:array<string,mixed>}
     */
    public static function validerFinances(array $finances): array
    {
        $erreurs = [];

        $scolarite = self::montant($finances['montant_scolarite_annuelle'] ?? null);
        if ($scolarite === null || $scolarite <= 0) {
            $erreurs['montant_scolarite_annuelle'] = 'Indiquez le montant de la scolarité annuelle.';
        } elseif ($scolarite > 99_999_999.99) {
            $erreurs['montant_scolarite_annuelle'] = 'Montant hors limites.';
        }

        // Facultatif : un candidat peut être inscrit sans avoir encore versé un
        // franc (échéancier propre à l'établissement). Vide = 0, et le reçu
        // n'affiche alors aucun versement plutôt qu'un montant inventé.
        $encaisseBrut = $finances['montant_encaisse_initial'] ?? null;
        $encaisse     = ($encaisseBrut === null || $encaisseBrut === '')
            ? 0.0
            : self::montant($encaisseBrut);

        if ($encaisse === null || $encaisse < 0) {
            $erreurs['montant_encaisse_initial'] = 'Montant encaissé illisible (laissez vide si aucun versement).';
        } elseif ($scolarite !== null && $encaisse > $scolarite) {
            // Pas bloquant en soi, mais presque toujours une faute de frappe :
            // on préfère faire confirmer plutôt que fausser la comptabilité.
            $erreurs['montant_encaisse_initial'] = 'Le montant encaissé dépasse la scolarité annuelle. Vérifiez la saisie.';
        }

        // Frais d'inscription : facultatifs (tous les établissements n'en
        // prennent pas), mais JAMAIS confondus avec la scolarité. Ils ne sont
        // pas bornés par le montant annuel — ils n'en font pas partie, et
        // n'entrent pas dans la base de calcul de la commission.
        $fraisInscription = self::montant($finances['montant_frais_inscription'] ?? null);
        if (($finances['montant_frais_inscription'] ?? '') !== '' && $fraisInscription === null) {
            $erreurs['montant_frais_inscription'] = 'Montant des frais d’inscription illisible.';
        } elseif ($fraisInscription !== null && $fraisInscription < 0) {
            $erreurs['montant_frais_inscription'] = 'Les frais d’inscription ne peuvent pas être négatifs.';
        } elseif ($fraisInscription !== null && $fraisInscription > 99_999_999.99) {
            $erreurs['montant_frais_inscription'] = 'Montant hors limites.';
        }

        $mode = (string)($finances['mode_paiement'] ?? '');
        if (!in_array($mode, ['especes', 'mobile_money', 'virement', 'cheque', 'autre'], true)) {
            $erreurs['mode_paiement'] = 'Choisissez un mode de paiement.';
        }

        $dateBrute = trim((string)($finances['date_paiement'] ?? ''));
        $date      = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateBrute);

        if ($date === false || $date->format('Y-m-d') !== $dateBrute) {
            $erreurs['date_paiement'] = 'Indiquez la date du paiement.';
        } elseif ($date > new \DateTimeImmutable('today')) {
            $erreurs['date_paiement'] = 'La date de paiement ne peut pas être dans le futur.';
        }

        $reference = self::texteCourt($finances['reference_paiement'] ?? null, 80);

        return [
            'erreurs' => $erreurs,
            'valeurs' => [
                'montant_scolarite_annuelle' => $scolarite ?? 0.0,
                'montant_encaisse_initial'   => $encaisse ?? 0.0,
                // null (et non 0) quand rien n'est saisi : le reçu masque alors
                // la ligne au lieu d'afficher « 0 XAF », qui laisserait croire
                // que l'établissement ne prend pas de frais d'inscription.
                'montant_frais_inscription'  => $fraisInscription,
                'mode_paiement'              => $mode,
                'reference_paiement'         => $reference,
                'date_paiement'              => $dateBrute,
            ],
        ];
    }

    /** Modes de paiement proposés au guichet (spec §8.4). */
    public static function modesPaiement(): array
    {
        return [
            'especes'      => 'Espèces',
            'mobile_money' => 'Mobile money',
            'virement'     => 'Virement bancaire',
            'cheque'       => 'Chèque',
            'autre'        => 'Autre',
        ];
    }

    /**
     * Frais d'inscription applicables au dossier : tarif du cycle et de la
     * situation de bourse, avec repli sur le tarif public affiché.
     *
     * Ce sont exactement les montants que le candidat aurait réglés en ligne si
     * l'établissement encaissait encore sur la plateforme — la cohérence entre
     * la vitrine et le guichet n'est pas négociable.
     *
     * Le dossier est facultatif pour rester appelable depuis un écran qui n'en
     * a pas ; sans lui la réponse porte sur le cycle Licence non boursier.
     */
    public static function fraisInscriptionPreremplis(?array $etab, ?array $dossier = null): ?float
    {
        return TarifCycle::fraisInscription($etab, $dossier);
    }

    /**
     * Montant de scolarité APPLICABLE au dossier — plus une proposition, une
     * règle. Il vient de la grille de la formation puis de la fiche
     * établissement, selon le niveau d'entrée et la situation de bourse :
     * l'établissement n'a rien à ressaisir ni à confirmer au guichet.
     *
     * @see TarifCycle pour l'ordre exact de recherche et les replis.
     */
    public static function scolaritePreremplie(array $dossier): ?float
    {
        return TarifCycle::scolariteApplicable($dossier);
    }

    /**
     * Détail du tarif retenu — alimente le récapitulatif affiché au guichet.
     *
     * @return array{cycle:string, cycle_libelle:string, montant:?float,
     *               source:string, tarif_boursier:?float, configure:bool}
     */
    public static function tarifApplicable(array $dossier, ?array $etab = null): array
    {
        return TarifCycle::detail($dossier, $etab);
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /**
     * Charge le dossier en vérifiant qu'il appartient bien à l'établissement.
     * Barrière de cloisonnement : un identifiant numérique est énumérable, on
     * ne se repose donc jamais sur sa difficulté à deviner (spec §15.1).
     *
     * @return array<string,mixed>|null
     */
    private static function dossierDeLEtablissement(Dossier $modele, int $dossierId, int $etablissementId): ?array
    {
        $dossier = $modele->findById($dossierId);

        if ($dossier === null || (int)$dossier['etablissement_id'] !== $etablissementId) {
            if ($dossier !== null) {
                \Core\TenantGuard::denyEtab($dossier, $etablissementId, 'gestionnaire.decision', $dossierId);
            }

            return null;
        }

        return $dossier;
    }

    private static function notifierCandidat(?array $dossier, string $type, string $titre, string $message): void
    {
        if ($dossier === null || empty($dossier['eleve_id'])) {
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

    /** Attestation d'inscription — jamais bloquante (spec §5.4, même principe). */
    private static function delivrerAttestation(int $dossierId): bool
    {
        try {
            return AttestationDossierService::generer($dossierId) !== null;
        } catch (\Throwable $e) {
            error_log('[DecisionEtablissement] Attestation #' . $dossierId . ' : ' . $e->getMessage());

            return false;
        }
    }

    private static function nomEtablissement(int $etablissementId): string
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT nom FROM etablissements WHERE id = ?'
            );
            $st->execute([$etablissementId]);

            return (string)($st->fetchColumn() ?: 'l’établissement');
        } catch (\Throwable) {
            return 'l’établissement';
        }
    }

    private static function suffixeCommentaire(?string $commentaire): string
    {
        $commentaire = self::texteCourt($commentaire, 2000);

        return $commentaire === null ? '' : ' Message de l’établissement : ' . $commentaire;
    }

    /** Normalise un texte libre : espaces réduits, caractères de contrôle ôtés. */
    private static function texteCourt(?string $valeur, int $max): ?string
    {
        if ($valeur === null) {
            return null;
        }

        $propre = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valeur) ?? '';
        $propre = trim(preg_replace('/[ \t]+/', ' ', $propre) ?? '');

        return $propre === '' ? null : mb_substr($propre, 0, $max);
    }

    /**
     * Convertit une saisie de montant en flottant.
     * Tolère « 750 000 », « 750000,50 » et « 750000.50 » : au guichet, la
     * saisie vient d'un clavier de téléphone, pas d'un tableur.
     */
    private static function montant(mixed $valeur): ?float
    {
        if (is_float($valeur) || is_int($valeur)) {
            return (float)$valeur;
        }
        if (!is_string($valeur)) {
            return null;
        }

        $propre = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim($valeur));
        $propre = str_replace(',', '.', $propre);

        if ($propre === '' || preg_match('/^\d+(\.\d{1,2})?$/', $propre) !== 1) {
            return null;
        }

        return (float)$propre;
    }

    /** @return array{ok:false, message:string, dossier:null} */
    private static function echec(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'dossier' => null];
    }
}
