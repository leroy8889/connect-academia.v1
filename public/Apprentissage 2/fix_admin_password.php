<?php
/**
 * Script temporaire pour corriger le mot de passe admin
 * À supprimer après utilisation
 */

require_once __DIR__ . '/includes/config.php';

$password = 'Admin@2024';
$email = 'admin@connectacademia.ga';

// Générer le hash bcrypt correct
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

echo "=== Correction du mot de passe admin ===\n\n";
echo "Email: $email\n";
echo "Mot de passe: $password\n";
echo "Nouveau hash: $hash\n\n";

try {
    // Essayer différentes configurations de connexion pour MAMP
    $host = DB_HOST;
    $dbname = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
    
    // Pour MAMP, essayer avec le socket Unix si localhost ne fonctionne pas
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    // Si c'est localhost et que ça ne fonctionne pas, essayer avec le socket MAMP
    if ($host === 'localhost' || $host === '127.0.0.1') {
        $socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
        if (file_exists($socket)) {
            $dsn = "mysql:unix_socket=$socket;dbname=$dbname;charset=utf8mb4";
            echo "Utilisation du socket Unix MAMP: $socket\n\n";
        }
    }
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    
    // Vérifier si l'admin existe
    $stmt = $pdo->prepare("SELECT id, email, password FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "Admin trouvé (ID: {$admin['id']})\n";
        echo "Ancien hash: {$admin['password']}\n\n";
        
        // Vérifier si le hash actuel fonctionne
        if (password_verify($password, $admin['password'])) {
            echo "✓ Le hash actuel fonctionne déjà !\n";
        } else {
            echo "✗ Le hash actuel ne fonctionne pas. Mise à jour...\n";
            
            // Mettre à jour le mot de passe
            $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE email = ?");
            $stmt->execute([$hash, $email]);
            
            echo "✓ Mot de passe mis à jour avec succès !\n";
            
            // Vérifier que ça fonctionne maintenant
            $stmt = $pdo->prepare("SELECT password FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            $updated = $stmt->fetch();
            
            if (password_verify($password, $updated['password'])) {
                echo "✓ Vérification : Le nouveau hash fonctionne correctement !\n";
            } else {
                echo "✗ ERREUR : Le nouveau hash ne fonctionne pas !\n";
            }
        }
    } else {
        echo "Admin non trouvé. Création...\n";
        
        // Créer l'admin
        $stmt = $pdo->prepare("
            INSERT INTO admins (nom, prenom, email, password, role) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute(['Admin', 'Connect', $email, $hash, 'super_admin']);
        
        echo "✓ Admin créé avec succès !\n";
    }
    
    echo "\n=== Résumé ===\n";
    echo "Vous pouvez maintenant vous connecter avec :\n";
    echo "Email: $email\n";
    echo "Mot de passe: $password\n";
    echo "\nNouveau hash pour seed.sql: $hash\n";
    
} catch (PDOException $e) {
    echo "ERREUR de connexion: " . $e->getMessage() . "\n\n";
    echo "Solutions possibles :\n";
    echo "1. Vérifiez que MAMP est démarré\n";
    echo "2. Vérifiez les paramètres dans includes/config.php\n";
    echo "3. Utilisez le script SQL directement :\n";
    echo "   mysql -u root -p connect_academia < database/update_admin_password.sql\n\n";
    echo "Le hash correct a été généré et est disponible dans :\n";
    echo "- database/seed.sql (pour les nouvelles installations)\n";
    echo "- database/update_admin_password.sql (pour les bases existantes)\n";
    exit(1);
}

