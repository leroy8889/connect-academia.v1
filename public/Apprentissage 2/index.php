<?php
/**
 * Connect'Academia - Page d'accueil (Redirection)
 */
session_start();

// Si l'utilisateur est déjà connecté, rediriger vers le dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Sinon, rediriger vers la page de connexion
header('Location: login.php');
exit;

