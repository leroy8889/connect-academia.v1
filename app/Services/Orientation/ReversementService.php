<?php
declare(strict_types=1);

namespace Services\Orientation;

use Models\Orientation\Reversement;

class ReversementService
{
    private const MAX_TENTATIVES = 5;

    public static function declencher(
        int    $paiementId,
        int    $etabId,
        int    $montantBrut,
        string $appUrl
    ): void {
        $rev = new Reversement();

        // Idempotence garantie par UNIQUE(paiement_id) + vérification préalable
        if ($rev->exists($paiementId)) {
            error_log("[Reversement] Déjà initié pour paiement:{$paiementId}");
            return;
        }

        $compte = $rev->getCompteEtab($etabId);

        $taux       = (float)($compte['taux_commission'] ?? $_ENV['COMMISSION_PLATEFORME_PERCENT'] ?? 5);
        $commission = (int)round($montantBrut * $taux / 100);
        $montantRev = $montantBrut - $commission;

        // Compte mobile money absent ou non activé : on trace quand même le
        // reversement en "a_rejouer" — visible en réconciliation admin, il
        // pourra être relancé une fois le compte activé (aucun fonds perdu).
        if (!$compte || $compte['statut'] !== 'actif') {
            $rev->create([
                'paiement_id'          => $paiementId,
                'etablissement_id'     => $etabId,
                'montant_brut'         => $montantBrut,
                'commission_plateforme'=> $commission,
                'montant_reverse'      => $montantRev,
                'numero_destination'   => $compte['numero_mobile_money'] ?? null,
                'withdraw_mode'        => $compte['withdraw_mode'] ?? null,
                'statut'               => 'a_rejouer',
            ]);
            error_log("[Reversement] Compte inactif/absent pour etab:{$etabId} — reversement mis en a_rejouer");
            return;
        }

        $revId = $rev->create([
            'paiement_id'          => $paiementId,
            'etablissement_id'     => $etabId,
            'montant_brut'         => $montantBrut,
            'commission_plateforme'=> $commission,
            'montant_reverse'      => $montantRev,
            'numero_destination'   => $compte['numero_mobile_money'],
            'withdraw_mode'        => $compte['withdraw_mode'],
            'statut'               => 'initie',
        ]);

        self::appelPayoutMF($rev, $revId, $compte, $montantRev, $appUrl);
    }

    public static function rejouer(int $reversementId, string $appUrl): array
    {
        $rev = new Reversement();
        $reversement = $rev->findById($reversementId);

        if (!$reversement) {
            return ['success' => false, 'message' => 'Reversement introuvable.'];
        }
        if ($reversement['statut'] === 'reussi') {
            return ['success' => false, 'message' => 'Reversement déjà effectué.'];
        }
        if ((int)$reversement['tentatives'] >= self::MAX_TENTATIVES) {
            return ['success' => false, 'message' => 'Maximum ' . self::MAX_TENTATIVES . ' tentatives atteint.'];
        }

        $compte = $rev->getCompteEtab((int)$reversement['etablissement_id']);
        if (!$compte || $compte['statut'] !== 'actif') {
            return ['success' => false, 'message' => 'Compte mobile money non actif.'];
        }

        $rev->incrementTentatives($reversementId);

        $ok = self::appelPayoutMF(
            $rev,
            $reversementId,
            $compte,
            (int)$reversement['montant_reverse'],
            $appUrl
        );

        return [
            'success' => $ok,
            'message' => $ok ? 'Payout relancé.' : 'API payout non configurée — reversement remis en file.',
        ];
    }

    private static function appelPayoutMF(
        Reversement $rev,
        int         $revId,
        array       $compte,
        int         $montant,
        string      $appUrl
    ): bool {
        if (($_ENV['MONEY_FUSION_PRIVATE_API_KEY'] ?? '') === '') {
            $rev->updateStatut(
                $revId, 'a_rejouer', null,
                'MONEY_FUSION_PRIVATE_API_KEY manquante dans .env'
            );
            error_log("[Reversement] Payout non configuré — rev:{$revId} mis en a_rejouer");
            return false;
        }

        // L'API withdraw attend le numéro au format national (countryCode à part)
        $numero = \Core\Phone::normalizeGabon((string)$compte['numero_mobile_money'])
               ?? preg_replace('/\D+/', '', (string)$compte['numero_mobile_money']);

        $result = \Core\MoneyFusion::payout(
            (string)($compte['pays_code'] ?? 'ga'),
            (string)$numero,
            $montant,
            (string)$compte['withdraw_mode'],
            $appUrl !== '' ? $appUrl . '/webhook/orientation/payout' : ''
        );

        $token = $result['tokenPay'] ?? null;

        if (!($result['statut'] ?? false) || !$token) {
            $rev->updateStatut(
                $revId, 'a_rejouer', null,
                'Payout MF refusé: ' . substr((string)($result['message'] ?? 'réponse invalide'), 0, 180)
            );
            error_log("[Reversement] Payout refusé — rev:{$revId} message:" . ($result['message'] ?? '?'));
            return false;
        }

        $rev->updateStatut($revId, 'en_cours', $token, null);
        error_log("[Reversement] Payout initié — rev:{$revId} tokenPay:" . \Core\MoneyFusion::maskToken($token));
        return true;
    }
}
