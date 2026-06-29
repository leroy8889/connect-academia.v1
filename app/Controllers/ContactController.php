<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;
use Models\ContactMessage;

class ContactController
{
    private ContactMessage $model;

    public function __construct()
    {
        $this->model = new ContactMessage();
    }

    public function submit(): void
    {
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
        if (strlen($message) < 10)                $errors[] = 'Le message doit contenir au moins 10 caractères.';

        if ($errors) {
            Response::json(['success' => false, 'errors' => $errors], 422);
        }

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

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
