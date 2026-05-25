<?php
declare(strict_types=1);

namespace Controllers\Communaute;

use Core\{Response, Session, Uploader};
use Models\{User, Post, Follow, Bookmark, Comment, Notification};

class UserController
{
    public function monProfil(): void
    {
        $uuid = Session::userUuid();
        if (!$uuid) {
            Response::redirect('/auth/connexion');
            return;
        }
        Response::redirect('/communaute/profil/' . $uuid);
    }

    public function show(string $uuid): void
    {
        // Validation UUID format pour éviter les requêtes malformées
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            http_response_code(404);
            require BASE_PATH . '/app/Views/errors/404.php';
            return;
        }

        $currentUserId = Session::userId();
        $userModel     = new User();

        // findProfileByUuid : champs publics uniquement, pas d'email/password/tokens
        $profileUser = $userModel->findProfileByUuid($uuid);
        if (!$profileUser) {
            http_response_code(404);
            require BASE_PATH . '/app/Views/errors/404.php';
            return;
        }

        $profileId   = (int) $profileUser['id'];
        $isOwner     = ($currentUserId === $profileId);
        $posts       = (new Post())->getByUser($profileId, 20);
        $isFollowing = $isOwner ? false : (new Follow())->isFollowing($currentUserId, $profileId);
        $bookmarks   = $isOwner ? (new Bookmark())->getByUser($profileId, 20) : [];
        $comments    = (new Comment())->getByUser($profileId, 20);
        $unreadNotifs = (new Notification())->getUnreadCount($currentUserId);

        $profileUser['photo_profil'] = User::normalizePhotoPath($profileUser['photo_profil'] ?? null);

        Response::view('communaute/profil/show', [
            'pageTitle'    => e($profileUser['prenom'] . ' ' . $profileUser['nom']) . " — Connect'Academia",
            'extraCss'     => ['communaute.css'],
            'extraJs'      => ['api.js', 'components/feed.js', 'components/post-composer.js',
                               'components/comments.js', 'components/notifications.js', 'components/follow.js'],
            'profileUser'  => $profileUser,
            'isOwner'      => $isOwner,
            'isFollowing'  => $isFollowing,
            'posts'        => $posts,
            'bookmarks'    => $bookmarks,
            'comments'     => $comments,
            'unreadNotifs' => $unreadNotifs,
            'hideFooter'   => true,
        ]);
    }

    public function follow(string $id): void
    {
        $userId   = Session::userId();
        $targetId = (int) $id;

        $result = (new Follow())->toggle($userId, $targetId);

        if ($result['following']) {
            $actorUuid = Session::userUuid() ?? '';
            (new Notification())->create([
                'user_id'  => $targetId,
                'actor_id' => $userId,
                'type'     => 'follow',
                'message'  => Session::get('user_name') . " a commencé à vous suivre",
                'link'     => "/communaute/profil/{$actorUuid}",
            ]);
        }

        Response::json(['success' => true, 'data' => $result]);
    }

    public function search(): void
    {
        $query = trim($_GET['q'] ?? '');
        if (mb_strlen($query) < 2) {
            Response::json(['success' => true, 'data' => ['users' => []]]);
        }

        $users = (new User())->search($query, 20);

        foreach ($users as &$user) {
            $user['photo_profil'] = User::normalizePhotoPath($user['photo_profil'] ?? null);
        }
        unset($user);

        Response::json(['success' => true, 'data' => ['users' => $users]]);
    }

    public function updateProfile(): void
    {
        $userId    = Session::userId();
        $userModel = new User();

        $nom    = trim($_POST['nom']    ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $bio    = trim($_POST['bio']    ?? '');

        if (empty($nom) || empty($prenom)) {
            Response::json(['success' => false, 'error' => ['message' => 'Nom et prénom requis']], 400);
        }

        $data = [
            'nom'    => mb_substr($nom, 0, 100),
            'prenom' => mb_substr($prenom, 0, 100),
            'bio'    => mb_substr($bio, 0, 500),
        ];

        // Upload photo profil si présente
        $newPhotoPath = null;
        if (!empty($_FILES['photo_profil']['tmp_name'])) {
            try {
                $newPhotoPath = (new Uploader())->handleAvatar($_FILES['photo_profil']);
                $data['photo_profil'] = $newPhotoPath;
                Session::set('user_photo', $newPhotoPath);
            } catch (\Exception $e) {
                Response::json(['success' => false, 'error' => ['message' => $e->getMessage()]], 422);
                return;
            }
        }

        $userModel->updateProfile($userId, $data);
        Session::set('user_name', $prenom . ' ' . $nom);

        $response = ['success' => true, 'message' => 'Profil mis à jour'];
        if ($newPhotoPath !== null) {
            // URL complète à utiliser directement comme src dans le HTML/JS
            $response['photo_url'] = User::normalizePhotoPath($newPhotoPath);
        }

        Response::json($response);
    }
}
