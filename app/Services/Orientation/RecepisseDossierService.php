<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\AnneeUniversitaire;
use Core\Database;
use Core\Pdf;
use Models\Orientation\DossierStatut;
use PDO;

/**
 * Récépissé de dépôt de dossier (spec §6).
 *
 * ── Pourquoi ce nom ────────────────────────────────────────────────────────
 * « Récépissé » est juridiquement neutre : il atteste d'un DÉPÔT, pas d'une
 * inscription. « Attestation » ou « certificat » laisserait croire au candidat
 * qu'il est inscrit — précisément l'erreur que toute la refonte cherche à
 * éviter. Le document porte d'ailleurs la mention encadrée qui le dit.
 *
 * ── Le papier ne ment jamais ───────────────────────────────────────────────
 * Le document ne contient AUCUNE donnée de statut faisant autorité : il porte
 * un QR code et un code court qui pointent vers la base. Un récépissé imprimé
 * le jour du dépôt (0 %) reste exact après la validation à 50 %, après les
 * 100 %, et même après un rejet. Il n'est donc jamais régénéré à chaque
 * changement d'état — c'est le scan qui dit la vérité.
 *
 * ── Choix technique : dompdf, pas Playwright ───────────────────────────────
 * La spec §6.4 suggère Playwright en invoquant « la chaîne déjà en place ».
 * Dans CE dépôt, la chaîne déjà en place est dompdf (Core\Pdf, avec son filet
 * anti-document-blanc). Installer un navigateur headless en production pour un
 * seul document ajouterait un runtime Node, ~300 Mo d'image et un mode de
 * panne supplémentaire. On reste donc sur dompdf, d'où la mise en page en
 * tables : ni flexbox ni grid n'y sont supportés.
 */
final class RecepisseDossierService
{
    /** Correspondances pays → code ISO 3166-1 alpha-3 pour la bande MRZ. */
    private const PAYS_ISO3 = [
        'gabon' => 'GAB', 'gabonaise' => 'GAB', 'gabonais' => 'GAB',
        'cameroun' => 'CMR', 'camerounaise' => 'CMR', 'camerounais' => 'CMR',
        'congo' => 'COG', 'congolaise' => 'COG', 'congolais' => 'COG',
        'republique democratique du congo' => 'COD', 'rdc' => 'COD',
        'cote d ivoire' => 'CIV', 'ivoirienne' => 'CIV', 'ivoirien' => 'CIV',
        'senegal' => 'SEN', 'senegalaise' => 'SEN', 'senegalais' => 'SEN',
        'benin' => 'BEN', 'beninoise' => 'BEN', 'beninois' => 'BEN',
        'togo' => 'TGO', 'togolaise' => 'TGO', 'togolais' => 'TGO',
        'mali' => 'MLI', 'malienne' => 'MLI', 'malien' => 'MLI',
        'guinee' => 'GIN', 'guineenne' => 'GIN', 'guineen' => 'GIN',
        'tchad' => 'TCD', 'tchadienne' => 'TCD', 'tchadien' => 'TCD',
        'niger' => 'NER', 'nigerienne' => 'NER', 'nigerien' => 'NER',
        'burkina faso' => 'BFA', 'burkinabe' => 'BFA',
        'france' => 'FRA', 'francaise' => 'FRA', 'francais' => 'FRA',
        'guinee equatoriale' => 'GNQ', 'centrafrique' => 'CAF',
        'sao tome et principe' => 'STP', 'maroc' => 'MAR', 'marocaine' => 'MAR',
    ];

    /** Longueur d'une ligne de la bande MRZ (les deux sont alignées). */
    private const MRZ_LONGUEUR = 51;

