<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Database;
use Core\Response;
use Core\Session;
use Models\Orientation\Dossier;
use Models\Orientation\DossierStatut;
use Services\Orientation\CommissionDossier;
use Services\Orientation\JetonDossier;
use Services\Orientation\Journal;
use Services\Orientation\ScanQr;
use Services\Orientation\SituationBourse;
use Services\Orientation\Tracabilite;
use PDO;

/**
 * Espace super administrateur — traçabilité (spec §11).
 *
 * « C'est le cœur de la valeur de Connect'Academia. La plateforme ne collecte
 * plus l'argent ; elle doit donc être irréprochable sur la donnée. »
 *
 * Quatre écrans, une seule intention : pouvoir répondre à n'importe quelle
 * question sur n'importe quel dossier, avec des dates à la seconde et un
 * auteur nommé.
 *
 * Les routes vivent hors des préfixes AdminPermissions : elles sont réservées
 * au super administrateur, comme /admin/securite.
 */
class TracabiliteController
{
    /** Taille de page commune aux listes de cet espace. */
    private const PAR_PAGE = 60;

    /**
     * Défense en profondeur : les routes portent le middleware 'admin', qui
     * laisse aussi passer les modérateurs. Or cet espace donne accès aux
     * données personnelles de TOUS les candidats, tous établissements
     * confondus — même barrière que /admin/securite.
     */
    public function __construct()
    {
        if (!in_array(Session::adminRole(), ['super_admin', 'admin'], true)) {
            Session::flash('error', 'Espace réservé au super administrateur.');
            Response::redirect('/admin');
        }
    }

    // ── §11.1 — Fiche utilisateur enrichie ──────────────────────────────────

    /** GET /admin/utilisateurs/{id} */
    public function utilisateur(array $params): void
    {
        $id   = (int)($params['id'] ?? 0);
        $user = $this->utilisateurComplet($id);

        if ($user === null) {
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Utilisateur introuvable'], 'admin');
            return;
        }

        $fiche = Tracabilite::ficheUtilisateur($id);

        Response::view('admin/tracabilite/utilisateur', [
            'pageTitle'       => trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?: 'Utilisateur',
            'utilisateur'     => $user,
            'activite'        => $fiche['activite'],
            'preinscriptions' => $fiche['preinscriptions'],
            'dossiers'        => $fiche['dossiers'],
            'frise'           => $fiche['frise'],
            'csrf'            => Session::getCsrfToken(),
            'flash'           => Session::getFlash('success'),
            'flashError'      => Session::getFlash('error'),
        ], 'admin');
    }

    // ── §13.4 — Vue transversale des dossiers ───────────────────────────────

    /** GET /admin/dossiers */
    public function dossiers(): void
    {
        $filtres = array_filter([
            'statut'           => trim((string)($_GET['statut'] ?? '')),
            'type'             => in_array($_GET['type'] ?? '', ['preinscription', 'inscription'], true)
                ? (string)$_GET['type'] : '',
            'etablissement_id' => (int)($_GET['etablissement_id'] ?? 0) ?: null,
            'statut_bourse'    => trim((string)($_GET['statut_bourse'] ?? '')),
            'annee'            => trim((string)($_GET['annee'] ?? '')),
            'q'                => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 120),
        ], static fn($v): bool => $v !== null && $v !== '');

        $page  = max(1, (int)($_GET['page'] ?? 1));
        $total = Tracabilite::compterDossiersTransversaux($filtres);

