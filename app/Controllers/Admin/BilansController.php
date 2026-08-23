<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\Pdf;
use Core\Response;
use Core\Session;
use Dompdf\Dompdf;
use Models\Bilan;

/**
 * Génération des bilans d'activité au format PDF (dashboard admin).
 *
 * Un seul point d'entrée `export()` : le paramètre `type` choisit le jeu de
 * données (préinscriptions / inscriptions / utilisateurs), la plage de dates
 * borne la période, et `etablissement_id` (optionnel) restreint aux dossiers
 * d'un établissement. Le PDF est rendu via dompdf et streamé inline.
 */
class BilansController
{
    private const TYPES = ['preinscriptions', 'inscriptions', 'utilisateurs'];

    public function export(): void
    {
        $type = (string)($_GET['type'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            $this->fail('Type de bilan invalide.');
        }

        [$debut, $fin] = $this->plage($_GET['debut'] ?? '', $_GET['fin'] ?? '');

        $model  = new Bilan();
        $etabId = isset($_GET['etablissement_id']) && $_GET['etablissement_id'] !== ''
            ? (int)$_GET['etablissement_id']
            : null;

        // Établissement ciblé (rapports dossiers) : null = tous confondus.
        $etab = ($type !== 'utilisateurs' && $etabId !== null) ? $model->etablissement($etabId) : null;
        if ($type !== 'utilisateurs' && $etabId !== null && $etab === null) {
            $this->fail('Établissement introuvable.');
        }

        // --- Données + libellés selon le type -----------------------------
        $filieres = Bilan::FILIERES;
        $statuts  = Bilan::STATUTS;

        switch ($type) {
            case 'preinscriptions':
                $rows      = $model->preinscriptions($etabId, $debut, $fin);
                $titre     = 'Bilan des préinscriptions';
                $sousTitre = 'Candidatures déposées';
                break;
            case 'inscriptions':
                $rows      = $model->inscriptions($etabId, $debut, $fin);
                $titre     = 'Bilan des inscriptions';
                $sousTitre = 'Inscriptions validées';
                break;
            default:
                $rows      = $model->utilisateurs($debut, $fin);
                $titre     = 'Bilan des utilisateurs';
                $sousTitre = 'Comptes de la plateforme';
        }

        // Portée affichée dans l'en-tête.
        if ($type === 'utilisateurs') {
            $portee = 'Plateforme Connect\'Academia';
        } elseif ($etab !== null) {
            $portee = trim(($etab['nom'] ?? '') . (($etab['ville'] ?? '') !== '' ? ' — ' . $etab['ville'] : ''));
        } else {
            $portee = 'Tous les établissements';
        }

        $couleur      = '#8B52FA'; // Accent Connect'Academia (violet).
        $accent2      = '#6D3BEA'; // Violet profond — dégradé d'en-tête.
        $coral        = '#D97757'; // Corail secondaire — liseré d'accent.
        $periode      = $this->libellePeriode($debut, $fin);
        $dateEmission = date('d/m/Y à H:i');
        $reference    = 'BIL-' . strtoupper($type[0]) . '-' . date('Ymd-His');
        $total        = count($rows);
        $nomFichier   = sprintf('bilan-%s-%s.pdf', $type, date('Ymd-His'));

        // --- Rendu du gabarit ---------------------------------------------
        $gabarit = BASE_PATH . '/app/Views/admin/bilans/_pdf.php';
        if (!is_file($gabarit)) {
            error_log('[Bilans] Gabarit PDF introuvable : ' . $gabarit);
            $this->fail('Gabarit du bilan introuvable sur le serveur.');
        }

        $niveau = ob_get_level();
        try {
            ob_start();
            include $gabarit;
            $html = (string)ob_get_clean();
        } catch (\Throwable $e) {
            // Le gabarit a explosé en plein tampon : le refermer avant tout.
            while (ob_get_level() > $niveau) {
                ob_end_clean();
            }
            error_log('[Bilans] Gabarit en erreur : ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
            $this->fail('Le gabarit du bilan a échoué. Consultez les journaux.');
        }

        // Un gabarit muet donnerait un PDF d'une page entièrement blanche :
        // mieux vaut une erreur explicite qu'un document vide.
        if (trim($html) === '') {
            error_log('[Bilans] Gabarit sans sortie (type=' . $type . ', lignes=' . $total . ')');
            $this->fail('Le bilan est vide : rendu impossible.');
        }

        // Sortie de secours pour diagnostiquer en production sans accès SSH :
        // `&format=html` renvoie le gabarit tel quel, avant passage dompdf.
        if (($_GET['format'] ?? '') === 'html') {
            $this->purger();
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit;
        }

        $this->stream($html, $nomFichier);
    }

    /**
     * Diagnostic de la chaîne PDF, exécuté sur le serveur qui pose problème.
     *
     * Un PDF blanc peut venir d'une dizaine de causes qui se ressemblent toutes
     * vues du navigateur (police absente, sortie tronquée, en-tête parasite,
     * moteur PHP qui compile mal la librairie). Cette page rend un document
     * minimal puis inspecte le résultat octet par octet : on lit la cause au
     * lieu de la deviner. Réservé au super-admin, aucun effet de bord.
     */
    public function diagnostic(): void
    {
        if (Session::adminRole() !== 'super_admin') {
            Response::redirect('/admin');
        }

        $l = [];
        $l[] = '=== ENVIRONNEMENT ===';
        $l[] = 'PHP                    : ' . PHP_VERSION . ' (' . PHP_SAPI . ')';
        $l[] = 'memory_limit           : ' . ini_get('memory_limit');
        $l[] = 'max_execution_time     : ' . ini_get('max_execution_time');
        $l[] = 'output_buffering       : ' . var_export(ini_get('output_buffering'), true);
        $l[] = 'zlib.output_compression: ' . var_export(ini_get('zlib.output_compression'), true);
        $l[] = 'niveaux de tampon      : ' . ob_get_level();
        $l[] = 'en-têtes déjà envoyés  : ' . (headers_sent() ? 'OUI (anormal)' : 'non');

        $l[] = '';
        $l[] = '=== OPCACHE / JIT ===';
        // Le JIT est le suspect n°1 d'un rendu correct en local et vide en
        // production : il ne change rien au code, seulement à son exécution.
        $l[] = 'opcache.enable         : ' . var_export(ini_get('opcache.enable'), true);
        $l[] = 'opcache.jit            : ' . var_export(ini_get('opcache.jit'), true);
        $l[] = 'opcache.jit_buffer_size: ' . var_export(ini_get('opcache.jit_buffer_size'), true);
        if (function_exists('opcache_get_status')) {
            $st = @opcache_get_status(false);
            $l[] = 'JIT actif              : ' . var_export($st['jit']['enabled'] ?? null, true)
                 . ' / en service : ' . var_export($st['jit']['on'] ?? null, true);
        }

        $l[] = '';
        $l[] = '=== EXTENSIONS ===';
        foreach (['mbstring', 'dom', 'gd', 'zlib', 'iconv'] as $ext) {
            $l[] = str_pad($ext, 23) . ': ' . (extension_loaded($ext) ? 'chargée' : 'ABSENTE');
        }

        $l[] = '';
        $l[] = '=== DOMPDF ===';
        $fontsLib = BASE_PATH . '/vendor/dompdf/dompdf/lib/fonts';
        $cache    = BASE_PATH . '/storage/dompdf';
        $l[] = 'polices livrées        : ' . (is_dir($fontsLib) ? 'présentes' : 'DOSSIER ABSENT')
             . ' (' . (is_dir($fontsLib) ? (string)count((array)glob($fontsLib . '/*.ttf')) : '0') . ' .ttf)';
        $l[] = 'lib inscriptible       : ' . (is_writable($fontsLib) ? 'oui' : 'non');
        $l[] = 'storage/dompdf         : ' . (is_dir($cache) ? 'existe' : 'absent')
             . ', inscriptible : ' . (is_dir($cache) && is_writable($cache) ? 'oui' : 'NON');

        $l[] = '';
        $l[] = '=== RENDUS RÉPÉTÉS (mise en chauffe) ===';
        // Un seul rendu ne prouve rien : juste après un déploiement le code est
        // interprété et sort un document correct. La panne n'apparaît qu'une fois
        // les fonctions assez appelées pour que le JIT les compile. On répète
        // donc, et on surveille le moment où le résultat change.
        $a       = null;
        $premier = null;
        $bascule = 0;
        for ($i = 1; $i <= 12; $i++) {
            $a = $this->essaiRendu();
            $signature = ($a['sain'] ? 'sain' : 'CASSÉ') . ' ' . implode(' | ', $a['lignes']);
            if ($premier === null) {
                $premier = $a['sain'];
                $l[] = '  rendu 1  : ' . ($a['sain'] ? 'sain' : 'CASSÉ');
                foreach ($a['lignes'] as $ligne) {
                    $l[] = '    ' . $ligne;
                }
            } elseif ($a['sain'] !== $premier && $bascule === 0) {
                $bascule = $i;
                $l[] = '  rendu ' . $i . '  : BASCULE — le résultat change en cours de requête';
                foreach ($a['lignes'] as $ligne) {
                    $l[] = '    ' . $ligne;
                }
            }
            unset($signature);
        }
        $l[] = '  rendu 12 : ' . ($a['sain'] ? 'sain' : 'CASSÉ');
        if ($bascule === 0) {
            $l[] = '  → résultat stable sur 12 rendus';
        }

        // A/B avec le JIT coupé : `opcache.jit` se change en cours d'exécution.
        // Si le même code produit un document sain une fois le JIT hors service,
        // c'est le compilateur qui abîme dompdf, pas l'application.
        $b = null;
        if ((string)ini_get('opcache.jit') !== '' && (string)ini_get('opcache.jit') !== 'off'
            && (int)ini_get('opcache.jit_buffer_size') !== 0) {
            $l[] = '';
            $l[] = '=== MÊME RENDU, JIT COUPÉ ===';
            $avant = ini_get('opcache.jit');
            if (@ini_set('opcache.jit', 'off') === false) {
                $l[] = '  bascule REFUSÉE : opcache.jit non modifiable à chaud sur ce serveur.';
                $l[] = '  Le pare-feu applicatif est donc inopérant ici — seul le php.ini compte.';
            } else {
                $b = $this->essaiRendu(true);
                foreach ($b['lignes'] as $ligne) {
                    $l[] = '  ' . $ligne;
                }
                @ini_set('opcache.jit', (string)$avant);
            }
        }

        $l[] = '';
        $l[] = '=== VERDICT ===';
        if (!$a['sain'] && $b !== null && $b['sain']) {
            $l[] = 'LE JIT OPCACHE EST EN CAUSE. Le même code rend un PDF correct';
            $l[] = 'dès que le JIT est hors service. Correctif serveur :';
            $l[] = '  opcache.jit_buffer_size=0   (dans le php.ini de PHP-FPM)';
            $l[] = '  puis : systemctl reload php8.3-fpm';
            $l[] = 'Le reste du réglage opcache (512 Mo de cache) est conservé.';
        } elseif (!$a['sain']) {
            $l[] = 'MOTEUR PDF EN CAUSE, hors JIT. Regarder ci-dessus : polices';
            $l[] = 'absentes, signature invalide ou exception.';
        } else {
            $l[] = 'MOTEUR PDF SAIN sur ce serveur : le document produit ici contient';
            $l[] = 'ses polices et ses pages. Si les documents livrés sont blancs, la';
            $l[] = 'cause est en aval (livraison HTTP, cache, ou données du gabarit).';
        }

        $this->purger();
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo implode("\n", $l) . "\n";
        exit;
    }

    /**
     * Rend un document minimal et inspecte les octets produits.
     * @return array{sain:bool,lignes:string[]}
     */
    private function essaiRendu(bool $couperJit = false): array
    {
        $html = '<html><body style="font-family:\'DejaVu Sans\'">'
              . '<h1 style="color:#8B52FA">Essai de rendu</h1>'
              . '<p>Accents : éàçùôî — ' . date('d/m/Y H:i:s') . '</p></body></html>';

        try {
            // Par défaut on observe le moteur TEL QU'IL TOURNE, sans le pare-feu
            // applicatif : c'est le comportement réel qu'il faut mesurer.
            $dompdf = new Dompdf(Pdf::options($couperJit));
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdf = (string)$dompdf->output();
        } catch (\Throwable $e) {
            return ['sain' => false, 'lignes' => [
                'EXCEPTION : ' . get_class($e) . ' — ' . $e->getMessage(),
                'à ' . $e->getFile() . ':' . $e->getLine(),
            ]];
        }

        preg_match_all('/\/BaseFont\s*\/([A-Za-z0-9+#-]+)/', $pdf, $mf);
        preg_match_all('/\/Type\s*\/Page[^s]/', $pdf, $mp);
        $polices = array_values(array_unique($mf[1]));
        $valide  = str_starts_with($pdf, '%PDF');

        return [
            'sain'   => $valide && $polices !== [] && $mp[0] !== [],
            'lignes' => [
                'octets produits        : ' . strlen($pdf),
                'signature              : ' . var_export(substr($pdf, 0, 5), true)
                    . ($valide ? ' (valide)' : ' (INVALIDE)'),
                'pages                  : ' . count($mp[0]),
                'polices embarquées     : ' . ($polices ? implode(', ', $polices) : 'AUCUNE → pages blanches'),
            ],
        ];
    }

    /**
     * Normalise la plage : dates YYYY-MM-DD, bornes datetime inclusives.
     * Défauts sûrs si vide ; réordonne si debut > fin.
     * @return array{0:string,1:string}
     */
    private function plage(mixed $debutIn, mixed $finIn): array
    {
        $reg   = static fn($v): string => (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) ? $v : '';
        $debut = $reg($debutIn) ?: '2000-01-01';
        $fin   = $reg($finIn)   ?: date('Y-m-d');

        if ($debut > $fin) {
            [$debut, $fin] = [$fin, $debut];
        }

        return [$debut . ' 00:00:00', $fin . ' 23:59:59'];
    }

    /** Libellé lisible « du J au J » à partir des bornes datetime. */
    private function libellePeriode(string $debut, string $fin): string
    {
        $d = date('d/m/Y', strtotime($debut));
        $f = date('d/m/Y', strtotime($fin));
        return $d === $f ? "Le {$d}" : "Du {$d} au {$f}";
    }

    /**
     * Rend le PDF puis le sert en téléchargement.
     *
     * Le document est intégralement construit en mémoire AVANT d'écrire quoi que
     * ce soit : si dompdf échoue, l'admin reçoit un message plutôt qu'un fichier
     * blanc. La réponse est ensuite servie « nue » — tampons vidés, compression
     * coupée, en-têtes HTML retirés — car le moindre octet parasite ou la moindre
     * politique héritée du front-controller suffit à donner un PDF illisible.
     */
    private function stream(string $html, string $nomFichier): void
    {
        // Core\Pdf vérifie le document et bascule seul sur une police native si
        // le moteur de glyphes défaille.
        $pdf = Pdf::rendre($html, static function (Dompdf $dompdf): void {
            // Pied de page « Page X / Y » sur chaque page.
            $dompdf->getCanvas()->page_text(
                520, 812, 'Page {PAGE_NUM} / {PAGE_COUNT}',
                null, 8, [0.6, 0.6, 0.6]
            );
        });

        // Dernier recours : plutôt qu'un fichier vide, la même page en HTML,
        // mise en page pour l'impression. L'admin repart toujours avec un
        // document exploitable.
        if ($pdf === null) {
            error_log('[Bilans] Aucun rendu PDF exploitable — repli HTML imprimable.');
            $this->servirHtmlImprimable($html);
        }

        $this->purger();

        // En-têtes HTML du front-controller : sans intérêt sur un flux binaire,
        // et `object-src none` empêche certains navigateurs d'ouvrir le document.
        header_remove('Content-Security-Policy');
        header_remove('X-Frame-Options');
        header_remove('Permissions-Policy');

        header('Content-Type: application/pdf');
        // `attachment` : le fichier arrive sur le disque et s'ouvre dans un vrai
        // lecteur, hors visionneuse intégrée du navigateur.
        header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');

        echo $pdf;
        exit;
    }

    /**
     * Repli : le bilan en HTML, prêt à imprimer ou à enregistrer en PDF depuis
     * le navigateur. Ne revient jamais.
     *
     * Le gabarit est déjà mis en page pour l'impression (largeurs en points,
     * tables) : il suffit d'ouvrir la boîte d'impression et de signaler
     * pourquoi on est arrivé là.
     */
    private function servirHtmlImprimable(string $html): never
    {
        $banniere = '<div style="margin:0 0 14pt;padding:10pt 13pt;border:1px solid #F5C6C0;'
            . 'border-left:3pt solid #D97757;border-radius:8pt;background:#FDF3F1;color:#8C3A26;'
            . 'font:600 11pt sans-serif" data-noprint>'
            . 'Génération PDF indisponible sur ce serveur — voici le bilan en version imprimable. '
            . 'Utilisez l\'impression du navigateur puis « Enregistrer au format PDF ».</div>'
            . '<style>@media print{[data-noprint]{display:none}}</style>'
            . '<script>window.addEventListener("load",function(){window.print();});</script>';

        $pos = stripos($html, '<body');
        $fin = $pos !== false ? strpos($html, '>', $pos) : false;

        $this->purger();
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, no-store');
        echo $fin !== false
            ? substr($html, 0, $fin + 1) . $banniere . substr($html, $fin + 1)
            : $banniere . $html;
        exit;
    }

    /**
     * Vide tous les tampons de sortie et coupe la compression : un PDF ne
     * tolère aucun octet parasite avant `%PDF`.
     */
    private function purger(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');

        if (headers_sent($fichier, $ligne)) {
            error_log("[Bilans] En-têtes déjà envoyés depuis {$fichier}:{$ligne} — réponse corrompue.");
        }
    }

    private function fail(string $message): never
    {
        Response::redirect('/admin?bilan_erreur=' . urlencode($message));
    }
}