    /**
     * Génère le récépissé, le dépose sur B2 et met à jour la ligne `recepisses`.
     *
     * Idempotente : si le PDF existe déjà pour ce dossier, elle ne refait rien
     * et renvoie la clé existante. C'est ce qui permet à la tâche de rattrapage
     * de repasser sans risque.
     *
     * @return string|null Clé B2 du document, ou null en cas d'échec.
     */
    public static function generer(int $dossierId, bool $forcer = false): ?string
    {
        $donnees = self::rassembler($dossierId);
        if ($donnees === null) {
            return null;
        }

        $db = Database::getInstance()->getConnection();

        // Ligne `recepisses` : numéro réservé atomiquement par le modèle
        // existant, réutilisé tel quel pour ne pas dupliquer la numérotation.
        $st = $db->prepare('SELECT * FROM recepisses WHERE dossier_id = ? LIMIT 1');
        $st->execute([$dossierId]);
        $ligne = $st->fetch(PDO::FETCH_ASSOC);

        if ($ligne !== false && !$forcer && !empty($ligne['cle_pdf'])) {
            return (string)$ligne['cle_pdf'];
        }

        if ($ligne === false) {
            try {
                (new \Models\Orientation\Dossier())->reserveRecepisse($dossierId);
                $st->execute([$dossierId]);
                $ligne = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (\Throwable $e) {
                error_log('[RecepisseDossier] Réservation numéro échouée #' . $dossierId . ' : ' . $e->getMessage());
                $ligne = null;
            }
        }

        $pdf = self::rendrePdf($donnees);
        if ($pdf === null) {
            error_log('[RecepisseDossier] Aucun rendu exploitable #' . $dossierId);

            return null;
        }

        $hash = hash('sha256', $pdf);
        $cle  = sprintf(
            'recepisses/%s/%d/%s.pdf',
            self::segment((string)$donnees['annee_academique']),
            (int)$donnees['dossier']['etablissement_id'],
            self::segment((string)($donnees['dossier']['reference'] ?? ('dossier-' . $dossierId)))
        );

        try {
            (new DocumentStorage())->uploadContent($pdf, $cle, 'application/pdf');
        } catch (\Throwable $e) {
            error_log('[RecepisseDossier] Dépôt B2 échoué (' . $cle . ') : ' . $e->getMessage());

            return null;
        }

        try {
            if ($ligne !== null) {
                $db->prepare(
                    'UPDATE recepisses
                     SET cle_pdf = ?, hash_sha256 = ?, version = version + ?
                     WHERE dossier_id = ?'
                )->execute([$cle, $hash, $forcer ? 1 : 0, $dossierId]);
            } else {
                $db->prepare(
                    'INSERT INTO recepisses (dossier_id, numero_recepisse, cle_pdf, hash_sha256, date_emission)
                     VALUES (?, NULL, ?, ?, NOW())'
                )->execute([$dossierId, $cle, $hash]);
            }
        } catch (\Throwable $e) {
            error_log('[RecepisseDossier] Mise à jour base échouée #' . $dossierId . ' : ' . $e->getMessage());
        }

        Journal::dossier(
            Journal::RECEPISSE_GENERE,
            $donnees['dossier'],
            ['cle' => $cle, 'empreinte' => substr($hash, 0, 12)],
            ['acteur_role' => 'systeme']
        );

        return $cle;
    }

    /**
     * Rend le PDF sans rien enregistrer (prévisualisation, téléchargement
     * direct quand B2 est indisponible).
     */
    public static function rendreDirect(int $dossierId): ?string
    {
        $donnees = self::rassembler($dossierId);

        return $donnees === null ? null : self::rendrePdf($donnees);
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    /**
     * Rassemble tout ce que le gabarit doit afficher. Une seule fonction pour
     * que le gabarit ne fasse aucune requête : il reçoit des chaînes prêtes.
     *
     * @return array<string,mixed>|null
     */
    private static function rassembler(int $dossierId): ?array
    {
        try {
            $db = Database::getInstance()->getConnection();

            $st = $db->prepare(
                'SELECT d.*, e.nom AS etab_nom, e.ville AS etab_ville, e.type AS etab_type,
                        e.logo_cle, e.code_court AS etab_code
                 FROM dossiers_inscription d
                 JOIN etablissements e ON e.id = d.etablissement_id
                 WHERE d.id = ? LIMIT 1'
            );
            $st->execute([$dossierId]);
            $dossier = $st->fetch(PDO::FETCH_ASSOC);

            if ($dossier === false) {
                return null;
            }

            $jeton = JetonDossier::pour($dossierId, $db);

            $st = $db->prepare(
                'SELECT libelle, type_document, date_depot, cle_b2, mime_type
                 FROM documents_orientation
                 WHERE dossier_id = ? AND statut <> \'refuse\'
                 ORDER BY date_depot ASC, id ASC'
            );
            $st->execute([$dossierId]);
            $documents = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[RecepisseDossier] Lecture échouée #' . $dossierId . ' : ' . $e->getMessage());

            return null;
        }

        $depot  = self::dateOuNull((string)($dossier['soumis_le'] ?? $dossier['date_creation'] ?? ''));
        $depot ??= new \DateTimeImmutable();
        $statut = DossierStatut::normaliser((string)$dossier['statut']);

        $nom = self::separerNomPrenoms((string)($dossier['nom_complet'] ?? ''));

        return [
            'dossier'          => $dossier,
            'annee_academique' => (string)($dossier['annee_academique'] ?: AnneeUniversitaire::code()),
            'annee_libelle'    => str_replace('-', ' — ', (string)($dossier['annee_academique'] ?: AnneeUniversitaire::code())),
            'reference'        => (string)($dossier['reference'] ?? ''),
            'jeton'            => $jeton,
            'code_court'       => $jeton !== null ? JetonDossier::formaterCodeCourt($jeton['code_court']) : '',
            'qr'               => $jeton !== null
                ? QrCodeVerification::dataUriPng($jeton['token'], 8, QrCodeVerification::CHEMIN_DOSSIER)
                : '',
            'logo_etab'        => LogoDataUri::pour((string)($dossier['logo_cle'] ?? '')),
            'photo'            => self::photoIdentite($documents),
            'motif'            => MotifSecurite::tuile(),
            'documents'        => self::listerDocuments($documents),
            'nom'              => $nom['nom'],
            'prenoms'          => $nom['prenoms'],
            'naissance'        => self::phraseNaissance($dossier),
            'telephone'        => self::telephone($dossier),
            'email'            => trim((string)($dossier['email'] ?? '')) ?: '—',
            'filiere'          => self::filiere($dossier),
            'bourse'           => SituationBourse::phrase($dossier),
            'statut_libelle'   => mb_strtoupper(DossierStatut::libelle($statut), 'UTF-8'),
            'progression'      => DossierStatut::progression($statut),
            'paliers'          => DossierStatut::paliers($statut),
            'depot'            => $depot,
            'genere_le'        => new \DateTimeImmutable(),
            'mrz'              => self::bandeMrz($dossier, $jeton, $depot, $statut, $nom),
            'url_verification' => self::domaineVerification(),
        ];
    }

    /** @param array<string,mixed> $donnees */
    private static function rendrePdf(array $donnees): ?string
    {
        ob_start();
        // Le gabarit lit $donnees ; aucune variable globale, aucune requête.
        include BASE_PATH . '/app/Views/orientation/_recepisse-dossier-pdf.php';
        $html = (string)ob_get_clean();

        // Empreinte du document imprimée en pied de page. Elle est calculée sur
        // le HTML source : deux rendus du même dossier donnent la même valeur,
        // là où le PDF binaire varie (horodatage interne du moteur).
        $empreinte = substr(hash('sha256', $html), 0, 12);
        $html      = str_replace('{{EMPREINTE}}', $empreinte, $html);

        return Pdf::rendre($html);
    }

    /**
     * Bande de lecture optique, dans l'esprit d'un passeport.
     *
     * Elle ne remplace pas le QR code : elle donne à un agent un moyen de
     * relever la référence et le code court À LA MAIN, en caractères
     * non ambigus, quand ni le scan ni le réseau ne fonctionnent.
     *
     * Ligne 1 — CA<<{PAYS}<{ETAB}<<{NOM}<<{PRÉNOMS}
     * Ligne 2 — {RÉFÉRENCE}<{CODE COURT}<{DATE}<{HEURE}<{STATUT}
     *
     * @param array{nom:string,prenoms:string} $nom
     * @return array{0:string,1:string}
     */
    private static function bandeMrz(
        array $dossier,
        ?array $jeton,
        \DateTimeImmutable $depot,
        string $statut,
        array $nom
    ): array {
        $pays = self::codePays(
            (string)($dossier['nationalite'] ?? ''),
            (string)($dossier['pays_provenance'] ?? '')
        );

        $etab = self::mrzMot((string)($dossier['etab_code'] ?? '')) ?: 'XXX';

        $ligne1 = 'CA<<' . $pays . '<' . $etab . '<<'
            . self::mrzMot($nom['nom']) . '<<' . self::mrzMot($nom['prenoms']);

        $reference = str_replace('-', '', (string)($dossier['reference'] ?? ''));
        $ligne2 = self::mrzMot($reference)
            . '<' . ($jeton !== null ? $jeton['code_court'] : '')
            . '<' . $depot->format('Ymd')
            . '<' . $depot->format('Hi')
            . '<' . self::mrzMot(DossierStatut::libelle($statut));

        return [self::mrzRemplir($ligne1), self::mrzRemplir($ligne2)];
    }

    /**
     * Réduit une chaîne à l'alphabet MRZ : A-Z, 0-9, séparateurs → « < ».
     * Passe par Core\Texte, dont la table de translittération est identique
     * sur tous les systèmes : la libiconv de macOS rendrait « Léa » en
     * « L'ea », donc « ARIANE<L<EA » au lieu de « ARIANE<LEA ».
     */
    private static function mrzMot(string $valeur): string
    {
        return \Core\Texte::mrz($valeur);
    }

    /** Complète (ou tronque) une ligne à la longueur fixe de la bande. */
    private static function mrzRemplir(string $ligne): string
    {
        if (strlen($ligne) > self::MRZ_LONGUEUR) {
            return substr($ligne, 0, self::MRZ_LONGUEUR);
        }

        return str_pad($ligne, self::MRZ_LONGUEUR, '<');
    }

    private static function codePays(string $nationalite, string $pays): string
    {
        foreach ([$nationalite, $pays] as $candidat) {
            $cle = self::normaliserTexte($candidat);
            if ($cle !== '' && isset(self::PAYS_ISO3[$cle])) {
                return self::PAYS_ISO3[$cle];
            }
        }

        // Repli : les trois premières lettres du pays, faute de mieux. Le champ
        // est indicatif, il n'entre dans aucun contrôle.
        $repli = self::mrzMot($pays !== '' ? $pays : $nationalite);

        return $repli === '' ? 'XXX' : str_pad(substr($repli, 0, 3), 3, 'X');
    }

    private static function normaliserTexte(string $valeur): string
    {
        return \Core\Texte::cle($valeur);
    }

    /**
     * Sépare « NZENGUE MBADINGA Ariane Léa » en nom et prénoms.
     *
     * Convention locale : le patronyme s'écrit en capitales, les prénoms en
     * casse normale. On prend donc les mots capitalisés de tête comme nom. Si
     * le candidat a tout saisi dans la même casse, on retombe sur « premier mot
     * = nom » — imparfait, mais jamais faux au point d'empêcher la lecture.
     *
     * @return array{nom:string,prenoms:string}
     */
    private static function separerNomPrenoms(string $nomComplet): array
    {
        $mots = preg_split('/\s+/', trim($nomComplet)) ?: [];
        $mots = array_values(array_filter($mots, static fn(string $m): bool => $m !== ''));

        if ($mots === []) {
            return ['nom' => '—', 'prenoms' => '—'];
        }
        if (count($mots) === 1) {
            return ['nom' => $mots[0], 'prenoms' => '—'];
        }

        $nom = [];
        foreach ($mots as $mot) {
            $sansAccent = \Core\Texte::sansAccents($mot);
            $estMajuscule = preg_match('/[a-z]/', $sansAccent) !== 1
                && preg_match('/[A-Z]/', $sansAccent) === 1;

            if (!$estMajuscule) {
                break;
            }
            $nom[] = $mot;
        }

        // Tout en majuscules, ou rien en majuscules : premier mot = patronyme.
        if ($nom === [] || count($nom) === count($mots)) {
            $nom = [array_shift($mots)];

            return ['nom' => $nom[0], 'prenoms' => implode(' ', $mots) ?: '—'];
        }

        $prenoms = array_slice($mots, count($nom));

        return [
            'nom'     => implode(' ', $nom),
            'prenoms' => implode(' ', $prenoms) ?: '—',
        ];
    }

    /**
     * Photo d'identité en data URI, si le candidat en a déposé une.
     * Une photo illisible ou trop lourde est ignorée : le gabarit affiche alors
     * l'emplacement vide, comme sur la maquette.
     */
    private static function photoIdentite(array $documents): string
    {
        foreach ($documents as $document) {
            if (($document['type_document'] ?? null) !== 'photo_identite') {
                continue;
            }

            $mime = (string)($document['mime_type'] ?? '');
            if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                return '';
            }

            // Mise en cache comme le logo et le cachet : la photo ne change
            // jamais une fois déposée, et la tâche de rattrapage regénère les
            // récépissés en lot — sans cache, chaque passage la retéléchargeait
            // depuis Backblaze, une transaction par document.
            $cle = (string)$document['cle_b2'];

            $clefCache = 'orientation:photo_datauri:' . md5($cle);
            $absent    = new \stdClass();
            $connu     = \Core\Cache::get($clefCache, $absent);

            if ($connu !== $absent) {
                return (string)$connu;
            }

            try {
                $contenu = (new DocumentStorage())->getContentClair($cle);
            } catch (\Throwable $e) {
                error_log('[RecepisseDossier] Photo illisible : ' . $e->getMessage());
                $contenu = '';
            }

            $donnees = ($contenu === '' || strlen($contenu) > 3 * 1024 * 1024)
                ? ''
                : 'data:' . $mime . ';base64,' . base64_encode($contenu);

            // Succès : six heures. Échec : une minute, pour que le rétablissement
            // du stockage se voie tout de suite et non six heures plus tard.
            \Core\Cache::set($clefCache, $donnees, $donnees === '' ? 60 : 21600);

            return $donnees;
        }

        return '';
    }

    /**
     * Liste « pièces reçues » avec leur date de réception (spec §6.3).
     *
     * @return list<array{libelle:string,date:string,bourse:bool}>
     */
    private static function listerDocuments(array $documents): array
    {
        $liste = [];

        foreach ($documents as $document) {
            $type = (string)($document['type_document'] ?? '');

            $liste[] = [
                'libelle' => (string)($document['libelle'] ?? 'Document'),
                'date'    => self::formaterDate((string)($document['date_depot'] ?? '')),
                'bourse'  => in_array($type, ['attestation_bourse', 'preuve_depot_anbg'], true),
            ];
        }

        return $liste;
    }

    private static function phraseNaissance(array $dossier): string
    {
        $date = self::formaterDate((string)($dossier['date_naissance'] ?? ''));
        $lieu = trim((string)($dossier['lieu_naissance'] ?? ''));
        $pays = trim((string)($dossier['pays_provenance'] ?? ''));

        $ou = trim($lieu . ($lieu !== '' && $pays !== '' ? ', ' : '') . $pays);

        if ($date === '—' && $ou === '') {
            return '—';
        }

        return trim($date . ($ou !== '' ? ' · ' . $ou : ''));
    }

    private static function telephone(array $dossier): string
    {
        $numero = trim((string)($dossier['telephone'] ?? ''));
        if ($numero === '') {
            return '—';
        }

        return str_starts_with($numero, '+')
            ? $numero
            : trim((string)($dossier['indicatif_tel'] ?? '+241')) . ' ' . $numero;
    }

    private static function filiere(array $dossier): string
    {
        $filiere    = trim((string)($dossier['filiere_visee'] ?? ''));
        $specialite = trim((string)($dossier['specialite_visee'] ?? ''));
        $niveau     = trim((string)($dossier['niveau_entree'] ?? ''));

        $parties = array_filter([$niveau ?: $filiere, $specialite]);

        return $parties === [] ? '—' : implode(' — ', $parties);
    }

    private static function formaterDate(string $valeur): string
    {
        if (trim($valeur) === '') {
            return '—';
        }

        try {
            return (new \DateTimeImmutable($valeur))->format('d/m/Y');
        } catch (\Throwable) {
            return '—';
        }
    }

    private static function dateOuNull(string $valeur): ?\DateTimeImmutable
    {
        if (trim($valeur) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($valeur);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Adresse affichée en pied de page, sans schéma ni token : « site.io/v ». */
    private static function domaineVerification(): string
    {
        $url = QrCodeVerification::urlDossier('');

        return rtrim(preg_replace('#^https?://#', '', $url) ?? '', '/');
    }

    private static function segment(string $valeur): string
    {
        $propre = preg_replace('/[^A-Za-z0-9_-]/', '-', $valeur) ?? '';

        return trim(preg_replace('/-+/', '-', $propre) ?? '', '-') ?: 'x';
    }
}
