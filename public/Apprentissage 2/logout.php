<?php
/**
 * Connect'Academia - Déconnexion
 */
session_start();
session_destroy();
header('Location: login.php');
exit;

