<?php
declare(strict_types=1);

namespace Core;

/**
 * Chiffrement authentifié de la donnée sensible du parcours d'inscription.
 *
 * ── Ce que cette classe protège, et contre quoi ──────────────────────────────
 *
 * Les pièces justificatives d'un dossier d'inscription sont des actes de
 * naissance, des cartes d'identité, des photos d'identité, des relevés de
 * notes. Jusqu'ici elles arrivaient chez Backblaze en CLAIR : le bucket est
 * privé, mais « privé » veut dire « protégé par une clé d'API ». Toute personne
 * qui obtient cette clé — fuite du .env, sauvegarde mal rangée, accès console
 * B2, réquisition chez l'hébergeur — lit l'intégralité des pièces d'identité de
 * la plateforme sans jamais toucher au serveur.
 *
 * Le chiffrement posé ici casse ce lien : les octets déposés dans le bucket ne
 * sont exploitables QUE par l'application, avec le secret qui vit dans le .env
 * du serveur. Un bucket entièrement exfiltré ne rend plus rien de lisible.
 *
 * ── Pourquoi AES-256-GCM, et pas « AES tout court » ─────────────────────────
 *
 * GCM est un mode AUTHENTIFIÉ : il produit, en plus du chiffré, une étiquette
 * (tag) de 16 octets qui scelle le contenu. Un octet modifié dans le bucket et
 * le déchiffrement ÉCHOUE, au lieu de rendre une bouillie qu'on afficherait
 * comme un document valide. Un mode non authentifié (CBC, CTR seul) chiffre
 * sans rien garantir de l'intégrité — inacceptable pour une pièce d'identité
 * qui fonde une décision d'inscription.
 *
 * ── La donnée associée (AAD) : à quoi elle sert ici ─────────────────────────
 *
 * Chaque objet est chiffré en liant son contenu à SA clé de stockage (le chemin
 * B2, qui contient l'identifiant du dossier). Déplacer le fichier chiffré du
 * dossier #12 vers le dossier #48 ne suffit donc pas à le faire lire : l'AAD ne
 * correspond plus, le tag ne valide plus, le déchiffrement échoue. Sans ce
 * détail, quelqu'un ayant l'accès au bucket pourrait intervertir les pièces de
 * deux candidats sans jamais lire quoi que ce soit — et fausser une décision.
 *
 * ── Compatibilité ascendante : la règle qui évite la perte de données ───────
 *
 * Les fichiers déjà en production ne sont PAS chiffrés. Toute enveloppe produite
 * ici commence par une signature reconnaissable ; à la lecture, un contenu sans
 * cette signature est rendu tel quel. Les anciennes pièces restent donc
 * lisibles, les nouvelles sont chiffrées, et aucune migration n'est nécessaire.
 * C'est `dechiffrerSiBesoin()` qui porte cette règle — jamais l'appelant.
 *
 * ── Le secret ───────────────────────────────────────────────────────────────
 *
 * La clé n'est pas APP_SECRET : c'est une clé DÉRIVÉE (HKDF-SHA256) avec une
 * étiquette de domaine propre au stockage documentaire. Le même secret sert
 * déjà à Hashid et aux jetons ; les dériver séparément évite qu'une faiblesse
 * ou une fuite sur l'un donne l'autre. `APP_ENCRYPTION_KEY` (32 octets en
 * base64) prime si elle est renseignée, ce qui permet une rotation future sans
 * toucher à APP_SECRET.
 *
 * Aucune méthode ne lève d'exception faute de secret : comme Hashid, on retombe
 * sur une valeur déterministe, et l'anomalie est journalisée. Un secret absent
 * est un défaut de configuration à corriger — pas une raison de rendre
 * indéchiffrables des pièces déjà écrites.
 */
final class Crypto
{
    /** Signature d'enveloppe. Sa présence en tête = « ce contenu est chiffré ». */
    private const MAGIC = "CAENC\x01";

    private const CIPHER  = 'aes-256-gcm';
    private const IV_LEN  = 12;   // taille recommandée pour GCM
    private const TAG_LEN = 16;

    /** Étiquette de domaine HKDF : isole cette clé de tous les autres usages du secret. */
    private const INFO_DOCUMENTS = 'connect-academia/chiffrement-documents/v1';

    /** Longueur minimale d'une enveloppe valide (signature + IV + tag). */
    private const OVERHEAD = 6 + self::IV_LEN + self::TAG_LEN;

