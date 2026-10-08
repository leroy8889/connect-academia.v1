<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\AnneeUniversitaire;
use Core\Database;
use Core\Pdf;
use Models\Orientation\DossierStatut;
use PDO;

/**
 * Reçu de règlement de scolarité — le document remis au candidat une fois son
 * inscription validée à 100 %.
 *
 * ── Trois documents, trois promesses. Ne pas les confondre ─────────────────
 *  - le RÉCÉPISSÉ atteste d'un DÉPÔT de dossier, et sort à 0 % ;
 *  - l'ATTESTATION prouve une INSCRIPTION, et sort à 100 % ;
 *  - le REÇU, ici, prouve un RÈGLEMENT : combien a été versé, quand, comment,
 *    et ce qu'il reste à payer. C'est la seule pièce que le candidat pourra
 *    opposer à l'établissement en cas de litige sur un encaissement.
 *
 * Depuis que la scolarité se règle au guichet, la plateforme n'encaisse plus
 * rien : elle n'émet donc pas une facture, mais le reflet fidèle de ce que
 * l'établissement a déclaré au moment de la validation à 100 % (spec §8.4).
 * Le document le dit explicitement — c'est ce qui le rend défendable.
 *
 * ── Rendu à la demande, jamais stocké ─────────────────────────────────────
 * Contrairement au récépissé et à l'attestation, le reçu n'est ni déposé sur
 * B2 ni numéroté en base. Il est intégralement dérivé de colonnes déjà
 * présentes dans `dossiers_inscription` : deux rendus du même dossier donnent
 * le même document, au même numéro. Aucune migration, aucun état à
 * resynchroniser, aucun fichier à rattraper si la génération échoue.
 *
 * Le numéro suit la référence du dossier, avec un préfixe distinct :
 *   dossier  CA-2026-ESM-000147
 *   reçu    REC-2026-ESM-000147
 * Un agent qui lit l'un retrouve l'autre sans chercher.
 *
 * ── dompdf, donc des tables ───────────────────────────────────────────────
 * Même contrainte que le récépissé : ni flexbox ni grid dans le moteur. Toute
 * la mise en page passe par des tables et des unités `pt`.
 */
final class RecuInscriptionService
{
    /** États dans lesquels un règlement a été enregistré (héritage compris). */
    private const ETATS_ELIGIBLES = ['valide_100', 'inscrit', 'accepte'];

    /** Libellés des modes de règlement, alignés sur DecisionEtablissement. */
    private const MODES = [
        'especes'      => 'Espèces',
        'mobile_money' => 'Mobile money',
        'virement'     => 'Virement bancaire',
        'cheque'       => 'Chèque',
        'autre'        => 'Autre',
    ];

    /**
     * Le dossier peut-il donner lieu à un reçu ?
     *
     * Deux conditions cumulatives, et les deux comptent : l'inscription doit
     * être définitive ET un montant doit avoir été saisi. Un dossier validé à
     * 100 % sans montant est le signe d'une reprise de données ; émettre un
     * reçu à zéro franc y ferait plus de mal que de bien.
     */
    public static function disponible(?array $dossier): bool
    {
        if ($dossier === null) {
            return false;
        }

        if (!in_array((string)($dossier['statut'] ?? ''), self::ETATS_ELIGIBLES, true)) {
            return false;
        }

        return (float)($dossier['montant_scolarite_annuelle'] ?? 0) > 0;
    }

