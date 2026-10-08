<?php
declare(strict_types=1);

namespace Controllers\Orientation;

use Core\{Redis, Response, Session};
use Models\Orientation\{Avis, Etablissement, Filiere, Formation, TarifFormation};
use Services\Orientation\{DocumentStorage, Niveau, PolitiqueBoursiers, SituationBourse, TarifCycle};

class PublicController
{
    public function show(string $slug): void
    {
        $model  = new Etablissement();
        $redis  = Redis::getInstance();

        // ── Redis cache (DB seulement — signed URLs exclues, TTL B2 trop court) ──
        // La clé porte un numéro de FORME : le contenu mis en cache a gagné les
        // grilles tarifaires. Sans ce numéro, les entrées écrites par la version
        // précédente auraient continué de servir des filières sans prix pendant
        // tout le TTL, et la correction serait passée pour inopérante.
        $cacheKey = "orientation:etab:v2:{$slug}";
        $cached   = $redis->get($cacheKey);

        if ($cached !== false && is_array($cached) && isset($cached['filieres'], $cached['tarifsFilieres'])) {
            [
                'etab'           => $etab,
                'formations'     => $formations,
                'actualites'     => $actualites,
                'filieres'       => $filieres,
                'tarifsFilieres' => $tarifsFilieres,
            ] = $cached;
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

            // Grilles tarifaires de TOUTES les filières en UNE requête : la page
            // en affiche autant qu'il y a de formations, une requête par ligne
            // aurait fait N+1 sur la page la plus visitée du module.
            $tarifsFilieres = self::tarifsPublics($filieres, $etab);

            $redis->set(
                $cacheKey,
                compact('etab', 'formations', 'actualites', 'filieres', 'tarifsFilieres'),
                600
            );
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
            'tarifsFilieres'=> $tarifsFilieres,
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
     * Tarifs publics de chaque formation d'un établissement.
     *
     * ── Pourquoi cette méthode existe ────────────────────────────────────────
     *
     * L'établissement remplit une grille tarifaire par formation (niveau ×
     * situation de bourse) depuis son back-office. Cette grille servait au
     * guichet et à la facture proforma… mais la page publique n'en affichait
     * rien : le bloc « Filières & spécialités » ne montrait que des noms. Un
     * établissement qui avait consciencieusement saisi ses prix ne les voyait
     * apparaître nulle part.
     *
     * ── Ce qui est calculé ───────────────────────────────────────────────────
     *
     * Pour chaque formation, le prix RÉELLEMENT applicable à chaque niveau,
     * résolu par TarifCycle — donc avec la même cascade de replis que le
     * guichet (ligne de niveau → ligne de cycle → fiche établissement). Un
     * niveau sans aucun tarif nulle part n'est pas affiché : mieux vaut ne rien
     * annoncer qu'annoncer « 0 FCFA ».
     *
     * Une seule requête pour toutes les grilles, une seule pour la fiche
     * établissement : c'est la page la plus visitée du module, elle ne peut pas
     * se permettre un N+1.
     *
     * @param list<array<string,mixed>> $filieres Sortie de Filiere::findActiveByEtab().
     * @param array<string,mixed>       $etab
     * @return array<int,array{min:int, cycles:list<array{libelle:string, lignes:list<array{niveau:string, non_boursier:int, boursier:int}>}>}>
     */
    private static function tarifsPublics(array $filieres, array $etab): array
    {
        $ids = [];
        foreach ($filieres as $f) {
            if (!empty($f['id'])) {
                $ids[] = (int)$f['id'];
            }
        }

        if ($ids === []) {
            return [];
        }

        try {
            $grilles = (new TarifFormation())->grillesPour($ids);
        } catch (\Throwable $e) {
            error_log('[Orientation/Public] Grilles tarifaires illisibles : ' . $e->getMessage());

            return [];
        }

        // Établissement fermé aux boursiers : la colonne « Boursier » n'a plus
        // de sens sur la page publique — personne ne peut candidater à ce
        // tarif. Elle est neutralisée ICI plutôt que dans le gabarit, pour que
        // les deux vues qui consomment cette structure disparaissent ensemble.
        $afficheBoursier = PolitiqueBoursiers::accepte($etab);

        $sortie = [];

        foreach ($ids as $filiereId) {
            $grille = $grilles[$filiereId] ?? [];
            $cycles = [];
            $min    = null;

            foreach ([TarifCycle::LICENCE, TarifCycle::MASTER] as $cycle) {
                $lignes = [];

                foreach (Niveau::codes() as $code) {
                    if (Niveau::cycle($code) !== $cycle) {
                        continue;
                    }

                    $plein = TarifCycle::scolaritePourFormation($grille, $cycle, $code, null, $etab)['montant'];
                    if ($plein === null || $plein <= 0) {
                        continue; // Rien de configuré pour ce niveau : on n'invente pas.
                    }

                    $boursier = $afficheBoursier
                        ? TarifCycle::scolaritePourFormation(
                            $grille,
                            $cycle,
                            $code,
                            SituationBourse::BOURSIER,
                            $etab
                        )['montant']
                        : null;

                    $lignes[] = [
                        'niveau'       => Niveau::libelle($code),
                        'non_boursier' => (int)$plein,
                        // Un tarif boursier identique au plein n'apporte rien :
                        // on ne l'affiche que s'il diffère réellement.
                        'boursier'     => ($boursier !== null && (int)$boursier !== (int)$plein)
                            ? (int)$boursier
                            : 0,
                    ];

                    $min = $min === null ? (int)$plein : min($min, (int)$plein);
                }

                if ($lignes !== []) {
                    $cycles[] = ['libelle' => TarifCycle::libelle($cycle), 'lignes' => $lignes];
                }
            }

            if ($cycles !== []) {
                $sortie[$filiereId] = ['min' => (int)$min, 'cycles' => $cycles];
            }
        }

        return $sortie;
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
