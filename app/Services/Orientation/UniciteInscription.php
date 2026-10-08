<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\AnneeUniversitaire;
use Core\Database;
use Models\Orientation\Dossier;
use Models\Orientation\DossierStatut;
use PDO;

/**
 * « Une seule inscription par candidat, par établissement et par année. »
 *
 * Un même candidat ouvrait autant de dossiers d'inscription qu'il le voulait
 * dans le même établissement pour la même année académique : un retour arrière
 * dans le navigateur, un second onglet, une hésitation sur la filière, et le
 * guichet recevait trois dossiers pour une seule personne. Chacun produisait sa
 * référence, sa proforma, ses pièces à instruire — et, à la validation, sa
 * commission. L'établissement les traitait tous les trois avant de découvrir
 * qu'il s'agissait du même étudiant.
 *
 * UNE SEULE DÉFINITION, DEUX PORTES D'ENTRÉE
 * ------------------------------------------
 * La règle est écrite ici, une fois, et appliquée aux deux seuls endroits qui
 * créent une inscription : le parcours public (CandidatureController, étape 1)
 * et le guichet (CandidatureGestionnaireController::store). La réécrire dans
 * chaque contrôleur aurait garanti qu'elle diverge à la première évolution.
 *
 * CE QUI COMPTE COMME « LA MÊME PERSONNE »
 * ----------------------------------------
 * Le compte (`eleve_id`) OU l'adresse e-mail du dossier. Les deux, parce que
 * les deux existent réellement : un dossier ouvert au guichet n'a pas toujours
 * de compte rattaché, et un candidat peut créer son espace après coup — c'est
 * l'e-mail qui rattache alors ses dossiers (Dossier::claimByEmail). Ne tester
 * que `eleve_id` laissait passer le doublon le plus fréquent : le dossier
 * guichet suivi d'une inscription en ligne.
 *
 * CE QUI BLOQUE, ET CE QUI NE BLOQUE PAS
 * --------------------------------------
 * Seul un dossier VIVANT occupe la place (self::STATUTS_BLOQUANTS). Un dossier
 * rejeté, expiré ou annulé n'en bloque aucun : le candidat doit pouvoir
 * recommencer après un refus, sinon un rejet — parfois dû à une simple pièce
 * illisible — l'enfermerait pour l'année entière sans recours en ligne.
 *
 * PRÉINSCRIPTION ≠ INSCRIPTION
 * ----------------------------
 * La règle ne porte QUE sur les inscriptions. Le critère est celui du modèle
 * (Dossier::SQL_EST_INSCRIPTION), jamais une règle réécrite ici. Une
 * préinscription reste libre : c'est une manifestation d'intérêt, pas un
 * engagement, et un candidat a le droit d'en déposer pour plusieurs filières
 * du même établissement.
 *
 * CE N'EST PAS UN VERROU DE BASE DE DONNÉES
 * -----------------------------------------
 * MySQL ne sait pas exprimer un index unique PARTIEL — « unique seulement pour
 * ces statuts, et seulement pour les inscriptions ». La condition dépend d'une
 * sous-requête sur `inscriptions`, qu'une colonne générée ne peut pas contenir.
 * Le contrôle est donc applicatif, posé sur les deux seuls chemins d'écriture,
 * et doublé côté écran pour que le candidat ne remplisse pas un formulaire
 * qu'on lui refusera à la fin.
 */
final class UniciteInscription
{
    /**
     * Statuts pour lesquels une inscription « occupe la place ».
     *
     * Les quatre derniers sont des écritures héritées : plus aucune transition
     * ne les produit (Dossier::transition n'émet que les états de la spec
     * §4.1), mais des dossiers de production les portent encore et ils
     * décrivent bien une inscription vivante — voire aboutie.
     */
    public const STATUTS_BLOQUANTS = [
        'brouillon',
        'soumis',
        'piece_manquante',
        'valide_50',
        'valide_100',
        'paye',
        'accepte',
        'inscrit',
    ];

