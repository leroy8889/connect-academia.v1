<?php
declare(strict_types=1);

namespace Controllers;

use Core\RateLimiter;
use Core\Response;
use Models\ContactMessage;

class ContactController
{
    private ContactMessage $model;

    public function __construct()
    {
        $this->model = new ContactMessage();
    }

    public function index(): void
    {
        Response::view('contact/index', [
            'pageTitle' => "Contact - Connect'Academia",
        ], '');
    }

    /**
     * Longueurs maximales, alignées sur les colonnes de `contact_messages`.
     *
     * MySQL tourne en `STRICT_TRANS_TABLES` : une chaîne trop longue n'est PAS
     * tronquée, elle lève une PDOException « Data too long ». Sans ce contrôle,
     * un visiteur au nom de plus de cent caractères — ou un copier-coller un
     * peu long dans le message — recevait une page 500 au lieu d'un message
     * d'erreur, et son message était perdu.
     *
     * Le compte se fait en CARACTÈRES (`mb_strlen`), comme MySQL : `strlen`
     * compte des octets et refuserait à tort un nom accentué de 60 lettres.
     */
    private const LONGUEURS = [
        'nom'       => 100,
        'prenom'    => 100,
        'email'     => 255,
        'telephone' => 30,
        'message'   => 5000,
    ];

    /** Envois autorisés depuis une même adresse IP, et sur quelle fenêtre. */
    private const ENVOIS_MAX     = 5;
    private const FENETRE_SECONDES = 600;

    public function submit(): void
    {
        // Formulaire public, sans authentification ni jeton CSRF : la seule
        // barrière contre l'inondation de la table est la limite de débit.
        $cle = RateLimiter::ipKey('contact');
        if (RateLimiter::tooManyAttempts($cle, self::ENVOIS_MAX)) {
            $minutes = (int)ceil(RateLimiter::availableIn($cle) / 60);
            Response::json([
                'success' => false,
                'errors'  => ['Trop de messages envoyés. Réessayez dans '
                              . max(1, $minutes) . ' minute' . ($minutes > 1 ? 's' : '') . '.'],
            ], 429);
        }

        $payload = $this->jsonOrPost();

        $nom       = trim($payload['nom']       ?? '');
        $prenom    = trim($payload['prenom']    ?? '');
        $email     = trim($payload['email']     ?? '');
        $telephone = trim($payload['telephone'] ?? '');
        $message   = trim($payload['message']   ?? '');

        $errors = [];
        if ($nom === '')                          $errors[] = 'Le nom est requis.';
        if ($prenom === '')                        $errors[] = 'Le prénom est requis.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse email invalide.';
        if ($telephone === '')                     $errors[] = 'Le téléphone est requis.';
        if (mb_strlen($message) < 10)             $errors[] = 'Le message doit contenir au moins 10 caractères.';

        $aBorner = [
            'Le nom'             => [$nom,       self::LONGUEURS['nom']],
            'Le prénom'          => [$prenom,    self::LONGUEURS['prenom']],
            'L’adresse email'    => [$email,     self::LONGUEURS['email']],
            'Le téléphone'       => [$telephone, self::LONGUEURS['telephone']],
            'Le message'         => [$message,   self::LONGUEURS['message']],
        ];
        foreach ($aBorner as $libelle => [$valeur, $max]) {
            if (mb_strlen($valeur) > $max) {
                $errors[] = $libelle . ' ne doit pas dépasser ' . $max . ' caractères.';
            }
        }

        if ($errors) {
            // Une saisie refusée compte quand même : sans cela, un robot
            // envoyant des données invalides passerait sous la limite.
            RateLimiter::hit($cle, self::FENETRE_SECONDES);
            Response::json(['success' => false, 'errors' => $errors], 422);
        }

        RateLimiter::hit($cle, self::FENETRE_SECONDES);

        // REMOTE_ADDR et non X-Forwarded-For : cet en-tête est fourni par le
        // client et se forge à volonté — l'adresse enregistrée en base n'aurait
        // alors plus aucune valeur de preuve. C'est aussi la source que
        // retiennent SecurityMonitor, RateLimiter et l'anti-force-brute des
        // connexions : nginx sert PHP-FPM sans relais intermédiaire.
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $this->model->create([
            'nom'        => $nom,
            'prenom'     => $prenom,
            'email'      => $email,
            'telephone'  => $telephone,
            'message'    => $message,
            'ip_address' => $ip ? substr($ip, 0, 45) : null,
        ]);

        Response::json([
            'success' => true,
            'message' => 'Votre message a bien été envoyé. Nous vous répondrons très prochainement.',
        ]);
    }

    private function jsonOrPost(): array
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) {
            return (array) (json_decode(file_get_contents('php://input'), true) ?? []);
        }
        return $_POST;
    }
}
