<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Database};
use PDO;

class SubjectsController
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * GET /admin/subjects — Gestion des matières
     */
    public function index(): void
    {
        // Récupérer la liste officielle depuis les paramètres
        $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'matieres_list'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $officialSubjects = $row ? array_map('trim', explode(',', $row['setting_value'])) : [];

        // Statistiques par matière (posts)
        $stmt = $this->db->prepare(
            "SELECT matiere_tag AS subject,
                    COUNT(*) AS posts_count,
                    COUNT(DISTINCT user_id) AS users_count,
                    SUM(likes_count) AS total_likes,
                    SUM(comments_count) AS total_comments,
                    MAX(created_at) AS last_activity
             FROM posts
             WHERE is_deleted = 0 AND matiere_tag IS NOT NULL AND matiere_tag != ''
             GROUP BY matiere_tag
             ORDER BY posts_count DESC"
        );
        $stmt->execute();
        $subjectStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Indexer par nom de matière
        $statsMap = [];
        foreach ($subjectStats as $s) {
            $statsMap[$s['subject']] = $s;
        }

        // Construire la liste complète
        $subjects = [];
        foreach ($officialSubjects as $name) {
            $stat = $statsMap[$name] ?? null;
            $subjects[] = [
                'name'           => $name,
                'posts_count'    => $stat ? (int) $stat['posts_count'] : 0,
                'users_count'    => $stat ? (int) $stat['users_count'] : 0,
                'total_likes'    => $stat ? (int) $stat['total_likes'] : 0,
                'total_comments' => $stat ? (int) $stat['total_comments'] : 0,
                'last_activity'  => $stat['last_activity'] ?? null,
                'is_official'    => true,
            ];
            unset($statsMap[$name]);
        }

        // Ajouter les matières présentes dans les posts mais pas dans la liste officielle
        foreach ($statsMap as $name => $stat) {
            $subjects[] = [
                'name'           => $name,
                'posts_count'    => (int) $stat['posts_count'],
                'users_count'    => (int) $stat['users_count'],
                'total_likes'    => (int) $stat['total_likes'],
                'total_comments' => (int) $stat['total_comments'],
                'last_activity'  => $stat['last_activity'],
                'is_official'    => false,
            ];
        }

        // Classes
        $stmtClasses = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'classes_list'");
        $stmtClasses->execute();
        $rowClasses = $stmtClasses->fetch(PDO::FETCH_ASSOC);
        $classes = $rowClasses ? array_map('trim', explode(',', $rowClasses['setting_value'])) : [];

        // Totaux
        $totalPosts = array_sum(array_column($subjects, 'posts_count'));

        Response::view('admin/subjects/index', [
            'pageTitle'   => 'Gestion des Matières — Admin StudyLink',
            'headerTitle' => 'Subjects & Classes',
            'subjects'    => $subjects,
            'classes'     => $classes,
            'totalPosts'  => $totalPosts,
        ], 'admin');
    }
}

