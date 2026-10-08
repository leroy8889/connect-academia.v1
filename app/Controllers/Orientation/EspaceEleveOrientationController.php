<?php
declare(strict_types=1);

namespace Controllers\Orientation;

use Core\{Response, Session, Hashid, TenantGuard};
use Models\Orientation\Dossier;
use Models\Orientation\Etablissement;
use Models\Orientation\NotificationOrientation;
use Models\User;
use Services\Orientation\{DocumentStorage, RecepisseService, AttestationService, PdfStream};

/**
 * Espace personnel Orientation — commun aux élèves de la plateforme
 * et aux comptes visiteur (parents, étudiants, candidats libres).
 *
 * App-shell à onglets (maquette compte/Compte.html) : vue d'ensemble,
 * profil, préinscriptions, inscriptions, paiements, reçus, documents,
 * notifications. Toutes les vues rendent dans le layout `espace-eleve`.
 */
class EspaceEleveOrientationController
{
    private Dossier $dossierModel;
    private NotificationOrientation $notifModel;
    private ?DocumentStorage $storage = null;
    private bool $storageFailed = false;

    public function __construct()
    {
        $this->dossierModel = new Dossier();
        $this->notifModel   = new NotificationOrientation();
    }

    // ── Contexte partagé (sidebar, compteurs, buckets) ────────────────────────

    /**
     * Charge l'utilisateur, ses dossiers et les compteurs de la sidebar.
     * Les brouillons (étape 1 d'inscription sauvegardée, pas encore soumis)
     * sont rangés avec les inscriptions : ce sont des dossiers d'inscription
     * à reprendre, jamais des préinscriptions (celles-ci sont soumises d'un bloc).
     */
    private function contexte(): array
    {
        $userId = (int)Session::userId();
        $user   = (new User())->findById($userId) ?? [];
        $email  = $user['email'] ?? '';

        $dossiers   = $this->dossierModel->findByUserContext($userId, $email);
        $documents  = $this->dossierModel->findDocumentsByUserContext($userId, $email);
        $nonLues    = $this->notifModel->countUnread($userId);

        // Une inscription est un PROCESSUS : le dossier appartient à l'onglet
        // « Mes inscriptions » dès l'étape 1, et y reste quelle que soit
        // l'issue. La règle est celle du modèle, partagée avec la console
        // d'administration (Dossier::estInscription).
        $preins = [];
        $inscs  = [];
        foreach ($dossiers as $d) {
            if (Dossier::estInscription($d)) {
                $inscs[] = $d;
            } else {
                $preins[] = $d;
            }
        }

        // Dossier "actif" : le plus récent non refusé, inscription en priorité.
        $actif = null;
        foreach ($inscs as $d) {
            if (($d['statut'] ?? '') !== 'refuse') { $actif = $d; break; }
        }
        if (!$actif) {
            foreach ($preins as $d) {
                if (($d['statut'] ?? '') !== 'refuse') { $actif = $d; break; }
            }
        }
        if (!$actif && !empty($dossiers)) {
            $actif = $dossiers[0];
        }

        return [
            'userId'    => $userId,
            'user'      => $user,
            'email'     => $email,
            'dossiers'  => $dossiers,
            'preins'    => $preins,
            'inscs'     => $inscs,
            'documents' => $documents,
            'actif'     => $actif,
            'navCounts' => [
                'preinscriptions' => count($preins),
                'inscriptions'    => count($inscs),
                'documents'       => count($documents),
                'notifications'   => $nonLues,
                'dossier_actif'   => $actif !== null,
            ],
        ];
    }

