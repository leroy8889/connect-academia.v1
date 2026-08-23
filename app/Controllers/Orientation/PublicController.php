<?php
declare(strict_types=1);

namespace Controllers\Orientation;

use Core\{Redis, Response, Session};
use Models\Orientation\{Avis, Etablissement, Filiere, Formation};
use Services\Orientation\DocumentStorage;

class PublicController
{
    public function show(string $slug): void
    {
        $model  = new Etablissement();
        $redis  = Redis::getInstance();

        // ── Redis cache (DB seulement — signed URLs exclues, TTL B2 trop court) ──
        $cacheKey = "orientation:etab:{$slug}";
        $cached   = $redis->get($cacheKey);

        // isset($cached['filieres']) : les entrées de cache antérieures à l'ajout
        // des filières publiques n'ont pas la clé → on refait un fetch frais et on
        // ré-écrit le cache au nouveau format (pas de destructuration cassée).
        if ($cached !== false && is_array($cached) && isset($cached['filieres'])) {
            ['etab' => $etab, 'formations' => $formations, 'actualites' => $actualites, 'filieres' => $filieres] = $cached;
        } else {
            $etab = $model->findBySlug($slug);

            if (!$etab || $etab['statut'] !== 'actif') {
                http_response_code(404);
                Response::view('errors/404', ['pageTitle' => 'Page introuvable'], 'main');
                return;
            }

            $formations = $model->getFormations((int)$etab['id']);
            $actualites = $model->getActualites((int)$etab['id'], 7);
            $filieres   = (new Filiere())->findActiveByEtab((int)$etab['id']);

            $redis->set($cacheKey, compact('etab', 'formations', 'actualites', 'filieres'), 600);
        }

        // 404 si statut changé depuis le cache
        if (!$etab || $etab['statut'] !== 'actif') {
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Page introuvable'], 'main');
            return;
        }

        // Log visite (non mis en cache, toujours frais)
        $ip = !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0])
            : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $model->logVisit((int)$etab['id'], $ip);

        // Signed URLs B2 (générées à chaque requête — TTL 10 min non cacheable)
        $logoUrl      = '';
        $imageUrls    = [];
        $videoFileUrl = '';
        try {
            $storage = new DocumentStorage();
            if (!empty($etab['logo_cle'])) {
                $logoUrl = $storage->signedUrl($etab['logo_cle'], 10);
            }
            if (!empty($etab['images_cles'])) {
                foreach (json_decode($etab['images_cles'], true) ?? [] as $key) {
                    if ($key) {
                        $imageUrls[] = $storage->signedUrl($key, 10);
                    }
                }
            }
            if (!empty($etab['video_cle'])) {
                $videoFileUrl = $storage->signedUrl($etab['video_cle'], 10);
            }
            // Image d'affiche propre à chaque actualité (URL signée, non cachée)
            foreach ($actualites as &$actu) {
                $actu['image_url'] = !empty($actu['image_cle'])
                    ? $storage->signedUrl($actu['image_cle'], 10)
                    : '';
            }
            unset($actu);
        } catch (\Throwable $e) {
            error_log('[Orientation/Public] B2 signedUrl échoué : ' . $e->getMessage());
        }

        // Avis 5 étoiles (jamais mis en cache : un avis déposé apparaît aussitôt)
        $avisAgg  = ['nb_avis' => 0, 'note_moyenne' => 0.0];
        $avisDist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $avisList = [];
        $monAvis  = null;
        try {
            $avisModel = new Avis();
            $etabId    = (int)$etab['id'];
            $avisAgg   = $avisModel->aggregateForEtab($etabId);
            $avisDist  = $avisModel->distribution($etabId);
            $avisList  = $avisModel->listForEtab($etabId, 6);
            if (Session::isLoggedIn()) {
                $monAvis = $avisModel->findByUser($etabId, (int)Session::userId());
            }
        } catch (\Throwable $e) {
            error_log('[Orientation/Public] Avis indisponibles : ' . $e->getMessage());
        }

        Response::view('orientation/etablissement', [
            'etab'          => $etab,
            'logoUrl'       => $logoUrl,
            'imageUrls'     => $imageUrls,
            'videoFileUrl'  => $videoFileUrl,
            'formations'    => $formations,
            'filieres'      => $filieres,
            'actualites'    => $actualites,
            'badges'        => json_decode($etab['badges_confiance'] ?? 'null', true) ?? [],
            'atouts'        => json_decode($etab['atouts']          ?? 'null', true) ?? [],
            'statistiques'  => json_decode($etab['statistiques']    ?? 'null', true) ?? [],
            'infosPaiement' => json_decode($etab['infos_paiement']  ?? 'null', true) ?? [],
            'avisAgg'       => $avisAgg,
            'avisDist'      => $avisDist,
            'avisList'      => $avisList,
            'monAvis'       => $monAvis,
        ], '');
    }

    /**
     * Page de détail d'une actualité publiée d'un établissement.
     * Route : GET /orientation/{slug}/actualite/{id}
     */
    public function actualite(string $slug, int $id): void
    {
        $model = new Etablissement();
        $etab  = $model->findBySlug($slug);

        if (!$etab || $etab['statut'] !== 'actif') {
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Page introuvable'], 'main');
            return;
        }

        $etabId = (int)$etab['id'];
        $actu   = $model->getActualiteById($etabId, $id);

        if (!$actu) {
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Article introuvable'], 'main');
            return;
        }

        // Autres actualités du même établissement (bloc « À lire aussi »)
        $autres = array_values(array_filter(
            $model->getActualites($etabId, 7),
            static fn ($a) => (int)$a['id'] !== $id
        ));
        $autres = array_slice($autres, 0, 3);

        // URLs signées B2 (logo + image d'affiche + vignettes des autres articles)
        $logoUrl  = '';
        $imageUrl = '';
        try {
            $storage = new DocumentStorage();
            if (!empty($etab['logo_cle'])) {
                $logoUrl = $storage->signedUrl($etab['logo_cle'], 10);
            }
            if (!empty($actu['image_cle'])) {
                $imageUrl = $storage->signedUrl($actu['image_cle'], 10);
            }
            foreach ($autres as &$a) {
                $a['image_url'] = !empty($a['image_cle'])
                    ? $storage->signedUrl($a['image_cle'], 10)
                    : '';
            }
            unset($a);
        } catch (\Throwable $e) {
            error_log('[Orientation/Public] B2 signedUrl (actualité) échoué : ' . $e->getMessage());
        }

        Response::view('orientation/actualite', [
            'etab'     => $etab,
            'actu'     => $actu,
            'autres'   => $autres,
            'logoUrl'  => $logoUrl,
            'imageUrl' => $imageUrl,
        ], '');
    }

    /**
     * Page de détail d'une formation d'un établissement.
     * Route : GET /orientation/{slug}/formations/{id}
     */
    public function formationDetail(string $slug, int $id): void
    {
        $model = new Etablissement();
        $etab  = $model->findBySlug($slug);

        if (!$etab || $etab['statut'] !== 'actif') {
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Page introuvable'], 'main');
            return;
        }

        $etabId       = (int)$etab['id'];
        $formationModel = new Formation();
        $formation    = $formationModel->findById($id, $etabId);

        if (!$formation) {
            http_response_code(404);
            Response::view('errors/404', ['pageTitle' => 'Formation introuvable'], 'main');
            return;
        }

        // Autres formations du même établissement (bloc « Autres formations »)
        $autres = array_values(array_filter(
            $formationModel->findByEtab($etabId),
            static fn ($f) => (int)$f['id'] !== $id
        ));
        $autres = array_slice($autres, 0, 3);

        $logoUrl = '';
        $formationImageUrl = '';
        try {
            $storage = new DocumentStorage();
            if (!empty($etab['logo_cle'])) {
                $logoUrl = $storage->signedUrl($etab['logo_cle'], 10);
            }
            if (!empty($formation['image_cle'])) {
                $formationImageUrl = $storage->signedUrl($formation['image_cle'], 10);
            }
        } catch (\Throwable $e) {
            error_log('[Orientation/Public] B2 signedUrl (formation) échoué : ' . $e->getMessage());
        }

        Response::view('orientation/formation-detail', [
            'etab'              => $etab,
            'formation'         => $formation,
            'autres'            => $autres,
            'logoUrl'           => $logoUrl,
            'formationImageUrl' => $formationImageUrl,
        ], '');
    }
}
