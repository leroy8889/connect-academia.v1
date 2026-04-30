<?php
declare(strict_types=1);

namespace Controllers;

use Core\{Response, Session};
use Models\Notification;

class NotificationController
{
    public function index(): void
    {
        $userId = Session::userId();
        $notifModel = new Notification();

        $notifications = $notifModel->getForUser($userId, 30);
        $unreadCount   = $notifModel->getUnreadCount($userId);

        Response::json([
            'success' => true,
            'data'    => [
                'notifications' => $notifications,
                'unread_count'  => $unreadCount,
            ],
        ]);
    }

    public function count(): void
    {
        $userId = Session::userId();
        $notifModel = new Notification();
        $unreadCount = $notifModel->getUnreadCount($userId);

        Response::json([
            'success' => true,
            'data'    => ['unread_count' => $unreadCount],
        ]);
    }

    public function read(string $id): void
    {
        $notifId = (int) $id;
        $userId = Session::userId();

        $notifModel = new Notification();
        $notifModel->markAsRead($notifId, $userId);

        Response::json(['success' => true]);
    }

    public function readAll(): void
    {
        $userId = Session::userId();

        $notifModel = new Notification();
        $notifModel->markAllAsRead($userId);

        Response::json(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
    }
}

