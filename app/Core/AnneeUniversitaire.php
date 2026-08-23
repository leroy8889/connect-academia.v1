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
}
