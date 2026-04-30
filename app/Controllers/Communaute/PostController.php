<?php
declare(strict_types=1);

namespace Controllers\Communaute;

use Core\{Response, Session, Validator, Uploader};
use Models\{Post, Like, Notification, Report, Bookmark, User};

class PostController
{
    public function index(): void
    {
        $userId  = Session::userId();
        $serieId = (int) Session::get('user_serie_id');

        $filters  = [
            'type'    => $_GET['type']    ?? null,
            'matiere' => $_GET['matiere'] ?? null,
        ];
        $beforeId = !empty($_GET['before']) ? (int) $_GET['before'] : null;
        $afterId  = !empty($_GET['after'])  ? (int) $_GET['after']  : null;
        $limit    = min((int) ($_GET['limit'] ?? 10), 50);

        $postModel = new Post();

        if ($afterId) {
            $newPosts = $postModel->getNewPosts($afterId, $limit);
            Response::json(['success' => true, 'data' => ['new_posts' => $newPosts, 'count' => count($newPosts)]]);
        }

        $result = $postModel->getFeed($userId, $serieId ?: null, $filters, $beforeId, $limit);

        Response::json(['success' => true, 'data' => ['posts' => $result['posts'], 'has_more' => $result['has_more']]]);
    }

    public function store(): void
    {
        $userId = Session::userId();

        // Détecter dépassement post_max_size
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
            Response::json(['success' => false, 'error' => ['code' => 'UPLOAD_ERROR', 'message' => 'Requête trop volumineuse.']], 422);
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $input = $_POST;
        }

        $data = [
            'user_id'     => $userId,
            'contenu'     => trim($input['contenu'] ?? ''),
            'type'        => $input['type'] ?? 'partage',
            'matiere_tag' => $input['matiere_tag'] ?? null,
            'hashtags'    => $input['hashtags'] ?? null,
        ];

        $validator = new Validator();
        if (!$validator->validate($data, [
            'contenu' => 'required|min:1',
            'type'    => 'in:question,ressource,partage,annonce',
        ])) {
            Response::json(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Données invalides', 'fields' => $validator->getErrors()]], 422);
        }

        if ($data['type'] === 'annonce' && !in_array(Session::userRole(), ['enseignant', 'admin'], true)) {
            Response::json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Seuls les enseignants peuvent publier des annonces']], 403);
        }

        // Upload image
        if (isset($_FILES['image']) && (int) $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $fileError = (int) $_FILES['image']['error'];
            if (in_array($fileError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                Response::json(['success' => false, 'error' => ['code' => 'UPLOAD_ERROR', 'message' => "Image trop volumineuse (max " . ini_get('upload_max_filesize') . ")"]], 422);
            }
            if ($fileError !== UPLOAD_ERR_OK || empty($_FILES['image']['tmp_name'])) {
                Response::json(['success' => false, 'error' => ['code' => 'UPLOAD_ERROR', 'message' => 'Erreur upload image']], 422);
            }
            try {
                $data['image'] = (new Uploader())->handleImage($_FILES['image'], 'posts');
            } catch (\Exception $e) {
                Response::json(['success' => false, 'error' => ['code' => 'UPLOAD_ERROR', 'message' => $e->getMessage()]], 422);
            }
        }

        // Extraire hashtags
        if (preg_match_all('/#(\w+)/u', $data['contenu'], $matches)) {
            $existing = $data['hashtags'] ? explode(',', $data['hashtags']) : [];
            $data['hashtags'] = implode(',', array_unique(array_merge($existing, $matches[1])));
        }

        $postModel = new Post();
        $postId    = $postModel->create($data);
        $post      = $postModel->findByIdWithUser($postId, $userId);

        Response::json(['success' => true, 'data' => ['post' => $post], 'message' => 'Publication créée avec succès'], 201);
    }

    public function update(string $id): void
    {
        $postId = (int) $id;
        $userId = Session::userId();

        $postModel = new Post();
        $post = $postModel->findById($postId);

        if (!$post) Response::json(['success' => false, 'error' => ['message' => 'Publication introuvable']], 404);
        if ((int) $post['user_id'] !== $userId && Session::userRole() !== 'admin') {
            Response::json(['success' => false, 'error' => ['message' => 'Non autorisé']], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $postModel->update($postId, $input);

        Response::json(['success' => true, 'message' => 'Publication mise à jour']);
    }

    public function destroy(string $id): void
    {
        $postId = (int) $id;
        $userId = Session::userId();

        $postModel = new Post();
        $post = $postModel->findById($postId);

        if (!$post) Response::json(['success' => false, 'error' => ['message' => 'Publication introuvable']], 404);
        if ((int) $post['user_id'] !== $userId && Session::userRole() !== 'admin') {
            Response::json(['success' => false, 'error' => ['message' => 'Non autorisé']], 403);
        }

        $postModel->softDelete($postId);
        (new User())->decrementCount((int) $post['user_id'], 'posts_count');

        Response::json(['success' => true, 'message' => 'Publication supprimée']);
    }

    public function like(string $id): void
    {
        $postId = (int) $id;
        $userId = Session::userId();

        $result = (new Like())->togglePostLike($userId, $postId);

        if ($result['liked']) {
            $post = (new Post())->findById($postId);
            if ($post && (int) $post['user_id'] !== $userId) {
                (new Notification())->create([
                    'user_id'  => (int) $post['user_id'],
                    'actor_id' => $userId,
                    'type'     => 'like',
                    'message'  => Session::get('user_name') . " a aimé votre publication",
                    'post_id'  => $postId,
                    'link'     => "/communaute#post-{$postId}",
                ]);
            }
        }

        Response::json(['success' => true, 'data' => $result]);
    }

    public function report(string $id): void
    {
        $postId = (int) $id;
        $userId = Session::userId();
        $input  = json_decode(file_get_contents('php://input'), true) ?? [];

        (new Report())->create([
            'reporter_id' => $userId,
            'post_id'     => $postId,
            'reason'      => $input['reason'] ?? 'other',
            'description' => $input['description'] ?? null,
        ]);

        Response::json(['success' => true, 'message' => 'Signalement envoyé. Merci de votre vigilance.']);
    }

    public function bookmark(string $id): void
    {
        $postId = (int) $id;
        $userId = Session::userId();

        $result = (new Bookmark())->toggle($userId, $postId);
        Response::json(['success' => true, 'data' => $result]);
    }
}
