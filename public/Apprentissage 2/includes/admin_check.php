<?php
/**
 * Middleware d'authentification pour les pages admin
 */
require_once __DIR__ . '/config.php';
session_start();

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

