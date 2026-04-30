<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session, Database};
use PDO;

class DashboardController
{
    /** @var PDO */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function index(): void
    {
        $stats = $this->getKpis();
        $recentActivity = $this->getRecentActivity();
        $subjectActivity = $this->getSubjectActivity();
        $chartData = $this->getChartData();

        Response::view('admin/dashboard/index', [
            'pageTitle'       => 'Dashboard — Admin StudyLink',
            'stats'           => $stats,
            'recentActivity'  => $recentActivity,
            'subjectActivity' => $subjectActivity,
            'chartData'       => $chartData,
        ], 'admin');
    }

    public function stats(): void
    {
        $period = (int) ($_GET['period'] ?? 30);
        if (!in_array($period, [7, 30, 90], true)) {
            $period = 30;
        }

        $kpis = $this->getKpis();
        $charts = $this->getChartData($period);
        $recentActivity = $this->getRecentActivity();
        $subjectActivity = $this->getSubjectActivity();

        Response::json([
            'success' => true,
            'data'    => [
                'kpis'             => $kpis,
                'charts'           => $charts,
                'recent_activity'  => $recentActivity,
                'subject_activity' => $subjectActivity,
            ],
        ]);
    }

    private function getKpis(): array
    {
        // Total étudiants
        $totalStudents = $this->countQuery(
            "SELECT COUNT(*) as total FROM users WHERE role = 'eleve' AND is_deleted = 0"
        );
        $studentsLastMonth = $this->countQuery(
            "SELECT COUNT(*) as total FROM users WHERE role = 'eleve' AND is_deleted = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)"
        );
        $studentsGrowth = $studentsLastMonth > 0
            ? round((($totalStudents - $studentsLastMonth) / $studentsLastMonth) * 100, 1)
            : 0;

        // Enseignants actifs
        $activeTeachers = $this->countQuery(
            "SELECT COUNT(*) as total FROM users WHERE role = 'enseignant' AND is_deleted = 0 AND is_active = 1"
        );
        $teachersLastMonth = $this->countQuery(
            "SELECT COUNT(*) as total FROM users WHERE role = 'enseignant' AND is_deleted = 0 AND is_active = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)"
        );
        $teachersGrowth = $teachersLastMonth > 0
            ? round((($activeTeachers - $teachersLastMonth) / $teachersLastMonth) * 100, 1)
            : 0;

        // Utilisateurs actifs (MAU - connectés ce mois)
        $activeUsers = $this->countQuery(
            "SELECT COUNT(*) as total FROM users WHERE is_deleted = 0 AND is_active = 1 AND last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $activeUsersLastMonth = $this->countQuery(
            "SELECT COUNT(*) as total FROM users WHERE is_deleted = 0 AND is_active = 1 AND last_login >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND last_login < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $activeUsersGrowth = $activeUsersLastMonth > 0
            ? round((($activeUsers - $activeUsersLastMonth) / $activeUsersLastMonth) * 100, 1)
            : 0;

        // Taux d'engagement (posts + commentaires / utilisateurs actifs)
        $totalPosts = $this->countQuery(
            "SELECT COUNT(*) as total FROM posts WHERE is_deleted = 0"
        );
        $totalComments = $this->countQuery(
            "SELECT COUNT(*) as total FROM comments WHERE is_deleted = 0"
        );
        $totalUsers = $this->countQuery(
            "SELECT COUNT(*) as total FROM users WHERE is_deleted = 0 AND is_active = 1"
        );
        // Actions par utilisateur actif, plafonné à 99.9 %
        $actionsPerUser = $totalUsers > 0 ? ($totalPosts + $totalComments) / $totalUsers : 0;
        $engagementRate = round(min($actionsPerUser * 10, 99.9), 1);

        // Calculer variation engagement (simplifiée)
        $postsThisMonth = $this->countQuery(
            "SELECT COUNT(*) as total FROM posts WHERE is_deleted = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $postsLastMonth = $this->countQuery(
            "SELECT COUNT(*) as total FROM posts WHERE is_deleted = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $engagementGrowth = $postsLastMonth > 0
            ? round((($postsThisMonth - $postsLastMonth) / $postsLastMonth) * 100, 1)
            : 0;

        return [
            'total_students'     => $totalStudents,
            'students_growth'    => $studentsGrowth,
            'active_teachers'    => $activeTeachers,
            'teachers_growth'    => $teachersGrowth,
            'active_users'       => $activeUsers,
            'active_users_growth'=> $activeUsersGrowth,
            'engagement_rate'    => min($engagementRate, 99.9),
            'engagement_growth'  => $engagementGrowth,
            'total_posts'        => $totalPosts,
            'total_comments'     => $totalComments,
            'pending_reports'    => $this->countQuery(
                "SELECT COUNT(*) as total FROM reports WHERE status = 'pending'"
            ),
        ];
    }

    private function getChartData(int $days = 30): array
    {
        // Inscriptions sur la période demandée
        $stmt = $this->db->prepare(
            "SELECT DATE(created_at) as date, 
                    SUM(role = 'eleve') as students,
                    SUM(role = 'enseignant') as teachers
             FROM users 
             WHERE is_deleted = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );
        $stmt->execute([$days]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Créer un tableau avec toutes les dates (même sans données)
        $dataByDate = [];
        foreach ($rows as $row) {
            $dataByDate[$row['date']] = [
                'students' => (int) $row['students'],
                'teachers' => (int) $row['teachers'],
            ];
        }

        $labels = [];
        $students = [];
        $teachers = [];
        $cumulative = 0;

        for ($i = $days; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $displayLabel = strtoupper(date('M d', strtotime($date)));
            $labels[] = $displayLabel;

            $dayStudents = $dataByDate[$date]['students'] ?? 0;
            $dayTeachers = $dataByDate[$date]['teachers'] ?? 0;
            $cumulative += $dayStudents + $dayTeachers;

            $students[] = $dayStudents;
            $teachers[] = $dayTeachers;
        }

        return [
            'registrations' => [
                'labels'     => $labels,
                'students'   => $students,
                'teachers'   => $teachers,
                'cumulative' => $cumulative,
            ],
        ];
    }

    private function getSubjectActivity(): array
    {
        $stmt = $this->db->prepare(
            "SELECT matiere_tag as subject, COUNT(*) as count
             FROM posts
             WHERE is_deleted = 0 AND matiere_tag IS NOT NULL AND matiere_tag != ''
             GROUP BY matiere_tag
             ORDER BY count DESC
             LIMIT 5"
        );
        $stmt->execute();
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subjects)) {
            return [
                ['subject' => 'Physique-Chimie', 'count' => 0, 'percentage' => 0],
                ['subject' => 'Mathématiques', 'count' => 0, 'percentage' => 0],
                ['subject' => 'Français', 'count' => 0, 'percentage' => 0],
                ['subject' => 'Histoire-Géographie', 'count' => 0, 'percentage' => 0],
                ['subject' => 'SVT', 'count' => 0, 'percentage' => 0],
            ];
        }

        $maxCount = (int) $subjects[0]['count'];
        $result = [];
        foreach ($subjects as $s) {
            $result[] = [
                'subject'    => $s['subject'],
                'count'      => (int) $s['count'],
                'percentage' => $maxCount > 0 ? round(((int) $s['count'] / $maxCount) * 100) : 0,
            ];
        }

        return $result;
    }

    private function getRecentActivity(int $limit = 10): array
    {
        $activities = [];

        // Derniers utilisateurs inscrits
        $stmt = $this->db->prepare(
            "SELECT u.id, u.nom, u.prenom, u.role, u.photo_profil, u.classe, u.matiere, u.created_at,
                    'new_user' as activity_type
             FROM users u
             WHERE u.is_deleted = 0
             ORDER BY u.created_at DESC
             LIMIT 3"
        );
        $stmt->execute();
        $newUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($newUsers as $user) {
            $activities[] = [
                'user_name'  => $user['prenom'] . ' ' . $user['nom'],
                'user_role'  => $this->translateRole($user['role']),
                'user_photo' => $user['photo_profil'],
                'action'     => $this->getUserAction($user),
                'group'      => $user['matiere'] ?: ($user['classe'] ?: 'Général'),
                'timestamp'  => $this->timeAgo($user['created_at']),
                'status'     => 'success',
            ];
        }

        // Derniers posts
        $stmt = $this->db->prepare(
            "SELECT p.id, p.type, p.matiere_tag, p.created_at, 
                    u.nom, u.prenom, u.role, u.photo_profil
             FROM posts p
             INNER JOIN users u ON p.user_id = u.id
             WHERE p.is_deleted = 0
             ORDER BY p.created_at DESC
             LIMIT 3"
        );
        $stmt->execute();
        $newPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($newPosts as $post) {
            $activities[] = [
                'user_name'  => $post['prenom'] . ' ' . $post['nom'],
                'user_role'  => $this->translateRole($post['role']),
                'user_photo' => $post['photo_profil'],
                'action'     => $this->getPostAction($post['type']),
                'group'      => $post['matiere_tag'] ?: 'Général',
                'timestamp'  => $this->timeAgo($post['created_at']),
                'status'     => 'success',
            ];
        }

        // Derniers signalements
        $stmt = $this->db->prepare(
            "SELECT r.id, r.reason, r.status, r.created_at,
                    u.nom, u.prenom, u.role, u.photo_profil
             FROM reports r
             INNER JOIN users u ON r.reporter_id = u.id
             ORDER BY r.created_at DESC
             LIMIT 3"
        );
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reports as $report) {
            $activities[] = [
                'user_name'  => $report['prenom'] . ' ' . $report['nom'],
                'user_role'  => $this->translateRole($report['role']),
                'user_photo' => $report['photo_profil'],
                'action'     => 'Reported a moderation issue',
                'group'      => $this->translateReason($report['reason']),
                'timestamp'  => $this->timeAgo($report['created_at']),
                'status'     => $report['status'] === 'pending' ? 'pending' : 'success',
            ];
        }

        // Trier par timestamp (plus récent d'abord) - on utilise un tri approximatif
        usort($activities, function ($a, $b) {
            return $this->timeAgoToSeconds($a['timestamp']) <=> $this->timeAgoToSeconds($b['timestamp']);
        });

        return array_slice($activities, 0, $limit);
    }