    /** Numéro du reçu, dérivé de la référence du dossier. Jamais aléatoire. */
    public static function numero(array $dossier): string
    {
        $reference = trim((string)($dossier['reference'] ?? ''));

        if ($reference !== '' && str_starts_with($reference, 'CA-')) {
            return 'REC-' . substr($reference, 3);
        }

        // Dossier antérieur à la numérotation CA- : on retombe sur l'identifiant,
        // qui reste unique et retrouvable.
        return 'REC-' . date('Y') . '-' . str_pad((string)(int)($dossier['id'] ?? 0), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Rend le PDF du reçu, ou null si le dossier n'y a pas droit ou si le
     * moteur défaille. Ne touche jamais la base.
     */
    public static function rendre(int $dossierId): ?string
    {
        $donnees = self::rassembler($dossierId);

        if ($donnees === null) {
            return null;
        }

        ob_start();
        // Le gabarit lit $donnees et ne fait aucune requête : il reçoit des
        // chaînes déjà formatées.
        include BASE_PATH . '/app/Views/orientation/_recu-inscription-pdf.php';
        $html = (string)ob_get_clean();

        // Empreinte calculée sur le HTML source : deux rendus du même dossier
        // donnent la même valeur, là où le PDF binaire varie (horodatage
        // interne du moteur). C'est ce qui permet de comparer deux exemplaires.
        $empreinte = substr(hash('sha256', $html), 0, 12);
        $html      = str_replace('{{EMPREINTE}}', $empreinte, $html);

        return Pdf::rendre($html);
    }

    /**
     * Rassemble tout ce que le gabarit affiche, déjà mis en forme.
     *
     * @return array<string,mixed>|null
     */
    private static function rassembler(int $dossierId): ?array
    {
        try {
            $db = Database::getInstance()->getConnection();

            $st = $db->prepare(
                'SELECT d.*,
                        e.nom AS etab_nom, e.ville AS etab_ville, e.adresse AS etab_adresse,
                        e.telephone AS etab_tel, e.email_contact AS etab_email,
                        e.logo_cle, e.signature_cle, e.code_court AS etab_code
                   FROM dossiers_inscription d
                   JOIN etablissements e ON e.id = d.etablissement_id
                  WHERE d.id = ? LIMIT 1'
            );
            $st->execute([$dossierId]);
            $dossier = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[RecuInscription] Lecture échouée #' . $dossierId . ' : ' . $e->getMessage());

            return null;
        }

        if ($dossier === false || !self::disponible($dossier)) {
            return null;
        }

        // ── Deux natures de somme, jamais mélangées ──────────────────────────
        // Les frais d'inscription sont dus une fois et acquis à l'établissement.
        // Le versement, lui, s'impute sur la scolarité annuelle. Les additionner
        // minorerait le reste à régler du candidat et lui ferait perdre la trace
        // écrite du règlement de ses frais.
        $scolarite = (float)$dossier['montant_scolarite_annuelle'];
        $encaisse  = (float)($dossier['montant_encaisse_initial'] ?? 0);
        $reste     = max(0.0, $scolarite - $encaisse);

        // NULL (colonne absente ou non renseignée) ≠ 0 : dans un cas la ligne
        // est masquée, dans l'autre elle affiche « 0 XAF », ce qui affirmerait
        // que l'établissement ne prend pas de frais d'inscription.
        $fraisBruts = $dossier['montant_frais_inscription'] ?? null;
        $frais      = $fraisBruts === null ? null : (float)$fraisBruts;
        $totalJour  = $encaisse + ($frais ?? 0.0);

        // Le jeton du DOSSIER, pas du document : un reçu réimprimé six mois
        // plus tard pointe toujours vers l'état réel du dossier (spec §7).
        $jeton = JetonDossier::pour($dossierId, $db ?? null);

        $candidat = trim((string)($dossier['nom_complet'] ?? '')) ?: '—';

        $datePaiement = self::dateOuNull((string)($dossier['date_paiement'] ?? ''));
        $dateValide   = self::dateOuNull((string)($dossier['valide_100_le'] ?? ''));

        return [
            'dossier'        => $dossier,
            'numero'         => self::numero($dossier),
            'reference'      => (string)($dossier['reference'] ?? ''),
            'annee_libelle'  => str_replace(
                '-',
                ' / ',
                (string)($dossier['annee_academique'] ?: AnneeUniversitaire::code())
            ),
            'candidat'       => $candidat,
            'candidat_long'  => mb_strlen($candidat) > self::SEUIL_NOM_LONG,
            'etab_nom_long'  => mb_strlen(trim((string)($dossier['etab_nom'] ?? ''))) > self::SEUIL_ETAB_NOM_LONG,
            'email'          => trim((string)($dossier['email'] ?? '')) ?: '—',
            'telephone'      => self::telephone($dossier),
            'formation'      => self::borner(self::formation($dossier), self::MAX_FORMATION),
            'etab_ligne'     => self::borner(self::ligneEtablissement($dossier), self::MAX_ETAB_LIGNE),
            'logo_etab'      => LogoDataUri::pour((string)($dossier['logo_cle'] ?? '')),
            'signature'      => SignatureDataUri::pour((string)($dossier['signature_cle'] ?? '')),
            'motif'          => MotifSecurite::tuile(),
            'qr'             => $jeton !== null
                ? QrCodeVerification::dataUriPng($jeton['token'], 7, QrCodeVerification::CHEMIN_DOSSIER)
                : '',
            'code_court'     => $jeton !== null ? JetonDossier::formaterCodeCourt($jeton['code_court']) : '',
            'mode_paiement'  => self::MODES[(string)($dossier['mode_paiement'] ?? '')] ?? 'Non précisé',
            'ref_paiement'   => trim((string)($dossier['reference_paiement'] ?? '')) ?: '—',
            'date_paiement'  => $datePaiement?->format('d/m/Y') ?? '—',
            'date_emission'  => ($dateValide ?? new \DateTimeImmutable())->format('d/m/Y'),
            'scolarite'      => self::montant($scolarite),
            'encaisse'       => self::montant($encaisse),
            'reste'          => self::montant($reste),
            'solde_regle'    => $reste <= 0.0,
            // Frais d'inscription : bloc à part entière, masqué s'il n'y en a
            // pas. Jamais additionné à la scolarité, jamais déduit du reste.
            'a_frais'        => $frais !== null && $frais > 0.0,
            'frais'          => self::montant($frais ?? 0.0),
            'total_jour'     => self::montant($totalJour),
            // La mention en toutes lettres porte sur le TOTAL effectivement
            // remis par le candidat ce jour-là : c'est la somme qu'il pourra
            // opposer à l'établissement, pas l'une de ses deux composantes.
            'lettres'        => self::enLettres($totalJour),
            'statut_libelle' => DossierStatut::libelle((string)$dossier['statut']),
        ];
    }

    // ── Mise en forme ────────────────────────────────────────────────────────

    /**
     * Longueurs maximales des champs à contenu libre.
     *
     * Le gabarit est calibré pour tenir sur UNE page A4 : son budget de hauteur
     * est fixe, alors que ces chaînes viennent de la saisie d'un établissement
     * et peuvent enfler sans limite. Un reçu de deux pages est un reçu raté —
     * la seconde page ne porte que le pied, et le candidat imprime deux
     * feuilles pour une seule information.
     *
     * Les valeurs juridiques (montants, numéros, dates) ne sont JAMAIS bornées.
     * Le nom du candidat non plus : il rétrécit typographiquement au lieu
     * d'être coupé, car un nom tronqué sur un justificatif est inacceptable.
     */
    private const MAX_FORMATION  = 60;
    private const MAX_ETAB_LIGNE = 76;

    /** Au-delà, le nom passe d'un cran de corps plutôt que d'être coupé. */
    private const SEUIL_NOM_LONG      = 30;
    private const SEUIL_ETAB_NOM_LONG = 36;

    /** Tronque proprement, avec une ellipse, sans couper un mot en deux. */
    private static function borner(string $valeur, int $max): string
    {
        if (mb_strlen($valeur) <= $max) {
            return $valeur;
        }

        $coupe = mb_substr($valeur, 0, $max - 1);
        $espace = mb_strrpos($coupe, ' ');

        if ($espace !== false && $espace > $max * 0.6) {
            $coupe = mb_substr($coupe, 0, $espace);
        }

        return rtrim($coupe, " ,;:·-") . '…';
    }

    /** « 750 000 XAF ». Espace insécable exclu : dompdf le rend mal. */
    private static function montant(float $valeur): string
    {
        return number_format($valeur, 0, ',', ' ') . ' XAF';
    }

    private static function formation(array $dossier): string
    {
        $parts = array_filter([
            trim((string)($dossier['filiere_visee'] ?? '')),
            trim((string)($dossier['specialite_visee'] ?? '')),
            trim((string)($dossier['niveau_entree'] ?? '')),
        ], static fn(string $v): bool => $v !== '');

        return $parts === [] ? '—' : implode(' · ', $parts);
    }

    private static function ligneEtablissement(array $dossier): string
    {
        $parts = array_filter([
            trim((string)($dossier['etab_adresse'] ?? '')),
            trim((string)($dossier['etab_ville'] ?? '')),
            trim((string)($dossier['etab_tel'] ?? '')),
            trim((string)($dossier['etab_email'] ?? '')),
        ], static fn(string $v): bool => $v !== '');

        return implode(' · ', $parts);
    }

    private static function telephone(array $dossier): string
    {
        $numero = trim((string)($dossier['telephone'] ?? ''));

        if ($numero === '') {
            return '—';
        }

        return trim((string)($dossier['indicatif_tel'] ?? '+241') . ' ' . $numero);
    }

    private static function dateOuNull(string $valeur): ?\DateTimeImmutable
    {
        if (trim($valeur) === '' || str_starts_with($valeur, '0000')) {
            return null;
        }

        try {
            return new \DateTimeImmutable($valeur);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Montant en toutes lettres — la mention qui donne à un reçu sa valeur
     * probante : un chiffre se retouche au stylo, une phrase beaucoup moins.
     *
     * Écrite à la main plutôt qu'avec NumberFormatter : l'extension intl n'est
     * pas garantie sur l'hébergement, et un reçu sans sa mention en lettres
     * serait un reçu dégradé.
     */
    public static function enLettres(float $montant): string
    {
        $entier = (int)round($montant);

        if ($entier <= 0) {
            return 'zéro franc CFA';
        }

        $lettres = trim(self::nombreEnLettres($entier, true));

        // « million » est un nom, pas un adjectif numéral : on dit « deux
        // millions DE francs », mais « deux millions cinq cent mille francs ».
        // Le « de » ne s'ajoute donc que si la phrase se termine par million(s).
        $de = (str_ends_with($lettres, 'million') || str_ends_with($lettres, 'millions')) ? ' de' : '';

        return $lettres . $de . ' franc' . ($entier > 1 ? 's' : '') . ' CFA';
    }

    /**
     * Composition d'un entier en toutes lettres.
     *
     * @param bool $final Ce groupe termine-t-il le nombre ? « vingt » et
     *        « cent » ne prennent leur `s` de pluriel que dans ce cas :
     *        « quatre-vingts » mais « quatre-vingt mille », « deux cents »
     *        mais « deux cent mille ». Sans ce drapeau, un reçu de 280 000
     *        s'écrivait « deux cent quatre-vingts mille ».
     */
    private static function nombreEnLettres(int $n, bool $final = true): string
    {
        $unites = [
            0 => 'zéro', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre', 5 => 'cinq',
            6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf', 10 => 'dix', 11 => 'onze',
            12 => 'douze', 13 => 'treize', 14 => 'quatorze', 15 => 'quinze', 16 => 'seize',
        ];
        // La dizaine 1 est indispensable : 17, 18 et 19 se construisent comme
        // les autres (dix-sept), et sans elle la composition partait en morceaux.
        $dizaines = [
            1 => 'dix', 2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante',
            6 => 'soixante', 7 => 'soixante', 8 => 'quatre-vingt', 9 => 'quatre-vingt',
        ];

        if ($n < 17) {
            return $unites[$n];
        }

        if ($n < 100) {
            $d = intdiv($n, 10);
            $u = $n % 10;

            // 70 et 90 se construisent sur 60 et 80 : soixante-dix, quatre-vingt-dix.
            if ($d === 7 || $d === 9) {
                $u += 10;
                $d--;
            }

            $mot = $dizaines[$d];

            if ($u === 0) {
                // « quatre-vingts » prend un s quand rien ne suit ; il le perd
                // devant « mille » ou « millions ».
                return ($d === 8 && $final) ? $mot . 's' : $mot;
            }

            // « et » devant l'unité de 21 à 71 inclus, jamais sur la série des
            // quatre-vingts : vingt et un, soixante et onze, mais
            // quatre-vingt-un et quatre-vingt-onze.
            $avecEt = ($d !== 8)
                && (($u === 1 && $d >= 2) || ($u === 11 && $d === 6));

            return $mot . ($avecEt ? ' et ' : '-') . self::nombreEnLettres($u, $final);
        }

        if ($n < 1000) {
            $c     = intdiv($n, 100);
            $reste = $n % 100;
            $tete  = $c === 1 ? 'cent' : self::nombreEnLettres($c, false) . ' cent';

            if ($reste === 0) {
                // « deux cents », mais « deux cent mille ».
                return ($c === 1 || !$final) ? $tete : $tete . 's';
            }

            return $tete . ' ' . self::nombreEnLettres($reste, $final);
        }

        if ($n < 1_000_000) {
            $milliers = intdiv($n, 1000);
            $reste    = $n % 1000;
            // Le groupe des milliers n'est jamais final : « mille » le suit.
            $tete     = $milliers === 1 ? 'mille' : self::nombreEnLettres($milliers, false) . ' mille';

            return $reste === 0 ? $tete : $tete . ' ' . self::nombreEnLettres($reste, $final);
        }

        if ($n < 1_000_000_000) {
            $millions = intdiv($n, 1_000_000);
            $reste    = $n % 1_000_000;
            $tete     = $millions === 1 ? 'un million' : self::nombreEnLettres($millions, false) . ' millions';

            return $reste === 0 ? $tete : $tete . ' ' . self::nombreEnLettres($reste, $final);
        }

        // Au-delà du milliard, la mention en lettres n'apporte plus rien sur un
        // reçu de scolarité : on rend le chiffre plutôt qu'une phrase illisible.
        return number_format($n, 0, ',', ' ');
    }
}
