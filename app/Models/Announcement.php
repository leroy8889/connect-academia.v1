<?php
declare(strict_types=1);

namespace Models;

use PDO;

class Announcement extends BaseModel
{
    protected string $table = 'announcements';

    /**
     * Retourne toutes les annonces paginées pour l'admin.
     */
    public function getAll(int $limit = 50, int $offset = 0): array
    {
        return $this->query(
            "SELECT a.*, adm.nom AS admin_nom, adm.prenom AS admin_prenom
             FROM announcements a
             LEFT JOIN admins adm ON adm.id = a.created_by
             ORDER BY a.created_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retourne l'annonce active la plus récente visible en ce moment.
     * Utilisée par le Hub front-office.
     */
    public function getActiveForHub(): array|false
    {
        return $this->query(
            "SELECT * FROM announcements
             WHERE is_active = 1
               AND (date_debut IS NULL OR date_debut <= NOW())
               AND (date_fin   IS NULL OR date_fin   >= NOW())
             ORDER BY is_pinned DESC, created_at DESC
             LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crée une annonce et retourne son id.
     */
    public function create(array $d): int
    {
        $this->query(
            "INSERT INTO announcements
               (titre, contenu, image_url, type, badge_label, cta_label, cta_url,
                date_debut, date_fin, is_active, is_pinned, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $d['titre'],
                $d['contenu'],
                $d['image_url']   ?: null,
                $d['type']        ?? 'info',
                $d['badge_label'] ?: null,
                $d['cta_label']   ?: null,
                $d['cta_url']     ?: null,
                $d['date_debut']  ?: null,
                $d['date_fin']    ?: null,
                (int) ($d['is_active'] ?? 1),
                (int) ($d['is_pinned'] ?? 0),
                $d['created_by']  ?? null,
            ]
        );
        return $this->lastInsertId();
    }

    /**
     * Met à jour une annonce existante.
     */
    public function update(int $id, array $d): bool
    {
        return $this->query(
            "UPDATE announcements SET
               titre       = ?,
               contenu     = ?,
               image_url   = ?,
               type        = ?,
               badge_label = ?,
               cta_label   = ?,
               cta_url     = ?,
               date_debut  = ?,
               date_fin    = ?,
               is_active   = ?,
               is_pinned   = ?,
               updated_at  = NOW()
             WHERE id = ?",
            [
                $d['titre'],
                $d['contenu'],
                $d['image_url']   ?: null,
                $d['type']        ?? 'info',
                $d['badge_label'] ?: null,
                $d['cta_label']   ?: null,
                $d['cta_url']     ?: null,
                $d['date_debut']  ?: null,
                $d['date_fin']    ?: null,
                (int) ($d['is_active'] ?? 1),
                (int) ($d['is_pinned'] ?? 0),
                $id,
            ]
        )->rowCount() > 0;
    }

    /**
     * Active / désactive une annonce.
     */
    public function toggle(int $id): array|false
    {
        $this->query(
            "UPDATE announcements SET is_active = 1 - is_active, updated_at = NOW() WHERE id = ?",
            [$id]
        );
        return $this->findById($id);
    }

    /**
     * Suppression définitive.
     */
    public function delete(int $id): bool
    {
        return $this->query(
            "DELETE FROM announcements WHERE id = ?",
            [$id]
        )->rowCount() > 0;
    }

    public function countAll(): int
    {
        return $this->count();
    }

    public function countActive(): int
    {
        return $this->count(
            "is_active = 1 AND (date_debut IS NULL OR date_debut <= NOW()) AND (date_fin IS NULL OR date_fin >= NOW())"
        );
    }
}
