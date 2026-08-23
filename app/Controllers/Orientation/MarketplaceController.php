<?php
declare(strict_types=1);

namespace Controllers\Orientation;

use Core\{Redis, Response};
use Models\Orientation\{Avis, Etablissement};
use Services\Orientation\DocumentStorage;

class MarketplaceController
{
    /** Hub /orientation — design orientation.html conservé, stats dynamiques. */
    public function index(): void
    {
        $redis    = Redis::getInstance();
        $cacheKey = 'orientation:stats:global';
        $statsHub = null;

        $cached = $redis->get($cacheKey);
        if ($cached !== false && is_array($cached)) {
            $statsHub = $cached;
        }

        if ($statsHub === null) {
            $statsHub = (new Etablissement())->statsHub();
            $redis->set($cacheKey, $statsHub, 1800);
        }

        Response::view('orientation/hub', [
            'statsHub'    => $statsHub,
            'searchIndex' => $this->buildSearchIndex(),
        ], '');
    }

    /**
     * Index de recherche compact embarqué dans le hub : tous les établissements
     * actifs (tous types) réduits aux champs affichés dans une carte + une chaîne
     * de mots-clés (nom, ville, type, formations, filières, spécialités). La barre
     * de recherche du hub filtre cet index côté client et affiche les résultats
     * en place (sans redirection). Volume faible → pas d'AJAX nécessaire.
     */
    private function buildSearchIndex(): array
    {
        $etabs = array_merge(
            $this->listeCachee('universite'),
            $this->listeCachee('grande_ecole'),
            $this->listeCachee('institut')
        );
        if (empty($etabs)) {
            return [];
        }
        usort($etabs, static fn(array $a, array $b): int => strcmp($a['nom'], $b['nom']));

        // Logos signés (silencieux si B2 indisponible)
        $logoUrls = [];
        try {
            $storage = new DocumentStorage();
            foreach ($etabs as $e) {
                if (!empty($e['logo_cle'])) {
                    try { $logoUrls[(int)$e['id']] = $storage->signedUrl($e['logo_cle'], 10); }
                    catch (\Throwable) {}
                }
            }
        } catch (\Throwable $ex) {
            error_log('[Hub search] B2 signedUrl échoué : ' . $ex->getMessage());
        }

        // Notes moyennes
        $avisAgg = [];
        try {
            $avisAgg = (new Avis())->aggregatesForEtabs(array_column($etabs, 'id'));
        } catch (\Throwable $ex) {
            error_log('[Hub search] Agrégats avis échoués : ' . $ex->getMessage());
        }

        $typeLabels = [
            'universite'   => 'Université',
            'grande_ecole' => 'Grande école',
            'institut'     => 'Institut',
        ];

        $index = [];
        foreach ($etabs as $e) {
            $id      = (int)$e['id'];
            $type    = $e['type'] ?? '';
            $couleur = preg_match('/^#[0-9A-Fa-f]{6}$/', $e['couleur_principale'] ?? '')
                ? $e['couleur_principale'] : '#8B52FA';
            $agg = $avisAgg[$id] ?? null;

            $index[] = [
                'nom'          => $e['nom'] ?? '',
                'ville'        => $e['ville'] ?? 'Gabon',
                'type'         => $type,
                'typeLabel'    => $typeLabels[$type] ?? $type,
                'slug'         => $e['slug'] ?? '',
                'logo'         => $logoUrls[$id] ?? '',
                'initial'      => mb_strtoupper(mb_substr($e['nom'] ?? '?', 0, 1, 'UTF-8'), 'UTF-8'),
                'couleur'      => $couleur,
                'nbFormations' => (int)($e['nb_formations'] ?? 0),
                'prixMin'      => (int)($e['prix_min'] ?? 0),
                'prixPreinsc'  => (int)($e['prix_preinscription'] ?? 0),
                'note'         => ($agg && $agg['nb_avis'] > 0) ? round((float)$agg['note_moyenne'], 1) : null,
                'nbAvis'       => $agg ? (int)$agg['nb_avis'] : 0,
                'kw'           => self::searchableString($e),
            ];
        }
        return $index;
    }

    /** Marketplace /orientation/universites */
    public function universites(): void
    {
        $this->marketplace('universite', 'Universités du Gabon');
    }

    /** Marketplace /orientation/grandes-ecoles (inclut les instituts) */
    public function grandesEcoles(): void
    {
        $this->marketplace('grande_ecole', 'Grandes écoles du Gabon');
    }