        Response::view('admin/tracabilite/dossiers', [
            'pageTitle'      => 'Tous les dossiers',
            'dossiers'       => Tracabilite::dossiersTransversaux($filtres, self::PAR_PAGE, ($page - 1) * self::PAR_PAGE),
            'total'          => $total,
            'page'           => $page,
            'pages'          => max(1, (int)ceil($total / self::PAR_PAGE)),
            'filtres'        => $filtres,
            'etablissements' => $this->etablissements(),
            'annees'         => $this->annees(),
            'statuts'        => DossierStatut::tous(),
            'csrf'           => Session::getCsrfToken(),
            'flash'          => Session::getFlash('success'),
            'flashError'     => Session::getFlash('error'),
        ], 'admin');
    }

    // ── §11.3 — Consultation du journal ─────────────────────────────────────

    /**
     * GET /admin/journal
     *
     * Lecture seule, sans exception. Le journal est en ajout seul : aucune
     * route de l'application ne doit permettre d'en modifier ou d'en supprimer
     * une entrée, sous peine qu'il ne prouve plus rien (spec §11.3).
     */
    public function journal(): void
    {
        $filtres = array_filter([
            'type'             => trim((string)($_GET['type'] ?? '')),
            'etablissement_id' => (int)($_GET['etablissement_id'] ?? 0) ?: null,
            'utilisateur_id'   => (int)($_GET['utilisateur_id'] ?? 0) ?: null,
            'du'               => $this->date($_GET['du'] ?? null),
            'au'               => $this->date($_GET['au'] ?? null),
        ], static fn($v): bool => $v !== null && $v !== '');

        $page  = max(1, (int)($_GET['page'] ?? 1));
        $total = Journal::compter($filtres);

        Response::view('admin/tracabilite/journal', [
            'pageTitle'      => 'Journal d’activité',
            'entrees'        => Journal::rechercher($filtres, self::PAR_PAGE, ($page - 1) * self::PAR_PAGE),
            'types'          => Journal::typesDisponibles(),
            'total'          => $total,
            'page'           => $page,
            'pages'          => max(1, (int)ceil($total / self::PAR_PAGE)),
            'filtres'        => $filtres,
            'etablissements' => $this->etablissements(),
        ], 'admin');
    }

    // ── §11.4 — Tableaux de bord ────────────────────────────────────────────

    /** GET /admin/tableaux-de-bord */
    public function tableauxDeBord(): void
    {
        $etabId = (int)($_GET['etablissement_id'] ?? 0) ?: null;

        Response::view('admin/tracabilite/tableaux', [
            'pageTitle'       => 'Tableaux de bord',
            'entonnoir'       => Tracabilite::entonnoir($etabId),
            'bourses'         => Tracabilite::repartitionBourses($etabId),
            'delais'          => Tracabilite::delaiTraitement(),
            'attente'         => Tracabilite::attenteProlongee(),
            // Engagement de service : l'établissement dispose de N jours pour
            // rendre une décision. Au-delà, le super administrateur est le seul
            // à pouvoir agir — d'où sa présence ici, et pas côté gestionnaire.
            'retards'         => Tracabilite::analysesEnRetard(),
            'delaiAnalyse'    => Tracabilite::delaiAnalyse(),
            'alerteBoursiers' => Tracabilite::alerteBoursiers(),
            'multiples'       => Tracabilite::inscriptionsMultiples(),
            'etablissements'  => $this->etablissements(),
            'etabId'          => $etabId,
        ], 'admin');
    }

    // ── §13.4 — Actions ─────────────────────────────────────────────────────

    /**
     * POST /admin/etablissements/{id}/taux — modification du taux de commission.
     *
     * Journalisée avec l'ancienne et la nouvelle valeur (spec §10.2). La
     * modification ne s'applique QU'AUX dossiers validés après elle : les
     * commissions déjà émises portent leur taux gelé et n'y touchent pas.
     */
    public function modifierTaux(array $params): void
    {
        $etabId = (int)($params['id'] ?? 0);
        $saisie = str_replace(',', '.', trim((string)($_POST['taux'] ?? '')));

        if (preg_match('/^\d{1,2}(\.\d{1,2})?$/', $saisie) !== 1) {
            Session::flash('error', 'Taux invalide. Attendu : un nombre entre 0 et 99,99.');
            Response::redirect('/admin/tableaux-de-bord');
            return;
        }

        $nouveau = (float)$saisie;

        try {
            $db = Database::getInstance()->getConnection();

            $st = $db->prepare('SELECT nom, taux_commission_scolarite FROM etablissements WHERE id = ?');
            $st->execute([$etabId]);
            $etab = $st->fetch(PDO::FETCH_ASSOC);

            if ($etab === false) {
                Session::flash('error', 'Établissement introuvable.');
                Response::redirect('/admin/tableaux-de-bord');
                return;
            }

            $ancien = (float)$etab['taux_commission_scolarite'];

            $db->prepare('UPDATE etablissements SET taux_commission_scolarite = ? WHERE id = ?')
               ->execute([number_format($nouveau, 2, '.', ''), $etabId]);
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Modification du taux #' . $etabId . ' : ' . $e->getMessage());
            Session::flash('error', 'La modification n’a pas pu être enregistrée.');
            Response::redirect('/admin/tableaux-de-bord');
            return;
        }

        Journal::ecrire(Journal::TAUX_COMMISSION_MODIFIE, [
            'objet_type'       => 'etablissement',
            'objet_id'         => $etabId,
            'etablissement_id' => $etabId,
            'acteur_id'        => Session::adminId(),
            'acteur_role'      => Session::adminRole() ?? 'admin',
            'donnees'          => [
                'etablissement' => $etab['nom'],
                'ancien_taux'   => $ancien,
                'nouveau_taux'  => $nouveau,
            ],
        ]);

        Session::flash('success', sprintf(
            'Taux de %s : %s %% → %s %%. Les commissions déjà émises conservent leur taux gelé.',
            $etab['nom'],
            number_format($ancien, 2, ',', ' '),
            number_format($nouveau, 2, ',', ' ')
        ));

        Response::redirect('/admin/tableaux-de-bord');
    }

    /**
     * POST /admin/dossiers/{id}/revoquer-jeton — révocation et régénération
     * du jeton de vérification (spec §7.5).
     *
     * Invalide le récépissé déjà imprimé : tout scan de l'ancien QR affichera
     * « Document annulé ». Le candidat doit donc être prévenu, et son nouveau
     * récépissé régénéré — c'est fait ici, pas laissé à un geste manuel.
     */
    public function revoquerJeton(array $params): void
    {
        $dossierId = (int)($params['id'] ?? 0);
        $modele    = new Dossier();
        $dossier   = $modele->findById($dossierId);

        if ($dossier === null) {
            Session::flash('error', 'Dossier introuvable.');
            Response::redirect('/admin/dossiers');
            return;
        }

        $motif   = mb_substr(trim((string)($_POST['motif'] ?? '')), 0, 500);
        $nouveau = JetonDossier::revoquerEtRegenerer($dossierId, Session::adminId());

        if ($nouveau === null) {
            Session::flash('error', 'La révocation a échoué.');
            Response::redirect('/admin/dossiers');
            return;
        }

        // Le récépissé porte l'ancien QR : il doit être refait, sinon le
        // candidat garde un document que le guichet refusera.
        try {
            \Services\Orientation\RecepisseDossierService::generer($dossierId, true);
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Régénération du récépissé #' . $dossierId . ' : ' . $e->getMessage());
        }

        Journal::dossier(
            Journal::JETON_REVOQUE,
            $dossier,
            ['motif' => $motif ?: null, 'nouveau_code' => $nouveau['code_court']],
            ['acteur_id' => Session::adminId(), 'acteur_role' => Session::adminRole() ?? 'admin']
        );

        Session::flash('success', sprintf(
            'Jeton révoqué. Nouveau code de vérification : %s. Le récépissé a été régénéré — prévenez le candidat que son ancien document n’est plus valable.',
            JetonDossier::formaterCodeCourt($nouveau['code_court'])
        ));

        Response::redirect('/admin/dossiers');
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /** @return array<string,mixed>|null */
    private function utilisateurComplet(int $id): ?array
    {
        try {
            $st = Database::getInstance()->getConnection()->prepare(
                'SELECT u.*, s.nom AS serie_nom
                 FROM users u
                 LEFT JOIN series s ON s.id = u.serie_id
                 WHERE u.id = ? AND u.is_deleted = 0
                 LIMIT 1'
            );
            $st->execute([$id]);
            $user = $st->fetch(PDO::FETCH_ASSOC);

            if ($user === false) {
                return null;
            }

            // Le hachage du mot de passe n'a rien à faire dans une vue, même
            // réservée au super administrateur.
            unset($user['password_hash'], $user['email_token']);

            return $user;
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Utilisateur illisible #' . $id . ' : ' . $e->getMessage());

            return null;
        }
    }

    /** @return list<array{id:int,nom:string}> */
    private function etablissements(): array
    {
        try {
            $st = Database::getInstance()->getConnection()->query(
                'SELECT id, nom FROM etablissements ORDER BY nom'
            );

            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Établissements illisibles : ' . $e->getMessage());

            return [];
        }
    }

    /** @return list<string> */
    private function annees(): array
    {
        try {
            $st = Database::getInstance()->getConnection()->query(
                'SELECT DISTINCT annee_academique FROM dossiers_inscription
                 WHERE annee_academique IS NOT NULL
                 ORDER BY annee_academique DESC LIMIT 12'
            );

            return $st->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (\Throwable $e) {
            error_log('[Tracabilite] Années illisibles : ' . $e->getMessage());

            return [];
        }
    }

    private function date(mixed $valeur): ?string
    {
        if (!is_string($valeur) || trim($valeur) === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($valeur));

        return $date !== false && $date->format('Y-m-d') === trim($valeur) ? $date->format('Y-m-d') : null;
    }
}
