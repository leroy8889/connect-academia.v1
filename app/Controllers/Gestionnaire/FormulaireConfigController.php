<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Response, Session, TenantGuard};
use Models\Orientation\{Etablissement, ChampFormulaire};
use Services\Orientation\JournalGestionnaire;

class FormulaireConfigController
{
    private int $etabId;

    public function __construct()
    {
        $this->etabId = (int)Session::get('gestionnaire_etab_id');
    }

    public function index(): void
    {
        $etab   = (new Etablissement())->findById($this->etabId);
        $champs = (new ChampFormulaire())->findByEtab($this->etabId);

        // Grouper par type_formulaire > section
        $grouped = [];
        foreach ($champs as $c) {
            $grouped[$c['type_formulaire']][$c['section']][] = $c;
        }

        Response::view('gestionnaire/formulaires/index', [
            'pageTitle' => 'Config formulaires — ' . ($etab['nom'] ?? ''),
            'etab'      => $etab,
            'grouped'   => $grouped,
            'flash'     => Session::getFlash('success'),
            'flashError'=> Session::getFlash('error'),
        ], 'gestionnaire');
    }

    public function toggle(int $id): void
    {
        $model = new ChampFormulaire();
        $champ = $model->findById($id, $this->etabId);

        if (!$champ) {
            TenantGuard::denyEtab($model->findByIdAny($id), $this->etabId, 'gestionnaire.champ_formulaire', $id);
            if ($this->isAjax()) {
                Response::json(['success' => false, 'error' => 'Champ introuvable.'], 404);
                return;
            }
            Session::flash('error', 'Champ introuvable.');
            Response::redirect('/gestionnaire/formulaires');
            return;
        }

        $model->toggle($id, $this->etabId);

        JournalGestionnaire::log('formulaire.bascule', 'champ_formulaire', $id,
            'Champ « ' . ($champ['libelle'] ?? ('#' . $id)) . ' » ' . ((int)$champ['actif'] === 1 ? 'désactivé' : 'activé'));

        if ($this->isAjax()) {
            $updated = $model->findById($id, $this->etabId);
            Response::json(['success' => true, 'actif' => (int)$updated['actif']]);
            return;
        }

        Session::flash('success', 'Champ mis à jour.');
        Response::redirect('/gestionnaire/formulaires');
    }

    public function store(): void
    {
        $libelle = trim($_POST['libelle'] ?? '');
        $type    = $_POST['type_formulaire'] ?? 'preinscription';

        if ($libelle === '') {
            Session::flash('error', 'Libellé obligatoire.');
            Response::redirect('/gestionnaire/formulaires');
            return;
        }

        (new ChampFormulaire())->create($this->etabId, [
            'type_formulaire' => in_array($type, ['preinscription','inscription'], true) ? $type : 'preinscription',
            'section'         => trim($_POST['section'] ?? 'Personnalisé') ?: 'Personnalisé',
            'cle'             => trim($_POST['cle'] ?? $libelle),
            'libelle'         => $libelle,
            'type_champ'      => $_POST['type_champ'] ?? 'text',
            'options_raw'     => $_POST['options_raw'] ?? '',
            'obligatoire'     => !empty($_POST['obligatoire']),
            'ordre'           => (int)($_POST['ordre'] ?? 99),
        ]);

        JournalGestionnaire::log('formulaire.creation', 'champ_formulaire', 0,
            'Champ de formulaire ajouté : ' . $libelle);
        Session::flash('success', 'Champ ajouté.');
        Response::redirect('/gestionnaire/formulaires');
    }

    public function destroy(int $id): void
    {
        $model = new ChampFormulaire();
        $champ = $model->findById($id, $this->etabId);

        if (!$champ) {
            TenantGuard::denyEtab($model->findByIdAny($id), $this->etabId, 'gestionnaire.champ_formulaire', $id);
            Session::flash('error', 'Champ introuvable.');
            Response::redirect('/gestionnaire/formulaires');
            return;
        }

        if ((int)$champ['standard'] === 1) {
            Session::flash('error', 'Les champs standard ne peuvent pas être supprimés — désactivez-les à la place.');
            Response::redirect('/gestionnaire/formulaires');
            return;
        }

        $model->delete($id, $this->etabId);
        JournalGestionnaire::log('formulaire.suppression', 'champ_formulaire', $id,
            'Champ de formulaire supprimé : ' . ($champ['libelle'] ?? ('#' . $id)));
        Session::flash('success', 'Champ supprimé.');
        Response::redirect('/gestionnaire/formulaires');
    }

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
