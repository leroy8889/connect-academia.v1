<?php
declare(strict_types=1);

namespace Core;

/**
 * Plafond d'appels SIMULTANÉS à une dépendance externe lente.
 *
 * ── Pourquoi ─────────────────────────────────────────────────────────────
 * Un appel sortant lent immobilise un worker PHP-FPM pour toute sa durée. La
 * question posée à l'assistant IA vaut jusqu'à trois tentatives de soixante
 * secondes, séparées par deux pauses de deux secondes : **184 secondes** dans
 * le pire cas. Le pool compte quatre-vingts workers.
 *
 * Il suffit donc de quatre-vingts élèves posant une question pendant un
 * ralentissement de l'API pour que le pool soit intégralement consommé — et
 * c'est alors TOUT le site qui ne répond plus : la page d'accueil, le tunnel
 * d'inscription, l'espace établissement. Une fonctionnalité annexe emporte le
 * produit entier. Aucune limite quotidienne ne protège de cela : elle borne le
 * nombre d'appels par élève et par jour, jamais leur simultanéité.
 *
 * Ce garde réserve une part du pool et rend la main IMMÉDIATEMENT au-delà. La
 * dépendance lente ne peut plus déborder de son quota : elle se dégrade seule,
 * le reste du site continue de servir.
 *
 * ── Choix de conception ──────────────────────────────────────────────────
 * *Repli ouvert* : si Redis est indisponible, on laisse passer. Un compteur
 * injoignable ne doit pas couper une fonctionnalité qui, elle, marche — c'est
 * la même règle que `RateLimiter`.
 *
 * *TTL de sûreté* : un worker tué par l'OS ne libère pas sa place. Le compteur
 * porte donc une expiration un peu supérieure au pire cas, ce qui garantit un
 * retour à zéro même après un incident. Une place ne peut pas fuir
 * définitivement.
 *
 * *Jamais négatif* : deux libérations pour une réservation — un cas que le TTL
 * peut provoquer en remettant le compteur à zéro pendant qu'un appel est encore
 * en vol — rendraient le compteur négatif et le plafond inopérant. On efface la
 * clé plutôt que de descendre sous zéro.
 */
final class Concurrence
{
    private const NS = 'conc:';

    /**
     * Places réservées mais pas encore rendues, par porte, dans CE process.
     *
     * @var array<string,int>
     */
    private static array $tenues = [];

    /**
     * Réserve une place. Renvoie false si le plafond est déjà atteint —
     * l'appelant doit alors refuser tout de suite, sans appeler la dépendance.
     *
     * La libération est posée en `register_shutdown_function` : `Response::json()`
     * termine par `exit`, et un bloc `finally` ne s'exécute PAS sur `exit`. Sans
     * cela, chaque chemin d'erreur aurait laissé filer une place.
     */
    public static function acquerir(string $porte, int $max, int $ttlSecondes): bool
    {
        $max = max(1, $max);
        $ttl = max(1, $ttlSecondes);

        $redis = Redis::getInstance();
        if (!$redis->isAvailable()) {
            return true; // repli ouvert
        }

        $cle    = self::NS . $porte;
        $compte = $redis->incrWithExpire($cle, $ttl);

        if ($compte === false) {
            return true; // repli ouvert
        }

        if ((int)$compte > $max) {
            self::decrementer($redis, $cle);

            return false;
        }

        self::$tenues[$porte] = (self::$tenues[$porte] ?? 0) + 1;
        register_shutdown_function(static function () use ($porte): void {
            self::liberer($porte);
        });

        return true;
    }

    /** Rend une place. Sans effet si ce process n'en tient aucune. */
    public static function liberer(string $porte): void
    {
        if ((self::$tenues[$porte] ?? 0) < 1) {
            return;
        }
        self::$tenues[$porte]--;

        $redis = Redis::getInstance();
        if ($redis->isAvailable()) {
            self::decrementer($redis, self::NS . $porte);
        }
    }

    /** Places actuellement occupées (supervision). */
    public static function occupees(string $porte): int
    {
        $redis = Redis::getInstance();

        return $redis->isAvailable() ? max(0, $redis->getInt(self::NS . $porte)) : 0;
    }

    private static function decrementer(Redis $redis, string $cle): void
    {
        $restant = $redis->decrBy($cle, 1);
        if ($restant !== false && (int)$restant <= 0) {
            // Un compteur négatif rendrait le plafond inopérant : on efface.
            $redis->del($cle);
        }
    }
}
