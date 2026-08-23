<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Response, Session, TenantGuard};
use Models\Orientation\{Etablissement, PieceRequise};
use Services\Orientation\JournalGestionnaire;

class PieceRequiseController
{
    private int $etabId;

    public function __construct()
    {
        $this->etabId = (int)Session::get('gestionnaire_etab_id');
    }

    public function index(): void
    {
        $etab   = (new Etablissement())->findById($this->etabId);
        $pieces = (new PieceRequise())->findByEtab($this->etabId);

        // Grouper par niveau (null = tous niveaux)
        $grouped = [];
        foreach ($pieces as $p) {
            $key = $p['niveau'] ?? '__tous';
            $grouped[$key][] = $p;
        }
        ksort($grouped);

        Response::view('gestionnaire/pieces/index', [
            'pageTitle' => 'Pièces requises — ' . ($etab['nom'] ?? ''),
            'etab'      => $etab,
            'grouped'   => $grouped,
            'pieces'    => $pieces,
            'flash'     => Session::getFlash('success'),
            'flashError'=> Session::getFlash('error'),
        ], 'gestionnaire');
    }

    public function store(): void
    {
        $libelle = trim($_POST['libelle'] ?? '');
        if ($libelle === '') {
            Session::flash('error', 'Libellé obligatoire.');
            Response::redirect('/gestionnaire/pieces');
            return;
        }

        (new PieceRequise())->create($this->etabId, [
            'libelle'    => $libelle,
            'niveau'     => trim($_POST['niveau'] ?? '') ?: null,
            'obligatoire'=> !empty($_POST['obligatoire']),
            'ordre'      => (int)($_POST['ordre'] ?? 0),
        ]);

        JournalGestionnaire::log('piece.creation', 'piece_requise', 0,
            'Pièce requise ajoutée : ' . $libelle);
        Session::flash('success', 'Pièce ajoutée.');
        Response::redirect('/gestionnaire/pieces');
    }

    public function update(int $id): void
    {
        $model = new PieceRequise();
        $piece = $model->findById($id, $this->etabId);

        if (!$piece) {
            TenantGuard::denyEtab($model->findByIdAny($id), $this->etabId, 'gestionnaire.piece', $id);
            Session::flash('error', 'Pièce introuvable.');
            Response::redirect('/gestionnaire/pieces');
            return;
        }

        $libelle = trim($_POST['libelle'] ?? '');
        if ($libelle === '') {
            Session::flash('error', 'Libellé obligatoire.');
            Response::redirect('/gestionnaire/pieces');
            return;
        }

        $model->update($id, $this->etabId, [
            'libelle'    => $libelle,
            'niveau'     => trim($_POST['niveau'] ?? '') ?: null,
            'obligatoire'=> !empty($_POST['obligatoire']),
            'ordre'      => (int)($_POST['ordre'] ?? 0),
        ]);

        JournalGestionnaire::log('piece.modification', 'piece_requise', $id,
            'Pièce requise modifiée : ' . $libelle);
        Session::flash('success', 'Pièce mise à jour.');
        Response::redirect('/gestionnaire/pieces');
    }

    public function destroy(int $id): void
    {
        $model = new PieceRequise();
        $piece = $model->findById($id, $this->etabId);

        if (!$piece) {
            TenantGuard::denyEtab($model->findByIdAny($id), $this->etabId, 'gestionnaire.piece', $id);
            Session::flash('error', 'Pièce introuvable.');
            Response::redirect('/gestionnaire/pieces');
            return;
        }

        $model->delete($id, $this->etabId);
        JournalGestionnaire::log('piece.suppression', 'piece_requise', $id,
            'Pièce requise supprimée : ' . ($piece['libelle'] ?? ('#' . $id)));
        Session::flash('success', 'Pièce supprimée.');
        Response::redirect('/gestionnaire/pieces');
    }
}