    private function countQuery(string $sql): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    private function translateRole(string $role): string
    {
        return match ($role) {
            'eleve'      => 'Student',
            'enseignant' => 'Teacher',
            'admin'      => 'Admin',
            default      => $role,
        };
    }

    private function translateReason(string $reason): string
    {
        return match ($reason) {
            'inappropriate' => 'Inappropriate Content',
            'spam'          => 'Spam',
            'harassment'    => 'Harassment',
            'other'         => 'General Chat',
            default         => $reason,
        };
    }

    private function getUserAction(array $user): string
    {
        if ($user['role'] === 'enseignant') {
            return 'Uploaded new lecture notes';
        }
        return 'Joined ' . ($user['classe'] ?: 'the platform');
    }

    private function getPostAction(string $type): string
    {
        return match ($type) {
            'question'  => 'Posted a new question',
            'ressource' => 'Uploaded new lecture notes',
            'annonce'   => 'Posted an announcement',
            default     => 'Created a new post',
        };
    }

    private function timeAgo(string $datetime): string
    {
        $now = new \DateTime();
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
        return 'Just now';
    }

    private function timeAgoToSeconds(string $timeAgo): int
    {
        if ($timeAgo === 'Just now') return 0;
        preg_match('/(\d+)\s+(year|month|day|hour|min)/', $timeAgo, $m);
        if (empty($m)) return 0;
        $n = (int) $m[1];
        return match ($m[2]) {
            'year'  => $n * 31536000,
            'month' => $n * 2592000,
            'day'   => $n * 86400,
            'hour'  => $n * 3600,
            'min'   => $n * 60,
            default => 0,
        };
    }
}

