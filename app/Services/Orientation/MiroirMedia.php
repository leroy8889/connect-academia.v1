<?php
declare(strict_types=1);

namespace Services\Orientation;

use Throwable;

/**
 * Miroir local des médias dérivés du stockage objet (logo, cachet, photo).
 *
 * ── POURQUOI ──────────────────────────────────────────────────────────────
 * Un logo d'établissement est petit, il ne change presque jamais, et il y en a
 * neuf sur toute la plateforme. Pourtant, chaque affichage en dépendait d'un
 * appel réseau vers Backblaze : le jour où le compte a atteint son plafond de
 * transactions, plus une seule image ne s'est affichée, en production comme en
 * local.
 *
 * Un cache ordinaire ne protège pas de cela : il expire, et Redis peut être
 * vidé ou redémarré. Il faut un ARCHIVE, pas un cache — une copie qui reste,
 * et qui répond quand le stockage distant ne répond plus.
 *
 * Ce miroir est le dernier filet :
 *   1. le cache (Redis, ou disque en repli) répond, on n'arrive jamais ici ;
 *   2. sinon on interroge Backblaze, et le résultat est archivé ici ;
 *   3. si Backblaze refuse, on sert la copie archivée.
 *
 * Résultat : un média téléchargé une seule fois s'affiche pour toujours, même
 * stockage coupé. C'est la différence entre « le site est plus lent » et « le
 * site n'a plus d'images ».
 *
 * ── CE QU'IL N'EST PAS ────────────────────────────────────────────────────
 * Ce n'est pas une sauvegarde : Backblaze reste la source de vérité, et le
 * miroir ne contient que des DÉRIVÉS (redimensionnés, ré-encodés, en data URI).
 * Il est effaçable à tout moment sans perte — au pire, un téléchargement.
 *
 * Réservé aux médias petits et rarement modifiés. Les pièces justificatives
 * des candidats n'y ont pas leur place : elles sont volumineuses, nombreuses,
 * et servies par URL signée sans passer par l'application.
 */
final class MiroirMedia
{
    /** Un dérivé dépasse rarement 200 Kio ; au-delà, il n'a rien à faire ici. */
    private const TAILLE_MAX = 1_048_576; // 1 Mio

    private static ?string $racine = null;
    private static bool    $resolu = false;

    /**
     * Copie archivée, ou null si elle n'existe pas.
     * Ne lève jamais : un miroir en panne est un miroir absent.
     */
    public static function lire(string $cle): ?string
    {
        $fichier = self::fichier($cle);

        if ($fichier === null || !is_file($fichier)) {
            return null;
        }

        try {
            $contenu = @file_get_contents($fichier);

            return ($contenu === false || $contenu === '') ? null : $contenu;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Archive un dérivé. Une valeur vide n'est jamais archivée : elle
     * signifierait « média illisible », et l'archiver reviendrait à graver la
     * panne dans le marbre.
     */
    public static function ecrire(string $cle, string $valeur): void
    {
        if ($valeur === '' || strlen($valeur) > self::TAILLE_MAX) {
            return;
        }

        $fichier = self::fichier($cle);
        if ($fichier === null) {
            return;
        }

        try {
            $dossier = dirname($fichier);
            if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
                return;
            }

            // Écriture atomique : un lecteur concurrent ne voit jamais un
            // fichier à moitié écrit.
            $temp = $fichier . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($temp, $valeur, LOCK_EX) === false) {
                @unlink($temp);
                return;
            }
            if (!@rename($temp, $fichier)) {
                @unlink($temp);
            }
        } catch (Throwable $e) {
            error_log('[MiroirMedia] Archivage impossible : ' . $e->getMessage());
        }
    }

    /** Retire une copie archivée — appelé quand le média est remplacé. */
    public static function oublier(string $cle): void
    {
        $fichier = self::fichier($cle);

        if ($fichier !== null && is_file($fichier)) {
            @unlink($fichier);
        }
    }

    /** Nombre de médias archivés — pour les commandes de diagnostic. */
    public static function compter(): int
    {
        $racine = self::racine();

        return $racine === null ? 0 : count(@glob($racine . '/*/*.media') ?: []);
    }

    // ── Interne ──────────────────────────────────────────────────────────────

    private static function racine(): ?string
    {
        if (self::$resolu) {
            return self::$racine;
        }
        self::$resolu = true;

        $base   = defined('BASE_PATH') ? (string)BASE_PATH : dirname(__DIR__, 3);
        $chemin = $base . '/storage/medias';

        try {
            if (!is_dir($chemin) && !@mkdir($chemin, 0775, true) && !is_dir($chemin)) {
                self::$racine = null;
                return null;
            }

            self::$racine = is_writable($chemin) ? $chemin : null;

            if (self::$racine === null) {
                error_log('[MiroirMedia] Répertoire non inscriptible : ' . $chemin);
            }
        } catch (Throwable $e) {
            error_log('[MiroirMedia] Répertoire indisponible : ' . $e->getMessage());
            self::$racine = null;
        }

        return self::$racine;
    }

    private static function fichier(string $cle): ?string
    {
        $racine = self::racine();
        if ($racine === null || $cle === '') {
            return null;
        }

        // Empreinte : aucune donnée d'appelant n'atteint le système de
        // fichiers, donc aucune traversée de chemin possible.
        $empreinte = sha1($cle);

        return $racine . '/' . substr($empreinte, 0, 2) . '/' . substr($empreinte, 2) . '.media';
    }
}
