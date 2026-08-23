<?php
declare(strict_types=1);

namespace Controllers\Orientation;

use Core\{Response, Session};
use Models\Orientation\{Avis, Etablissement};

/**
 * Avis 5 étoiles sur les établissements (façon Google Reviews).
 * POST /api/orientation/avis            — créer / mettre à jour son avis (auth + csrf)
 * GET  /api/orientation/avis/{etabId}   — liste paginée publique
 * DELETE /api/orientation/avis/{etabId} — supprimer son avis (auth + csrf)
 */
class AvisController
{
    public function store(): void
    {
        $userId = (int)Session::userId();

        // Les gestionnaires d'établissement ne notent pas (conflit d'intérêt,
        // même règle que Google Business : le propriétaire ne note pas sa fiche).
        $role = Session::userRole();
        if ($role === 'gestionnaire' || $role === 'admin') {
            Response::json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'Ce type de compte ne peut pas déposer d\'avis.'],
            ], 403);
        }

        $input = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $etabId      = (int)($input['etablissement_id'] ?? $_POST['etablissement_id'] ?? 0);
        $note        = (int)($input['note'] ?? $_POST['note'] ?? 0);
        $commentaire = (string)($input['commentaire'] ?? $_POST['commentaire'] ?? '');

        if ($note < 1 || $note > 5) {
            Response::json([
                'success' => false,
                'error'   => ['code' => 'INVALID_NOTE', 'message' => 'La note doit être comprise entre 1 et 5 étoiles.'],
            ], 422);
        }

        if (mb_strlen($commentaire) > 2000) {
            Response::json([
                'success' => false,
                'error'   => ['code' => 'COMMENT_TOO_LONG', 'message' => 'Le commentaire est limité à 2000 caractères.'],
            ], 422);
        }

        $etab = (new Etablissement())->findById($etabId);
        if (!$etab || $etab['statut'] !== 'actif') {
            Response::json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Établissement introuvable.'],
            ], 404);
        }

        $avisModel = new Avis();
        $existant  = $avisModel->findByUser($etabId, $userId);
        $avisModel->upsert($etabId, $userId, $note, $commentaire);

        Response::json([
            'success' => true,
            'data'    => [
                'updated'  => $existant !== null,
                'aggregat' => $avisModel->aggregateForEtab($etabId),
            ],
        ]);
    }

    public function liste(int $etabId): void
    {
        $etab = (new Etablissement())->findById($etabId);
        if (!$etab || $etab['statut'] !== 'actif') {
            Response::json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Établissement introuvable.'],
            ], 404);
        }

        $limit  = (int)($_GET['limit']  ?? 6);
        $offset = (int)($_GET['offset'] ?? 0);

        $avisModel = new Avis();
        $avis = array_map(static function (array $a): array {
            return [
                'note'        => (int)$a['note'],
                'commentaire' => $a['commentaire'],
                'auteur'      => trim(($a['prenom'] ?? '') . ' ' . mb_strtoupper(mb_substr((string)($a['nom'] ?? ''), 0, 1)) . '.'),
                'date'        => $a['date_modification'] ?? $a['date_creation'],
            ];
        }, $avisModel->listForEtab($etabId, $limit, $offset));

        Response::json([
            'success' => true,
            'data'    => [
                'avis'     => $avis,
                'aggregat' => $avisModel->aggregateForEtab($etabId),
            ],
        ]);
    }

    public function destroy(int $etabId): void
    {
        $userId  = (int)Session::userId();
        $avisModel = new Avis();
        $deleted = $avisModel->deleteByUser($etabId, $userId);

        Response::json([
            'success' => $deleted,
            'data'    => ['aggregat' => $avisModel->aggregateForEtab($etabId)],
        ], $deleted ? 200 : 404);
    }
}
