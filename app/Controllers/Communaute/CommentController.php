<?php
declare(strict_types=1);

namespace Controllers\Communaute;

use Core\{Response, Session, Validator};
use Models\{Comment, Post, Notification, Like};

class CommentController
{
    public function index(string $id): void
    {
        $postId = (int) $id;
        $userId = Session::userId();

        $commentModel = new Comment();
        $comments     = $commentModel->getByPost($postId, $userId);
        $post         = (new Post())->findById($postId);

        Response::json([
            'success' => true,
            'data'    => [
                'comments' => $comments,
                'total'    => (int) ($post['comments_count'] ?? count($comments)),
                'post'     => $post,
            ],
        ]);
    }

    public function store(string $id): void
    {
        $postId = (int) $id;
        $userId = Session::userId();
        $input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $data = [
            'post_id'   => $postId,
            'user_id'   => $userId,
            'contenu'   => trim($input['contenu'] ?? ''),
            'parent_id' => !empty($input['parent_id']) ? (int) $input['parent_id'] : null,
        ];

        $validator = new Validator();
        if (!$validator->validate($data, ['contenu' => 'required|min:1'])) {
            Response::json(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Le commentaire ne peut pas être vide']], 422);
        }

        $commentModel = new Comment();
        $commentId    = $commentModel->create($data);
        $comment      = $commentModel->findByIdWithUser($commentId, $userId);

        // Notification à l'auteur du post
        $post = (new Post())->findById($postId);
        if ($post && (int) $post['user_id'] !== $userId) {
            (new Notification())->create([
                'user_id'  => (int) $post['user_id'],
                'actor_id' => $userId,
                'type'     => 'comment',
                'message'  => Session::get('user_name') . " a commenté votre publication",
                'post_id'  => $postId,
                'link'     => "/communaute#post-{$postId}",
            ]);
        }

        // Notification à l'auteur du commentaire parent (réponse)
        if ($data['parent_id']) {
            $parent = $commentModel->findById($data['parent_id']);
            if ($parent && (int) $parent['user_id'] !== $userId) {
                (new Notification())->create([
                    'user_id'  => (int) $parent['user_id'],
                    'actor_id' => $userId,
                    'type'     => 'reply',
                    'message'  => Session::get('user_name') . " a répondu à votre commentaire",
                    'post_id'  => $postId,
                    'link'     => "/communaute#post-{$postId}",
                ]);
            }
        }

        Response::json(['success' => true, 'data' => ['comment' => $comment], 'message' => 'Commentaire ajouté'], 201);
    }

    public function destroy(string $id): void
    {
        $commentId = (int) $id;
        $userId    = Session::userId();

        $commentModel = new Comment();
        $comment      = $commentModel->findById($commentId);

        if (!$comment) Response::json(['success' => false, 'error' => ['message' => 'Commentaire introuvable']], 404);
        if ((int) $comment['user_id'] !== $userId && Session::userRole() !== 'admin') {
            Response::json(['success' => false, 'error' => ['message' => 'Non autorisé']], 403);
        }

        $commentModel->softDelete($commentId);

        // Décrémenter le compteur du post
        \Core\Database::getInstance()->getConnection()->prepare(
            "UPDATE posts SET comments_count = GREATEST(comments_count - 1, 0) WHERE id = ?"
        )->execute([$comment['post_id']]);

        Response::json(['success' => true, 'message' => 'Commentaire supprimé']);
    }

    public function markBest(string $id): void
    {
        $commentId = (int) $id;
        $userId    = Session::userId();

        $commentModel = new Comment();
        $comment      = $commentModel->findById($commentId);

        if (!$comment) Response::json(['success' => false, 'error' => ['message' => 'Commentaire introuvable']], 404);

        $post = (new Post())->findById((int) $comment['post_id']);
        if (!$post || ((int) $post['user_id'] !== $userId && !in_array(Session::userRole(), ['enseignant', 'admin'], true))) {
            Response::json(['success' => false, 'error' => ['message' => 'Non autorisé']], 403);
        }

        $commentModel->markBestAnswer($commentId, (int) $comment['post_id']);
        Response::json(['success' => true, 'message' => 'Meilleure réponse marquée']);
    }

    public function replies(string $id): void
    {
        $commentId = (int) $id;
        $userId    = Session::userId();

        $replies = (new Comment())->getReplies($commentId, $userId);
        Response::json(['success' => true, 'data' => $replies]);
    }

    public function likeComment(string $id): void
    {
        $commentId = (int) $id;
        $userId    = Session::userId();

        $result = (new Like())->toggleCommentLike($userId, $commentId);
        Response::json(['success' => true, 'data' => $result]);
    }
}
