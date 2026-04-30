<?php
declare(strict_types=1);

namespace Controllers;

use Core\Response;

class PaiementController
{
    public function initier(): void
    {
        Response::json(['success' => false, 'error' => ['code' => 'NOT_IMPLEMENTED', 'message' => 'Module paiement pas encore activé']], 501);
    }

    public function callback(): void
    {
        http_response_code(200);
        echo 'OK';
    }
}
