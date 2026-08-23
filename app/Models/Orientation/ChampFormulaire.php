<?php
declare(strict_types=1);

namespace Models\Orientation;

use Core\Database;
use PDO;

class ChampFormulaire
{
    private PDO $db;

    // Jeu standard de 11 champs inséré à la création d'un établissement
    private const CHAMPS_STANDARD = [
        // Identité
        ['type_formulaire'=>'preinscription','section'=>'Identité','cle'=>'nom_complet','libelle'=>'Nom complet','type_champ'=>'text','obligatoire'=>1,'ordre'=>1],
        ['type_formulaire'=>'preinscription','section'=>'Identité','cle'=>'genre','libelle'=>'Genre','type_champ'=>'select','options'=>['homme'=>'Homme','femme'=>'Femme','autre'=>'Autre'],'obligatoire'=>1,'ordre'=>2],
        ['type_formulaire'=>'preinscription','section'=>'Identité','cle'=>'date_naissance','libelle'=>'Date de naissance','type_champ'=>'date','obligatoire'=>1,'ordre'=>3],
        ['type_formulaire'=>'preinscription','section'=>'Identité','cle'=>'lieu_naissance','libelle'=>'Lieu de naissance','type_champ'=>'text','obligatoire'=>1,'ordre'=>4],
        ['type_formulaire'=>'preinscription','section'=>'Identité','cle'=>'nationalite','libelle'=>'Nationalité','type_champ'=>'text','obligatoire'=>1,'ordre'=>5],
        // Contact
        ['type_formulaire'=>'preinscription','section'=>'Contact','cle'=>'email','libelle'=>'Adresse e-mail','type_champ'=>'email','obligatoire'=>1,'ordre'=>6],
        ['type_formulaire'=>'preinscription','section'=>'Contact','cle'=>'telephone','libelle'=>'Téléphone','type_champ'=>'tel','obligatoire'=>1,'ordre'=>7],
        ['type_formulaire'=>'preinscription','section'=>'Contact','cle'=>'pays_provenance','libelle'=>'Pays de provenance','type_champ'=>'text','obligatoire'=>0,'ordre'=>8],
        ['type_formulaire'=>'preinscription','section'=>'Contact','cle'=>'ville','libelle'=>'Ville','type_champ'=>'text','obligatoire'=>0,'ordre'=>9],
        // Formation
        ['type_formulaire'=>'preinscription','section'=>'Formation souhaitée','cle'=>'filiere_visee','libelle'=>'Filière visée','type_champ'=>'select','options'=>['socle_l1_l2'=>'Socle L1-L2','licence_l3'=>'Licence L3','master_m1_m2'=>'Master M1-M2','certification'=>'Certification'],'obligatoire'=>1,'ordre'=>10],
        ['type_formulaire'=>'preinscription','section'=>'Formation souhaitée','cle'=>'dernier_etablissement','libelle'=>'Dernier établissement fréquenté','type_champ'=>'text','obligatoire'=>0,'ordre'=>11],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByEtab(int $etabId, ?string $type = null): array
    {
        if ($type !== null) {
            $st = $this->db->prepare(
                'SELECT * FROM champs_formulaire WHERE etablissement_id = ? AND type_formulaire = ?
                 ORDER BY section, ordre ASC, id ASC'
            );
            $st->execute([$etabId, $type]);
        } else {
            $st = $this->db->prepare(
                'SELECT * FROM champs_formulaire WHERE etablissement_id = ?
                 ORDER BY type_formulaire, section, ordre ASC, id ASC'
            );
            $st->execute([$etabId]);
        }
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, int $etabId): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM champs_formulaire WHERE id = ? AND etablissement_id = ? LIMIT 1'
        );
        $st->execute([$id, $etabId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** NON cloisonné (inclut etablissement_id) — télémétrie TenantGuard sur le chemin de refus. */
    public function findByIdAny(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM champs_formulaire WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function toggle(int $id, int $etabId): void
    {
        $this->db->prepare(
            'UPDATE champs_formulaire SET actif = 1 - actif WHERE id = ? AND etablissement_id = ?'
        )->execute([$id, $etabId]);
    }

    public function create(int $etabId, array $d): int
    {
        $options = null;
        if (!empty($d['options_raw'])) {
            $opts = [];
            foreach (explode("\n", $d['options_raw']) as $line) {
                $line = trim($line);
                if ($line !== '') $opts[] = $line;
            }
            if (!empty($opts)) $options = json_encode($opts);
        }

        $st = $this->db->prepare('
            INSERT INTO champs_formulaire
              (etablissement_id, type_formulaire, section, cle, libelle, type_champ, options, obligatoire, actif, standard, ordre)
            VALUES
              (:etab, :type, :section, :cle, :libelle, :type_champ, :options, :obligatoire, 1, 0, :ordre)
        ');
        $st->execute([
            ':etab'       => $etabId,
            ':type'       => $d['type_formulaire'],
            ':section'    => $d['section'] ?? 'Personnalisé',
            ':cle'        => preg_replace('/[^a-z0-9_]/', '_', strtolower($d['cle'] ?? $d['libelle'] ?? 'champ')),
            ':libelle'    => $d['libelle'],
            ':type_champ' => $d['type_champ'] ?? 'text',
            ':options'    => $options,
            ':obligatoire'=> (int)(!empty($d['obligatoire'])),
            ':ordre'      => (int)($d['ordre'] ?? 99),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $id, int $etabId): void
    {
        // Ne supprimer que les champs non-standard
        $this->db->prepare(
            'DELETE FROM champs_formulaire WHERE id = ? AND etablissement_id = ? AND standard = 0'
        )->execute([$id, $etabId]);
    }

    public function insertDefaultSet(int $etabId): void
    {
        $st = $this->db->prepare('
            INSERT IGNORE INTO champs_formulaire
              (etablissement_id, type_formulaire, section, cle, libelle, type_champ, options, obligatoire, actif, standard, ordre)
            VALUES
              (:etab, :type, :section, :cle, :libelle, :type_champ, :options, :obligatoire, 1, 1, :ordre)
        ');

        foreach (self::CHAMPS_STANDARD as $champ) {
            $options = isset($champ['options']) ? json_encode($champ['options']) : null;
            $st->execute([
                ':etab'       => $etabId,
                ':type'       => $champ['type_formulaire'],
                ':section'    => $champ['section'],
                ':cle'        => $champ['cle'],
                ':libelle'    => $champ['libelle'],
                ':type_champ' => $champ['type_champ'],
                ':options'    => $options,
                ':obligatoire'=> $champ['obligatoire'],
                ':ordre'      => $champ['ordre'],
            ]);
        }
    }
}
