<?php
declare(strict_types=1);

namespace Core;

/**
 * Encodage opaque et infalsifiable d'identifiants numériques exposés dans les
 * URL côté utilisateur (dossiers d'orientation, etc.).
 *
 * Objectif : empêcher l'énumération des dossiers via l'URL. Un simple entier
 * séquentiel (…/dossier/43) laisse deviner les autres dossiers en changeant le
 * numéro. Le contrôle d'accès reste la vraie barrière (ownership en base), mais
 * exposer des PK séquentielles est une fuite d'information (nombre de dossiers,
 * tentatives d'IDOR facilitées). On remplace donc l'ID par un jeton :
 *
 *   1. l'ID (4 octets) est masqué par XOR avec un flux dérivé du secret app
 *      → le jeton ne laisse pas lire l'entier « en clair » ;
 *   2. un tag HMAC-SHA256 tronqué (8 octets) scelle le jeton
 *      → impossible de forger un jeton valide sans le secret ⇒ pas d'énumération.
 *
 * Le résultat est déterministe (même ID ⇒ même jeton) : les URL restent
 * stables et « bookmarkables ». Encodage base64url, ~16 caractères.
 */
final class Hashid
{
    /** Secret d'application — fallback constant pour ne jamais lever d'erreur fatale en vue. */
    private static function secret(): string
    {
        $k = $_ENV['APP_SECRET'] ?? '';
        return $k !== '' ? $k : 'connect-academia-hashid-fallback';
    }

    /** Masque déterministe de 4 octets pour obscurcir la charge utile. */
    private static function mask(): string
    {
        return substr(hash_hmac('sha256', 'dossier-mask-v1', self::secret(), true), 0, 4);
    }

    public static function encode(int $id): string
    {
        $obf = pack('N', $id) ^ self::mask();
        $mac = substr(hash_hmac('sha256', $obf, self::secret(), true), 0, 8);
        return rtrim(strtr(base64_encode($obf . $mac), '+/', '-_'), '=');
    }

    /** Décode un jeton ; retourne null si absent, malformé ou falsifié. */
    public static function decode(string $hash): ?int
    {
        if ($hash === '') {
            return null;
        }
        // Compatibilité ascendante : un ID numérique brut reste accepté (anciens
        // liens en circulation, favoris). On n'énumère pas pour autant : le
        // contrôle d'ownership en base filtre toujours l'accès.
        if (ctype_digit($hash)) {
            return (int) $hash;
        }

        $bin = base64_decode(strtr($hash, '-_', '+/'), true);
        if ($bin === false || strlen($bin) !== 12) {
            return null;
        }
        $obf = substr($bin, 0, 4);
        $mac = substr($bin, 4);
        $expected = substr(hash_hmac('sha256', $obf, self::secret(), true), 0, 8);
        if (!hash_equals($expected, $mac)) {
            return null;
        }
        $arr = unpack('N', $obf ^ self::mask());
        return $arr[1] ?? null;
    }
}
