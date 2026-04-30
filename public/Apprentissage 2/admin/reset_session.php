<?php
/**
 * Script pour réinitialiser la session et débloquer l'accès
 * À supprimer après utilisation si nécessaire
 */
session_start();
session_destroy();
session_start();

// Réinitialiser toutes les variables de session liées aux tentatives
unset($_SESSION['login_attempts']);

echo "✓ Session réinitialisée avec succès !<br>";
echo "Vous pouvez maintenant vous connecter librement.<br><br>";
echo '<a href="login.php">← Retour à la page de connexion</a>';

