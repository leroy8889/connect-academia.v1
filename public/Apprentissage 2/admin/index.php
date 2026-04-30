<?php
/**
 * Connect'Academia - Page d'accueil Admin (Redirection)
 */
session_start();

// Si l'admin est déjà connecté, rediriger vers le dashboard admin
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Sinon, rediriger vers la page de connexion admin
header('Location: login.php');
exit;

