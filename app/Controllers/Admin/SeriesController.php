<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session};
use Core\Database;
use PDO;

class SeriesController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function index(): void
    {
        $series = $this->db->query(
            "SELECT s.*, COUNT(u.id) AS nb_users,
                    (SELECT COUNT(*) FROM ressources r
                     JOIN matieres m ON m.id = r.matiere_id
                     WHERE m.serie_id = s.id AND r.is_deleted = 0) AS nb_ressources
             FROM series s
             LEFT JOIN users u ON u.serie_id = s.id AND u.is_deleted = 0
             WHERE s.is_active = 1
             GROUP BY s.id
             ORDER BY s.nom"
        )->fetchAll(PDO::FETCH_ASSOC);

        $activeSerieId = (int) ($_GET['serie'] ?? ($series[0]['id'] ?? 0));
        $activeSerie   = null;
        $matieres      = [];

        foreach ($series as $s) {
            if ((int)$s['id'] === $activeSerieId) {
                $activeSerie = $s;
            }
        }

        if ($activeSerieId) {
            $stmt = $this->db->prepare(
                "SELECT m.id, m.nom, m.description, m.coef, m.icone, m.is_active,
                        COUNT(DISTINCT r.id) AS nb_ressources,
                        COUNT(DISTINCT r.admin_id) AS nb_enseignants,
                        COALESCE(AVG(p.pourcentage), 0) AS progression_moyenne
                 FROM matieres m
                 LEFT JOIN ressources r ON r.matiere_id = m.id AND r.is_deleted = 0
                 LEFT JOIN progressions p ON p.ressource_id = r.id
                 WHERE m.serie_id = ? AND m.is_active = 1
                 GROUP BY m.id
                 ORDER BY m.coef DESC, m.nom ASC"
            );
            $stmt->execute([$activeSerieId]);
            $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        Response::view('admin/series/index', [
            'pageTitle'         => 'Séries & Matières — Admin',
            'breadcrumbSection' => 'Apprentissage',
            'breadcrumbPage'    => 'Séries & Matières',
            'series'            => $series,
            'activeSerie'       => $activeSerie,
            'matieres'          => $matieres,
        ], 'admin');
    }

    public function storeSerie(): void
    {
        $nom    = trim($_POST['nom'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $color  = trim($_POST['couleur'] ?? '#8B52FA');

        if (!$nom) {
            Response::json(['success' => false, 'message' => 'Nom requis.'], 422);
        }

        $exists = $this->db->prepare("SELECT id FROM series WHERE nom = ?");
        $exists->execute([$nom]);
        if ($exists->fetch()) {
            Response::json(['success' => false, 'message' => 'Une série avec ce nom existe déjà.'], 409);
        }

        $this->db->prepare(
            "INSERT INTO series (nom, description, couleur) VALUES (?, ?, ?)"
        )->execute([$nom, $desc, $color]);

        Response::json(['success' => true, 'message' => "Série {$nom} créée avec succès."]);
    }

    public function storeMatiere(): void
    {
        $nom      = trim($_POST['nom'] ?? '');
        $serieId  = (int) ($_POST['serie_id'] ?? 0);
        $coef     = max(1, (int) ($_POST['coef'] ?? 1));
        $icone    = trim($_POST['icone'] ?? '');

        if (!$nom || !$serieId) {
            Response::json(['success' => false, 'message' => 'Nom et série requis.'], 422);
        }

        $this->db->prepare(
            "INSERT INTO matieres (nom, serie_id, coef, icone) VALUES (?, ?, ?, ?)"
        )->execute([$nom, $serieId, $coef, $icone ?: null]);

        Response::json(['success' => true, 'message' => "Matière {$nom} créée avec succès."]);
    }
}
