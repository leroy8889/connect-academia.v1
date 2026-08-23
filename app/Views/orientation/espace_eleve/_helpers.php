<?php
/**
 * Helpers partagés des vues Espace élève Orientation (shell .acct).
 * Inclus via require_once — toutes les fonctions sont guardées.
 */

if (!function_exists('acct_icon')) {
    /** Icônes SVG inline (outline 24×24, stroke courant) — fidèles à la maquette. */
    function acct_icon(string $name, int $size = 18, float $sw = 2): string
    {
        static $paths = [
            'grid'        => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
            'user'        => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'fileText'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/>',
            'briefcase'   => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
            'card'        => '<rect x="2" y="5" width="20" height="14" rx="2.5"/><line x1="2" y1="10" x2="22" y2="10"/>',
            'receipt'     => '<path d="M6 2h12v19l-3-2-3 2-3-2-3 2z"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="9" y1="12" x2="15" y2="12"/>',
            'folder'      => '<path d="M4 4h5l2 2h9a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/>',
            'bell'        => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
            'home'        => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"/>',
            'check'       => '<polyline points="20 6 9 17 4 12"/>',
            'checkCircle' => '<circle cx="12" cy="12" r="10"/><polyline points="8 12 11 15 16 9"/>',
            'clock'       => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'download'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
            'upload'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
            'arrowR'      => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
            'school'      => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
            'pin'         => '<path d="M12 21s7-6.4 7-12a7 7 0 0 0-14 0c0 5.6 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
            'calendar'    => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            'idcard'      => '<rect x="2" y="5" width="20" height="14" rx="2.5"/><circle cx="8" cy="12" r="2"/><line x1="14" y1="9" x2="19" y2="9"/><line x1="14" y1="13" x2="19" y2="13"/><line x1="14" y1="17" x2="19" y2="17"/>',
            'image'       => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
            'alert'       => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'shield'      => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'menu'        => '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
            'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
            'compass'     => '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
            'lock'        => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        ];
        $d = $paths[$name] ?? '';
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $sw
             . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
    }
}

if (!function_exists('acct_date')) {
    /** "2026-03-12 10:04:00" → "12 mars 2026" */
    function acct_date(?string $sql, bool $withTime = false): string
    {
        if (!$sql) return '—';
        $ts = strtotime($sql);
        if ($ts === false) return '—';
        static $mois = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $out = (int)date('j', $ts) . ' ' . $mois[(int)date('n', $ts)] . ' ' . date('Y', $ts);
        return $withTime ? $out . ' · ' . date('H\hi', $ts) : $out;
    }
}

if (!function_exists('acct_filiere')) {
    function acct_filiere(?string $code): string
    {
        static $labels = [
            'socle_l1_l2'   => 'Socle L1-L2',
            'licence_l3'    => 'Licence L3',
            'master_m1_m2'  => 'Master M1-M2',
            'certification' => 'Certification',
        ];
        return $labels[$code ?? ''] ?? ($code ?: 'Filière non précisée');
    }
}

if (!function_exists('acct_filiere_spec')) {
    /** "Filière · Spécialité" quand une spécialité a été choisie. */
    function acct_filiere_spec(array $d): string
    {
        $lbl  = acct_filiere($d['filiere_visee'] ?? null);
        $spec = trim((string)($d['specialite_visee'] ?? ''));
        return $spec !== '' ? $lbl . ' · ' . $spec : $lbl;
    }
}

if (!function_exists('acct_statut_label')) {
    /** Libellé contextuel : une inscription "soumis" attend la validation. */
    function acct_statut_label(array $d): string
    {
        $estInscription = !empty($d['inscription_id']);
        return match ($d['statut'] ?? 'brouillon') {
            'brouillon' => 'Brouillon',
            'soumis'    => $estInscription ? 'En attente de validation' : 'Préinscrit',
            'paye'      => 'Paiement confirmé',
            'inscrit'   => 'Inscrit',
            'accepte'   => $estInscription ? 'Inscription validée' : 'Acceptée',
            'refuse'    => 'Refusé',
            default     => (string)($d['statut'] ?? '—'),
        };
    }
}

