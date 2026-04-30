<?php
declare(strict_types=1);

namespace Controllers\Communaute;

use Core\{Response, Session};
use Models\Notification;

class NotificationController
{
    public function index(): void
    {
        $userId       = Session::userId();
        $notifModel   = new Notification();

        Response::json([
            'success' => true,
            'data'    => [
                'notifications' => $notifModel->getForUser($userId, 30),
                'unread_count'  => $notifModel->getUnreadCount($userId),
            ],
        ]);
    }

    public function count(): void
    {
        $userId = Session::userId();
        Response::json(['success' => true, 'data' => ['unread_count' => (new Notification())->getUnreadCount($userId)]]);
    }

    public function read(string $id): void
    {
        $userId = Session::userId();
        (new Notification())->markAsRead((int) $id, $userId);
        Response::json(['success' => true]);
    }

    public function readAll(): void
    {
        $userId = Session::userId();
        (new Notification())->markAllAsRead($userId);
        Response::json(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
    }
}
