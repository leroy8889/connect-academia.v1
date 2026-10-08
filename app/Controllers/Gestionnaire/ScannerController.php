<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\Response;
use Core\Session;
use Services\Orientation\ScanQr;

/**
 * Écran de scan du back-office établissement (spec §8.5).
 *
 * C'est l'écran qu'un agent de guichet garde ouvert toute la journée en
 * période de rentrée. Trois exigences en découlent :
 *
 *  - rapide : la caméra s'ouvre en une action, le résultat tient en une vue ;
 *  - tolérant à une mauvaise connexion : la lecture du QR se fait dans le
 *    navigateur, une seule requête part ensuite ;
 *  - toujours utilisable : si la caméra est refusée, absente, ou si le
 *    document est trop abîmé, la saisie du code court à 8 caractères donne
 *    exactement le même résultat.
 *
 * La bibliothèque de lecture (html5-qrcode) est servie depuis nos propres
 * assets, pas depuis un CDN : la politique de sécurité de contenu du site
 * interdit les scripts tiers, et un guichet doit fonctionner même quand un
 * CDN est injoignable.
 */
class ScannerController
{
    public function index(): void
    {
        $etabId = (int)(Session::get('gestionnaire_etab_id') ?? 0);

        Response::view('gestionnaire/scanner', [
            'pageTitle'   => 'Scanner un récépissé',
            'erreur'      => Session::getFlash('scan_erreur'),
            'csrf'        => Session::getCsrfToken(),
            // Derniers scans de l'établissement : l'agent retrouve d'un coup
            // d'œil le dossier qu'il vient de traiter, sans le rescanner.
            'recents'     => $this->derniersScans($etabId),
        ], 'gestionnaire');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function derniersScans(int $etabId): array
    {
        if ($etabId <= 0) {
            return [];
        }

        try {
            $st = \Core\Database::getInstance()->getConnection()->prepare(
                'SELECT s.dossier_id, s.resultat, s.scanne_le,
                        d.reference, d.nom_complet, d.statut
                 FROM scans_qr s
                 JOIN dossiers_inscription d ON d.id = s.dossier_id
                 WHERE s.etablissement_id = ? AND s.dossier_id IS NOT NULL
                 ORDER BY s.scanne_le DESC
                 LIMIT 8'
            );
            $st->execute([$etabId]);
            $lignes = $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[Scanner] Historique illisible : ' . $e->getMessage());

            return [];
        }

        foreach ($lignes as &$ligne) {
            $ligne['resultat_libelle'] = ScanQr::libelle((string)$ligne['resultat']);
        }

        return $lignes;
    }
}
