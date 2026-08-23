<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Redis, Response, Session, TenantGuard};
use Models\Orientation\{Etablissement, Formation};
use Services\Orientation\{JournalGestionnaire, DocumentStorage};

class FormationController
{
    private int $etabId;

    public function __construct()
    {
        $this->etabId = (int)Session::get('gestionnaire_etab_id');
    }

    public function index(): void
    {
        $etab      = (new Etablissement())->findById($this->etabId);
        $formations= (new Formation())->findByEtab($this->etabId);

        Response::view('gestionnaire/formations/index', [
            'pageTitle' => 'Formations — ' . ($etab['nom'] ?? ''),
            'etab'      => $etab,
            'formations'=> $formations,
            'flash'     => Session::getFlash('success'),
            'flashError'=> Session::getFlash('error'),
        ], 'gestionnaire');
    }

    public function store(): void
    {
        $intitule = trim($_POST['intitule'] ?? '');
        if ($intitule === '') {
            Session::flash('error', 'Intitulé obligatoire.');
            Response::redirect('/gestionnaire/formations');
            return;
        }

        $imageKey = $this->uploadImageSiEnvoyee();
        if ($imageKey === false) {
            return;
        }

        (new Formation())->create($this->etabId, [
            'intitule'    => $intitule,
            'niveau'      => trim($_POST['niveau'] ?? ''),
            'icone'       => trim($_POST['icone'] ?? ''),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'duree_texte' => trim($_POST['duree_texte'] ?? ''),
            'places_texte'=> trim($_POST['places_texte'] ?? ''),
            'prix'        => max(0, (int)($_POST['prix'] ?? 0)),
            'prix_unite'  => trim($_POST['prix_unite'] ?? 'FCFA/an') ?: 'FCFA/an',
            'debouches'   => trim($_POST['debouches'] ?? '') ?: null,
            'avantages'   => trim($_POST['avantages'] ?? '') ?: null,
            'image_cle'   => $imageKey,
            'statut'      => $_POST['statut'] ?? 'ouverte',
            'ordre'       => (int)($_POST['ordre'] ?? 0),
        ]);

        $this->invalidateEtabCache();
        JournalGestionnaire::log('formation.creation', 'formation', 0,
            'Formation ajoutée : ' . $intitule);
        Session::flash('success', 'Formation ajoutée.');
        Response::redirect('/gestionnaire/formations');
    }

    public function update(int $id): void
    {
        $model    = new Formation();
        $formation= $model->findById($id, $this->etabId);

        if (!$formation) {
            TenantGuard::denyEtab($model->findByIdAny($id), $this->etabId, 'gestionnaire.formation', $id);
            Session::flash('error', 'Formation introuvable.');
            Response::redirect('/gestionnaire/formations');
            return;
        }

        $intitule = trim($_POST['intitule'] ?? '');
        if ($intitule === '') {
            Session::flash('error', 'Intitulé obligatoire.');
            Response::redirect('/gestionnaire/formations');
            return;
        }

        $imageKey = $this->uploadImageSiEnvoyee();
        if ($imageKey === false) {
            return;
        }

        $model->update($id, $this->etabId, [
            'intitule'    => $intitule,
            'niveau'      => trim($_POST['niveau'] ?? ''),
            'icone'       => trim($_POST['icone'] ?? ''),
            'description' => trim($_POST['description'] ?? '') ?: null,
            'duree_texte' => trim($_POST['duree_texte'] ?? ''),
            'places_texte'=> trim($_POST['places_texte'] ?? ''),
            'prix'        => max(0, (int)($_POST['prix'] ?? 0)),
            'prix_unite'  => trim($_POST['prix_unite'] ?? 'FCFA/an') ?: 'FCFA/an',
            'debouches'   => trim($_POST['debouches'] ?? '') ?: null,
            'avantages'   => trim($_POST['avantages'] ?? '') ?: null,
            'image_cle'   => $imageKey,
            'statut'      => $_POST['statut'] ?? 'ouverte',
            'ordre'       => (int)($_POST['ordre'] ?? 0),
        ]);

        $this->invalidateEtabCache();
        JournalGestionnaire::log('formation.modification', 'formation', $id,
            'Formation modifiée : ' . $intitule);
        Session::flash('success', 'Formation mise à jour.');
        Response::redirect('/gestionnaire/formations');
    }

    public function destroy(int $id): void
    {
        $model    = new Formation();
        $formation= $model->findById($id, $this->etabId);

        if (!$formation) {
            TenantGuard::denyEtab($model->findByIdAny($id), $this->etabId, 'gestionnaire.formation', $id);
            Session::flash('error', 'Formation introuvable.');
            Response::redirect('/gestionnaire/formations');
            return;
        }

        $model->delete($id, $this->etabId);
        $this->invalidateEtabCache();
        JournalGestionnaire::log('formation.suppression', 'formation', $id,
            'Formation supprimée : ' . ($formation['intitule'] ?? ('#' . $id)));
        Session::flash('success', 'Formation supprimée.');
        Response::redirect('/gestionnaire/formations');
    }

    private function invalidateEtabCache(): void
    {
        $slug = (new Etablissement())->findSlugById($this->etabId);
        if ($slug) {
            Redis::getInstance()->del("orientation:etab:{$slug}");
        }
    }

    /**
     * Upload optionnel de l'image formation (format horizontal, 2 Mo max).
     * Retourne : clé B2 si un fichier a été envoyé, null si aucun fichier
     * (l'image actuelle est conservée), ou false si l'upload est rejeté
     * (taille) — dans ce cas la réponse a déjà été redirigée.
     */
    private function uploadImageSiEnvoyee(): string|null|false
    {
        if (empty($_FILES['image']['tmp_name']) || ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            Session::flash('error', 'Image trop lourde — 2 Mo maximum.');
            Response::redirect('/gestionnaire/formations');
            return false;
        }
        $ext = strtolower(pathinfo($_FILES['image']['name'] ?? '', PATHINFO_EXTENSION)) ?: 'jpg';
        return (new DocumentStorage())->upload(
            $_FILES['image']['tmp_name'],
            "formations/{$this->etabId}/" . uniqid() . ".{$ext}",
            $_FILES['image']['type'] ?? 'image/jpeg',
            false
        );
    }
}
