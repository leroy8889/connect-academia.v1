<?php
declare(strict_types=1);

namespace Controllers\Communaute;

use Core\{Response, Session};
use Models\{Post, User, Notification};

class FeedController
{
    public function index(): void
    {
        $userId  = Session::userId();
        $serieId = (int) Session::get('user_serie_id');

        $postModel = new Post();
        $userModel = new User();

        $topQuestions      = $postModel->getTopQuestions(3);
        $suggestions       = $userModel->getSuggestions($userId, $serieId ?: null, 5);
        $trendingHashtags  = $postModel->getTrendingHashtags(8);
        $unreadNotifs      = (new Notification())->getUnreadCount($userId);
        $currentUser       = $userModel->findById($userId);

        // Normaliser la photo de l'utilisateur courant
        if ($currentUser) {
            $currentUser['photo_profil'] = User::normalizePhotoPath($currentUser['photo_profil'] ?? null);
        }

        // Normaliser suggestions
        foreach ($suggestions as &$s) {
            $s['photo_profil'] = User::normalizePhotoPath($s['photo_profil'] ?? null);
        }
        unset($s);

        Response::view('communaute/feed/index', [
            'pageTitle'        => "Communauté — Connect'Academia",
            'extraCss'         => ['communaute.css'],
            'extraJs'          => ['api.js', 'components/feed.js', 'components/post-composer.js',
                                   'components/comments.js', 'components/notifications.js', 'components/follow.js'],
            'currentUser'      => $currentUser,
            'topQuestions'     => $topQuestions,
            'suggestions'      => $suggestions,
            'trendingHashtags' => $trendingHashtags,
            'unreadNotifs'     => $unreadNotifs,
        ]);
    }

    public function explore(): void
    {
        $userId       = Session::userId();
        $unreadNotifs = (new Notification())->getUnreadCount($userId);

        Response::view('communaute/feed/explore', [
            'pageTitle'    => "Explorer — Connect'Academia",
            'extraCss'     => ['communaute.css'],
            'extraJs'      => ['api.js', 'components/feed.js', 'components/post-composer.js',
                               'components/comments.js', 'components/notifications.js', 'components/follow.js'],
            'unreadNotifs' => $unreadNotifs,
        ]);
    }
}