    /**
     * Le dossier d'inscription qui interdit d'en ouvrir un nouveau, ou null.
     *
     * @param int         $etablissementId Établissement visé.
     * @param int|null    $eleveId         Compte du candidat, s'il en a un.
     * @param string|null $email           Adresse portée par le dossier.
     * @param string|null $annee           Année académique (défaut : la courante).
     * @param int|null    $excludeId       Dossier à ignorer (modification).
     *
     * @return array<string,mixed>|null Ligne du dossier bloquant, enrichie du
     *         slug et du nom de l'établissement.
     */
    public static function existante(
        int $etablissementId,
        ?int $eleveId,
        ?string $email,
        ?string $annee = null,
        ?int $excludeId = null,
        ?PDO $db = null
    ): ?array {
        if ($etablissementId <= 0) {
            return null;
        }

        $email = $email !== null ? strtolower(trim($email)) : '';
        $eleveId = $eleveId !== null && $eleveId > 0 ? $eleveId : null;

        // Aucune identité exploitable : rien à comparer. On ne bloque pas —
        // un contrôle qui ne sait pas qui il regarde ne doit rien refuser.
        if ($eleveId === null && $email === '') {
            return null;
        }

        $annee = $annee !== null && trim($annee) !== ''
            ? trim($annee)
            : AnneeUniversitaire::code();

        // Identité : compte OU e-mail. Les placeholders sont tous distincts —
        // la connexion tourne en EMULATE_PREPARES=false, qui interdit de
        // réutiliser un même nom dans une requête.
        $identite = [];
        $params   = [
            ':etab'  => $etablissementId,
            ':annee' => $annee,
        ];

        if ($eleveId !== null) {
            $identite[]        = 'd.eleve_id = :eleve';
            $params[':eleve']  = $eleveId;
        }
        if ($email !== '') {
            $identite[]        = 'LOWER(TRIM(d.email)) = :email';
            $params[':email']  = $email;
        }

        $statuts = [];
        foreach (array_values(self::STATUTS_BLOQUANTS) as $i => $statut) {
            $marque            = ':st' . $i;
            $statuts[]         = $marque;
            $params[$marque]   = $statut;
        }

        $sql = 'SELECT d.id, d.reference, d.statut, d.annee_academique, d.nom_complet,
                       d.email, d.eleve_id, d.filiere_visee, d.niveau_entree,
                       d.date_creation, d.soumis_le,
                       e.slug AS etab_slug, e.nom AS etab_nom
                FROM dossiers_inscription d
                JOIN etablissements e ON e.id = d.etablissement_id
                WHERE d.etablissement_id = :etab
                  AND d.annee_academique = :annee
                  AND d.statut IN (' . implode(', ', $statuts) . ')
                  AND ' . Dossier::SQL_EST_INSCRIPTION . '
                  AND (' . implode(' OR ', $identite) . ')';

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND d.id <> :exclude';
            $params[':exclude'] = $excludeId;
        }

        // Le plus récent : c'est celui que le candidat vient de quitter, donc
        // celui vers lequel il faut le renvoyer.
        $sql .= ' ORDER BY d.id DESC LIMIT 1';

        try {
            $db ??= Database::getInstance()->getConnection();
            $st = $db->prepare($sql);
            $st->execute($params);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[UniciteInscription] Lecture échouée etab #' . $etablissementId . ' : ' . $e->getMessage());

            // Un contrôle d'unicité qui tombe ne doit pas fermer le guichet :
            // on laisse passer plutôt que de refuser tout le monde sur une
            // erreur de lecture. Le doublon éventuel est rattrapable, une
            // plateforme bloquée ne l'est pas.
            return null;
        }

