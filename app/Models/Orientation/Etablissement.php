<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use Core\TarifFormationSchema;
use PDO;
use Services\Orientation\TarifCycle;

class Etablissement
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Colonnes des frais d'inscription par cycle et situation de bourse.
     *
     * Écrites SEULEMENT si `migration_tarifs_formation.sql` a été rejouée. Le
     * code est déployé par rsync et la base migrée à la main : les deux ne sont
     * jamais synchrones. Nommer une colonne absente dans l'INSERT ferait
     * échouer la création d'établissement en entier, et le super administrateur
     * n'aurait aucun moyen de comprendre pourquoi.
     */
    private const COLONNES_FRAIS_INSCRIPTION = [
        'frais_inscription_licence',
        'frais_inscription_licence_boursier',
        'frais_inscription_master',
        'frais_inscription_master_boursier',
    ];

    /**
     * Fragments SQL et paramètres de ces colonnes, ou vides si indisponibles.
     *
     * @param array<string,mixed> $d
     * @return array{cols:string, vals:string, set:string, params:array<string,int>}
     */
    private function fragmentsFraisInscription(array $d): array
    {
        if (!TarifFormationSchema::fraisInscriptionDisponible($this->db)) {
            return ['cols' => '', 'vals' => '', 'set' => '', 'params' => []];
        }

        $cols = $vals = $set = [];
        $params = [];

        foreach (self::COLONNES_FRAIS_INSCRIPTION as $col) {
            $cols[]           = $col;
            $vals[]           = ':' . $col;
            $set[]            = $col . ' = :' . $col;
            $params[':' . $col] = max(0, (int)($d[$col] ?? 0));
        }

        return [
            'cols'   => ', ' . implode(', ', $cols),
            'vals'   => ', ' . implode(', ', $vals),
            'set'    => implode(', ', $set) . ',',
            'params' => $params,
        ];
    }

    /**
     * Assiette de la commission d'un BOURSIER confirmé, négociée par accord.
     *
     * Même précaution que ci-dessus : la colonne n'existe que si
     * `migration_commission_boursier_tranches.sql` a été rejouée, et le code
     * est déployé avant la base. Absente, le réglage n'est simplement pas
     * écrit — la résolution retombe sur le défaut (frais d'inscription), qui
     * est le comportement d'avant la migration.
     *
     * La valeur est normalisée par TarifCycle : le formulaire est un `select`,
     * mais un POST forgé ne doit pas pouvoir écrire n'importe quoi dans une
     * colonne qui décide d'une assiette de facturation.
     *
     * @param array<string,mixed> $d
     * @return array{cols:string, vals:string, set:string, params:array<string,string>}
     */
    private function fragmentBaseBoursier(array $d): array
    {
        if (!TarifFormationSchema::baseBoursierDisponible($this->db)) {
            return ['cols' => '', 'vals' => '', 'set' => '', 'params' => []];
        }

        $valeur = TarifCycle::normaliserBaseBoursier(
            isset($d['base_commission_boursier']) ? (string)$d['base_commission_boursier'] : null
        );

        return [
            'cols'   => ', base_commission_boursier',
            'vals'   => ', :base_commission_boursier',
            'set'    => 'base_commission_boursier = :base_commission_boursier,',
            'params' => [':base_commission_boursier' => $valeur],
        ];
    }

    /**
     * « Cet établissement accepte-t-il les candidatures de boursiers ? »
     *
     * Même précaution que les deux fragments ci-dessus : la colonne n'existe
     * que si `migration_accepte_boursiers.sql` a été rejouée. Absente, le
     * réglage n'est pas écrit et la lecture retombe sur le défaut « accepte »
     * (Services\Orientation\PolitiqueBoursiers), qui est le comportement
     * d'avant la migration.
     *
     * @param array<string,mixed> $d
     * @return array{cols:string, vals:string, set:string, params:array<string,int>}
     */
    private function fragmentAccepteBoursiers(array $d): array
    {
        if (!\Core\PolitiqueBoursiersSchema::disponible($this->db)) {
            return ['cols' => '', 'vals' => '', 'set' => '', 'params' => []];
        }

        return [
            'cols'   => ', accepte_boursiers',
            'vals'   => ', :accepte_boursiers',
            'set'    => 'accepte_boursiers = :accepte_boursiers,',
            // Défaut 1 : une clé absente du tableau ne doit jamais fermer la
            // porte aux boursiers par accident.
            'params' => [':accepte_boursiers' => (int)($d['accepte_boursiers'] ?? 1) === 1 ? 1 : 0],
        ];
    }

    /**
     * Composition du dossier d'inscription (JSON), saisie par le super
     * administrateur et imprimée sur l'attestation, la proforma et le gabarit
     * public.
     *
     * Même précaution que les fragments ci-dessus : la colonne n'existe que si
     * `migration_composition_dossier.sql` a été rejouée, et le code est déployé
     * avant la base. Absente, la composition n'est pas écrite — les documents
     * gardent leur formulation générique, exactement comme avant la migration.
     * Faire échouer la création d'un établissement entier pour une information
     * éditoriale serait hors de proportion.
     *
     * @param array<string,mixed> $d
     * @return array{cols:string, vals:string, set:string, params:array<string,?string>}
     */
    private function fragmentCompositionDossier(array $d): array
    {
        if (!\Core\CompositionDossierSchema::disponible($this->db)) {
            return ['cols' => '', 'vals' => '', 'set' => '', 'params' => []];
        }

        $valeur = $d['composition_dossier'] ?? null;

        return [
            'cols'   => ', composition_dossier',
            'vals'   => ', :composition_dossier',
            'set'    => 'composition_dossier = :composition_dossier,',
            'params' => [':composition_dossier' => is_string($valeur) && $valeur !== '' ? $valeur : null],
        ];
    }

    public function create(array $d): int
    {
        $frais    = $this->fragmentsFraisInscription($d);
        $boursier = $this->fragmentBaseBoursier($d);
        $accepteB = $this->fragmentAccepteBoursiers($d);
        $compo    = $this->fragmentCompositionDossier($d);

        $st = $this->db->prepare('
            INSERT INTO etablissements
              (slug, nom, type, ville, logo_cle, cachet_cle, couleur_principale, statut,
               label_campagne, titre_accroche, sous_titre, date_limite_dossier,
               description, video_url, video_cle, citation_texte, citation_auteur, citation_fonction,
               adresse, latitude, longitude, map_embed_url, email_contact, telephone, whatsapp,
               prix_preinscription, prix_inscription, frais_dossier' . $frais['cols'] . ',
               frais_scolarite, frais_scolarite_boursier,
               frais_scolarite_master, frais_scolarite_master_boursier, frais_uniforme,
               taux_commission_scolarite' . $boursier['cols'] . ', infos_paiement,
               preinscription_active, genere_recepisse, genere_attestation' . $accepteB['cols'] . ', inscription_active,
               badges_confiance, atouts, statistiques' . $compo['cols'] . ', images_cles, date_creation)
            VALUES
              (:slug, :nom, :type, :ville, :logo_cle, :cachet_cle, :couleur, :statut,
               :label_campagne, :titre_accroche, :sous_titre, :date_limite,
               :description, :video_url, :video_cle, :citation_texte, :citation_auteur, :citation_fonction,
               :adresse, :latitude, :longitude, :map_embed_url, :email_contact, :telephone, :whatsapp,
               :prix_preinscription, :prix_inscription, :frais_dossier' . $frais['vals'] . ',
               :frais_scolarite, :frais_scolarite_boursier,
               :frais_scolarite_master, :frais_scolarite_master_boursier, :frais_uniforme,
               :taux_commission_scolarite' . $boursier['vals'] . ', :infos_paiement,
               :preinscription_active, :genere_recepisse, :genere_attestation' . $accepteB['vals'] . ', :inscription_active,
               :badges_confiance, :atouts, :statistiques' . $compo['vals'] . ', :images_cles, NOW())
        ');

        $st->execute([
            ':slug'                 => $d['slug'],
            ':nom'                  => $d['nom'],
            ':type'                 => $d['type'],
            ':ville'                => $d['ville'],
            ':logo_cle'             => $d['logo_cle'] ?? '',
            ':cachet_cle'           => $d['cachet_cle'] ?? null,
            ':couleur'              => $d['couleur_principale'] ?? '#1F4E79',
            ':statut'               => $d['statut'] ?? 'brouillon',
            ':label_campagne'       => $d['label_campagne'] ?? null,
            ':titre_accroche'       => $d['titre_accroche'] ?? null,
            ':sous_titre'           => $d['sous_titre'] ?? null,
            ':date_limite'          => $d['date_limite_dossier'] ?: null,
            ':description'          => $d['description'] ?? null,
            ':video_url'            => $d['video_url'] ?? null,
            ':video_cle'            => $d['video_cle'] ?? null,
            ':citation_texte'       => $d['citation_texte'] ?? null,
            ':citation_auteur'      => $d['citation_auteur'] ?? null,
            ':citation_fonction'    => $d['citation_fonction'] ?? null,
            ':adresse'              => $d['adresse'] ?? null,
            ':latitude'             => $d['latitude'] !== '' ? $d['latitude'] : null,
            ':longitude'            => $d['longitude'] !== '' ? $d['longitude'] : null,
            ':map_embed_url'        => $d['map_embed_url'] ?? null,
            ':email_contact'        => $d['email_contact'] ?? null,
            ':telephone'            => $d['telephone'] ?? null,
            ':whatsapp'             => $d['whatsapp'] ?? null,
            ':prix_preinscription'  => (int)($d['prix_preinscription'] ?? 0),
            ':prix_inscription'     => (int)($d['prix_inscription'] ?? 0),
            ':frais_dossier'        => (int)($d['frais_dossier'] ?? 0),
            ':frais_scolarite'      => (int)($d['frais_scolarite'] ?? 0),
            ':frais_scolarite_boursier' => (int)($d['frais_scolarite_boursier'] ?? 0),
            ':frais_scolarite_master' => (int)($d['frais_scolarite_master'] ?? 0),
            ':frais_scolarite_master_boursier' => (int)($d['frais_scolarite_master_boursier'] ?? 0),
            ':frais_uniforme'       => (int)($d['frais_uniforme'] ?? 0),
            ':taux_commission_scolarite' => (float)($d['taux_commission_scolarite'] ?? 10.0),
            ':infos_paiement'       => $d['infos_paiement'] ?? null,
            ':preinscription_active'=> (int)($d['preinscription_active'] ?? 1),
            ':genere_recepisse'     => (int)($d['genere_recepisse'] ?? 1),
            ':genere_attestation'   => (int)($d['genere_attestation'] ?? 1),
            // Les inscriptions restent fermées par défaut — un établissement
            // tout juste créé n'a ni formations ni pièces requises — mais le
            // super administrateur peut les ouvrir dès la création.
            ':inscription_active'   => (int)($d['inscription_active'] ?? 0),
            ':badges_confiance'     => $d['badges_confiance'] ?? null,
            ':atouts'               => $d['atouts'] ?? null,
            ':statistiques'         => $d['statistiques'] ?? null,
            ':images_cles'          => $d['images_cles'] ?? null,
        ] + $frais['params'] + $boursier['params'] + $accepteB['params'] + $compo['params']);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $frais    = $this->fragmentsFraisInscription($d);
        $boursier = $this->fragmentBaseBoursier($d);
        $accepteB = $this->fragmentAccepteBoursiers($d);
        $compo    = $this->fragmentCompositionDossier($d);

        $st = $this->db->prepare('
            UPDATE etablissements SET
              nom = :nom, type = :type, ville = :ville,
              logo_cle = :logo_cle, cachet_cle = :cachet_cle, couleur_principale = :couleur, statut = :statut,
              label_campagne = :label_campagne, titre_accroche = :titre_accroche,
              sous_titre = :sous_titre, date_limite_dossier = :date_limite,
              description = :description, video_url = :video_url, video_cle = :video_cle,
              citation_texte = :citation_texte, citation_auteur = :citation_auteur,
              citation_fonction = :citation_fonction,
              adresse = :adresse, latitude = :latitude, longitude = :longitude,
              map_embed_url = :map_embed_url,
              email_contact = :email_contact, telephone = :telephone, whatsapp = :whatsapp,
              prix_preinscription = :prix_preinscription, prix_inscription = :prix_inscription,
              frais_dossier = :frais_dossier,
              ' . $frais['set'] . '
              frais_scolarite = :frais_scolarite,
              frais_scolarite_boursier = :frais_scolarite_boursier,
              frais_scolarite_master = :frais_scolarite_master,
              frais_scolarite_master_boursier = :frais_scolarite_master_boursier,
              frais_uniforme = :frais_uniforme,
              taux_commission_scolarite = :taux_commission_scolarite,
              ' . $boursier['set'] . '
              infos_paiement = :infos_paiement,
              preinscription_active = :preinscription_active, genere_recepisse = :genere_recepisse,
              inscription_active = :inscription_active,
              genere_attestation = :genere_attestation,
              ' . $accepteB['set'] . '
              badges_confiance = :badges_confiance, atouts = :atouts,
              statistiques = :statistiques,
              ' . $compo['set'] . '
              images_cles = :images_cles
            WHERE id = :id
        ');

        $st->execute([
            ':id'                   => $id,
            ':nom'                  => $d['nom'],
            ':type'                 => $d['type'],
            ':ville'                => $d['ville'],
            ':logo_cle'             => $d['logo_cle'] ?? '',
            ':cachet_cle'           => $d['cachet_cle'] ?? null,
            ':couleur'              => $d['couleur_principale'] ?? '#1F4E79',
            ':statut'               => $d['statut'] ?? 'brouillon',
            ':label_campagne'       => $d['label_campagne'] ?? null,
            ':titre_accroche'       => $d['titre_accroche'] ?? null,
            ':sous_titre'           => $d['sous_titre'] ?? null,
            ':date_limite'          => $d['date_limite_dossier'] ?: null,
            ':description'          => $d['description'] ?? null,
            ':video_url'            => $d['video_url'] ?? null,
            ':video_cle'            => $d['video_cle'] ?? null,
            ':citation_texte'       => $d['citation_texte'] ?? null,
            ':citation_auteur'      => $d['citation_auteur'] ?? null,
            ':citation_fonction'    => $d['citation_fonction'] ?? null,
            ':adresse'              => $d['adresse'] ?? null,
            ':latitude'             => $d['latitude'] !== '' ? $d['latitude'] : null,
            ':longitude'            => $d['longitude'] !== '' ? $d['longitude'] : null,
            ':map_embed_url'        => $d['map_embed_url'] ?? null,
            ':email_contact'        => $d['email_contact'] ?? null,
            ':telephone'            => $d['telephone'] ?? null,
            ':whatsapp'             => $d['whatsapp'] ?? null,
            ':prix_preinscription'  => (int)($d['prix_preinscription'] ?? 0),
            ':prix_inscription'     => (int)($d['prix_inscription'] ?? 0),
            ':frais_dossier'        => (int)($d['frais_dossier'] ?? 0),
            ':frais_scolarite'      => (int)($d['frais_scolarite'] ?? 0),
            ':frais_scolarite_boursier' => (int)($d['frais_scolarite_boursier'] ?? 0),
            ':frais_scolarite_master' => (int)($d['frais_scolarite_master'] ?? 0),
            ':frais_scolarite_master_boursier' => (int)($d['frais_scolarite_master_boursier'] ?? 0),
            ':frais_uniforme'       => (int)($d['frais_uniforme'] ?? 0),
            ':taux_commission_scolarite' => (float)($d['taux_commission_scolarite'] ?? 10.0),
            ':infos_paiement'       => $d['infos_paiement'] ?? null,
            ':preinscription_active'=> (int)($d['preinscription_active'] ?? 1),
            ':inscription_active'   => (int)($d['inscription_active'] ?? 0),
            ':genere_recepisse'     => (int)($d['genere_recepisse'] ?? 1),
            ':genere_attestation'   => (int)($d['genere_attestation'] ?? 1),
            ':badges_confiance'     => $d['badges_confiance'] ?? null,
            ':atouts'               => $d['atouts'] ?? null,
            ':statistiques'         => $d['statistiques'] ?? null,
            ':images_cles'          => $d['images_cles'] ?? null,
        ] + $frais['params'] + $boursier['params'] + $accepteB['params'] + $compo['params']);

        // Le réglage vient d'être réécrit : le cache de résolution doit
        // repartir de la base, sinon l'écran qui suit l'enregistrement
        // afficherait encore l'assiette précédente.
        TarifCycle::oublierReglages();

        // Même raison pour l'ouverture aux boursiers : le cache de process
        // répondrait « accepte » juste après une fermeture.
        \Services\Orientation\PolitiqueBoursiers::oublier();
    }

    /**
     * Mise à jour restreinte au périmètre gestionnaire (paramètres de l'espace
     * établissement). Le nom, le type, le slug, le statut, le logo et le compte
     * de reversement restent pilotés par le super-admin.
     */
    public function updateParametres(int $id, array $d): void
    {
        $st = $this->db->prepare('
            UPDATE etablissements SET
              couleur_principale    = :couleur,
              label_campagne        = :label_campagne,
              titre_accroche        = :titre_accroche,
              sous_titre            = :sous_titre,
              date_limite_dossier   = :date_limite,
              description           = :description,
              citation_texte        = :citation_texte,
              citation_auteur       = :citation_auteur,
              citation_fonction     = :citation_fonction,
              adresse               = :adresse,
              email_contact         = :email_contact,
              telephone             = :telephone,
              whatsapp              = :whatsapp,
              prix_preinscription   = :prix_preinscription,
              prix_inscription      = :prix_inscription,
              frais_dossier         = :frais_dossier,
              preinscription_active = :preinscription_active,
              inscription_active    = :inscription_active,
              genere_recepisse      = :genere_recepisse,
              genere_attestation    = :genere_attestation,
              signature_cle         = :signature_cle
            WHERE id = :id
        ');

        $st->execute([
            ':id'                    => $id,
            ':couleur'               => $d['couleur_principale'],
            ':label_campagne'        => $d['label_campagne'] ?: null,
            ':titre_accroche'        => $d['titre_accroche'] ?: null,
            ':sous_titre'            => $d['sous_titre'] ?: null,
            ':date_limite'           => $d['date_limite_dossier'] ?: null,
            ':description'           => $d['description'] ?: null,
            ':citation_texte'        => $d['citation_texte'] ?: null,
            ':citation_auteur'       => $d['citation_auteur'] ?: null,
            ':citation_fonction'     => $d['citation_fonction'] ?: null,
            ':adresse'               => $d['adresse'] ?: null,
            ':email_contact'         => $d['email_contact'] ?: null,
            ':telephone'             => $d['telephone'] ?: null,
            ':whatsapp'              => $d['whatsapp'] ?: null,
            ':prix_preinscription'   => (int)$d['prix_preinscription'],
            ':prix_inscription'      => (int)$d['prix_inscription'],
            ':frais_dossier'         => (int)$d['frais_dossier'],
            ':preinscription_active' => (int)$d['preinscription_active'],
            ':inscription_active'    => (int)($d['inscription_active'] ?? 0),
            ':genere_recepisse'      => (int)$d['genere_recepisse'],
            ':genere_attestation'    => (int)($d['genere_attestation'] ?? 1),
            ':signature_cle'         => ($d['signature_cle'] ?? '') !== '' ? $d['signature_cle'] : null,
        ]);
    }

    public function addFormation(int $etablissementId, array $d): void
    {
        $st = $this->db->prepare('
            INSERT INTO formations
              (etablissement_id, intitule, niveau, icone, duree_texte, places_texte, prix, prix_unite, statut, ordre)
            VALUES
              (:etab_id, :intitule, :niveau, :icone, :duree, :places, :prix, :prix_unite, :statut, :ordre)
        ');

        $st->execute([
            ':etab_id'   => $etablissementId,
            ':intitule'  => $d['intitule'],
            ':niveau'    => $d['niveau'] ?? null,
            ':icone'     => $d['icone'] ?? null,
            ':duree'     => $d['duree_texte'] ?? null,
            ':places'    => $d['places_texte'] ?? null,
            ':prix'      => (int)($d['prix'] ?? 0),
            ':prix_unite'=> $d['prix_unite'] ?? 'FCFA/an',
            ':statut'    => $d['statut'] ?? 'ouverte',
            ':ordre'     => (int)($d['ordre'] ?? 0),
        ]);
    }

    // Met à jour une formation existante en conservant son id
    // (dossiers_inscription.formation_id reste valide)
    public function updateFormation(int $formationId, int $etablissementId, array $d): void
    {
        $st = $this->db->prepare('
            UPDATE formations SET
              intitule = :intitule, niveau = :niveau, icone = :icone,
              duree_texte = :duree, places_texte = :places,
              prix = :prix, prix_unite = :prix_unite, statut = :statut, ordre = :ordre
            WHERE id = :id AND etablissement_id = :etab_id
        ');

        $st->execute([
            ':id'        => $formationId,
            ':etab_id'   => $etablissementId,
            ':intitule'  => $d['intitule'],
            ':niveau'    => $d['niveau'] ?? null,
            ':icone'     => $d['icone'] ?? null,
            ':duree'     => $d['duree_texte'] ?? null,
            ':places'    => $d['places_texte'] ?? null,
            ':prix'      => (int)($d['prix'] ?? 0),
            ':prix_unite'=> $d['prix_unite'] ?? 'FCFA/an',
            ':statut'    => $d['statut'] ?? 'ouverte',
            ':ordre'     => (int)($d['ordre'] ?? 0),
        ]);
    }

    public function addFormationReturningId(int $etablissementId, array $d): int
    {
        $this->addFormation($etablissementId, $d);
        return (int)$this->db->lastInsertId();
    }

    public function deleteFormationsExcept(int $etablissementId, array $keepIds): void
    {
        if (empty($keepIds)) {
            $this->db->prepare('DELETE FROM formations WHERE etablissement_id = ?')
                     ->execute([$etablissementId]);
            return;
        }
        $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
        $st = $this->db->prepare(
            "DELETE FROM formations WHERE etablissement_id = ? AND id NOT IN ({$placeholders})"
        );
        $st->execute([$etablissementId, ...array_map('intval', $keepIds)]);
    }

    public function addCompteEncaissement(int $etablissementId, array $d): void
    {
        $st = $this->db->prepare('
            INSERT INTO etablissement_paiement
              (etablissement_id, pays_code, withdraw_mode, numero_mobile_money, titulaire, taux_commission, statut)
            VALUES (:etab_id, :pays, :mode, :numero, :titulaire, :taux, :statut)
        ');

        $st->execute([
            ':etab_id'  => $etablissementId,
            ':pays'     => 'ga',
            ':mode'     => $d['withdraw_mode'],
            ':numero'   => $d['numero_mobile_money'],
            ':titulaire'=> $d['titulaire'] ?? null,
            ':taux'     => (float)($_ENV['COMMISSION_PLATEFORME_PERCENT'] ?? 5),
            ':statut'   => 'en_attente',
        ]);
    }

    public function createGestionnaire(array $d): int
    {
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $st = $this->db->prepare('
            INSERT INTO users
              (uuid, nom, prenom, email, password_hash, role, is_verified, is_active, is_deleted, created_at, updated_at)
            VALUES
              (:uuid, :nom, :prenom, :email, :hash, :role, 1, 1, 0, NOW(), NOW())
        ');

        $st->execute([
            ':uuid'   => $uuid,
            ':nom'    => $d['nom'],
            ':prenom' => $d['prenom'],
            ':email'  => $d['email'],
            ':hash'   => password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            ':role'   => 'gestionnaire',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function linkGestionnaire(int $etablissementId, int $userId): void
    {
        $st = $this->db->prepare('
            INSERT INTO etablissement_admins (etablissement_id, user_id, role, date_creation)
            VALUES (:etab_id, :user_id, :role, NOW())
        ');

        $st->execute([
            ':etab_id' => $etablissementId,
            ':user_id' => $userId,
            ':role'    => 'gestionnaire',
        ]);
    }

    public function findAll(): array
    {
        return $this->db->query('
            SELECT e.*,
                   (SELECT COUNT(*) FROM formations f WHERE f.etablissement_id = e.id) AS nb_formations,
                   (SELECT COUNT(*) FROM dossiers_inscription d WHERE d.etablissement_id = e.id) AS nb_dossiers
            FROM etablissements e
            ORDER BY e.date_creation DESC
        ')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByStatut(): array
    {
        $rows = $this->db->query('
            SELECT statut, COUNT(*) AS n FROM etablissements GROUP BY statut
        ')->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'total'    => array_sum($rows),
            'actif'    => (int)($rows['actif'] ?? 0),
            'brouillon'=> (int)($rows['brouillon'] ?? 0),
            'inactif'  => (int)($rows['inactif'] ?? 0),
        ];
    }

    public function emailExists(string $email): bool
    {
        $st = $this->db->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        return (bool)$st->fetch();
    }

    // Vrai seulement si l'email est déjà lié à un établissement actif
    public function emailExistsAndLinked(string $email): bool
    {
        $st = $this->db->prepare('
            SELECT u.id FROM users u
            JOIN etablissement_admins ea ON ea.user_id = u.id
            WHERE u.email = ?
            LIMIT 1
        ');
        $st->execute([$email]);
        return (bool)$st->fetch();
    }

    /**
     * Ce compte est-il DÉJÀ gestionnaire de cet établissement précis ?
     *
     * Sert au seul message d'erreur : « déjà gestionnaire de cet
     * établissement » plutôt que « d'un autre », qui enverrait le super
     * administrateur chercher lequel.
     */
    public function estLieAEtablissement(int $userId, int $etablissementId): bool
    {
        $st = $this->db->prepare('
            SELECT 1 FROM etablissement_admins
            WHERE user_id = ? AND etablissement_id = ?
            LIMIT 1
        ');
        $st->execute([$userId, $etablissementId]);
        return (bool)$st->fetchColumn();
    }

    public function findUserByEmail(string $email): ?array
    {
        $st = $this->db->prepare(
            'SELECT id, email, role, is_active, is_deleted FROM users WHERE email = ? LIMIT 1'
        );
        $st->execute([$email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // Réutilise un compte gestionnaire orphelin : identité + mot de passe réinitialisés
    public function resetGestionnaire(int $userId, string $nom, string $prenom, string $password): void
    {
        $st = $this->db->prepare('
            UPDATE users
            SET nom = ?, prenom = ?, password_hash = ?,
                is_active = 1, is_deleted = 0, is_verified = 1, updated_at = NOW()
            WHERE id = ?
        ');
        $st->execute([$nom, $prenom, password_hash($password, PASSWORD_BCRYPT), $userId]);
    }

    public function findAllActive(?string $type = null): array
    {
        $sql = '
            SELECT e.*,
                   (SELECT COUNT(*) FROM formations f WHERE f.etablissement_id = e.id) AS nb_formations,
                   (SELECT MIN(f2.prix) FROM formations f2
                    WHERE f2.etablissement_id = e.id AND f2.statut = "ouverte" AND f2.prix > 0) AS prix_min,
                   -- Mots-clés recherchables : intitulés de formations + filières + spécialités
                   -- actives (concaténés via sous-requêtes corrélées, pas de fan-out de lignes).
                   (SELECT GROUP_CONCAT(f3.intitule SEPARATOR " ")
                    FROM formations f3 WHERE f3.etablissement_id = e.id)                AS kw_formations,
                   (SELECT GROUP_CONCAT(fe.nom SEPARATOR " ")
                    FROM filieres_etablissement fe
                    WHERE fe.etablissement_id = e.id AND fe.actif = 1)                  AS kw_filieres,
                   (SELECT GROUP_CONCAT(sp.nom SEPARATOR " ")
                    FROM specialites_filiere sp
                    JOIN filieres_etablissement fe2 ON fe2.id = sp.filiere_id
                    WHERE fe2.etablissement_id = e.id AND fe2.actif = 1 AND sp.actif = 1) AS kw_specialites
            FROM etablissements e
            WHERE e.statut = "actif"';
        $params = [];
        if ($type !== null) {
            $sql     .= ' AND e.type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY e.nom ASC';

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Établissements publiés (statut « actif ») destinés au sitemap XML :
     * slug + date de dernière évolution connue (date_creation). Volume faible,
     * requête minimale — appelée à chaque génération du /sitemap.xml pour que
     * tout établissement mis en ligne soit automatiquement référencé.
     *
     * @return array<int, array{slug:string, lastmod:?string}>
     */
    public function sitemapEntries(): array
    {
        return $this->db->query(
            'SELECT slug, date_creation AS lastmod
               FROM etablissements
              WHERE statut = "actif"
              ORDER BY nom ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Stats du hub orientation : établissements actifs par type + préinscriptions. */
    public function statsHub(): array
    {
        $types = $this->db->query('
            SELECT type, COUNT(*) AS n FROM etablissements WHERE statut = "actif" GROUP BY type
        ')->fetchAll(PDO::FETCH_KEY_PAIR);

        $pre = (int)$this->db->query('
            SELECT COUNT(*) FROM dossiers_inscription WHERE statut != "brouillon"
        ')->fetchColumn();

        return [
            'universites'    => (int)($types['universite'] ?? 0),
            'grandes_ecoles' => (int)($types['grande_ecole'] ?? 0) + (int)($types['institut'] ?? 0),
            'preinscriptions'=> $pre,
        ];
    }

    public function findBySlug(string $slug): ?array
    {
        $st = $this->db->prepare('SELECT * FROM etablissements WHERE slug = ? LIMIT 1');
        $st->execute([$slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getFormations(int $etablissementId): array
    {
        $st = $this->db->prepare(
            'SELECT * FROM formations WHERE etablissement_id = ? ORDER BY ordre ASC, id ASC'
        );
        $st->execute([$etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActualites(int $etablissementId, int $limit = 7): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM actualites
             WHERE etablissement_id = ? AND statut = 'publie'
             ORDER BY a_la_une DESC, date_publication DESC, id DESC LIMIT ?"
        );
        $st->execute([$etablissementId, $limit]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActualiteById(int $etablissementId, int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT * FROM actualites
             WHERE id = ? AND etablissement_id = ? AND statut = 'publie' LIMIT 1"
        );
        $st->execute([$id, $etablissementId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function logVisit(int $etablissementId, string $ip): void
    {
        try {
            $st = $this->db->prepare(
                'INSERT INTO visites_etablissement (etablissement_id, ip, date_visite) VALUES (?, ?, NOW())'
            );
            $st->execute([$etablissementId, $ip]);
        } catch (\Throwable) {
            // Silently ignore — visit tracking must not break page load
        }
    }

    public function findById(int $id): ?array
    {
        $st = $this->db->prepare('
            SELECT e.*,
                   ep.withdraw_mode, ep.numero_mobile_money, ep.titulaire,
                   ep.taux_commission, ep.statut AS paiement_statut
            FROM etablissements e
            LEFT JOIN etablissement_paiement ep ON ep.etablissement_id = e.id
            WHERE e.id = ? LIMIT 1
        ');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function toggleInscriptionActive(int $id, bool $actif): void
    {
        $this->db->prepare(
            'UPDATE etablissements SET inscription_active = ? WHERE id = ?'
        )->execute([(int)$actif, $id]);
    }

    public function findAllWithPaiement(): array
    {
        return $this->db->query('
            SELECT e.*,
                   (SELECT COUNT(*) FROM formations f WHERE f.etablissement_id = e.id) AS nb_formations,
                   (SELECT COUNT(*) FROM dossiers_inscription d WHERE d.etablissement_id = e.id) AS nb_dossiers,
                   ep.statut AS paiement_statut,
                   ep.numero_mobile_money, ep.withdraw_mode
            FROM etablissements e
            LEFT JOIN etablissement_paiement ep ON ep.etablissement_id = e.id
            ORDER BY e.date_creation DESC
        ')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function statsGlobales(): array
    {
        // Chaque compteur est isolé dans sa propre sous-requête : joindre
        // paiements_orientation ici démultipliait les lignes (1 dossier à
        // plusieurs paiements était compté plusieurs fois → totaux gonflés).
        $row = $this->db->query('
            SELECT
                (SELECT COUNT(*) FROM dossiers_inscription)                              AS nb_dossiers,
                (SELECT COUNT(*) FROM dossiers_inscription
                  WHERE statut = "soumis")                                               AS nb_preinscriptions,
                (SELECT COUNT(*) FROM dossiers_inscription
                  WHERE statut IN ("paye","accepte","inscrit"))                          AS nb_inscriptions,
                (SELECT COALESCE(SUM(p.montant), 0)
                   FROM paiements_orientation p
                   JOIN dossiers_inscription d ON d.id = p.dossier_id
                  WHERE p.statut = "reussi")                                             AS revenus_total,
                (SELECT COUNT(DISTINCT user_id) FROM etablissement_admins)               AS nb_gestionnaires
        ')->fetch(\PDO::FETCH_ASSOC);

        return $row ?: [
            'nb_dossiers'       => 0,
            'nb_preinscriptions'=> 0,
            'nb_inscriptions'   => 0,
            'revenus_total'     => 0,
            'nb_gestionnaires'  => 0,
        ];
    }

    /**
     * Inscriptions payées + revenus encaissés ventilés par établissement
     * (donuts du tableau de bord admin). Sous-requêtes corrélées : pas de
     * démultiplication des lignes quand un dossier a plusieurs paiements.
     */
    public function statsParEtablissement(): array
    {
        return $this->db->query('
            SELECT e.id, e.nom, e.couleur_principale,
                   (SELECT COUNT(*)
                      FROM dossiers_inscription d0
                     WHERE d0.etablissement_id = e.id
                       AND d0.statut = "soumis")                               AS nb_preinscriptions,
                   (SELECT COUNT(*)
                      FROM dossiers_inscription d
                     WHERE d.etablissement_id = e.id
                       AND d.statut IN ("paye","accepte","inscrit"))           AS nb_inscriptions,
                   (SELECT COALESCE(SUM(p.montant), 0)
                      FROM paiements_orientation p
                      JOIN dossiers_inscription d2 ON d2.id = p.dossier_id
                     WHERE d2.etablissement_id = e.id
                       AND p.statut = "reussi")                                AS revenus
            FROM etablissements e
            ORDER BY nb_inscriptions DESC, e.nom ASC
        ')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllGestionnaires(): array
    {
        return $this->db->query('
            SELECT u.id AS user_id, u.nom, u.prenom, u.email, u.is_active, u.last_login,
                   ea.role, ea.date_creation AS date_lien,
                   e.id AS etab_id, e.nom AS etab_nom, e.slug AS etab_slug, e.statut AS etab_statut
            FROM etablissement_admins ea
            JOIN users u ON u.id = ea.user_id AND u.is_deleted = 0
            JOIN etablissements e ON e.id = ea.etablissement_id
            ORDER BY e.nom ASC, u.nom ASC
        ')->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findSlugById(int $id): ?string
    {
        $st = $this->db->prepare('SELECT slug FROM etablissements WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $val = $st->fetchColumn();
        return $val ?: null;
    }

    // Gestionnaires liés à un établissement (page détail admin)
    public function findGestionnairesByEtab(int $etabId): array
    {
        $st = $this->db->prepare('
            SELECT u.id AS user_id, u.nom, u.prenom, u.email, u.is_active, u.last_login,
                   ea.role, ea.date_creation AS date_lien
            FROM etablissement_admins ea
            JOIN users u ON u.id = ea.user_id AND u.is_deleted = 0
            WHERE ea.etablissement_id = ?
            ORDER BY ea.date_creation ASC
        ');
        $st->execute([$etabId]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Stats d'un établissement : dossiers par statut + revenus encaissés. */
    public function statsEtablissement(int $etabId): array
    {
        // Sous-requêtes isolées : joindre paiements_orientation dans le même
        // SELECT démultipliait les dossiers à plusieurs paiements (totaux gonflés).
        $st = $this->db->prepare('
            SELECT
                (SELECT COUNT(*) FROM dossiers_inscription
                  WHERE etablissement_id = ?)                                          AS nb_dossiers,
                (SELECT COUNT(*) FROM dossiers_inscription
                  WHERE etablissement_id = ? AND statut = "soumis")                    AS nb_preinscriptions,
                (SELECT COUNT(*) FROM dossiers_inscription
                  WHERE etablissement_id = ? AND statut IN ("paye","accepte","inscrit")) AS nb_inscriptions,
                (SELECT COALESCE(SUM(p.montant), 0)
                   FROM paiements_orientation p
                   JOIN dossiers_inscription d ON d.id = p.dossier_id
                  WHERE d.etablissement_id = ? AND p.statut = "reussi")                AS revenus_total
        ');
        $st->execute([$etabId, $etabId, $etabId, $etabId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        return $row ?: [
            'nb_dossiers'        => 0,
            'nb_preinscriptions' => 0,
            'nb_inscriptions'    => 0,
            'revenus_total'      => 0,
        ];
    }

    public function findGestionnairesEmail(int $etabId): array
    {
        $st = $this->db->prepare('
            SELECT u.id, u.prenom, u.nom, u.email
            FROM etablissement_admins ea
            JOIN users u ON u.id = ea.user_id AND u.is_active = 1 AND u.is_deleted = 0
            WHERE ea.etablissement_id = ?
        ');
        $st->execute([$etabId]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }
}
