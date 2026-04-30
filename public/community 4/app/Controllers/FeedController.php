<?php
declare(strict_types=1);

namespace Controllers;

use Core\{Response, Session};
use Models\{Post, User, Notification};

class FeedController
{
    public function index(): void
    {
        $userId    = Session::userId();
        $userClasse = Session::get('user_classe');

        $postModel = new Post();
        $userModel = new User();
        $notifModel = new Notification();

        // Récupérer les données pour la sidebar droite
        $topQuestions    = $postModel->getTopQuestions(3);
        $suggestions     = $userModel->getSuggestions($userId, $userClasse, 5);
        $trendingRaw     = $postModel->getTrendingHashtags(8);
        $unreadNotifs    = $notifModel->getUnreadCount($userId);

        // Extraire les hashtags tendances
        $trendingHashtags = $this->extractTrendingHashtags($trendingRaw);

        // Récupérer l'utilisateur courant
        $currentUser = $userModel->findById($userId);

        Response::view('feed/index', [
            'pageTitle'        => 'Fil d\'actualité — StudyLink',
            'currentUser'      => $currentUser,
            'topQuestions'     => $topQuestions,
            'suggestions'      => $suggestions,
            'trendingHashtags' => $trendingHashtags,
            'unreadNotifs'     => $unreadNotifs,
        ]);
    }

    public function explore(): void
    {
        $userId = Session::userId();
        $unreadNotifs = (new Notification())->getUnreadCount($userId);

        Response::view('feed/explore', [
            'pageTitle'    => 'Explorer — StudyLink',
            'unreadNotifs' => $unreadNotifs,
        ]);
    }

    private function extractTrendingHashtags(array $rawPosts): array
    {
        $tagCounts = [];
        foreach ($rawPosts as $post) {
            $tags = array_map('trim', explode(',', $post['hashtags']));
            foreach ($tags as $tag) {
                $tag = ltrim($tag, '#');
                if (!empty($tag)) {
                    $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                }
            }
        }
        arsort($tagCounts);
        return array_slice(array_keys($tagCounts), 0, 8);
    }
}

