<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session};
use Models\Announcement;

class AnnoncesController
{
    private Announcement $model;

    public function __construct()
    {
        $this->model = new Announcement();
    }

    // ── PAGE PRINCIPALE ─────────────────────────────────────────────────────

    public function index(): void
    {
        $annonces     = $this->model->getAll(100);
        $totalAll     = $this->model->countAll();
        $totalActive  = $this->model->countActive();

        Response::view('admin/annonces/index', [
            'pageTitle'         => "Annonces — Connect'Academia",
            'breadcrumbSection' => 'Communauté',
            'breadcrumbPage'    => 'Annonces',
            'annonces'          => $annonces,
            'totalAll'          => $totalAll,
            'totalActive'       => $totalActive,
        ], 'admin');
    }

    // ── SHOW (pour edit fetch) ───────────────────────────────────────────────

    public function show(array $params): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $annonce = $this->model->findById($id);

        if (!$annonce) {
            Response::json(['success' => false, 'message' => 'Annonce introuvable.'], 404);
            return;
        }

        Response::json(['success' => true, 'annonce' => $annonce]);
    }

    // ── CREATE ───────────────────────────────────────────────────────────────

    public function store(): void
    {
        $data = $this->extractFields();
        $errors = $this->validate($data);

        if ($errors) {
            Response::json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        $data['created_by'] = Session::adminId();

        try {
            $id       = $this->model->create($data);
            $annonce  = $this->model->findById($id);
            Response::json(['success' => true, 'annonce' => $annonce], 201);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    // ── UPDATE ───────────────────────────────────────────────────────────────

    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$id || !$this->model->findById($id)) {
            Response::json(['success' => false, 'message' => 'Annonce introuvable.'], 404);
            return;
        }

        $data   = $this->extractFields();
        $errors = $this->validate($data);

        if ($errors) {
            Response::json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        try {
            $this->model->update($id, $data);
            $annonce = $this->model->findById($id);
            Response::json(['success' => true, 'annonce' => $annonce]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    // ── TOGGLE ACTIF ─────────────────────────────────────────────────────────

    public function toggle(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $annonce = $this->model->toggle($id);

        if (!$annonce) {
            Response::json(['success' => false, 'message' => 'Annonce introuvable.'], 404);
            return;
        }

        Response::json(['success' => true, 'annonce' => $annonce]);
    }

    // ── DELETE ───────────────────────────────────────────────────────────────

    public function delete(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        if (!$this->model->delete($id)) {
            Response::json(['success' => false, 'message' => 'Annonce introuvable.'], 404);
            return;
        }

        Response::json(['success' => true]);
    }

    // ── API PUBLIQUE — Hub front-office ─────────────────────────────────────

    public function activeForHub(): void
    {
        $annonce = $this->model->getActiveForHub();
        Response::json(['success' => true, 'annonce' => $annonce ?: null]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function extractFields(): array
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($body)) {
            $body = $_POST;
        }

        return [
            'titre'       => trim($body['titre']       ?? ''),
            'contenu'     => trim($body['contenu']      ?? ''),
            'image_url'   => trim($body['image_url']    ?? ''),
            'type'        => in_array($body['type'] ?? '', ['info','warning','success','urgent'])
                                ? $body['type']
                                : 'info',
            'badge_label' => trim($body['badge_label']  ?? ''),
            'cta_label'   => trim($body['cta_label']    ?? ''),
            'cta_url'     => trim($body['cta_url']      ?? ''),
            'date_debut'  => !empty($body['date_debut']) ? $body['date_debut'] : null,
            'date_fin'    => !empty($body['date_fin'])   ? $body['date_fin']   : null,
            'is_active'   => isset($body['is_active'])  ? (int)(bool)$body['is_active']  : 1,
            'is_pinned'   => isset($body['is_pinned'])  ? (int)(bool)$body['is_pinned']  : 0,
        ];
    }

    private function validate(array $d): array
    {
        $errors = [];
        if (empty($d['titre']))   $errors['titre']   = 'Le titre est requis.';
        if (empty($d['contenu'])) $errors['contenu'] = 'Le contenu est requis.';
        if ($d['date_debut'] && $d['date_fin'] && $d['date_debut'] > $d['date_fin']) {
            $errors['date_fin'] = 'La date de fin doit être après la date de début.';
        }
        return $errors;
    }
}
