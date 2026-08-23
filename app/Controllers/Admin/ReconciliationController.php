<?php
declare(strict_types=1);

namespace Controllers\Admin;

use Core\{Response, Session};
use Models\Orientation\Reversement;
use Services\Orientation\ReversementService;

class ReconciliationController
{
    public function index(): void
    {
        $rev = new Reversement();

        Response::view('admin/orientation/reconciliation', [
            'pageTitle'   => 'Réconciliation reversements',
            'aRejouer'    => $rev->getARejouer(),
            'tous'        => $rev->getAll(200),
            'flash'       => Session::getFlash('success'),
            'flashError'  => Session::getFlash('error'),
        ], 'admin');
    }

    public function rejouer(int $id): void
    {
        $proto  = $_SERVER['HTTP_X_FORWARDED_PROTO']
               ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $appUrl = $host ? rtrim($proto . '://' . $host . BASE_URL, '/') : '';

        $result = ReversementService::rejouer($id, $appUrl);

        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        Response::redirect('/admin/orientation/reconciliation');
    }

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
