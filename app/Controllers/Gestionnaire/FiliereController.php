<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Response, Session, TenantGuard};
use Models\Orientation\{Etablissement, Filiere};
use Services\Orientation\JournalGestionnaire;

/**
 * Configuration des filières & spécialités de l'établissement.
 * Chaque établissement définit librement ses filières (ex. "Droit")
 * et leurs spécialités (ex. "Conseil juridique"), affichées ensuite
 * en menus déroulants dans les formulaires de préinscription / inscription.
 */
class FiliereController
{
    private int $etabId;

    public function __construct()
    {
        $this->etabId = (int)Session::get('gestionnaire_etab_id');
    }

    public function index(): void
    {
        $etab     = (new Etablissement())->findById($this->etabId);
        $filieres = (new Filiere())->findByEtab($this->etabId);

        Response::view('gestionnaire/filieres/index', [
            'pageTitle'  => 'Filières & spécialités — ' . ($etab['nom'] ?? ''),
            'etab'       => $etab,
            'filieres'   => $filieres,
            'flash'      => Session::getFlash('success'),
            'flashError' => Session::getFlash('error'),
        ], 'gestionnaire');
    }

    // ── Filières ──────────────────────────────────────────────────────────────

    public function store(): void
    {
        $nom = trim($_POST['nom'] ?? '');
        if ($nom === '') {
            Session::flash('error', 'Le nom de la filière est obligatoire.');
            Response::redirect('/gestionnaire/filieres');
            return;
        }

        try {
            (new Filiere())->create($this->etabId, [
                'nom'               => $nom,
                'frais_inscription' => self::toInt($_POST['frais_inscription'] ?? 0),
                'frais_scolarite'   => self::toInt($_POST['frais_scolarite'] ?? 0),
                'ordre'             => (int)($_POST['ordre'] ?? 0),
                'actif'             => isset($_POST['actif']) ? !empty($_POST['actif']) : true,
            ]);
            JournalGestionnaire::log('filiere.creation', 'filiere', 0,
                'Filière ajoutée : ' . $nom);
            Session::flash('success', 'Filière ajoutée.');
        } catch (\PDOException $e) {
            // Doublon (contrainte UNIQUE etablissement_id + nom)
            Session::flash('error', 'Cette filière existe déjà.');
        }

        Response::redirect('/gestionnaire/filieres');
    }

    public function update(int $id): void
    {
        $model = new Filiere();
        if (!$model->findById($id, $this->etabId)) {
            TenantGuard::denyEtab($model->findByIdAny($id), $this->etabId, 'gestionnaire.filiere', $id);
            Session::flash('error', 'Filière introuvable.');
            Response::redirect('/gestionnaire/filieres');
            return;
        }

        $nom = trim($_POST['nom'] ?? '');
        if ($nom === '') {
            Session::flash('error', 'Le nom de la filière est obligatoire.');
            Response::redirect('/gestionnaire/filieres');
            return;
        }

        try {
            $model->update($id, $this->etabId, [
                'nom'               => $nom,
                'frais_inscription' => self::toInt($_POST['frais_inscription'] ?? 0),
                'frais_scolarite'   => self::toInt($_POST['frais_scolarite'] ?? 0),
                'ordre'             => (int)($_POST['ordre'] ?? 0),
                'actif'             => !empty($_POST['actif']),
            ]);
            JournalGestionnaire::log('filiere.modification', 'filiere', $id,
                'Filière modifiée : ' . $nom);
            Session::flash('success', 'Filière mise à jour.');
        } catch (\PDOException $e) {
            Session::flash('error', 'Une filière porte déjà ce nom.');
        }

        Response::redirect('/gestionnaire/filieres');
    }

    public function destroy(int $id): void
    {
        $model = new Filiere();
        if (!$model->findById($id, $this->etabId)) {
            TenantGuard::denyEtab($model->findByIdAny($id), $this->etabId, 'gestionnaire.filiere', $id);
            Session::flash('error', 'Filière introuvable.');
            Response::redirect('/gestionnaire/filieres');
            return;
        }

        $model->delete($id, $this->etabId);
        JournalGestionnaire::log('filiere.suppression', 'filiere', $id,
            'Filière supprimée (avec ses spécialités) : #' . $id);
        Session::flash('success', 'Filière supprimée (avec ses spécialités).');
        Response::redirect('/gestionnaire/filieres');
    }

    // ── Spécialités ───────────────────────────────────────────────────────────

    public function storeSpecialite(int $filiereId): void
    {
        $nom = trim($_POST['nom'] ?? '');
        if ($nom === '') {
            Session::flash('error', 'Le nom de la spécialité est obligatoire.');
            Response::redirect('/gestionnaire/filieres');
            return;
        }

        try {
            $created = (new Filiere())->createSpecialite($filiereId, $this->etabId, [
                'nom'   => $nom,
                'ordre' => (int)($_POST['ordre'] ?? 0),
                'actif' => isset($_POST['actif']) ? !empty($_POST['actif']) : true,
            ]);
            if ($created === null) {
                Session::flash('error', 'Filière introuvable.');
            } else {
                JournalGestionnaire::log('filiere.specialite.creation', 'specialite', 0,
                    'Spécialité ajoutée : ' . $nom);
                Session::flash('success', 'Spécialité ajoutée.');
            }
        } catch (\PDOException $e) {
            Session::flash('error', 'Cette spécialité existe déjà pour cette filière.');
        }

        Response::redirect('/gestionnaire/filieres');
    }

    public function updateSpecialite(int $specId): void
    {
        $model = new Filiere();
        if (!$model->findSpecialite($specId, $this->etabId)) {
            TenantGuard::denyEtab($model->findSpecialiteAny($specId), $this->etabId, 'gestionnaire.specialite', $specId);
            Session::flash('error', 'Spécialité introuvable.');
            Response::redirect('/gestionnaire/filieres');
            return;
        }

        $nom = trim($_POST['nom'] ?? '');
        if ($nom === '') {
            Session::flash('error', 'Le nom de la spécialité est obligatoire.');
            Response::redirect('/gestionnaire/filieres');
            return;
        }

        try {
            $model->updateSpecialite($specId, $this->etabId, [
                'nom'   => $nom,
                'ordre' => (int)($_POST['ordre'] ?? 0),
                'actif' => !empty($_POST['actif']),
            ]);
            JournalGestionnaire::log('filiere.specialite.modification', 'specialite', $specId,
                'Spécialité modifiée : ' . $nom);
            Session::flash('success', 'Spécialité mise à jour.');
        } catch (\PDOException $e) {
            Session::flash('error', 'Une spécialité porte déjà ce nom.');
        }

        Response::redirect('/gestionnaire/filieres');
    }

    public function destroySpecialite(int $specId): void
    {
        $model = new Filiere();
        if (!$model->findSpecialite($specId, $this->etabId)) {
            TenantGuard::denyEtab($model->findSpecialiteAny($specId), $this->etabId, 'gestionnaire.specialite', $specId);
            Session::flash('error', 'Spécialité introuvable.');
            Response::redirect('/gestionnaire/filieres');
            return;
        }

        $model->deleteSpecialite($specId, $this->etabId);
        JournalGestionnaire::log('filiere.specialite.suppression', 'specialite', $specId,
            'Spécialité supprimée : #' . $specId);
        Session::flash('success', 'Spécialité supprimée.');
        Response::redirect('/gestionnaire/filieres');
    }

    /** Entier robuste : tolère "150 000", "150.000", "150,000" saisis à la française. */
    private static function toInt(mixed $val): int
    {
        return max(0, (int)preg_replace('/[^\d]/', '', (string)$val));
    }
}
