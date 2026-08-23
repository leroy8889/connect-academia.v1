<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Response, Session};
use Models\Orientation\{Etablissement, Dossier};
use Services\Orientation\{BilanPeriodeService, JournalGestionnaire};

class GestionnaireDashboardController
{
    public function index(): void
    {
        $etabId    = (int)Session::get('gestionnaire_etab_id');
        $etabModel = new Etablissement();
        $etab      = $etabModel->findById($etabId);

        if (!$etab) {
            Session::remove('gestionnaire_etab_id');
            Session::remove('gestionnaire_role');
            Response::redirect('/gestionnaire/login');
        }

        $dossierModel = new Dossier();

        // Les agrégats du tableau de bord (5 requêtes SQL) sont servis
        // depuis Redis pendant 60 s — un dossier qui arrive apparaît vite,
        // sans marteler MySQL à chaque visite du dashboard.
        // v2 : ajoute la répartition des candidatures par ville.
        // v3 : ajoute la croissance mensuelle (préinscriptions / inscriptions)
        // pour le graphique à deux courbes du tableau de bord.
        $redis    = \Core\Redis::getInstance();
        $cacheKey = "orientation:statsetab:v3:{$etabId}";
        $stats    = $redis->get($cacheKey);
        if ($stats === false || !is_array($stats) || !array_key_exists('croissance', $stats)) {
            $stats               = $dossierModel->statsForEtab($etabId);
            $stats['villes']     = $dossierModel->statsVilles($etabId);
            $stats['croissance'] = $dossierModel->croissanceMensuelle($etabId, 12);
            $redis->set($cacheKey, $stats, 60);
        }

        $recent = $dossierModel->findByEtab($etabId, ['limit' => 8]);

        Response::view('gestionnaire/dashboard', [
            'pageTitle'   => 'Tableau de bord — ' . $etab['nom'],
            'etab'        => $etab,
            'stats'       => $stats,
            'recent'      => $recent,
            'flash'       => Session::getFlash('success'),
            'flashError'  => Session::getFlash('error'),
        ], 'gestionnaire');
    }

    /**
     * Bilan statistique imprimable (PDF via Ctrl+P, comme les récépissés) :
     * candidats ayant préinscrit ou inscrit sur une période choisie au
     * calendrier depuis le tableau de bord.
     */
    public function bilan(): void
    {
        $etabId = (int)Session::get('gestionnaire_etab_id');
        $etab   = (new Etablissement())->findById($etabId);

        if (!$etab) {
            Session::remove('gestionnaire_etab_id');
            Session::remove('gestionnaire_role');
            Response::redirect('/gestionnaire/login');
            return;
        }

        $type = (string)($_GET['type'] ?? 'preinscription');
        if (!in_array($type, ['preinscription', 'inscription'], true)) {
            $type = 'preinscription';
        }

        $du = self::dateOuNull((string)($_GET['du'] ?? ''));
        $au = self::dateOuNull((string)($_GET['au'] ?? ''));

        if ($du === null || $au === null) {
            Session::flash('error', 'Bilan : choisissez une date de début et une date de fin valides.');
            Response::redirect('/gestionnaire');
            return;
        }

        // Bornes inversées : on les remet dans l'ordre plutôt que d'échouer
        if ($du > $au) {
            [$du, $au] = [$au, $du];
        }

        $rows = (new Dossier())->bilanPeriode($etabId, $type, $du->format('Y-m-d'), $au->format('Y-m-d'));

        JournalGestionnaire::log('bilan.export', 'etablissement', $etabId, sprintf(
            'Bilan des %ss du %s au %s exporté (%d candidat%s)',
            $type === 'inscription' ? 'inscription' : 'préinscription',
            $du->format('d/m/Y'),
            $au->format('d/m/Y'),
            count($rows),
            count($rows) > 1 ? 's' : ''
        ));

        header('Content-Type: text/html; charset=utf-8');
        // Justification : render() produit un document HTML de confiance ; toutes
        // les valeurs dynamiques (nom, email, téléphone, filière…) sont échappées
        // via htmlspecialchars(ENT_QUOTES,'UTF-8') dans la vue _bilan-periode.php.
        // nosemgrep: php.lang.security.injection.echoed-request.echoed-request
        echo (new BilanPeriodeService())->render($etab, $type, $du, $au, $rows);
    }

    /** 'Y-m-d' strict (input type="date") → DateTime à minuit, ou null si invalide. */
    private static function dateOuNull(string $s): ?\DateTime
    {
        $d = \DateTime::createFromFormat('!Y-m-d', $s);
        return ($d !== false && $d->format('Y-m-d') === $s) ? $d : null;
    }
}
