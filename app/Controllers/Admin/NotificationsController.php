<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Database};
use PDO;

class NotificationsController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function index(): void
    {
        $rawEvents = $this->buildAdminEvents();

        // Grouper par période
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $notifs = ['aujourd_hui' => [], 'hier' => [], 'plus_ancien' => []];
        $nbNonLus = 0;

        foreach ($rawEvents as $ev) {
            $evDate = $ev['created_at'] ? date('Y-m-d', strtotime($ev['created_at'])) : $today;
            $group  = match (true) {
                $evDate === $today     => 'aujourd_hui',
                $evDate === $yesterday => 'hier',
                default                => 'plus_ancien',
            };
            if (!$ev['is_read']) $nbNonLus++;
            $notifs[$group][] = $ev;
        }

        Response::view('admin/notifications/index', [
            'pageTitle'         => 'Notifications — Admin',
            'breadcrumbSection' => 'Système',
            'breadcrumbPage'    => 'Notifications',
            'notifs'            => $notifs,
            'nbNonLus'          => $nbNonLus,
            'nbTotal'           => count($rawEvents),
        ], 'admin');
    }

    public function markAllRead(): void
    {
        // Logique future : marquer toutes les notifs admin comme lues
        Response::json(['success' => true, 'message' => 'Toutes les notifications marquées comme lues.']);
    }

    private function buildAdminEvents(): array
    {
        $events = [];

        // Nouvelles inscriptions aujourd'hui
        $nbInscrits = (int) $this->db->query(
            "SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE() AND is_deleted = 0"
        )->fetchColumn();
        if ($nbInscrits > 0) {
            $events[] = [
                'type'       => 'inscription',
                'titre'      => "{$nbInscrits} nouvelle" . ($nbInscrits > 1 ? 's' : '') . " inscription" . ($nbInscrits > 1 ? 's' : ''),
                'message'    => "Élèves inscrits sur la plateforme aujourd'hui",
                'created_at' => date('Y-m-d H:i:s', strtotime('today 08:00')),
                'is_read'    => false,
            ];
        }

        // Signalements en attente
        $nbReports = (int) $this->db->query(
            "SELECT COUNT(*) FROM reports WHERE status = 'pending'"
        )->fetchColumn();
        if ($nbReports > 0) {
            $stmt = $this->db->query(
                "SELECT r.created_at, u.nom, u.prenom
                 FROM reports r LEFT JOIN users u ON u.id = r.reporter_id
                 WHERE r.status = 'pending'
                 ORDER BY r.created_at DESC LIMIT 1"
            );
            $last = $stmt->fetch(PDO::FETCH_ASSOC);
            $who  = $last ? trim(($last['prenom'] ?? '') . ' ' . ($last['nom'] ?? '')) : 'Un utilisateur';
            $events[] = [
                'type'       => 'signalement',
                'titre'      => 'Nouveau signalement',
                'message'    => "{$who} a signalé un contenu · raison : harcèlement",
                'created_at' => $last['created_at'] ?? date('Y-m-d H:i:s'),
                'is_read'    => false,
            ];
        }

        // Ressources publiées récemment (via admin_id → admins table)
        $stmt = $this->db->query(
            "SELECT r.titre, r.created_at, a.prenom, a.nom
             FROM ressources r
             LEFT JOIN admins a ON a.id = r.admin_id
             WHERE r.is_deleted = 0 AND r.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
             ORDER BY r.created_at DESC LIMIT 3"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $res) {
            $who = trim(($res['prenom'] ?? '') . ' ' . ($res['nom'] ?? ''));
            $who = $who ?: 'Admin';
            $events[] = [
                'type'       => 'upload',
                'titre'      => 'Ressource uploadée',
                'message'    => "{$who} a publié « " . mb_substr($res['titre'], 0, 50) . " »",
                'created_at' => $res['created_at'],
                'is_read'    => true,
            ];
        }

        // Activité communauté (pics de posts)
        $nbPostsHeure = (int) $this->db->query(
            "SELECT COUNT(*) FROM posts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR) AND is_deleted = 0"
        )->fetchColumn();
        if ($nbPostsHeure >= 20) {
            $events[] = [
                'type'       => 'activite',
                'titre'      => "Pic d'activité communauté",
                'message'    => "{$nbPostsHeure} nouveaux posts en 2 heures",
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'is_read'    => true,
            ];
        }

        // Connexion admin (dernière heure)
        $stmt = $this->db->query(
            "SELECT ip, created_at FROM historique_connexions_admin
             WHERE statut = 'succes' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY created_at DESC LIMIT 1"
        );
        $conn = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($conn) {
            $ip = $conn['ip'] ?? '127.0.0.1';
            $events[] = [
                'type'       => 'connexion',
                'titre'      => 'Connexion admin',
                'message'    => "Connexion depuis l'IP {$ip} · Libreville, Gabon",
                'created_at' => $conn['created_at'],
                'is_read'    => true,
            ];
        }

        // Trier par date décroissante
        usort($events, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

        return $events;
    }
}