    /**
     * Recherche globale /orientation/recherche?q=…&type=…
     * Interroge TOUS les établissements actifs (universités + grandes écoles +
     * instituts) et ne garde que ceux dont le nom, la ville, la description ou
     * l'un des intitulés de formation / filière / spécialité contient le mot-clé.
     * Mot-clé vide → liste complète (filtrable par type). Mot-clé inconnu → 0 résultat.
     */
    public function recherche(): void
    {
        $q    = trim($_GET['q'] ?? '');
        $type = $_GET['type'] ?? '';
        $type = in_array($type, ['universite', 'grande_ecole', 'institut'], true) ? $type : '';

        // Toutes les vitrines actives, servies depuis le cache marketplace existant
        $etablissements = array_merge(
            $this->listeCachee('universite'),
            $this->listeCachee('grande_ecole'),
            $this->listeCachee('institut')
        );

        // Filtre par type (les instituts rejoignent les grandes écoles)
        if ($type === 'grande_ecole') {
            $etablissements = array_values(array_filter(
                $etablissements,
                static fn(array $e): bool => in_array($e['type'] ?? '', ['grande_ecole', 'institut'], true)
            ));
        } elseif ($type !== '') {
            $etablissements = array_values(array_filter(
                $etablissements,
                static fn(array $e): bool => ($e['type'] ?? '') === $type
            ));
        }

        // Filtre par mot-clé sur l'index de recherche complet
        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $etablissements = array_values(array_filter(
                $etablissements,
                static fn(array $e): bool => str_contains(self::searchableString($e), $needle)
            ));
        }

        usort($etablissements, static fn(array $a, array $b): int => strcmp($a['nom'], $b['nom']));

        $titre = $q !== ''
            ? 'Résultats pour « ' . $q . ' »'
            : 'Tous les établissements du Gabon';

        $this->render($etablissements, $type, $titre, true);
    }

    /** Concatène en minuscules tous les champs indexés d'un établissement. */
    private static function searchableString(array $e): string
    {
        // Volontairement SANS la description libre : la recherche porte sur ce que
        // l'établissement PROPOSE (formations / filières / spécialités) + son
        // identité (nom, ville, type). Indexer la description produirait des faux
        // positifs (un mot cité dans un paragraphe marketing ≠ formation offerte).
        return mb_strtolower(trim(implode(' ', array_filter([
            $e['nom']            ?? '',
            $e['ville']          ?? '',
            $e['type']           ?? '',
            $e['kw_formations']  ?? '',
            $e['kw_filieres']    ?? '',
            $e['kw_specialites'] ?? '',
        ]))), 'UTF-8');
    }

    private function marketplace(string $type, string $titre): void
    {
        $etablissements = $this->listeCachee($type);

        // Les instituts rejoignent la marketplace grandes écoles
        if ($type === 'grande_ecole') {
            $etablissements = array_merge($etablissements, $this->listeCachee('institut'));
            usort($etablissements, fn(array $a, array $b) => strcmp($a['nom'], $b['nom']));
        }

        $this->render($etablissements, $type, $titre);
    }

    /**
     * Liste des établissements actifs d'un type, servie depuis Redis (TTL 5 min).
     * Clés `orientation:marketplace:{type}` — déjà invalidées par l'admin et
     * les paramètres gestionnaire à chaque modification d'établissement.
     */
    private function listeCachee(string $type): array
    {
        $redis    = Redis::getInstance();
        $cacheKey = 'orientation:marketplace:' . $type;

        $cached = $redis->get($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $liste = (new Etablissement())->findAllActive($type);
        $redis->set($cacheKey, $liste, 300);
        return $liste;
    }

    private function render(array $etablissements, string $initialType, string $titre, bool $isRecherche = false): void
    {
        // Signed URLs pour les logos (silencieux si B2 indisponible)
        $logoUrls = [];
        if (!empty($etablissements)) {
            try {
                $storage = new DocumentStorage();
                foreach ($etablissements as $etab) {
                    if (!empty($etab['logo_cle'])) {
                        try {
                            $logoUrls[(int)$etab['id']] = $storage->signedUrl($etab['logo_cle'], 10);
                        } catch (\Throwable) {}
                    }
                }
            } catch (\Throwable $e) {
                error_log('[Marketplace] B2 signedUrl échoué : ' . $e->getMessage());
            }
        }

        // Villes uniques pour le filtre
        $villes = array_values(array_unique(array_filter(
            array_column($etablissements, 'ville')
        )));
        sort($villes);

        // Notes moyennes (jamais mises en cache : un avis déposé apparaît aussitôt)
        $avisAgg = [];
        try {
            $avisAgg = (new Avis())->aggregatesForEtabs(array_column($etablissements, 'id'));
        } catch (\Throwable $e) {
            error_log('[Marketplace] Agrégats avis échoués : ' . $e->getMessage());
        }

        Response::view('orientation/marketplace', [
            'etablissements' => $etablissements,
            'logoUrls'       => $logoUrls,
            'villes'         => $villes,
            'avisAgg'        => $avisAgg,
            'initialType'    => $initialType,
            'heroTitre'      => $titre,
            'initialQ'       => trim($_GET['q'] ?? ''),
            'isRecherche'    => $isRecherche,
        ], '');
    }
}
