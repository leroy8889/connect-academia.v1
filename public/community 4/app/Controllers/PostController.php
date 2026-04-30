<?php
declare(strict_types=1);

namespace Controllers;

use Core\{Response, Session, Validator, Uploader};
use Models\{Post, Like, Notification, Report, Bookmark, User};

class PostController
{
    public function index(): void
    {
        $userId    = Session::userId();
        $userClasse = Session::get('user_classe');

        $filters = [
            'type'    => $_GET['type'] ?? null,
            'matiere' => $_GET['matiere'] ?? null,
            'classe'  => $_GET['classe'] ?? null,
        ];

        $beforeId = !empty($_GET['before']) ? (int) $_GET['before'] : null;
        $afterId  = !empty($_GET['after']) ? (int) $_GET['after'] : null;
        $limit    = min((int) ($_GET['limit'] ?? 10), 50);

        $postModel = new Post();

        // Nouveaux posts (polling)
        if ($afterId) {
            $newPosts = $postModel->getNewPosts($afterId, $limit);
            Response::json([
                'success'   => true,
                'data'      => [
                    'new_posts' => $newPosts,
                    'count'     => count($newPosts),
                ],
            ]);
        }

        // Feed normal
        $result = $postModel->getFeed($userId, $userClasse, $filters, $beforeId, null, $limit);

        Response::json([
            'success' => true,
            'data'    => [
                'posts'    => $result['posts'],
                'has_more' => $result['has_more'],
            ],
        ]);
    }

