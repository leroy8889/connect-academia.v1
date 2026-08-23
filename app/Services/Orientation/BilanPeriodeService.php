<?php
declare(strict_types=1);

namespace Services\Orientation;

/**
 * Bilan statistique PDF (préinscriptions / inscriptions sur une période)
 * téléchargé depuis le tableau de bord gestionnaire.
 *
 * Même famille de documents que le récépissé et l'attestation : HTML
 * autonome prêt à imprimer (Ctrl+P → « Enregistrer en PDF »), aucune
 * ressource externe hormis la police. Ici le document est aux couleurs
 * de Connect'Academia (design system : Purple Neon, Montserrat) et non
 * à celles de l'établissement — c'est un état statistique de la
 * plateforme, pas un document officiel de l'école.
 */
class BilanPeriodeService
{
    /** Anciennes clés ENUM encore présentes dans les dossiers historiques */
    private const FILIERES_LEGACY = [
        'socle_l1_l2'   => 'Socle L1-L2',
        'licence_l3'    => 'Licence L3',
        'master_m1_m2'  => 'Master M1-M2',
        'certification' => 'Certification',
    ];

    /** Logo plateforme dérivé (320 px, ~25 Ko) — jamais l'original 8 Mo */
    private const LOGO_DERIVE = '/public/assets/images/logo-officiel-320.png';

    /**
     * Rend le bilan en HTML imprimable.
     *
     * @param array               $etab Row établissement (nom, ville).
     * @param string              $type 'preinscription' | 'inscription'
     * @param \DateTimeInterface  $du   Début de période (inclus)
     * @param \DateTimeInterface  $au   Fin de période (incluse)
     * @param array               $rows Lignes de Dossier::bilanPeriode()
     */
    public function render(array $etab, string $type, \DateTimeInterface $du, \DateTimeInterface $au, array $rows): string
    {
        $estInscription = ($type === 'inscription');

        $titreDoc = $estInscription ? 'Bilan des inscriptions' : 'Bilan des préinscriptions';

        $etabNom   = (string)($etab['nom'] ?? '');
        $etabVille = (string)($etab['ville'] ?? '');

        $duStr      = $du->format('d/m/Y');
        $auStr      = $au->format('d/m/Y');
        $genereLe   = (new \DateTime())->format('d/m/Y à H\hi');

        // Lignes normalisées pour le gabarit : téléphone complet, filière lisible
        $lignes    = [];
        $nbEnligne = 0;
        $nbGuichet = 0;
        foreach ($rows as $r) {
            $telBrut = trim((string)($r['telephone'] ?? ''));
            $tel     = '—';
            if ($telBrut !== '') {
                // Ne pas doubler l'indicatif si le numéro saisi le contient déjà
                $tel = str_starts_with($telBrut, '+')
                    ? $telBrut
                    : trim((string)($r['indicatif_tel'] ?? '+241')) . ' ' . $telBrut;
            }

            $filiere = trim((string)($r['filiere_visee'] ?? ''));
            $filiere = self::FILIERES_LEGACY[$filiere] ?? ($filiere !== '' ? $filiere : '—');

            $niveau = trim((string)($r['niveau_entree'] ?? '')) ?: '—';

            if (($r['origine'] ?? '') === 'etablissement') {
                $nbGuichet++;
            } else {
                $nbEnligne++;
            }

            $dateEvt = '—';
            if (!empty($r['date_evenement'])) {
                try {
                    $dateEvt = (new \DateTime((string)$r['date_evenement']))->format('d/m/Y');
                } catch (\Throwable) {
                    // Date illisible : le tiret reste
                }
            }

            $lignes[] = [
                'nom'     => trim((string)($r['nom_complet'] ?? '')) ?: '—',
                'email'   => trim((string)($r['email'] ?? '')) ?: '—',
                'tel'     => $tel,
                'filiere' => $filiere,
                'niveau'  => $niveau,
                'date'    => $dateEvt,
            ];
        }
        $total = count($lignes);

        $logoDataUri = $this->logoPlateforme();

        ob_start();
        include BASE_PATH . '/app/Views/orientation/_bilan-periode.php';
        return (string)ob_get_clean();
    }

    /**
     * Logo Connect'Academia en data URI (dérivé léger). Chaîne vide si le
     * fichier manque : le gabarit bascule sur le monogramme typographique.
     */
    private function logoPlateforme(): string
    {
        $chemin = BASE_PATH . self::LOGO_DERIVE;
        if (!is_file($chemin)) {
            return '';
        }
        $bin = @file_get_contents($chemin);
        return $bin === false ? '' : 'data:image/png;base64,' . base64_encode($bin);
    }
}
