<?php
/**
 * Configuration de Connect'Academia
 */

// Base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'connect_academia');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_CHARSET', 'utf8mb4');

// Upload
define('UPLOAD_PATH', __DIR__ . '/../uploads/ressources/');
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024); // 50 Mo
define('ALLOWED_MIME', 'application/pdf');

// Session
define('SESSION_TIMEOUT', 7200); // 2 heures

// Application
define('BASE_URL', '');
define('ALLOW_PDF_DOWNLOAD', true);

// Timezone
date_default_timezone_set('Africa/Libreville');

// Gemini AI
define('GEMINI_API_KEY', 'AIzaSyCoJAh_Okx6had13hcUewafRaOEuHOOf4U'); // À configurer par l'administrateur
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');
define('GEMINI_MAX_TOKENS', 1024);
define('GEMINI_TEMPERATURE', 0.7);
define('GEMINI_RATE_LIMIT', 10); // Requêtes par minute
define('GEMINI_MAX_DOCUMENT_LENGTH', 15000); // Caractères max pour le contenu du document