    public function store(): void
    {
        $userId = Session::userId();

        // ── Détecter le débordement de post_max_size ──────────────
        // Quand la requête dépasse post_max_size, $_POST et $_FILES
        // sont tous les deux vides et on ne peut pas récupérer les données.
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
            $maxPost = $this->parseIniSize(ini_get('post_max_size'));
            if ($maxPost > 0 && $contentLength > $maxPost) {
                Response::json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'UPLOAD_ERROR',
                        'message' => 'La requête est trop volumineuse. Limite serveur : ' . ini_get('post_max_size') . '.',
                    ],
                ], 422);
            }
        }

        // Récupérer les données (support JSON et FormData)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        } else {
            $input = $_POST;
        }

        $data = [
            'user_id'     => $userId,
            'contenu'     => trim($input['contenu'] ?? ''),
            'type'        => $input['type'] ?? 'partage',
            'matiere_tag' => $input['matiere_tag'] ?? null,
            'classe_tag'  => $input['classe_tag'] ?? null,
            'hashtags'    => $input['hashtags'] ?? null,
        ];

        // Validation
        $validator = new Validator();
        if (!$validator->validate($data, [
            'contenu' => 'required|min:1',
            'type'    => 'in:question,ressource,partage,annonce',
        ])) {
            Response::json([
                'success' => false,
                'error'   => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Données invalides',
                    'fields'  => $validator->getErrors(),
                ],
            ], 422);
        }

        // Vérifier que seuls les enseignants/admins peuvent publier des annonces
        if ($data['type'] === 'annonce' && !in_array(Session::userRole(), ['enseignant', 'admin'], true)) {
            Response::json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'Seuls les enseignants peuvent publier des annonces'],
            ], 403);
        }

        // ── Upload image ───────────────────────────────────────────
        // On vérifie d'abord si un fichier a été soumis (même en erreur)
        if (isset($_FILES['image']) && (int) $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $fileError = (int) $_FILES['image']['error'];

            // Détecter les erreurs de taille PHP avant d'appeler l'Uploader
            if ($fileError === UPLOAD_ERR_INI_SIZE || $fileError === UPLOAD_ERR_FORM_SIZE) {
                Response::json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'UPLOAD_ERROR',
                        'message' => 'L\'image est trop volumineuse. La limite du serveur est ' . ini_get('upload_max_filesize') . '.',
                    ],
                ], 422);
            }

            if ($fileError !== UPLOAD_ERR_OK || empty($_FILES['image']['tmp_name'])) {
                Response::json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'UPLOAD_ERROR',
                        'message' => 'Erreur lors du transfert de l\'image (code : ' . $fileError . '). Veuillez réessayer.',
                    ],
                ], 422);
            }

            try {
                $uploader = new Uploader();
                $data['image'] = $uploader->handle($_FILES['image'], 'posts');
            } catch (\Exception $e) {
                Response::json([
                    'success' => false,
                    'error'   => ['code' => 'UPLOAD_ERROR', 'message' => $e->getMessage()],
                ], 422);
            }
        }

        // Extraire les hashtags du contenu
        if (preg_match_all('/#(\w+)/u', $data['contenu'], $matches)) {
            $existingTags = $data['hashtags'] ? explode(',', $data['hashtags']) : [];
            $allTags = array_unique(array_merge($existingTags, $matches[1]));
            $data['hashtags'] = implode(',', $allTags);
        }

        $postModel = new Post();
        $postId = $postModel->create($data);

        $post = $postModel->findByIdWithUser($postId, $userId);

        Response::json([
            'success' => true,
            'data'    => ['post' => $post],
            'message' => 'Publication créée avec succès',
        ], 201);
    }

    public function update(string $id): void
    {
        $postId = (int) $id;
        $userId = Session::userId();

        $postModel = new Post();
        $post = $postModel->findById($postId);

        if (!$post) {
            Response::json(['success' => false, 'error' => ['message' => 'Publication introuvable']], 404);
        }

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

        if (!$post) {
            Response::json(['success' => false, 'error' => ['message' => 'Publication introuvable']], 404);
        }

        if ((int) $post['user_id'] !== $userId && Session::userRole() !== 'admin') {
            Response::json(['success' => false, 'error' => ['message' => 'Non autorisé']], 403);
        }

        $postModel->softDelete($postId);

        // Décrémenter le compteur de publications de l'utilisateur
        (new User())->decrementCount((int) $post['user_id'], 'posts_count');

        Response::json(['success' => true, 'message' => 'Publication supprimée']);
    }

    /**
     * Convertit une valeur ini (ex: "8M", "1G") en octets.
     */
    private function parseIniSize(string $val): int
    {
        $val  = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $num  = (int) $val;
        switch ($last) {
            case 'g': $num *= 1024;
            // no break
            case 'm': $num *= 1024;
            // no break
            case 'k': $num *= 1024;
        }
        return $num;
    }

    public function like(string $id): void
    {
        $postId = (int) $id;
        $userId = Session::userId();

        $likeModel = new Like();
        $result = $likeModel->togglePostLike($userId, $postId);

        // Créer une notification si c'est un like
        if ($result['liked']) {
            $post = (new Post())->findById($postId);
            if ($post && (int) $post['user_id'] !== $userId) {
                $userName = Session::get('user_name');
                (new Notification())->create([
                    'user_id'  => (int) $post['user_id'],
                    'actor_id' => $userId,
                    'type'     => 'like',
                    'message'  => "{$userName} a aimé votre publication",
                    'post_id'  => $postId,
                    'link'     => "/#post-{$postId}",
                ]);
            }
        }

        Response::json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    public function report(string $id): void
    {
        $postId = (int) $id;
        $userId = Session::userId();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $reportModel = new Report();
        $reportModel->create([
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

        $bookmarkModel = new Bookmark();
        $result = $bookmarkModel->toggle($userId, $postId);

        Response::json(['success' => true, 'data' => $result]);
    }

    public function search(): void
    {
        $query = trim($_GET['q'] ?? '');
        
        if (empty($query)) {
            Response::json([
                'success' => false,
                'error' => ['message' => 'La requête de recherche ne peut pas être vide'],
            ], 400);
        }

        $postModel = new Post();
        $posts = $postModel->search($query, 20);

        Response::json([
            'success' => true,
            'data' => ['posts' => $posts],
        ]);
    }
}

