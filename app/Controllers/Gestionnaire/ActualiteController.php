<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Redis, Response, Session, TenantGuard};
use Models\Orientation\{Actualite, Etablissement};
use Services\Orientation\{DocumentStorage, JournalGestionnaire};

class ActualiteController
{
    private int $etabId;

    public function __construct()
    {
        $this->etabId = (int)Session::get('gestionnaire_etab_id');
    }

    public function index(): void
    {
        $etab      = (new Etablissement())->findById($this->etabId);
        $actualites= (new Actualite())->findByEtab($this->etabId);

        Response::view('gestionnaire/actualites/index', [
            'pageTitle' => 'Actualités — ' . ($etab['nom'] ?? ''),
            'etab'      => $etab,
            'actualites'=> $actualites,
            'flash'     => Session::getFlash('success'),
            'flashError'=> Session::getFlash('error'),
        ], 'gestionnaire');
    }

    public function store(): void
    {
        $titre = trim($_POST['titre'] ?? '');
        if ($titre === '') {
            Session::flash('error', 'Titre obligatoire.');
            Response::redirect('/gestionnaire/actualites');
            return;
        }

        // Upload image si fournie — refus explicite (>15 Mo, format non image)
        try {
            $imageKey = $this->uploadImageArticle();
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/gestionnaire/actualites');
            return;
        }

        (new Actualite())->create($this->etabId, [
            'titre'            => $titre,
            'categorie'        => trim($_POST['categorie'] ?? ''),
            'date_publication' => trim($_POST['date_publication'] ?? '') ?: date('Y-m-d'),
            'image_cle'        => $imageKey,
            'extrait'          => trim($_POST['extrait'] ?? '') ?: null,
            'contenu'          => trim($_POST['contenu'] ?? '') ?: null,
            'a_la_une'         => !empty($_POST['a_la_une']),
            'statut'           => $_POST['statut'] ?? 'brouillon',
        ]);

        $this->invalidateEtabCache();
        JournalGestionnaire::log('actualite.creation', 'actualite', 0,
            'Actualité créée : ' . $titre);
        Session::flash('success', 'Actualité créée.');
        Response::redirect('/gestionnaire/actualites');
    }

    public function update(int $id): void
    {
        $model    = new Actualite();
        $actualite= $model->findById($id, $this->etabId);

        if (!$actualite) {
            TenantGuard::denyEtab($model->findByIdAny($id), $this->etabId, 'gestionnaire.actualite', $id);
            Session::flash('error', 'Actualité introuvable.');
            Response::redirect('/gestionnaire/actualites');
            return;
        }

        $titre = trim($_POST['titre'] ?? '');
        if ($titre === '') {
            Session::flash('error', 'Titre obligatoire.');
            Response::redirect('/gestionnaire/actualites');
            return;
        }

        // Nouvelle image : on ne remplace l'existante qu'en cas de succès
        try {
            $nouvelleImage = $this->uploadImageArticle();
        } catch (\InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
            Response::redirect('/gestionnaire/actualites');
            return;
        }
        $imageKey = $nouvelleImage ?? $actualite['image_cle'];

        $model->update($id, $this->etabId, [
            'titre'            => $titre,
            'categorie'        => trim($_POST['categorie'] ?? ''),
            'date_publication' => trim($_POST['date_publication'] ?? '') ?: date('Y-m-d'),
            'image_cle'        => $imageKey,
            'extrait'          => trim($_POST['extrait'] ?? '') ?: null,
            'contenu'          => trim($_POST['contenu'] ?? '') ?: null,
            'a_la_une'         => !empty($_POST['a_la_une']),
            'statut'           => $_POST['statut'] ?? 'brouillon',
        ]);

        $this->invalidateEtabCache();
        JournalGestionnaire::log('actualite.modification', 'actualite', $id,
            'Actualité modifiée : ' . $titre);
        Session::flash('success', 'Actualité mise à jour.');
        Response::redirect('/gestionnaire/actualites');
    }

    public function destroy(int $id): void
    {
        $model    = new Actualite();
        $actualite= $model->findById($id, $this->etabId);

        if (!$actualite) {
            TenantGuard::denyEtab($model->findByIdAny($id), $this->etabId, 'gestionnaire.actualite', $id);
            Session::flash('error', 'Actualité introuvable.');
            Response::redirect('/gestionnaire/actualites');
            return;
        }

        $model->delete($id, $this->etabId);
        $this->invalidateEtabCache();
        JournalGestionnaire::log('actualite.suppression', 'actualite', $id,
            'Actualité supprimée : ' . ($actualite['titre'] ?? ('#' . $id)));
        Session::flash('success', 'Actualité supprimée.');
        Response::redirect('/gestionnaire/actualites');
    }

    /**
     * Traite le champ fichier « image » du formulaire article.
     *
     * Tous les formats image bitmap sont acceptés (JPG, PNG, WebP, GIF, BMP,
     * TIFF, AVIF, HEIC…), taille maximale 15 Mo.
     *
     * @return string|null clé B2 de l'image, ou null si aucun fichier envoyé
     * @throws \InvalidArgumentException message affichable tel quel à l'utilisateur
     */
    private function uploadImageArticle(): ?string
    {
        $f = $_FILES['image'] ?? null;
        if (!is_array($f)) {
            return null;
        }

        $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $maxMo = (int)(DocumentStorage::IMAGE_MAX_SIZE_BYTES / 1048576);

        // Fichier tronqué par PHP (upload_max_filesize / MAX_FILE_SIZE du form)
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            throw new \InvalidArgumentException(
                "La taille de l'image n'est pas supportée : le fichier dépasse la limite de {$maxMo} Mo."
            );
        }
        if ($err !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException(
                "L'envoi de l'image a échoué (code {$err}). Réessayez."
            );
        }

        $tmp = (string)($f['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \InvalidArgumentException("L'envoi de l'image a échoué. Réessayez.");
        }

        // Garde-fou taille avant même d'instancier le client B2
        if ((int)($f['size'] ?? 0) > DocumentStorage::IMAGE_MAX_SIZE_BYTES) {
            throw new \InvalidArgumentException(sprintf(
                "La taille de l'image n'est pas supportée : %s Mo (maximum %d Mo).",
                number_format(((int)$f['size']) / 1048576, 1, ',', ' '),
                $maxMo
            ));
        }

        try {
            $storage = new DocumentStorage();
            return $storage->uploadImage(
                $tmp,
                "actualites/{$this->etabId}/" . uniqid('', true),
                false
            );
        } catch (\InvalidArgumentException $e) {
            // Message métier (taille / format) — remonté tel quel
            throw $e;
        } catch (\Throwable $e) {
            error_log('[Actualite] Upload image échoué : ' . $e->getMessage());
            throw new \InvalidArgumentException(
                "L'image n'a pas pu être enregistrée. Réessayez dans un instant."
            );
        }
    }

    private function invalidateEtabCache(): void
    {
        $slug = (new Etablissement())->findSlugById($this->etabId);
        if ($slug) {
            Redis::getInstance()->del("orientation:etab:{$slug}", "orientation:etab:v2:{$slug}");
        }
    }
}
