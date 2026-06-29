<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Response;
use Models\ContactMessage;

class ContactController
{
    private ContactMessage $model;

    public function __construct()
    {
        $this->model = new ContactMessage();
    }

    public function index(): void
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $limit   = 20;
        $offset  = ($page - 1) * $limit;
        $filter  = $_GET['filter'] ?? 'all';

        $messages = $this->model->getAll($limit, $offset);
        $total    = $this->model->countTotal();
        $unread   = $this->model->countUnread();
        $pages    = (int) ceil($total / $limit);

        Response::view('admin/contact/index', [
            'pageTitle'         => 'Messages de contact — Admin',
            'breadcrumbSection' => 'Communication',
            'breadcrumbPage'    => 'Messages de contact',
            'messages'          => $messages,
            'total'             => $total,
            'unread'            => $unread,
            'page'              => $page,
            'pages'             => $pages,
            'filter'            => $filter,
        ], 'admin');
    }

    public function markRead(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$id) {
            Response::json(['success' => false, 'message' => 'ID invalide.'], 422);
        }
        $this->model->markRead($id);
        Response::json(['success' => true]);
    }

    public function markAllRead(): void
    {
        $this->model->markAllRead();
        Response::json(['success' => true, 'message' => 'Tous les messages marqués comme lus.']);
    }

    public function delete(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if (!$id) {
            Response::json(['success' => false, 'message' => 'ID invalide.'], 422);
        }
        $ok = $this->model->delete($id);
        Response::json(['success' => $ok]);
    }
}