    /** Le moteur sait-il faire ? (extension openssl + algorithme disponible) */
    public static function disponible(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $ok = function_exists('openssl_encrypt')
                && in_array(self::CIPHER, openssl_get_cipher_methods(), true);
        }
        return $ok;
    }

    /**
     * Chiffre un contenu et retourne l'enveloppe complète.
     *
     * @param string $clair    Octets à protéger.
     * @param string $contexte Donnée associée (ici : la clé de stockage B2).
     *
     * @throws \RuntimeException si le moteur de chiffrement échoue — il ne faut
     *         JAMAIS écrire du clair en croyant écrire du chiffré.
     */
    public static function chiffrer(string $clair, string $contexte = ''): string
    {
        if (!self::disponible()) {
            throw new \RuntimeException('[Crypto] AES-256-GCM indisponible sur ce serveur.');
        }

        $iv  = random_bytes(self::IV_LEN);
        $tag = '';

        $chiffre = openssl_encrypt(
            $clair,
            self::CIPHER,
            self::cle(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $contexte,
            self::TAG_LEN
        );

        if ($chiffre === false || $tag === '') {
            throw new \RuntimeException('[Crypto] Chiffrement échoué.');
        }

        return self::MAGIC . $iv . $tag . $chiffre;
    }

    /**
     * Déchiffre une enveloppe produite par self::chiffrer().
     *
     * @throws \RuntimeException enveloppe tronquée, altérée, ou contexte faux.
     */
    public static function dechiffrer(string $enveloppe, string $contexte = ''): string
    {
        if (!self::estChiffre($enveloppe)) {
            throw new \RuntimeException('[Crypto] Contenu non chiffré ou enveloppe inconnue.');
        }
        if (!self::disponible()) {
            throw new \RuntimeException('[Crypto] AES-256-GCM indisponible sur ce serveur.');
        }

        $iv      = substr($enveloppe, 6, self::IV_LEN);
        $tag     = substr($enveloppe, 6 + self::IV_LEN, self::TAG_LEN);
        $chiffre = substr($enveloppe, self::OVERHEAD);

        $clair = openssl_decrypt(
            $chiffre,
            self::CIPHER,
            self::cle(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $contexte
        );

        if ($clair === false) {
            throw new \RuntimeException('[Crypto] Déchiffrement refusé : contenu altéré, déplacé, ou clé différente.');
        }

        return $clair;
    }

    /** Le contenu porte-t-il la signature d'enveloppe ? */
    public static function estChiffre(string $data): bool
    {
        return strlen($data) > self::OVERHEAD && str_starts_with($data, self::MAGIC);
    }

    /**
     * Déchiffre si l'enveloppe est présente, rend le contenu tel quel sinon.
     *
     * C'est LE point qui rend la bascule non destructive : les fichiers déposés
     * avant l'activation du chiffrement continuent de s'ouvrir normalement.
     */
    public static function dechiffrerSiBesoin(string $data, string $contexte = ''): string
    {
        return self::estChiffre($data) ? self::dechiffrer($data, $contexte) : $data;
    }

    /**
     * Clé de 32 octets, dérivée une seule fois par requête.
     *
     * Ordre de préférence :
     *   1. APP_ENCRYPTION_KEY (base64 de 32 octets) — permet la rotation ;
     *   2. HKDF-SHA256 sur APP_SECRET, étiqueté par domaine ;
     *   3. repli déterministe documenté, si le .env est incomplet.
     */
    private static function cle(): string
    {
        static $cle = null;
        if ($cle !== null) {
            return $cle;
        }

        $brute = trim((string)($_ENV['APP_ENCRYPTION_KEY'] ?? ''));
        if ($brute !== '') {
            $decodee = base64_decode($brute, true);
            if ($decodee !== false && strlen($decodee) === 32) {
                return $cle = $decodee;
            }
            error_log('[Crypto] APP_ENCRYPTION_KEY ignorée : 32 octets en base64 attendus.');
        }

        $secret = (string)($_ENV['APP_SECRET'] ?? '');
        if ($secret === '') {
            // Même parti pris que Core\Hashid : ne jamais rendre l'application
            // inopérante sur une variable manquante. Le repli est déterministe,
            // donc rien de déjà écrit ne devient illisible — mais il n'offre
            // aucune confidentialité réelle, d'où la trace explicite.
            error_log('[Crypto] APP_SECRET absent du .env — chiffrement documentaire DÉGRADÉ.');
            $secret = 'connect-academia-crypto-fallback';
        }

        return $cle = hash_hkdf('sha256', $secret, 32, self::INFO_DOCUMENTS);
    }
}