        return $ligne !== false ? $ligne : null;
    }

    /**
     * Statuts qui décrivent une inscription MENÉE À TERME — « inscrit à 100 % ».
     *
     * `valide_100` est l'état de la spec §4.1 ; `inscrit` est son ancêtre, resté
     * dans l'ENUM MySQL pour les lignes non migrées (cf. DossierStatut::HERITES,
     * qui normalise `inscrit` vers `valide_100` à la lecture). Les deux doivent
     * figurer ici : un dossier de production encore marqué `inscrit` décrit un
     * étudiant bel et bien inscrit, et l'oublier rouvrirait la porte à
     * exactement ce que cette règle interdit.
     *
     * `valide_50` n'y est PAS : à 50 %, l'inscription est engagée mais pas
     * aboutie — elle peut encore expirer faute de présentation au guichet. La
     * bloquer ailleurs enfermerait le candidat pour l'année sur un dossier qui
     * va tomber tout seul.
     */
    public const STATUTS_INSCRIT_100 = ['valide_100', 'inscrit'];

    /**
     * L'inscription à 100 % qui interdit d'en ouvrir une AILLEURS cette année.
     *
     * ── La règle ────────────────────────────────────────────────────────────
     *
     * Un candidat inscrit à 100 % dans un établissement pour une année
     * académique ne peut pas ouvrir d'inscription dans un AUTRE établissement
     * pour la même année. Une année d'études se fait dans un seul établissement :
     * les inscriptions parallèles produisent des places réservées jamais
     * occupées, des commissions dues sur des dossiers fantômes, et deux écoles
     * qui instruisent le même étudiant sans le savoir.
     *
     * ── Ce que cette méthode ne fait pas ────────────────────────────────────
     *
     * Elle ne regarde JAMAIS l'établissement passé en paramètre : il est au
     * contraire EXCLU de la recherche. Le doublon interne au même établissement
     * relève de self::existante(), qui a ses propres règles — dont la reprise
     * du brouillon en cours, qu'il ne faut surtout pas confondre avec un refus.
     * Les deux contrôles sont complémentaires et s'appliquent l'un après l'autre.
     *
     * Elle ne bloque pas non plus les PRÉINSCRIPTIONS : le critère
     * `Dossier::SQL_EST_INSCRIPTION` s'applique ici comme ailleurs. Se
     * préinscrire dans plusieurs écoles est le fonctionnement normal de
     * l'orientation ; c'est l'inscription ferme qui est exclusive.
     *
     * @param int         $etablissementVise Établissement où le candidat veut
     *                                       s'inscrire — exclu de la recherche.
     * @param int|null    $eleveId           Compte du candidat, s'il en a un.
     * @param string|null $email             Adresse portée par le dossier.
     * @param string|null $annee             Année académique (défaut : la courante).
     *
     * @return array<string,mixed>|null Ligne du dossier bloquant, enrichie du
     *         slug et du nom de son établissement.
     */
    public static function inscriptionFinaliseeAilleurs(
        int $etablissementVise,
        ?int $eleveId,
        ?string $email,
        ?string $annee = null,
        ?PDO $db = null
    ): ?array {
        $email   = $email !== null ? strtolower(trim($email)) : '';
        $eleveId = $eleveId !== null && $eleveId > 0 ? $eleveId : null;

        // Même prudence que self::existante() : un contrôle qui ne sait pas qui
        // il regarde ne refuse rien.
        if ($eleveId === null && $email === '') {
            return null;
        }

        $annee = $annee !== null && trim($annee) !== ''
            ? trim($annee)
            : AnneeUniversitaire::code();

        $identite = [];
        $params   = [':annee' => $annee];

        if ($eleveId !== null) {
            $identite[]       = 'd.eleve_id = :eleve';
            $params[':eleve'] = $eleveId;
        }
        if ($email !== '') {
            $identite[]       = 'LOWER(TRIM(d.email)) = :email';
            $params[':email'] = $email;
        }

        $statuts = [];
        foreach (array_values(self::STATUTS_INSCRIT_100) as $i => $statut) {
            $marque          = ':fin' . $i;
            $statuts[]       = $marque;
            $params[$marque] = $statut;
        }

        $sql = 'SELECT d.id, d.reference, d.statut, d.annee_academique, d.nom_complet,
                       d.email, d.eleve_id, d.etablissement_id, d.filiere_visee,
                       d.niveau_entree, d.date_creation, d.soumis_le,
                       e.slug AS etab_slug, e.nom AS etab_nom
                FROM dossiers_inscription d
                JOIN etablissements e ON e.id = d.etablissement_id
                WHERE d.annee_academique = :annee
                  AND d.statut IN (' . implode(', ', $statuts) . ')
                  AND ' . Dossier::SQL_EST_INSCRIPTION . '
                  AND (' . implode(' OR ', $identite) . ')';

        // L'établissement visé est exclu : s'y réinscrire relève de
        // self::existante(), avec son propre message.
        if ($etablissementVise > 0) {
            $sql .= ' AND d.etablissement_id <> :etab_vise';
            $params[':etab_vise'] = $etablissementVise;
        }

        $sql .= ' ORDER BY d.id DESC LIMIT 1';

        try {
            $db ??= Database::getInstance()->getConnection();
            $st = $db->prepare($sql);
            $st->execute($params);
            $ligne = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[UniciteInscription] Lecture inscription finalisée échouée : ' . $e->getMessage());

            // Même parti pris que self::existante() : un contrôle en panne ne
            // ferme pas la plateforme. Le doublon est rattrapable, pas le blocage.
            return null;
        }

        return $ligne !== false ? $ligne : null;
    }

    /**
     * Message adressé AU CANDIDAT quand il est déjà inscrit ailleurs.
     *
     * Il nomme l'établissement et l'année — pas la référence du dossier, qui
     * appartient à l'autre établissement et n'aide en rien le candidat ici :
     * il doit comprendre d'où vient le refus sans écrire au support, et savoir
     * quoi faire — une inscription abandonnée s'annule auprès de
     * l'établissement concerné.
     *
     * @param array<string,mixed> $dossier Retour de self::inscriptionFinaliseeAilleurs().
     */
    public static function messageCandidatAutreEtablissement(array $dossier): string
    {
        return 'Vous êtes déjà inscrit à '
            . (string)($dossier['etab_nom'] ?? 'un autre établissement')
            . ' pour l’année ' . self::anneeLisible($dossier)
            . '. Une inscription menée à son terme vaut pour toute l’année académique : '
            . 'il n’est pas possible de s’inscrire dans un second établissement sur la même année. '
            . 'Si vous avez renoncé à cette inscription, demandez son annulation à l’établissement '
            . 'concerné avant d’en ouvrir une nouvelle.';
    }

    /**
     * Même information, adressée à l'AGENT du guichet.
     *
     * Elle nomme l'établissement d'accueil : sans cela, l'agent ne peut ni
     * vérifier l'information ni orienter le candidat, et il conclurait à un bug.
     *
     * @param array<string,mixed> $dossier Retour de self::inscriptionFinaliseeAilleurs().
     */
    public static function messageGuichetAutreEtablissement(array $dossier): string
    {
        $reference = trim((string)($dossier['reference'] ?? ''));

        return 'Ce candidat est déjà inscrit à '
            . (string)($dossier['etab_nom'] ?? 'un autre établissement')
            . ' pour l’année ' . self::anneeLisible($dossier)
            . ($reference !== '' ? ' (dossier ' . $reference . ')' : '')
            . '. Une seule inscription aboutie par année académique, tous établissements confondus : '
            . 'l’inscription précédente doit être annulée par l’établissement d’accueil avant d’en ouvrir une ici.';
    }

    /** Même question, réponse booléenne. */
    public static function dejaInscrit(
        int $etablissementId,
        ?int $eleveId,
        ?string $email,
        ?string $annee = null,
        ?int $excludeId = null,
        ?PDO $db = null
    ): bool {
        return self::existante($etablissementId, $eleveId, $email, $annee, $excludeId, $db) !== null;
    }

    /**
     * Message adressé AU CANDIDAT, sur le parcours public.
     *
     * Il nomme l'établissement, l'année et — quand elle existe — la référence
     * du dossier : le candidat doit pouvoir vérifier lui-même de quel dossier
     * on parle, sans écrire au support.
     *
     * @param array<string,mixed> $dossier Retour de self::existante().
     */
    public static function messageCandidat(array $dossier): string
    {
        $reference = trim((string)($dossier['reference'] ?? ''));

        return 'Vous avez déjà une inscription en cours à '
            . (string)($dossier['etab_nom'] ?? 'cet établissement')
            . ' pour l’année ' . self::anneeLisible($dossier)
            . ($reference !== '' ? ' (dossier ' . $reference . ')' : '')
            . '. Une seule inscription par établissement et par année académique est possible : '
            . 'retrouvez et complétez votre dossier depuis votre espace candidat. '
            . 'Si vous souhaitez le remplacer, demandez son annulation à l’établissement.';
    }

    /**
     * Même information, adressée à l'AGENT du guichet : elle nomme le dossier
     * par son numéro pour qu'il puisse l'ouvrir immédiatement.
     *
     * @param array<string,mixed> $dossier Retour de self::existante().
     */
    public static function messageGuichet(array $dossier): string
    {
        $reference = trim((string)($dossier['reference'] ?? ''));

        return 'Ce candidat a déjà une inscription pour l’année ' . self::anneeLisible($dossier)
            . ' — dossier #' . (int)($dossier['id'] ?? 0)
            . ($reference !== '' ? ' (' . $reference . ')' : '')
            . ', ' . mb_strtolower(DossierStatut::libelle($dossier['statut'] ?? null, true))
            . '. Une seule inscription par candidat et par année est possible : '
            . 'complétez le dossier existant, ou annulez-le avant d’en ouvrir un autre.';
    }

    /** « 2026-2027 » → « 2026 — 2027 ». */
    private static function anneeLisible(array $dossier): string
    {
        $annee = trim((string)($dossier['annee_academique'] ?? ''));

        if ($annee === '') {
            return AnneeUniversitaire::courante();
        }

        return str_replace('-', ' — ', $annee);
    }
}
