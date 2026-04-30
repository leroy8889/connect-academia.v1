<?php
declare(strict_types=1);

namespace Controllers;

use Core\{Response, Session, Uploader};
use Models\{User, Post, Follow, Bookmark, Comment, Notification};

class UserController
{
    public function me(): void
    {
        $userId = Session::userId();
        $this->showProfile($userId);
    }

    public function show(string $id): void
    {
        $this->showProfile((int) $id);
    }

    private function showProfile(int $profileId): void
    {
        $userModel = new User();
        $user = $userModel->findById($profileId);

        if (!$user) {
            http_response_code(404);
            require BASE_PATH . '/app/Views/errors/404.php';
            return;
        }

        $currentUserId = Session::userId();
        $isOwner = ($currentUserId === $profileId);

        // Posts de l'utilisateur
        $postModel = new Post();
        $posts = $postModel->getByUser($profileId, 20);

        // Vérifier si on suit cet utilisateur
        $followModel = new Follow();
        $isFollowing = $isOwner ? false : $followModel->isFollowing($currentUserId, $profileId);

        // Bookmarks (seulement pour le propriétaire)
        $bookmarks = $isOwner ? (new Bookmark())->getByUser($profileId, 20) : [];

        // Commentaires de l'utilisateur
        $comments = (new Comment())->getByUser($profileId, 20);

        // Notifications non lues pour le layout
        $unreadNotifs = (new Notification())->getUnreadCount($currentUserId);

        Response::view('profile/show', [
            'pageTitle'    => ($user['prenom'] . ' ' . $user['nom']) . ' — StudyLink',
            'profileUser'  => $user,
            'isOwner'      => $isOwner,
            'isFollowing'  => $isFollowing,
            'posts'        => $posts,
            'bookmarks'    => $bookmarks,
            'comments'     => $comments,
            'unreadNotifs' => $unreadNotifs,
        ]);
    }

    public function update(string $id): void
    {
        $userId = Session::userId();
        $targetId = (int) $id;

        if ($userId !== $targetId && Session::userRole() !== 'admin') {
            Response::json(['success' => false, 'error' => ['message' => 'Non autorisé']], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        // Upload photo de profil
        if (!empty($_FILES['photo_profil']['tmp_name'])) {
            try {
                $uploader = new Uploader();
                $input['photo_profil'] = $uploader->handle($_FILES['photo_profil'], 'avatars');
            } catch (\Exception $e) {
                Response::json(['success' => false, 'error' => ['message' => $e->getMessage()]], 422);
            }
        }

        $userModel = new User();
        $userModel->updateProfile($targetId, $input);

        // Mettre à jour la session
        if ($userId === $targetId) {
            $user = $userModel->findById($userId);
            Session::set('user_name', $user['prenom'] . ' ' . $user['nom']);
            Session::set('user_photo', !empty($user['photo_profil']) ? url($user['photo_profil']) : null);
            Session::set('user_classe', $user['classe']);
        }

        Response::json(['success' => true, 'message' => 'Profil mis à jour']);
    }

    public function uploadAvatar(string $id): void
    {
        $userId = Session::userId();
        $targetId = (int) $id;

        if ($userId !== $targetId && Session::userRole() !== 'admin') {
            Response::json(['success' => false, 'error' => ['message' => 'Non autorisé']], 403);
        }

        if (empty($_FILES['avatar']) || (int) $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
            Response::json(['success' => false, 'error' => ['message' => 'Aucun fichier reçu']], 422);
        }

        try {
            $uploader = new Uploader();
            $path = $uploader->handle($_FILES['avatar'], 'avatars');
        } catch (\Exception $e) {
            Response::json(['success' => false, 'error' => ['message' => $e->getMessage()]], 422);
        }

        $userModel = new User();
        $userModel->updateProfile($targetId, ['photo_profil' => $path]);

        $photoUrl = url($path);

        // Mettre à jour la session
        if ($userId === $targetId) {
            Session::set('user_photo', $photoUrl);
        }

        Response::json(['success' => true, 'data' => ['photo_url' => $photoUrl]]);
    }

    public function follow(string $id): void
    {
        $userId = Session::userId();
        $targetId = (int) $id;

        $followModel = new Follow();
        $result = $followModel->toggle($userId, $targetId);

        // Notification si follow
        if ($result['following']) {
            $userName = Session::get('user_name');
            (new \Models\Notification())->create([
                'user_id'  => $targetId,
                'actor_id' => $userId,
                'type'     => 'follow',
                'message'  => "{$userName} a commencé à vous suivre",
                'link'     => "/profile/{$userId}",
            ]);
        }

        Response::json(['success' => true, 'data' => $result]);
    }

    public function search(): void
    {
        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            Response::json(['success' => true, 'data' => ['users' => []]]);
        }

        $userModel = new User();
        $users = $userModel->search($query, 20);

        Response::json(['success' => true, 'data' => ['users' => $users]]);
    }
}

