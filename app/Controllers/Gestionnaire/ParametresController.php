<?php
declare(strict_types=1);

namespace Controllers\Gestionnaire;

use Core\{Redis, Response, Session};
use Models\Orientation\Etablissement;
use Services\Orientation\{DocumentStorage, JournalGestionnaire, SignatureDataUri};

/**
 * Paramètres de l'espace établissement (périmètre gestionnaire).
 * Le nom, le type, le slug, le statut, le logo et le compte de reversement
 * restent pilotés par le super-admin (voir /admin/orientation).
 */
class ParametresController
{
    private int $etabId;

    public function __construct()
    {
        $this->etabId = (int)Session::get('gestionnaire_etab_id');
    }

    public function index(): void
    {
        $etab = (new Etablissement())->findById($this->etabId);
        if (!$etab) {
            Response::redirect('/gestionnaire');
            return;
        }

        Response::view('gestionnaire/parametres/index', [
            'pageTitle'        => 'Paramètres — ' . $etab['nom'],
            'etab'             => $etab,
            'signatureDataUri' => SignatureDataUri::pour((string)($etab['signature_cle'] ?? '')),
            'errors'     => Session::getFlash('form_errors') ?? [],
            'old'        => Session::getFlash('form_old') ?? [],
            'flash'      => Session::getFlash('success'),
            'flashError' => Session::getFlash('error'),
        ], 'gestionnaire');
    }

    public function update(): void
    {
        $etabModel = new Etablissement();
        $etab      = $etabModel->findById($this->etabId);
        if (!$etab) {
            Response::redirect('/gestionnaire');
            return;
        }

        $errors = [];

        $couleur = trim($_POST['couleur_principale'] ?? '');
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $couleur)) {
            $couleur = $etab['couleur_principale'] ?? '#1F4E79';
        }

        $emailContact = trim($_POST['email_contact'] ?? '');
        if ($emailContact !== '' && !filter_var($emailContact, FILTER_VALIDATE_EMAIL)) {
            $errors['email_contact'] = 'Adresse e-mail invalide.';
        }

        $dateLimite = trim($_POST['date_limite_dossier'] ?? '');
        if ($dateLimite !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateLimite)) {
            $errors['date_limite_dossier'] = 'Date invalide.';
        }

        // ── Signature / cachet officiel : retrait puis upload optionnels ────
        $signatureCle = (string)($etab['signature_cle'] ?? '');

        if (!empty($_POST['supprimer_signature']) && $signatureCle !== '') {
            try { (new DocumentStorage())->delete($signatureCle); } catch (\Throwable) {}
            SignatureDataUri::invalider($signatureCle);
            $signatureCle = '';
        }

        if (!empty($_FILES['signature']['tmp_name']) && is_uploaded_file($_FILES['signature']['tmp_name'])) {
            $uploadErr = (int)($_FILES['signature']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadErr === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo((string)($_FILES['signature']['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                    $errors['signature'] = 'Format non supporté — PNG, JPG ou WEBP attendu.';
                } else {
                    try {
                        $ancienne = $signatureCle;
                        // Extension figée sur l'ext réelle : évite un fichier orphelin si le type change.
                        $nouvelle = (new DocumentStorage())->upload(
                            $_FILES['signature']['tmp_name'],
                            'signatures/' . ($etab['slug'] ?? $this->etabId) . '.' . $ext,
                            (string)($_FILES['signature']['type'] ?? 'image/png')
                        );
                        SignatureDataUri::invalider($ancienne);
                        SignatureDataUri::invalider($nouvelle);
                        if ($ancienne !== '' && $ancienne !== $nouvelle) {
                            try { (new DocumentStorage())->delete($ancienne); } catch (\Throwable) {}
                        }
                        $signatureCle = $nouvelle;
                    } catch (\Throwable $e) {
                        $errors['signature'] = 'Échec de l\'upload de la signature : ' . $e->getMessage();
                    }
                }
            } elseif ($uploadErr !== UPLOAD_ERR_NO_FILE) {
                $errors['signature'] = 'Fichier trop volumineux ou upload interrompu.';
            }
        }

        if (!empty($errors)) {
            Session::flash('form_errors', $errors);
            Session::flash('form_old', $_POST);
            Session::flash('error', 'Certains champs sont invalides — vérifiez le formulaire.');
            Response::redirect('/gestionnaire/parametres');
            return;
        }

        $etabModel->updateParametres($this->etabId, [
            'couleur_principale'    => $couleur,
            'label_campagne'        => trim($_POST['label_campagne'] ?? ''),
            'titre_accroche'        => trim($_POST['titre_accroche'] ?? ''),
            'sous_titre'            => trim($_POST['sous_titre'] ?? ''),
            'date_limite_dossier'   => $dateLimite,
            'description'           => trim($_POST['description'] ?? ''),
            'citation_texte'        => trim($_POST['citation_texte'] ?? ''),
            'citation_auteur'       => trim($_POST['citation_auteur'] ?? ''),
            'citation_fonction'     => trim($_POST['citation_fonction'] ?? ''),
            'adresse'               => trim($_POST['adresse'] ?? ''),
            'email_contact'         => $emailContact,
            'telephone'             => trim($_POST['telephone'] ?? ''),
            'whatsapp'              => trim($_POST['whatsapp'] ?? ''),
            'prix_preinscription'   => max(0, (int)($_POST['prix_preinscription'] ?? 0)),
            'prix_inscription'      => max(0, (int)($_POST['prix_inscription'] ?? 0)),
            'frais_dossier'         => max(0, (int)($_POST['frais_dossier'] ?? 0)),
            'preinscription_active' => isset($_POST['preinscription_active']) ? 1 : 0,
            'inscription_active'    => isset($_POST['inscription_active']) ? 1 : 0,
            'genere_recepisse'      => isset($_POST['genere_recepisse']) ? 1 : 0,
            'genere_attestation'    => isset($_POST['genere_attestation']) ? 1 : 0,
            'signature_cle'         => $signatureCle,
        ]);

        $this->invalidateCaches($etab);

        JournalGestionnaire::log('parametres.modification', 'etablissement', $this->etabId,
            'Paramètres de l\'établissement modifiés');

        Session::flash('success', 'Paramètres enregistrés — le site public est à jour.');
        Response::redirect('/gestionnaire/parametres');
    }

    private function invalidateCaches(array $etab): void
    {
        try {
            $redis = Redis::getInstance();
            if (!empty($etab['slug'])) {
                $redis->del('orientation:etab:' . $etab['slug'], 'orientation:etab:v2:' . $etab['slug']);
            }
            if (!empty($etab['type'])) {
                $redis->del('orientation:marketplace:' . $etab['type']);
            }
            $redis->del('orientation:stats:global');
        } catch (\Throwable) {
            // Redis indisponible : le cache expirera naturellement (TTL courts)
        }
    }
}
