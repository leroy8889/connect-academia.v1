<?php
declare(strict_types=1);

namespace Controllers\Communaute;

use Core\{Response, Session, Database};
use Models\Notification;

class ChatController
{
    public function salons(): void
    {
        $userId = Session::userId();
        $db     = Database::getInstance()->getConnection();

        $salons = $db->query(
            "SELECT id, nom, description, serie_tag, matiere_tag FROM salons WHERE is_active = 1 ORDER BY id ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $unreadNotifs = (new Notification())->getUnreadCount($userId);

        Response::view('communaute/chat/salons', [
            'pageTitle'    => "Chat — Connect'Academia",
            'extraCss'     => ['communaute.css'],
            'extraJs'      => ['api.js', 'components/notifications.js'],
            'salons'       => $salons,
            'unreadNotifs' => $unreadNotifs,
        ]);
    }

    public function salon(string $salon_id): void
    {
        $userId   = Session::userId();
        $salonId  = (int) $salon_id;
        $db       = Database::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT * FROM salons WHERE id = ? AND is_active = 1");
        $stmt->execute([$salonId]);
        $salon = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$salon) {
            Response::redirect('/communaute/chat');
            return;
        }

        // Derniers messages
        $stmt = $db->prepare(
            "SELECT m.id, m.contenu, m.created_at, u.nom, u.prenom, u.photo_profil, u.role
             FROM messages_chat m JOIN users u ON m.user_id = u.id
             WHERE m.salon_id = ? AND m.is_deleted = 0
             ORDER BY m.created_at DESC LIMIT 50"
        );
        $stmt->execute([$salonId]);
        $messages = array_reverse($stmt->fetchAll(\PDO::FETCH_ASSOC));

        $unreadNotifs = (new Notification())->getUnreadCount($userId);

        Response::view('communaute/chat/salon', [
            'pageTitle'    => e($salon['nom']) . " — Chat — Connect'Academia",
            'extraCss'     => ['communaute.css'],
            'extraJs'      => ['api.js', 'components/notifications.js', 'chat.js'],
            'salon'        => $salon,
            'messages'     => $messages,
            'unreadNotifs' => $unreadNotifs,
        ]);
    }

    // ── Long Polling API ──────────────────────────────────────────────────

    public function poll(string $salon_id): void
    {
        $userId    = Session::userId();
        $salonId   = (int) $salon_id;
        $lastId    = (int) ($_GET['last_id'] ?? 0);
        $timeout   = min((int) ($_GET['timeout'] ?? 25), 30);
        $db        = Database::getInstance()->getConnection();

        $deadline = time() + $timeout;

        while (time() < $deadline) {
            $stmt = $db->prepare(
                "SELECT m.id, m.contenu, m.created_at, u.id AS user_id, u.nom, u.prenom, u.photo_profil, u.role
                 FROM messages_chat m JOIN users u ON m.user_id = u.id
                 WHERE m.salon_id = ? AND m.id > ? AND m.is_deleted = 0
                 ORDER BY m.created_at ASC LIMIT 20"
            );
            $stmt->execute([$salonId, $lastId]);
            $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($messages)) {
                foreach ($messages as &$msg) {
                    $msg['photo_profil'] = \Models\User::normalizePhotoPath($msg['photo_profil'] ?? null);
                    $msg['is_me']        = ((int) $msg['user_id'] === $userId);
                }
                unset($msg);
                Response::json(['success' => true, 'data' => ['messages' => $messages]]);
            }

            sleep(2);
        }

        Response::json(['success' => true, 'data' => ['messages' => []]]);
    }

    public function send(string $salon_id): void
    {
        $userId  = Session::userId();
        $salonId = (int) $salon_id;
        $input   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $contenu = trim($input['contenu'] ?? '');

        if (empty($contenu) || mb_strlen($contenu) > 1000) {
            Response::json(['success' => false, 'error' => ['message' => 'Message invalide']], 400);
        }

        $db = Database::getInstance()->getConnection();

        // Vérifier que le salon existe et est actif
        $stmt = $db->prepare("SELECT id FROM salons WHERE id = ? AND is_active = 1");
        $stmt->execute([$salonId]);
        if (!$stmt->fetch()) {
            Response::json(['success' => false, 'error' => ['message' => 'Salon introuvable']], 404);
        }

        $stmt = $db->prepare(
            "INSERT INTO messages_chat (salon_id, user_id, contenu, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$salonId, $userId, $contenu]);

        Response::json(['success' => true, 'message' => 'Message envoyé'], 201);
    }
}
