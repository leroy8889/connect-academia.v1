<?php
/**
 * Script de vérification de la base de données
 * Vérifie que toutes les tables et colonnes sont correctement créées
 */

// Charger les variables d'environnement
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if ($env !== false) {
        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
        }
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbname = $_ENV['DB_NAME'] ?? 'studylink_db';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? 'root';

try {
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "✓ Connexion à MySQL réussie\n\n";

    // Vérifier si la base de données existe
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$dbname}'");
    if ($stmt->rowCount() === 0) {
        echo "✗ La base de données '{$dbname}' n'existe pas.\n";
        echo "  Exécutez le script schema.sql pour la créer.\n\n";
        exit(1);
    }
    echo "✓ La base de données '{$dbname}' existe\n\n";

    // Se connecter à la base de données
    $pdo->exec("USE {$dbname}");

    // Vérifier si la table users existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() === 0) {
        echo "✗ La table 'users' n'existe pas.\n";
        echo "  Exécutez le script schema.sql pour la créer.\n\n";
        exit(1);
    }
    echo "✓ La table 'users' existe\n\n";

    // Vérifier les colonnes de la table users
    $requiredColumns = [
        'id', 'uuid', 'nom', 'prenom', 'email', 'password_hash',
        'role', 'is_verified', 'is_active', 'is_deleted',
        'email_token', 'created_at', 'updated_at'
    ];

    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Vérification des colonnes requises :\n";
    $missingColumns = [];
    foreach ($requiredColumns as $col) {
        if (in_array($col, $columns, true)) {
            echo "  ✓ {$col}\n";
        } else {
            echo "  ✗ {$col} - MANQUANTE\n";
            $missingColumns[] = $col;
        }
    }

    if (!empty($missingColumns)) {
        echo "\n✗ Certaines colonnes sont manquantes.\n";
        echo "  Exécutez le script schema.sql pour corriger.\n\n";
        exit(1);
    }

    echo "\n✓ Toutes les colonnes requises sont présentes\n\n";

    // Vérifier les contraintes
    echo "Vérification des contraintes :\n";
    $stmt = $pdo->query("SHOW INDEXES FROM users WHERE Key_name = 'uk_users_email'");
    if ($stmt->rowCount() > 0) {
        echo "  ✓ Contrainte UNIQUE sur 'email'\n";
    } else {
        echo "  ✗ Contrainte UNIQUE sur 'email' - MANQUANTE\n";
    }

    $stmt = $pdo->query("SHOW INDEXES FROM users WHERE Key_name = 'uk_users_uuid'");
    if ($stmt->rowCount() > 0) {
        echo "  ✓ Contrainte UNIQUE sur 'uuid'\n";
    } else {
        echo "  ✗ Contrainte UNIQUE sur 'uuid' - MANQUANTE\n";
    }

    echo "\n✓ Vérification terminée avec succès !\n";
    echo "La base de données est correctement configurée.\n\n";

} catch (PDOException $e) {
    echo "✗ Erreur de connexion à la base de données :\n";
    echo "  " . $e->getMessage() . "\n\n";
    echo "Vérifiez vos paramètres de connexion dans le fichier .env\n";
    exit(1);
}

