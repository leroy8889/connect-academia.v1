<?php
declare(strict_types=1);

namespace Core;

/**
 * Année universitaire courante de la plateforme.
 *
 * La plateforme d'orientation ouvre les préinscriptions AVANT la rentrée de
 * septembre : dès juin, les candidats préparent l'année scolaire suivante.
 * La bascule se fait donc en juin (mois >= 6) → année N / N+1.
 *
 * Exemples : juillet 2026 → « 2026 — 2027 » ; février 2027 → « 2026 — 2027 ».
 *
 * Une valeur peut être forcée via la variable d'environnement
 * ANNEE_UNIVERSITAIRE (ex. « 2026 — 2027 ») pour figer l'affichage sans
 * dépendre de la date serveur.
 */
final class AnneeUniversitaire
{
    /** Mois de bascule vers l'année suivante (1 = janvier … 12 = décembre). */
    private const MOIS_BASCULE = 6;

    /**
     * Retourne l'année universitaire formatée « AAAA — AAAA ».
     */
    public static function courante(?\DateTimeInterface $date = null): string
    {
        $forcee = trim((string)($_ENV['ANNEE_UNIVERSITAIRE'] ?? ''));
        if ($forcee !== '') {
            return $forcee;
        }

        $date = $date ?? new \DateTime();
        $an   = (int)$date->format('Y');
        $mois = (int)$date->format('n');

        [$debut, $fin] = $mois >= self::MOIS_BASCULE
            ? [$an, $an + 1]
            : [$an - 1, $an];

        return sprintf('%d — %d', $debut, $fin);
    }

    /**
     * Année d'entrée de l'année académique, sur 4 chiffres (spec §5.5).
     * Juillet 2026 → 2026 ; février 2027 → 2026.
     */
    public static function debut(?\DateTimeInterface $date = null): int
    {
        // Respecte ANNEE_UNIVERSITAIRE si elle est forcée : on lit le premier
        // groupe de 4 chiffres du libellé.
        if (preg_match('/(\d{4})/', self::courante($date), $m) === 1) {
            return (int)$m[1];
        }

        $date = $date ?? new \DateTime();
        $an   = (int)$date->format('Y');

        return (int)$date->format('n') >= self::MOIS_BASCULE ? $an : $an - 1;
    }

    /**
     * Forme canonique stockée en base : « 2026-2027 » (VARCHAR(9)).
     * Distincte de courante() qui est une forme d'AFFICHAGE (tiret cadratin).
     */
    public static function code(?\DateTimeInterface $date = null): string
    {
        $debut = self::debut($date);

        return sprintf('%d-%d', $debut, $debut + 1);
    }
}