    /** URL signée B2 silencieuse — null si stockage indisponible ou clé vide. */
    private function signedUrl(?string $cle): ?string
    {
        if (!$cle || $this->storageFailed) {
            return null;
        }
        try {
            $this->storage ??= new DocumentStorage();
            return $this->storage->signedUrl($cle, 10);
        } catch (\Throwable $e) {
            $this->storageFailed = true;
            error_log('[EspaceOrientation] B2 indisponible : ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Lien de consultation d'une pièce justificative.
     *
     * On ne distribue plus l'URL signée B2 : sa clé contient l'identifiant du
     * dossier (`dossiers/43/pieces/…`), visible dans la barre d'adresse, et le
     * lien reste valable hors session pendant sa durée de vie. Le fichier passe
     * désormais par l'application, derrière un jeton opaque et le contrôle de
     * propriété (voir document()).
     */
    private static function lienDocument(array $doc): ?string
    {
        $id = (int)($doc['id'] ?? 0);
        return $id > 0 ? url('/eleve/orientation/document/' . hid($id)) : null;
    }

    /**
     * Pièces justificatives d'un dossier d'inscription : requises par
     * l'établissement (niveau du dossier + communes) croisées avec les
     * documents déjà déposés. Retourne [['libelle','obligatoire','ok','doc'], …].
     */
    private function piecesPourDossier(array $dossier): array
    {
        if (!Dossier::estInscription($dossier)) {
            return [];
        }

        // Niveau d'entrée ET situation de bourse : la liste affichée doit être
        // exactement celle que le serveur exigera. Une pièce conditionnelle
        // montrée à qui elle ne concerne pas se lit comme un dossier incomplet
        // qu'on ne peut jamais compléter.
        $requises = $this->dossierModel->piecesAttenduesPourDossier($dossier);

        $deposes = $this->dossierModel->getDocumentsByDossier((int)$dossier['id']);

        // Une pièce remplacée n'est plus la pièce du dossier : elle reste en
        // base pour l'historique du refus, mais l'afficher à côté de sa
        // remplaçante ferait croire à deux documents concurrents.
        $perimes = \Services\Orientation\ComplementDossier::documentsRemplaces((int)$dossier['id']);

        // Identifiants des pièces réellement attendues pour ce dossier : sert
        // à distinguer un document rattaché au référentiel courant d'un
        // document orphelin (voir plus bas).
        $attendues = [];
        foreach ($requises as $p) {
            $attendues[(int)$p['id']] = true;
        }

        $parPiece = [];
        $horsListe = [];
        foreach ($deposes as $doc) {
            if (in_array((int)$doc['id'], $perimes, true)) {
                continue;
            }
            // Un document rattaché à une pièce qui n'est PLUS attendue (portée
            // modifiée, niveau du dossier changé, pièce supprimée du
            // référentiel) tombait dans un trou : ni dans $parPiece — la
            // boucle sur $requises ne l'aurait jamais lu — ni dans $horsListe,
            // réservé aux documents sans piece_requise_id. Il disparaissait
            // donc de l'écran, refus de l'établissement compris. On le range
            // ici avec les documents hors référentiel : le candidat voit
            // toujours ce qu'il a envoyé, et peut toujours le remplacer.
            if (!empty($doc['piece_requise_id']) && isset($attendues[(int)$doc['piece_requise_id']])) {
                // Ordonné par id croissant : la dernière rencontrée est la
                // version la plus récente.
                $parPiece[(int)$doc['piece_requise_id']] = $doc;
            } else {
                $horsListe[] = $doc;
            }
        }

        // Le dépôt n'est proposé que si le dossier y est réellement ouvert :
        // un bouton qui mène à un refus serveur est pire que pas de bouton.
        $ouvert  = \Services\Orientation\ComplementDossier::ouvertAuDepot($dossier);
        $bourse  = $dossier['statut_bourse'] ?? null;

        $rows = [];
        foreach ($requises as $p) {
            $doc         = $parPiece[(int)$p['id']] ?? null;
            $etat        = $doc !== null ? (string)($doc['statut'] ?? 'en_attente') : null;
            $obligatoire = \Services\Orientation\PieceDossier::estObligatoire($p, $bourse);

            $rows[] = [
                'libelle'     => $p['libelle'],
                'obligatoire' => $obligatoire,
                'ok'          => $doc !== null && $etat !== 'refuse',
                'doc'         => $doc,
                'piece_id'    => (int)$p['id'],
                'etat'        => $etat,
                'motif_refus' => $doc['motif_refus'] ?? null,
                // Remplaçable tant que l'établissement ne l'a pas acceptée.
                'deposable'   => $ouvert && $etat !== 'accepte',
            ];
        }

        // Pièces déposées hors référentiel courant : déposées au guichet, ou
        // rattachées à une pièce que l'établissement a depuis modifiée. Le
        // `piece_id` d'origine est conservé quand il existe, pour que le
        // remplacement retombe sur le bon emplacement du dossier.
        foreach ($horsListe as $doc) {
            $etat = (string)($doc['statut'] ?? 'en_attente');
            $rows[] = [
                'libelle'     => $doc['libelle'] ?: 'Document',
                'obligatoire' => false,
                'ok'          => $etat !== 'refuse',
                'doc'         => $doc,
                'piece_id'    => !empty($doc['piece_requise_id']) ? (int)$doc['piece_requise_id'] : null,
                'etat'        => $etat,
                'motif_refus' => $doc['motif_refus'] ?? null,
                'deposable'   => $ouvert && $etat !== 'accepte',
            ];
        }

        return $rows;
    }

    // ── Onglets ───────────────────────────────────────────────────────────────

    public function index(): void
    {
        $ctx = $this->contexte();

        // Pièces du dossier actif (aperçu "Pièces à finaliser")
        $pieces = $ctx['actif'] ? $this->piecesPourDossier($ctx['actif']) : [];

        // Fil d'activité dérivé des dossiers (toujours à jour, zéro dépendance)
        $feed = [];
        foreach ($ctx['dossiers'] as $d) {
            $etab = $d['etab_nom'] ?? '';
            $feed[] = [
                'date' => $d['date_creation'] ?? null,
                'icon' => Dossier::estInscription($d) ? 'briefcase' : 'fileText',
                'tone' => '',
                'txt'  => (Dossier::estInscription($d) ? 'Dossier d\'inscription créé pour <b>' : 'Préinscription créée pour <b>') . e($etab) . '</b>.',
                'cat'  => Dossier::estInscription($d) ? 'Inscription' : 'Préinscription',
            ];
            if (!empty($d['numero_recepisse'])) {
                $feed[] = [
                    'date' => $d['recepisse_date'] ?? $d['date_creation'],
                    'icon' => 'fileText', 'tone' => 'p',
                    // Un dossier d'inscription reçoit un RÉCÉPISSÉ DE DÉPÔT, pas
                    // l'attestation de préinscription du parcours court.
                    'txt'  => 'Votre <b>' . (Dossier::estInscription($d) ? 'récépissé de dépôt de dossier' : 'attestation de préinscription')
                              . '</b> (' . e($d['numero_recepisse']) . ') est disponible.',
                    'cat'  => Dossier::estInscription($d) ? 'Inscription' : 'Préinscription',
                ];
            }
            if (!empty($d['paiement_montant'])) {
                $feed[] = [
                    'date' => $d['paiement_date'] ?? $d['date_creation'],
                    'icon' => 'card', 'tone' => 'g',
                    'txt'  => 'Paiement de <b>' . number_format((int)$d['paiement_montant'], 0, ',', ' ') . ' XAF</b> confirmé — ' . e($etab) . '.',
                    'cat'  => 'Paiement',
                ];
            }
            if (!empty($d['numero_attestation']) && in_array($d['statut'] ?? '', ['inscrit','accepte'], true)) {
                $feed[] = [
                    'date' => $d['attestation_date'] ?? $d['date_creation'],
                    'icon' => 'checkCircle', 'tone' => 'g',
                    'txt'  => 'Inscription <b>validée</b> par ' . e($etab) . ' — attestation ' . e($d['numero_attestation']) . ' délivrée.',
                    'cat'  => 'Établissement',
                ];
            }
            if (($d['statut'] ?? '') === 'refuse') {
                $feed[] = [
                    'date' => $d['date_creation'],
                    'icon' => 'alert', 'tone' => 't',
                    'txt'  => 'Dossier <b>refusé</b> par ' . e($etab) . '.',
                    'cat'  => 'Établissement',
                ];
            }
        }
        usort($feed, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        $feed = array_slice($feed, 0, 6);

        Response::view('orientation/espace_eleve/dashboard', [
            'pageTitle' => "Mon compte — Espace orientation",
            'activeTab' => 'overview',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'actif'     => $ctx['actif'],
            'preins'    => $ctx['preins'],
            'inscs'     => $ctx['inscs'],
            'pieces'    => $pieces,
            'feed'      => $feed,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function preinscriptions(): void
    {
        $ctx = $this->contexte();

        // Les vues pointent la route interne /dossier/{jeton}/recepisse : plus
        // aucune URL signée B2 côté front (la clé B2 contient l'ID du dossier).
        $rows = $ctx['preins'];

        Response::view('orientation/espace_eleve/preinscriptions', [
            'pageTitle' => "Mes préinscriptions — Espace orientation",
            'activeTab' => 'preinscriptions',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'dossiers'  => $rows,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function inscriptions(): void
    {
        $ctx = $this->contexte();

        $rows = [];
        foreach ($ctx['inscs'] as $d) {
            $pieces = $this->piecesPourDossier($d);
            $oblig  = array_filter($pieces, fn($p) => $p['obligatoire']);
            $d['pieces']          = $pieces;
            $d['pieces_ok']       = count(array_filter($pieces, fn($p) => $p['ok']));
            $d['pieces_total']    = count($pieces);
            $d['oblig_manquantes'] = count(array_filter($oblig, fn($p) => !$p['ok']));
            $rows[] = $d;
        }

        Response::view('orientation/espace_eleve/inscriptions', [
            'pageTitle' => "Mes inscriptions — Espace orientation",
            'activeTab' => 'inscriptions',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'dossiers'  => $rows,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function paiements(): void
    {
        $ctx = $this->contexte();
        $paiements = $this->dossierModel->findPaiementsByUserContext($ctx['userId'], $ctx['email']);

        Response::view('orientation/espace_eleve/paiements', [
            'pageTitle' => "Mes paiements — Espace orientation",
            'activeTab' => 'paiements',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'paiements' => $paiements,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    /**
     * Onglet « Mes reçus » — tous les justificatifs de règlement, quelle que
     * soit la façon dont l'argent a été remis.
     *
     * Deux sources, une seule liste. Le candidat ne sait pas — et n'a pas à
     * savoir — que la plateforme distingue un paiement encaissé en ligne d'un
     * règlement remis au guichet : il a payé, il veut son reçu.
     *
     *  1. `paiements_orientation` + `recus_orientation` : parcours de paiement
     *     en ligne. Le PDF est archivé sur B2, servi par une URL signée.
     *  2. Dossiers validés à 100 % avec un montant saisi : reçu de règlement
     *     rendu à la demande (RecuInscriptionService), sans stockage.
     *
     * Tri par date décroissante, sources confondues.
     */
    public function recus(): void
    {
        $ctx       = $this->contexte();
        $paiements = $this->dossierModel->findPaiementsByUserContext($ctx['userId'], $ctx['email']);

        $recus = [];

        foreach ($paiements as $p) {
            if (empty($p['numero_recu'])) {
                continue;
            }

            $recus[] = [
                'type'        => 'en_ligne',
                'numero'      => (string)$p['numero_recu'],
                'etab_nom'    => $p['etab_nom'] ?? null,
                'montant'     => (float)($p['montant'] ?? 0),
                'mode'        => $p['mode'] ?? null,
                'date'        => $p['recu_date'] ?? $p['date_confirmation'] ?? null,
                'url'         => $this->signedUrl($p['recu_cle'] ?? null),
                'detail'      => null,
            ];
        }

        foreach ($ctx['dossiers'] as $d) {
            if (!\Services\Orientation\RecuInscriptionService::disponible($d)) {
                continue;
            }

            $frais    = $d['montant_frais_inscription'] ?? null;
            $scolarie = (float)($d['montant_encaisse_initial'] ?? 0);

            $recus[] = [
                'type'     => 'guichet',
                'numero'   => \Services\Orientation\RecuInscriptionService::numero($d),
                'etab_nom' => $d['etab_nom'] ?? null,
                'montant'  => $scolarie + (float)($frais ?? 0),
                'mode'     => $d['mode_paiement'] ?? null,
                'date'     => $d['date_paiement'] ?? $d['valide_100_le'] ?? null,
                // Route à la demande : toujours disponible, jamais un lien mort
                // (le document est reconstruit à chaque téléchargement).
                'url'      => url('/eleve/orientation/dossier/' . hid((int)$d['id']) . '/recu'),
                'detail'   => [
                    'frais'     => $frais === null ? null : (float)$frais,
                    'scolarite' => $scolarie,
                ],
            ];
        }

        // Une date manquante ne doit pas remonter en tête de liste par accident.
        usort($recus, static function (array $a, array $b): int {
            return strtotime((string)($b['date'] ?? '1970-01-01'))
                 <=> strtotime((string)($a['date'] ?? '1970-01-01'));
        });

        Response::view('orientation/espace_eleve/recus', [
            'pageTitle' => "Mes reçus — Espace orientation",
            'activeTab' => 'recus',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'recus'     => $recus,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function documents(): void
    {
        $ctx = $this->contexte();

        // Documents groupés par dossier + pièces manquantes des inscriptions
        $groupes = [];
        foreach ($ctx['dossiers'] as $d) {
            $pieces = $this->piecesPourDossier($d);
            $docs   = [];
            foreach ($pieces as $p) {
                if ($p['ok'] && $p['doc']) {
                    $p['doc']['url_doc'] = self::lienDocument($p['doc']);
                }
                $docs[] = $p;
            }
            // Préinscriptions pures : aucune pièce requise, on n'affiche pas de groupe vide
            if (empty($docs)) {
                continue;
            }
            $groupes[] = ['dossier' => $d, 'pieces' => $docs];
        }

        Response::view('orientation/espace_eleve/documents', [
            'pageTitle' => "Mes documents — Espace orientation",
            'activeTab' => 'documents',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'groupes'   => $groupes,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function notifications(): void
    {
        $ctx    = $this->contexte();
        $notifs = $this->notifModel->findByUser($ctx['userId'], 50);

        Response::view('orientation/espace_eleve/notifications', [
            'pageTitle' => "Mes notifications — Espace orientation",
            'activeTab' => 'notifications',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'notifs'    => $notifs,
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function marquerNotificationsLues(): void
    {
        $this->notifModel->markAllRead((int)Session::userId());
        Session::flash('success', 'Toutes les notifications ont été marquées comme lues.');
        Response::redirect('/eleve/orientation/notifications');
    }

    // ── Détail d'un dossier ───────────────────────────────────────────────────

    public function dossier(string $hash): void
    {
        $id  = Hashid::decode($hash);
        $ctx = $this->contexte();

        $dossier = $id !== null
            ? $this->dossierModel->findByIdWithOwnership($id, $ctx['userId'], $ctx['email'])
            : null;

        if (!$dossier) {
            // Défense-en-profondeur : si le hash pointe vers un dossier RÉEL non
            // détenu par l'utilisateur, c'est une sonde IDOR → on la journalise.
            if ($id !== null && $this->dossierModel->findById($id)) {
                TenantGuard::denyOwnership('eleve.dossier', $id);
            }
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Dossier introuvable'], 'main');
            return;
        }

        $pieces = $this->piecesPourDossier($dossier);
        foreach ($pieces as &$p) {
            // Y compris pour une pièce REFUSÉE : le candidat doit pouvoir
            // relire ce qu'il a envoyé pour comprendre le motif du refus.
            if (!empty($p['doc'])) {
                $p['doc']['url_doc'] = self::lienDocument($p['doc']);
            }
        }
        unset($p);

        $recuUrl = $this->signedUrl($dossier['recu_cle'] ?? null);

        $statut = \Models\Orientation\DossierStatut::normaliser((string)($dossier['statut'] ?? ''));

        Response::view('orientation/espace_eleve/dossier', [
            'pageTitle'      => 'Dossier — ' . ($dossier['etab_nom'] ?? ''),
            'activeTab'      => Dossier::estInscription($dossier) ? 'inscriptions' : 'preinscriptions',
            'user'           => $ctx['user'],
            'navCounts'      => $ctx['navCounts'],
            'dossier'        => $dossier,
            'pieces'         => $pieces,
            'recuUrl'        => $recuUrl,

            // Reçu de règlement de scolarité (spec §8.4) : disponible dès que
            // l'établissement a validé à 100 % ET saisi un montant. Distinct
            // de $recuUrl, qui est le reçu d'un paiement EN LIGNE hérité.
            'peutRecuScolarite' => \Services\Orientation\RecuInscriptionService::disponible($dossier),
            'numeroRecu'        => \Services\Orientation\RecuInscriptionService::disponible($dossier)
                                    ? \Services\Orientation\RecuInscriptionService::numero($dossier)
                                    : null,

            // ── Suivi du dossier (spec §9.1, §9.2) ──
            // Le message est en langage clair : le nom technique de l'état ne
            // doit jamais apparaître à l'étudiant.
            'statut'         => $statut,
            'paliers'        => \Models\Orientation\DossierStatut::paliers($statut),
            // Le message suit la nature du dossier : envoyer au guichet « avec
            // son récépissé pour régler sa scolarité » quelqu'un qui a
            // seulement fait une préinscription lui fait faire le déplacement
            // pour rien.
            'messageStatut'  => \Models\Orientation\DossierStatut::messageEtudiant(
                $statut,
                $dossier['motif_rejet'] ?? null,
                Dossier::estInscription($dossier)
            ),
            'tonStatut'      => \Models\Orientation\DossierStatut::ton($statut),

            // ── Complément de dossier (spec §9.3) ──
            // Le candidat doit pouvoir CORRIGER une pièce refusée, pas
            // seulement en être informé.
            'depotOuvert'    => \Services\Orientation\ComplementDossier::ouvertAuDepot($dossier),
            'blocages'       => $statut === \Models\Orientation\DossierStatut::PIECE_MANQUANTE
                                    ? \Services\Orientation\ComplementDossier::blocages($dossier)
                                    : [],

            // ── Situation de bourse (spec §9.4) ──
            // Établissement fermé aux boursiers : le bloc entier disparaît de
            // l'espace candidat. Le dossier garde la situation qu'il portait
            // déjà — fermer une porte ne réécrit pas ce qui est entré avant —
            // mais plus aucune déclaration ne peut être posée ici.
            'bourseActive'   => \Services\Orientation\PolitiqueBoursiers::accepteId(
                                    (int)($dossier['etablissement_id'] ?? 0)
                                ),
            'bourseOptions'  => \Services\Orientation\PolitiqueBoursiers::optionsId(
                                    (int)($dossier['etablissement_id'] ?? 0)
                                ),
            'bourseActuelle' => $dossier['statut_bourse'] ?? null,
            'bourseHisto'    => \Services\Orientation\SituationBourse::historique($id),
            'bourseNote'     => \Services\Orientation\SituationBourse::NOTE_EXPLICATIVE,

            'csrf'           => Session::getCsrfToken(),
            'flash'          => Session::getFlash('success'),
            'flashError'     => Session::getFlash('error'),
        ], 'espace-eleve');
    }

    /**
     * Attestation de préinscription — PDF généré à la demande.
     * Regénérée à la demande depuis la base : le téléchargement fonctionne
     * toujours, même si le fichier B2 est absent (upload échoué, ancien dossier).
     */
    public function recepisse(string $hash): void
    {
        $id      = Hashid::decode($hash);
        $ctx     = $this->contexte();
        $dossier = $id !== null
            ? $this->dossierModel->findByIdWithOwnership($id, $ctx['userId'], $ctx['email'])
            : null;
        if (!$dossier) {
            // Défense-en-profondeur : si le hash pointe vers un dossier RÉEL non
            // détenu par l'utilisateur, c'est une sonde IDOR → on la journalise.
            if ($id !== null && $this->dossierModel->findById($id)) {
                TenantGuard::denyOwnership('eleve.dossier', $id);
            }
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Dossier introuvable'], 'main');
            return;
        }

        // ── Récépissé de dépôt de dossier (spec §6) ──
        // Un dossier portant une référence CA-AAAA-CODE-NNNNNN relève de la
        // refonte : son récépissé est le document conforme à la maquette, pas
        // l'ancienne attestation de préinscription. Les dossiers antérieurs
        // gardent leur document d'origine — un document déjà remis ne doit
        // jamais changer d'apparence sous les yeux de son porteur.
        if (!empty($dossier['reference'])) {
            \Services\Orientation\Journal::dossier(
                \Services\Orientation\Journal::RECEPISSE_TELECHARGE,
                $dossier,
                ['reference' => $dossier['reference']],
                ['acteur_id' => $ctx['userId'], 'acteur_role' => 'etudiant']
            );

            $this->compterTelechargement('recepisses', $id);

            PdfStream::emettre(
                \Services\Orientation\RecepisseDossierService::rendreDirect($id),
                fn(): string => '<!doctype html><meta charset="utf-8">'
                    . '<p style="font-family:sans-serif;padding:40px">Votre récépissé est en cours de '
                    . 'génération. Réessayez dans quelques instants.</p>',
                'recepisse-' . (string)$dossier['reference']
            );
            return;
        }

        $etab      = (new Etablissement())->findById((int)$dossier['etablissement_id']) ?? [];
        $recepisse = $this->dossierModel->getRecepisse($id);

        if ($recepisse && !empty($recepisse['numero_recepisse'])) {
            $numero = (string)$recepisse['numero_recepisse'];
            try { $dateEmission = new \DateTime((string)$recepisse['date_emission']); }
            catch (\Throwable) { $dateEmission = new \DateTime(); }
        } else {
            $numero       = $this->dossierModel->reserveRecepisse($id);
            $dateEmission = new \DateTime();
        }

        $token         = $this->dossierModel->tokenVerificationRecepisse($id);
        $svc           = new RecepisseService();
        $numeroFacture = 'FAC-' . substr($numero, 4);

        PdfStream::emettre(
            $svc->renderPdfComplet($dossier, $etab, $numero, $numeroFacture, $dateEmission, $token),
            fn() => $svc->render($dossier, $etab, $numero, $dateEmission, $token),
            $numero
        );
    }

    /**
     * Attestation d'inscription — PDF généré à la demande.
     * Réservée aux inscriptions validées (statut « inscrit », ou « accepte » legacy).
     */
    public function attestation(string $hash): void
    {
        $id      = Hashid::decode($hash);
        $ctx     = $this->contexte();
        $dossier = $id !== null
            ? $this->dossierModel->findByIdWithOwnership($id, $ctx['userId'], $ctx['email'])
            : null;
        if (!$dossier) {
            // Défense-en-profondeur : si le hash pointe vers un dossier RÉEL non
            // détenu par l'utilisateur, c'est une sonde IDOR → on la journalise.
            if ($id !== null && $this->dossierModel->findById($id)) {
                TenantGuard::denyOwnership('eleve.dossier', $id);
            }
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Dossier introuvable'], 'main');
            return;
        }

        $etab        = (new Etablissement())->findById((int)$dossier['etablissement_id']) ?? [];
        $attestation = $this->dossierModel->getAttestation($id);

        if ($attestation && !empty($attestation['numero_attestation'])) {
            // Déjà délivrée : téléchargeable sans limite par le candidat, même
            // si le statut a évolué depuis (le document reste archivé).
            $numero = (string)$attestation['numero_attestation'];
            try { $dateEmission = new \DateTime((string)$attestation['date_emission']); }
            catch (\Throwable) { $dateEmission = new \DateTime(); }
        } else {
            // Première émission : réservée aux inscriptions définitives.
            // 'inscrit'/'accepte' sont les écritures héritées d'avant la refonte.
            if (!in_array($dossier['statut'] ?? '', ['valide_100', 'inscrit', 'accepte'], true)) {
                Session::flash('error', "L'attestation d'inscription n'est disponible qu'après validation de votre dossier par l'établissement.");
                Response::redirect('/eleve/orientation/dossier/' . $hash);
                return;
            }
            $numero       = $this->dossierModel->reserveAttestation($id);
            $dateEmission = new \DateTime();
        }

        \Services\Orientation\Journal::dossier(
            \Services\Orientation\Journal::ATTESTATION_TELECHARGEE,
            $dossier,
            ['numero' => $numero],
            ['acteur_id' => $ctx['userId'], 'acteur_role' => 'etudiant']
        );
        $this->compterTelechargement('attestations_inscription', $id);

        $token = $this->dossierModel->tokenVerificationAttestation($id);
        $svc   = new AttestationService();

        PdfStream::emettre(
            $svc->renderPdf($dossier, $etab, $numero, $dateEmission, $token),
            fn() => $svc->render($dossier, $etab, $numero, $dateEmission, $token),
            $numero
        );
    }

    /**
     * Reçu de règlement de scolarité — PDF rendu à la demande.
     *
     * Disponible dès que l'inscription est validée à 100 % avec un montant
     * saisi (spec §8.4). À ne pas confondre avec l'attestation : celle-ci
     * prouve l'inscription, celui-là prouve le versement.
     *
     * Rien n'est stocké : le document est intégralement dérivé des colonnes du
     * dossier, et deux rendus successifs donnent le même reçu au même numéro.
     */
    public function recuInscription(string $hash): void
    {
        $id      = Hashid::decode($hash);
        $ctx     = $this->contexte();
        $dossier = $id !== null
            ? $this->dossierModel->findByIdWithOwnership($id, $ctx['userId'], $ctx['email'])
            : null;

        if (!$dossier) {
            // Défense-en-profondeur : un hash qui pointe vers un dossier RÉEL
            // non détenu par l'utilisateur est une sonde IDOR → journalisée.
            if ($id !== null && $this->dossierModel->findById($id)) {
                TenantGuard::denyOwnership('eleve.dossier', $id);
            }
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Dossier introuvable'], 'main');
            return;
        }

        if (!\Services\Orientation\RecuInscriptionService::disponible($dossier)) {
            Session::flash('error', 'Votre reçu de scolarité sera disponible dès que '
                . 'l’établissement aura validé votre inscription et enregistré votre règlement.');
            Response::redirect('/eleve/orientation/dossier/' . $hash);
            return;
        }

        $numero = \Services\Orientation\RecuInscriptionService::numero($dossier);

        \Services\Orientation\Journal::dossier(
            \Services\Orientation\Journal::RECU_TELECHARGE,
            $dossier,
            ['numero' => $numero],
            ['acteur_id' => $ctx['userId'], 'acteur_role' => 'etudiant']
        );

        PdfStream::emettre(
            \Services\Orientation\RecuInscriptionService::rendre($id),
            static fn(): string => '<!doctype html><meta charset="utf-8">'
                . '<p style="font-family:sans-serif;padding:40px">Votre reçu est en cours de '
                . 'génération. Réessayez dans quelques instants.</p>',
            $numero
        );
    }

    /**
     * Incrémente le compteur de téléchargements d'un document généré.
     * Purement statistique : jamais bloquant, jamais dans une transaction.
     */
    private function compterTelechargement(string $table, int $dossierId): void
    {
        // $table est un littéral du code, jamais une entrée utilisateur.
        if (!in_array($table, ['recepisses', 'attestations_inscription'], true)) {
            return;
        }

        try {
            \Core\Database::getInstance()->getConnection()->prepare(
                "UPDATE {$table} SET nb_telechargements = nb_telechargements + 1 WHERE dossier_id = ?"
            )->execute([$dossierId]);
        } catch (\Throwable $e) {
            error_log('[EspaceEleve] Compteur de téléchargement : ' . $e->getMessage());
        }
    }

    /**
     * POST /eleve/orientation/dossier/{hash}/bourse — mise à jour de la
     * situation de bourse par l'étudiant (spec §9.4).
     *
     * Possible À TOUT MOMENT, y compris après la validation à 100 % : un
     * dossier ANBG peut aboutir des mois après l'inscription.
     *
     * Le changement ne modifie NI la commission, NI le statut du dossier.
     * C'est une information de suivi, pas un paramètre de facturation — et
     * c'est ce qui supprime toute incitation à la manipuler.
     */
    public function updateBourse(string $hash): void
    {
        $id      = Hashid::decode($hash);
        $ctx     = $this->contexte();
        $dossier = $id !== null
            ? $this->dossierModel->findByIdWithOwnership($id, $ctx['userId'], $ctx['email'])
            : null;

        if (!$dossier) {
            if ($id !== null && $this->dossierModel->findById($id)) {
                TenantGuard::denyOwnership('eleve.bourse', $id);
            }
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Dossier introuvable'], 'main');
            return;
        }

        // Établissement fermé aux boursiers : la déclaration est refusée avant
        // tout traitement. L'écran ne propose plus le bloc ; ce contrôle vise
        // le POST forgé, et le formulaire resté ouvert dans un onglet quand le
        // super administrateur a fermé la porte entre-temps.
        if (!\Services\Orientation\PolitiqueBoursiers::statutAutoriseId(
                (int)$dossier['etablissement_id'],
                is_string($_POST['statut_bourse'] ?? null) ? trim($_POST['statut_bourse']) : null
            )) {
            Session::flash('error', \Services\Orientation\PolitiqueBoursiers::MESSAGE_REFUS);
            Response::redirect('/eleve/orientation/dossier/' . $hash);
            return;
        }

        $controle = \Services\Orientation\SituationBourse::valider($_POST);

        if ($controle['erreurs'] !== []) {
            Session::flash('error', implode(' ', $controle['erreurs']));
            Response::redirect('/eleve/orientation/dossier/' . $hash . '#bourse');
            return;
        }

        // Justificatif joint : obligatoire pour se déclarer boursier confirmé,
        // sauf si l'attestation a déjà été déposée avec le dossier.
        $nouveau      = (string)$controle['valeurs']['statut_bourse'];
        $codePiece    = \Services\Orientation\SituationBourse::pieceAssociee($nouveau);
        $justificatif = null;

        if ($codePiece !== null && !empty($_FILES['justificatif']['name'])) {
            $piece = $this->pieceParCode((int)$dossier['etablissement_id'], $codePiece);

            $depot = \Services\Orientation\PieceDossier::deposer(
                $_FILES['justificatif'],
                $dossier,
                $piece,
                $ctx['userId']
            );

            if (!$depot['ok']) {
                Session::flash('error', (string)$depot['erreur']);
                Response::redirect('/eleve/orientation/dossier/' . $hash . '#bourse');
                return;
            }

            $justificatif = $depot['cle'];
        } elseif (\Services\Orientation\SituationBourse::pieceObligatoire($nouveau)
                  && !$this->possedePiece((int)$dossier['id'], (string)$codePiece)) {
            Session::flash('error', 'Joignez votre attestation de bourse ANBG pour déclarer ce statut.');
            Response::redirect('/eleve/orientation/dossier/' . $hash . '#bourse');
            return;
        }

        $change = \Services\Orientation\SituationBourse::enregistrer(
            (int)$dossier['id'],
            $controle['valeurs'],
            \Services\Orientation\SituationBourse::PAR_ETUDIANT,
            $ctx['userId'],
            $justificatif,
            trim((string)($_POST['commentaire'] ?? '')) ?: null
        );

        if ($change) {
            // L'établissement est prévenu : c'est une information de suivi qui
            // l'intéresse, même si elle ne change rien à sa facturation.
            $this->notifierEtablissementBourse($dossier, $nouveau);
            Session::flash('success', 'Votre situation de bourse a été mise à jour. Elle ne modifie ni votre inscription ni les frais dus.');
        } else {
            Session::flash('success', 'Aucun changement : cette situation était déjà enregistrée.');
        }

        Response::redirect('/eleve/orientation/dossier/' . $hash . '#bourse');
    }

    /**
     * POST /eleve/orientation/dossier/{hash}/piece
     *
     * Remplace une pièce refusée, ou dépose une pièce demandée en complément
     * (spec §9.3). C'est la sortie de secours du parcours : sans elle, une
     * notification « pièce non conforme » ne mène nulle part.
     *
     * La propriété du dossier est revérifiée ici, et le rattachement de la
     * pièce au référentiel de l'établissement l'est dans le service : un
     * identifiant forgé ne franchit ni l'une ni l'autre.
     */
    public function remplacerPiece(string $hash): void
    {
        $id      = Hashid::decode($hash);
        $ctx     = $this->contexte();
        $dossier = $id !== null
            ? $this->dossierModel->findByIdWithOwnership($id, $ctx['userId'], $ctx['email'])
            : null;

        if (!$dossier) {
            if ($id !== null && $this->dossierModel->findById($id)) {
                TenantGuard::denyOwnership('eleve.piece', $id);
            }
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Dossier introuvable'], 'main');
            return;
        }

        $retour = '/eleve/orientation/dossier/' . $hash . '#pieces';

        if (empty($_FILES['fichier']['name'])) {
            Session::flash('error', 'Choisissez le fichier à déposer.');
            Response::redirect($retour);
            return;
        }

        $resultat = \Services\Orientation\ComplementDossier::deposer(
            $dossier,
            $_FILES['fichier'],
            ($_POST['piece_requise_id'] ?? '') !== '' ? (int)$_POST['piece_requise_id'] : null,
            ($_POST['document_id'] ?? '') !== '' ? (int)$_POST['document_id'] : null,
            $ctx['userId']
        );

        Session::flash($resultat['ok'] ? 'success' : 'error', $resultat['message']);
        Response::redirect($retour);
    }

    /**
     * POST /eleve/orientation/dossier/{hash}/renvoyer
     *
     * Renvoie explicitement le dossier à l'analyse. Le cas nominal est
     * automatique (dernier dépôt = renvoi), ce bouton couvre les demandes de
     * complément formulées en texte libre, sans pièce du référentiel en face.
     */
    public function renvoyerDossier(string $hash): void
    {
        $id      = Hashid::decode($hash);
        $ctx     = $this->contexte();
        $dossier = $id !== null
            ? $this->dossierModel->findByIdWithOwnership($id, $ctx['userId'], $ctx['email'])
            : null;

        if (!$dossier) {
            if ($id !== null && $this->dossierModel->findById($id)) {
                TenantGuard::denyOwnership('eleve.renvoi', $id);
            }
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Dossier introuvable'], 'main');
            return;
        }

        $resultat = \Services\Orientation\ComplementDossier::renvoyer($dossier, $ctx['userId']);

        Session::flash($resultat['ok'] ? 'success' : 'error', $resultat['message']);
        Response::redirect('/eleve/orientation/dossier/' . $hash . '#pieces');
    }

    /**
     * Ligne de pièce requise correspondant à un code interne, pour rattacher le
     * justificatif au bon emplacement du dossier.
     *
     * @return array<string,mixed>|null
     */
    private function pieceParCode(int $etablissementId, string $code): ?array
    {
        try {
            $st = \Core\Database::getInstance()->getConnection()->prepare(
                'SELECT * FROM pieces_justificatives_requises
                 WHERE etablissement_id = ? AND code_interne = ? LIMIT 1'
            );
            $st->execute([$etablissementId, $code]);
            $ligne = $st->fetch(\PDO::FETCH_ASSOC);

            return $ligne === false ? null : $ligne;
        } catch (\Throwable $e) {
            error_log('[EspaceEleve] Pièce par code : ' . $e->getMessage());

            return null;
        }
    }

    /** Le dossier porte-t-il déjà une pièce de ce type, non refusée ? */
    private function possedePiece(int $dossierId, string $code): bool
    {
        if ($code === '') {
            return false;
        }

        try {
            $st = \Core\Database::getInstance()->getConnection()->prepare(
                'SELECT COUNT(*) FROM documents_orientation
                 WHERE dossier_id = ? AND type_document = ? AND statut <> \'refuse\''
            );
            $st->execute([$dossierId, $code]);

            return (int)$st->fetchColumn() > 0;
        } catch (\Throwable $e) {
            error_log('[EspaceEleve] Vérification de pièce : ' . $e->getMessage());

            return false;
        }
    }

    /** Notifie les gestionnaires d'un changement de situation de bourse. */
    private function notifierEtablissementBourse(array $dossier, string $nouveau): void
    {
        try {
            $notifications = new NotificationOrientation();
            $etabId        = (int)$dossier['etablissement_id'];

            foreach ($notifications->findGestionnaireIds($etabId) as $gestionnaireId) {
                $notifications->create([
                    'user_id'          => (int)$gestionnaireId,
                    'etablissement_id' => $etabId,
                    'type'             => 'bourse_modifiee',
                    'titre'            => 'Situation de bourse modifiée',
                    'message'          => trim((string)($dossier['nom_complet'] ?? 'Un candidat'))
                        . ' (' . (string)($dossier['reference'] ?? 'dossier #' . (int)$dossier['id']) . ') a déclaré : '
                        . \Services\Orientation\SituationBourse::libelleCourt($nouveau)
                        . '. Aucune incidence sur la facturation.',
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[EspaceEleve] Notification bourse : ' . $e->getMessage());
        }
    }

    /**
     * Sert une pièce justificative déposée par le candidat.
     *
     * Le fichier transite par l'application au lieu d'une URL signée B2 :
     *   - l'URL ne contient plus qu'un jeton opaque (l'ID du dossier n'apparaît
     *     plus dans la barre d'adresse, et n'est pas énumérable) ;
     *   - la propriété du dossier est revérifiée à CHAQUE consultation, alors
     *     qu'un lien signé restait valable hors session jusqu'à expiration.
     *
     * Le type MIME servi est restreint : tout ce qui n'est pas un PDF ou une
     * image connue part en téléchargement `octet-stream`, jamais interprété par
     * le navigateur (un HTML ou un SVG rendu inline sur notre domaine serait un
     * XSS stocké).
     */
    public function document(string $hash): void
    {
        $docId = Hashid::decode($hash);
        $doc   = $docId !== null ? $this->dossierModel->findDocumentById($docId) : null;

        if (!$doc) {
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Document introuvable'], 'main');
            return;
        }

        $ctx     = $this->contexte();
        $dossier = $this->dossierModel->findByIdWithOwnership(
            (int)$doc['dossier_id'], $ctx['userId'], $ctx['email']
        );

        if (!$dossier) {
            TenantGuard::denyOwnership('eleve.document', $docId);
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Document introuvable'], 'main');
            return;
        }

        $cle = (string)($doc['cle_b2'] ?? '');
        if ($cle === '') {
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Document introuvable'], 'main');
            return;
        }

        try {
            // getContentClair : déchiffre l'enveloppe AES-256-GCM des pièces
            // déposées depuis l'activation du chiffrement, et rend tel quel un
            // fichier historique déposé en clair.
            $contenu = (new DocumentStorage())->getContentClair($cle);
        } catch (\Throwable $e) {
            error_log('[EspaceOrientation] Lecture document #' . $docId . ' échouée : ' . $e->getMessage());
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Document indisponible'], 'main');
            return;
        }

        // Livraison centralisée (Core\FichierBinaire) : liste blanche des types
        // affichables en ligne, nom de fichier assaini ET pourvu de son
        // extension, et surtout neutralisation de la politique de sécurité HTML.
        //
        // C'est ce dernier point qui corrige la page blanche : le
        // front-controller pose `object-src 'none'` sur toutes les réponses, ce
        // qui empêche les navigateurs Chromium d'ouvrir leur visionneuse PDF.
        // Le candidat qui touchait « Voir » sur une pièce refusée — pour lire ce
        // qu'il devait corriger — tombait sur un écran vide. `PdfStream` corrige
        // ce défaut depuis longtemps pour les documents ENGENDRÉS ; les pièces
        // DÉPOSÉES ne passaient simplement pas par le même chemin.
        \Core\FichierBinaire::emettre(
            $contenu,
            (string)($doc['mime_type'] ?? ''),
            (string)($doc['libelle'] ?? 'document'),
            true
        );
    }

    // ── Profil ────────────────────────────────────────────────────────────────

    public function profil(): void
    {
        $ctx = $this->contexte();

        // Identité issue du dernier dossier déposé (pré-remplit les démarches)
        $identite = null;
        foreach ($ctx['dossiers'] as $d) {
            if (($d['statut'] ?? '') !== 'brouillon') { $identite = $d; break; }
        }
        $identite ??= $ctx['dossiers'][0] ?? null;

        Response::view('orientation/espace_eleve/profil', [
            'pageTitle' => "Mon profil — Espace orientation",
            'activeTab' => 'profil',
            'user'      => $ctx['user'],
            'navCounts' => $ctx['navCounts'],
            'identite'  => $identite,
            'stats'     => $this->dossierModel->countByUserContext($ctx['userId'], $ctx['email']),
            'errors'    => Session::getFlash('errors', []),
            'flash'     => Session::getFlash('success'),
        ], 'espace-eleve');
    }

    public function updateProfil(): void
    {
        $userId    = (int)Session::userId();
        $userModel = new User();
        $user      = $userModel->findById($userId);

        $prenom = trim($_POST['prenom'] ?? '');
        $nom    = trim($_POST['nom'] ?? '');
        $email  = trim(strtolower($_POST['email'] ?? ''));

        $errors = [];
        if (mb_strlen($prenom) < 2 || mb_strlen($prenom) > 100) {
            $errors['prenom'] = 'Le prénom doit contenir entre 2 et 100 caractères.';
        }
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 100) {
            $errors['nom'] = 'Le nom doit contenir entre 2 et 100 caractères.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'Adresse email invalide.';
        }

        // Changement d'email : mot de passe actuel obligatoire + unicité
        if (empty($errors) && $email !== ($user['email'] ?? '')) {
            $currentPassword = $_POST['current_password'] ?? '';
            if (!$currentPassword || !$userModel->verifyPassword($currentPassword, $user['password_hash'])) {
                $errors['current_password'] = 'Mot de passe actuel requis pour changer d\'adresse email.';
            } else {
                $existing = $userModel->findByEmail($email);
                if ($existing && (int)$existing['id'] !== $userId) {
                    $errors['email'] = 'Cette adresse email est déjà utilisée par un autre compte.';
                }
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Response::redirect('/eleve/orientation/profil');
        }

        try {
            $userModel->updateProfile($userId, ['prenom' => $prenom, 'nom' => $nom]);
            if ($email !== ($user['email'] ?? '')) {
                $userModel->updateEmail($userId, $email);
                // Les dossiers restent liés via eleve_id ; on rattache aussi
                // ceux éventuellement déposés avec la nouvelle adresse.
                $this->dossierModel->claimByEmail($userId, $email);
            }
            Session::set('user_name', $prenom . ' ' . $nom);
        } catch (\Exception $e) {
            error_log('[EspaceOrientation] Profil update échoué : ' . $e->getMessage());
            Session::flash('errors', ['general' => 'Erreur lors de la mise à jour. Veuillez réessayer.']);
            Response::redirect('/eleve/orientation/profil');
        }

        Session::flash('success', 'Profil mis à jour.');
        Response::redirect('/eleve/orientation/profil');
    }

    public function updatePassword(): void
    {
        $userId    = (int)Session::userId();
        $userModel = new User();
        $user      = $userModel->findById($userId);

        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirmation'] ?? '';

        $errors = [];
        if (!$userModel->verifyPassword($current, $user['password_hash'])) {
            $errors['current_password'] = 'Mot de passe actuel incorrect.';
        }
        if (strlen($new) < 8 || strlen($new) > 128) {
            $errors['new_password'] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        }
        if ($new !== $confirm) {
            $errors['new_password_confirmation'] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Response::redirect('/eleve/orientation/profil');
        }

        try {
            $userModel->updatePassword($userId, $new);
            Session::regenerate();
        } catch (\Exception $e) {
            error_log('[EspaceOrientation] Password update échoué : ' . $e->getMessage());
            Session::flash('errors', ['general' => 'Erreur lors de la mise à jour. Veuillez réessayer.']);
            Response::redirect('/eleve/orientation/profil');
        }

        Session::flash('success', 'Mot de passe mis à jour.');
        Response::redirect('/eleve/orientation/profil');
    }
}