if (!function_exists('acct_statut_badge')) {
    /** Badge complet (span.badge2) pour un dossier. */
    function acct_statut_badge(array $d): string
    {
        $class = match ($d['statut'] ?? 'brouillon') {
            'brouillon' => 'draft',
            'soumis'    => !empty($d['inscription_id']) ? 'info' : 'ok',
            'paye'      => 'ok',
            'inscrit'   => 'ok',
            'accepte'   => 'ok',
            'refuse'    => 'err',
            default     => 'draft',
        };
        $label = htmlspecialchars(acct_statut_label($d), ENT_QUOTES, 'UTF-8');
        return "<span class=\"badge2 {$class}\"><span class=\"d\"></span>{$label}</span>";
    }
}

if (!function_exists('acct_etab_logo')) {
    /**
     * Logo établissement : le vrai logo uploadé si disponible (data URI compressé
     * + cache Redis via LogoDataUri), sinon repli sur les initiales colorées.
     */
    function acct_etab_logo(array $d, int $size = 48): string
    {
        $couleur = preg_match('/^#[0-9a-fA-F]{6}$/', $d['couleur_principale'] ?? '') ? $d['couleur_principale'] : '#3B6EA5';
        $nom     = trim($d['etab_nom'] ?? '?');
        $radius  = (int)round($size / 4);

        // Vrai logo de l'établissement (si la clé B2 est présente et le fichier lisible)
        $logoUri = '';
        if (!empty($d['logo_cle'])) {
            try {
                $logoUri = \Services\Orientation\LogoDataUri::pour((string)$d['logo_cle']);
            } catch (\Throwable $e) {
                $logoUri = '';
            }
        }
        if ($logoUri !== '') {
            return '<div class="acct-dossier-logo acct-dossier-logo--img" style="width:' . $size . 'px;height:' . $size . 'px;border-radius:' . $radius . 'px;">'
                 . '<img src="' . htmlspecialchars($logoUri, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') . '" loading="lazy" width="' . $size . '" height="' . $size . '">'
                 . '</div>';
        }

        // Repli : initiales sur la couleur de l'établissement
        $mots = preg_split('/\s+/', $nom) ?: [];
        $ini  = '';
        foreach ($mots as $m) {
            if ($ini !== '' && mb_strlen($ini) >= 2) break;
            if (preg_match('/^\p{Lu}/u', $m)) $ini .= mb_strtoupper(mb_substr($m, 0, 1));
        }
        if ($ini === '') $ini = mb_strtoupper(mb_substr($nom, 0, 1));
        $ini = mb_substr($ini, 0, 2);
        $fs  = (int)round($size / 3);
        return '<div class="acct-dossier-logo" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . $fs . 'px;border-radius:' . $radius . 'px;background:'
             . htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8') . ';">' . htmlspecialchars($ini, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}

if (!function_exists('acct_mode_paiement')) {
    function acct_mode_paiement(?string $mode): string
    {
        return match ($mode) {
            'en_ligne'     => 'MoneyFusion · En ligne',
            'mobile_money' => 'Mobile Money',
            'espece'       => 'Espèces · Guichet',
            'virement'     => 'Virement bancaire',
            default        => $mode ?: '—',
        };
    }
}

if (!function_exists('acct_taille')) {
    function acct_taille(?int $octets): string
    {
        if (!$octets) return '';
        if ($octets >= 1048576) return number_format($octets / 1048576, 1, ',', ' ') . ' Mo';
        return max(1, (int)round($octets / 1024)) . ' Ko';
    }
}

if (!function_exists('acct_montant')) {
    function acct_montant(int|float|string|null $xaf): string
    {
        return number_format((int)($xaf ?? 0), 0, ',', ' ') . ' XAF';
    }
}
