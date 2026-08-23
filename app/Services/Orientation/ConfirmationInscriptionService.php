<?php
declare(strict_types=1);

namespace Services\Orientation;

use Core\Queue;
use Models\Orientation\{Dossier, Etablissement, NotificationOrientation};

/**
 * Confirmation d'une inscription après paiement réussi — chemin unique.
 *
 * Appelé par le webhook MoneyFusion ET par le retour navigateur (les deux
 * peuvent arriver dans n'importe quel ordre) : idempotent via
 * inscriptions.dossier_id unique + statut paiement.
 *
 * Enchaîne : dossier "paye" → ligne inscriptions → paiement "reussi" →
 * reçu PDF → e-mails/notifications candidat et gestionnaires → audit →
 * reversement automatique vers le compte mobile money de l'établissement.
 */
class ConfirmationInscriptionService
{
    private const FILIERES = [
        'socle_l1_l2'   => 'Socle L1-L2',
        'licence_l3'    => 'Licence L3',
        'master_m1_m2'  => 'Master M1-M2',
        'certification' => 'Certification',
    ];

    /**
     * @return bool true si l'inscription est confirmée (y compris si elle
     *              l'était déjà — idempotence), false si paiement introuvable.
     */
    public static function confirmer(int $paiementId, ?string $mfToken = null): bool
    {
        $dossierModel = new Dossier();

        $paiement = $dossierModel->findPaiementById($paiementId);
        if (!$paiement) {
            error_log("[ConfirmationInscription] Paiement introuvable — id:{$paiementId}");
            return false;
        }

        $dossierId = (int)$paiement['dossier_id'];
        $etabId    = (int)$paiement['etablissement_id'];
        $montant   = (int)$paiement['montant'];

        if ($mfToken !== null && $mfToken !== '' && empty($paiement['token_pay'])) {
            $dossierModel->updatePaiementToken($paiementId, $mfToken);
        }

        // Idempotence : déjà confirmé (par le webhook ou par le retour)
        if ($paiement['statut'] === 'reussi' || $dossierModel->hasInscription($dossierId)) {
            return true;
        }

        $dossier = $dossierModel->findById($dossierId);
        $etab    = (new Etablissement())->findById($etabId);
        if (!$dossier || !$etab) {
            error_log("[ConfirmationInscription] Dossier/établissement introuvable — dossier:{$dossierId} etab:{$etabId}");
            return false;
        }

        $dossierModel->updateStatut($dossierId, 'paye');
        $dossierModel->createInscription($dossierId, $etabId);
        $dossierModel->updatePaiementStatut($paiementId, 'reussi');

        // Reçu PDF — déchargé sur la file (dompdf + upload B2 hors du cycle requête).
        Queue::push('generate_recu', [
            'dossierId'  => $dossierId,
            'paiementId' => $paiementId,
            'etabId'     => $etabId,
            'montant'    => $montant,
        ]);

        // Notifications (non bloquantes)
        try {
            self::notifierCandidat($dossier, $etab, $etabId, $montant);
        } catch (\Throwable $e) {
            error_log('[ConfirmationInscription] Notification candidat échouée : ' . $e->getMessage());
        }
        try {
            self::notifierGestionnaires($dossier, $etab, $dossierId, $montant);
        } catch (\Throwable $e) {
            error_log('[ConfirmationInscription] Notification gestionnaires échouée : ' . $e->getMessage());
        }

        AuditService::log(
            'inscription.confirmed',
            'dossier',
            $dossierId,
            null,
            ['paiement_id' => $paiementId, 'montant' => $montant, 'etablissement_id' => $etabId]
        );

        // Reversement automatique vers l'établissement (payin → payout).
        // Base de calcul = montant réellement encaissé côté MoneyFusion
        // (totalPrice envoyé à l'initiation = montant / FEE_RATE, les frais MF
        // étant à la charge du candidat) — jamais le montant affiché, sinon la
        // plateforme reverserait plus qu'elle ne reçoit.
        if ($montant > 0) {
            try {
                $encaisse = \Core\MoneyFusion::calculateAmountToSend((float)$montant);
                ReversementService::declencher($paiementId, $etabId, $encaisse, self::appUrl());
            } catch (\Throwable $e) {
                error_log('[ConfirmationInscription] Déclenchement reversement échoué : ' . $e->getMessage());
            }
        }

        error_log("[ConfirmationInscription] Confirmée — dossier:{$dossierId} paiement:{$paiementId} montant:{$montant}");
        return true;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function appUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '') {
            $proto = $_SERVER['HTTP_X_FORWARDED_PROTO']
                  ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
            $base  = defined('BASE_URL') ? BASE_URL : '';
            return rtrim($proto . '://' . $host . $base, '/');
        }
        return rtrim($_ENV['APP_URL'] ?? '', '/');
    }

    private static function filiereLibelle(array $dossier): string
    {
        $cle = (string)($dossier['filiere_visee'] ?? '');
        $lbl = self::FILIERES[$cle] ?? ($cle !== '' ? $cle : '—');
        $spec = trim((string)($dossier['specialite_visee'] ?? ''));
        return $spec !== '' && $lbl !== '—' ? $lbl . ' — ' . $spec : $lbl;
    }

    private static function notifierCandidat(array $dossier, array $etab, int $etabId, int $montant): void
    {
        $email      = (string)($dossier['email'] ?? '');
        $nomComplet = (string)($dossier['nom_complet'] ?? 'Candidat');
        $etabNom    = (string)($etab['nom'] ?? '—');
        $couleur    = (string)($etab['couleur_principale'] ?? '#8B52FA');
        $filiere    = self::filiereLibelle($dossier);
        $niveau     = (string)($dossier['niveau_entree'] ?? '—');
        $montantFmt = $montant > 0 ? number_format($montant, 0, ',', ' ') . ' XAF' : 'Gratuit';
        $date       = date('d/m/Y à H:i');

        if ($email !== '') {
            $hc = htmlspecialchars($couleur, ENT_QUOTES, 'UTF-8');
            $hn = htmlspecialchars($etabNom, ENT_QUOTES, 'UTF-8');

            $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="font-family:-apple-system,Helvetica,Arial,sans-serif;background:#f4f4f9;margin:0;padding:32px 16px">'
                . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.08)">'
                . '<div style="background:' . $hc . ';padding:32px 36px">'
                . '<div style="width:48px;height:48px;background:rgba(255,255,255,.25);border-radius:10px;text-align:center;font-size:20px;font-weight:800;color:#fff;line-height:48px;margin-bottom:16px">'
                . mb_strtoupper(mb_substr($etabNom, 0, 1))
                . '</div>'
                . '<h1 style="color:#fff;font-size:20px;font-weight:800;margin:0">Paiement confirmé ✓</h1>'
                . '<p style="color:rgba(255,255,255,.85);font-size:14px;margin:8px 0 0">' . $hn . '</p>'
                . '</div>'
                . '<div style="padding:32px 36px">'
                . '<p style="font-size:15px;color:#333;margin-bottom:24px">Bonjour <strong>' . htmlspecialchars($nomComplet, ENT_QUOTES, 'UTF-8') . '</strong>,<br><br>'
                . 'Votre paiement a bien été reçu par <strong>' . $hn . '</strong>. Votre dossier d\'inscription complet est maintenant entre les mains de l\'établissement, qui le validera et délivrera votre attestation d\'inscription.</p>'
                . '<table style="width:100%;border-collapse:collapse;margin-bottom:24px">'
                . '<tr style="background:#f8f7ff"><td style="padding:10px 14px;font-size:13px;color:#888;width:140px">Formation</td><td style="padding:10px 14px;font-size:14px;font-weight:600;color:#1a1228">' . htmlspecialchars($filiere . ($niveau !== '—' ? ' — ' . $niveau : ''), ENT_QUOTES, 'UTF-8') . '</td></tr>'
                . '<tr><td style="padding:10px 14px;font-size:13px;color:#888">Montant réglé</td><td style="padding:10px 14px;font-size:14px;font-weight:600;color:' . $hc . '">' . $montantFmt . '</td></tr>'
                . '<tr style="background:#f8f7ff"><td style="padding:10px 14px;font-size:13px;color:#888">Date</td><td style="padding:10px 14px;font-size:14px;color:#333">' . $date . '</td></tr>'
                . '</table>'
                . '<p style="font-size:13px;color:#666">Votre reçu de paiement (PDF) est disponible dans votre espace élève, rubrique <strong>Reçus</strong> :</p>'
                . '<p style="margin:18px 0"><a href="https://connectacademia.io/eleve/orientation/recus" style="background:' . $hc . ';color:#fff;text-decoration:none;padding:11px 22px;border-radius:10px;font-size:14px;font-weight:700;display:inline-block">Voir mon reçu</a></p>'
                . '<p style="font-size:12px;color:#aaa;margin-top:24px">Connect\'Academia · <a href="https://connectacademia.io" style="color:#aaa">connectacademia.io</a></p>'
                . '</div></div></body></html>';

            Queue::push('send_email', [
                'to'      => $email,
                'toName'  => $nomComplet,
                'subject' => "Paiement confirmé — {$etabNom}",
                'html'    => $html,
            ]);
        }

        $eleveId = (int)($dossier['eleve_id'] ?? 0);
        if ($eleveId > 0) {
            (new NotificationOrientation())->create([
                'user_id'          => $eleveId,
                'etablissement_id' => $etabId,
                'type'             => 'inscription_confirmee',
                'titre'            => "Paiement confirmé — {$etabNom}",
                'message'          => "Votre paiement de {$montantFmt} a bien été reçu. Votre dossier ({$filiere}) est en attente de validation par l'établissement.",
            ]);
        }
    }

    private static function notifierGestionnaires(array $dossier, array $etab, int $dossierId, int $montant): void
    {
        $etabId     = (int)$etab['id'];
        $etabNom    = (string)($etab['nom'] ?? '');
        $couleurH   = htmlspecialchars((string)($etab['couleur_principale'] ?? '#8B52FA'), ENT_QUOTES, 'UTF-8');
        $etabNomH   = htmlspecialchars($etabNom, ENT_QUOTES, 'UTF-8');
        $nomH       = htmlspecialchars(trim((string)($dossier['nom_complet'] ?? '')), ENT_QUOTES, 'UTF-8');
        $emailH     = htmlspecialchars(trim((string)($dossier['email'] ?? '')), ENT_QUOTES, 'UTF-8');
        $filiereH   = htmlspecialchars(self::filiereLibelle($dossier), ENT_QUOTES, 'UTF-8');
        $montantFmt = number_format($montant, 0, ',', ' ') . ' XAF';
        $dateH      = date('d/m/Y à H:i');

        $gestionnaires = (new Etablissement())->findGestionnairesEmail($etabId);
        $notif         = new NotificationOrientation();

        foreach ($gestionnaires as $g) {
            $gPrenomH = htmlspecialchars((string)($g['prenom'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html = <<<HTML
            <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
            <body style="font-family:Montserrat,sans-serif;background:#F5F5F7;padding:40px 0;color:#1A1A1A;">
              <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                <div style="background:{$couleurH};padding:28px 36px;">
                  <p style="color:#fff;font-size:18px;font-weight:700;margin:0;">Inscription payée — dossier à valider</p>
                  <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:4px 0 0;">{$etabNomH}</p>
                </div>
                <div style="padding:28px 36px;">
                  <p style="font-size:14px;">Bonjour <strong>{$gPrenomH}</strong>,</p>
                  <p style="font-size:14px;color:#4A4A4A;line-height:1.6;">
                    Un dossier d'inscription complet vient d'être déposé <strong>avec paiement confirmé</strong>.
                    Vous pouvez le valider pour délivrer l'attestation d'inscription.
                  </p>
                  <div style="background:#F9F9FB;border-radius:12px;padding:16px 20px;margin:16px 0;border:1px solid #E5E5E5;">
                    <p style="margin:0 0 6px;font-size:12px;color:#9E9E9E;">Candidat</p>
                    <p style="margin:0 0 12px;font-size:14px;font-weight:600;">{$nomH}</p>
                    <p style="margin:0 0 6px;font-size:12px;color:#9E9E9E;">Email</p>
                    <p style="margin:0 0 12px;font-size:14px;">{$emailH}</p>
                    <p style="margin:0 0 6px;font-size:12px;color:#9E9E9E;">Filière</p>
                    <p style="margin:0 0 12px;font-size:14px;font-weight:600;">{$filiereH}</p>
                    <p style="margin:0 0 6px;font-size:12px;color:#9E9E9E;">Montant payé</p>
                    <p style="margin:0;font-size:14px;font-weight:700;color:#15803d;">{$montantFmt}</p>
                  </div>
                  <p style="font-size:12px;color:#9E9E9E;">Payé le {$dateH} — Dossier #{$dossierId}</p>
                </div>
              </div>
            </body></html>
            HTML;

            Queue::push('send_email', [
                'to'      => (string)$g['email'],
                'toName'  => trim(($g['prenom'] ?? '') . ' ' . ($g['nom'] ?? '')),
                'subject' => "Inscription payée à valider — {$etabNom}",
                'html'    => $html,
            ]);

            $notif->create([
                'user_id'          => (int)$g['id'],
                'etablissement_id' => $etabId,
                'type'             => 'inscription_recue',
                'titre'            => "Inscription payée — {$etabNom}",
                'message'          => "Dossier #{$dossierId} — {$nomH} — {$filiereH} — {$montantFmt} reçus.",
            ]);
        }
    }
}
