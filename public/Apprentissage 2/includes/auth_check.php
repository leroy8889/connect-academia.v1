<?php
/**
 * Middleware d'authentification pour les pages élève
 */
require_once __DIR__ . '/config.php';
session_start();

// Timeout de session
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI']);
    header('Location: login.php?redirect=' . $redirect);
    exit;
}

