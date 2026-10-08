<?php
declare(strict_types=1);

namespace Core;

class Session
{
    /**
     * Durée de vie MAXIMALE d'une session utilisateur (front-office), en
     * secondes — deux heures.
     *
     * ── Pourquoi une durée propre au front-office ────────────────────────────
     *
     * Le cookie PHP vit SESSION_LIFETIME (30 jours) : c'est un choix assumé
     * pour les comptes d'établissement et d'administration, qui travaillent
     * toute la journée dans le back-office et que des déconnexions
     * intempestives empêchaient de travailler. Ce choix n'a jamais été valable
     * pour un candidat : il se connecte depuis un téléphone partagé, un
     * cybercafé, l'ordinateur d'un parent — et sa session y restait ouverte des
     * semaines, donnant à quiconque rouvrait le navigateur l'accès à son
     * dossier, à ses pièces d'identité et à ses paiements.
     *
     * ── Absolue, pas « d'inactivité » ────────────────────────────────────────
     *
     * Le délai court depuis la CONNEXION, pas depuis la dernière action. Une
     * expiration par inactivité se prolonge indéfiniment tant que l'onglet
     * rafraîchit quelque chose ; une session laissée ouverte sur un poste
     * public n'expirerait donc jamais. Deux heures après l'ouverture, la
     * session tombe, quoi qu'il arrive.
     *
     * ── Ce que cela NE touche pas ────────────────────────────────────────────
     *
     * Ni la session gestionnaire, ni la session administrateur : seules les
     * clés `user_*` sont retirées (cf. self::CLES_UTILISATEUR). Un poste de
     * guichet ne se fait pas déconnecter parce qu'un candidat s'est connecté
     * deux heures plus tôt sur le même navigateur.
     */
    public const DUREE_MAX_UTILISATEUR = 7200;

    /**
     * Les clés qui CONSTITUENT la session utilisateur.
     *
     * Liste unique : elle sert à l'expiration (self::expirerUtilisateurSiBesoin)
     * comme à la fermeture forcée par le middleware. Deux listes divergentes
     * laisseraient une clé orpheline derrière — et `isLoggedIn()` ne regarde
     * que `user_id`, donc l'oubli serait invisible jusqu'à l'incident.
     */
    private const CLES_UTILISATEUR = [
        'user_id', 'user_uuid', 'user_name', 'user_role',
        'user_serie_id', 'user_photo', 'login_at',
    ];

    /**
     * Ferme la session utilisateur si elle a dépassé sa durée maximale.
     *
     * Appelée UNE fois par requête, depuis le contrôleur frontal, juste après
     * `session_start()` : toutes les pages en bénéficient, y compris celles qui
     * lisent `Session::isLoggedIn()` sans passer par AuthMiddleware (accueil,
     * pages publiques d'orientation, navigation). La poser uniquement dans le
     * middleware aurait laissé ces pages afficher un utilisateur « connecté »
     * dont la session est morte.
     *
     * @return bool true si une session vient d'être fermée pour expiration.
     */
    public static function expirerUtilisateurSiBesoin(): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        $ouverteA = (int)($_SESSION['login_at'] ?? 0);

        // Session ouverte avant l'introduction du repère (utilisateurs déjà
        // connectés au moment du déploiement) : on l'horodate maintenant plutôt
        // que de les déconnecter tous d'un coup. Ils expireront normalement
        // deux heures plus tard.
        if ($ouverteA <= 0) {
            $_SESSION['login_at'] = time();
            return false;
        }

        // Horloge reculée ou repère falsifié : on ne fait pas confiance à une
        // date d'ouverture dans le futur, elle rendrait la session éternelle.
        if ($ouverteA > time()) {
            $_SESSION['login_at'] = time();
            return false;
        }

        if ((time() - $ouverteA) < self::DUREE_MAX_UTILISATEUR) {
            return false;
        }

        self::clearUtilisateur();

        // Aucun message n'est posé : l'écran de connexion ne signale plus
        // l'expiration (choix produit). La fermeture de session, elle, reste
        // entière — c'est elle qui protège un poste partagé, pas l'avis.
        return true;
    }

    /**
     * Retire la session utilisateur, et elle seule.
     *
     * Les sessions gestionnaire et administrateur éventuellement ouvertes dans
     * le même navigateur restent intactes — c'est la règle de cloisonnement des
     * espaces, elle vaut aussi pour une fermeture.
     */
    public static function clearUtilisateur(): void
    {
        foreach (self::CLES_UTILISATEUR as $cle) {
            unset($_SESSION[$cle]);
        }
    }

    /** Secondes restantes avant expiration de la session utilisateur (0 si close/expirée). */
    public static function secondesRestantesUtilisateur(): int
    {
        if (empty($_SESSION['user_id'])) {
            return 0;
        }
        $ouverteA = (int)($_SESSION['login_at'] ?? 0);
        if ($ouverteA <= 0) {
            return self::DUREE_MAX_UTILISATEUR;
        }
        return max(0, self::DUREE_MAX_UTILISATEUR - (time() - $ouverteA));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function userUuid(): ?string
    {
        return $_SESSION['user_uuid'] ?? null;
    }

    public static function userRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Compte "visiteur" (grand public — module Orientation) : parent,
     * étudiant ou toute personne qui suit ses dossiers d'inscription
     * sans être élève de terminale sur la plateforme.
     */
    public static function isVisiteur(): bool
    {
        return self::isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'visiteur';
    }

    public static function user(): array
    {
        return [
            'id'    => $_SESSION['user_id']    ?? null,
            'uuid'  => $_SESSION['user_uuid']  ?? null,
            'role'  => $_SESSION['user_role']  ?? null,
            'name'  => $_SESSION['user_name']  ?? null,
            'photo' => $_SESSION['user_photo'] ?? null,
        ];
    }

    // ── Gestionnaire (établissements orientation) ─────────────────────────
    public static function isGestionnaire(): bool
    {
        return !empty($_SESSION['gestionnaire_id']) && !empty($_SESSION['gestionnaire_etab_id']);
    }

    public static function gestionnaireId(): ?int
    {
        return isset($_SESSION['gestionnaire_id']) ? (int) $_SESSION['gestionnaire_id'] : null;
    }

    // ── Admin ─────────────────────────────────────────────────────────────
    public static function isAdmin(): bool
    {
        return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_2fa_ok']);
    }

    public static function adminId(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    }

    public static function adminRole(): ?string
    {
        return $_SESSION['admin_role'] ?? null;
    }
}
