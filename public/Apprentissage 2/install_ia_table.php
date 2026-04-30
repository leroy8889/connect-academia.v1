<?php
/**
 * Script d'installation de la table ia_conversations
 * Connect'Academia - Assistant Connect'Acadrmia
 * 
 * Usage: Ouvrir dans le navigateur: http://localhost/ApprentissageV1%203/install_ia_table.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Table IA - Connect'Academia</title>
    <style>
        body { 
            font-family: 'Inter', Arial, sans-serif; 
            max-width: 900px; 
            margin: 50px auto; 
            padding: 20px; 
            background: #F9FAFB;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 { color: #8B52FA; margin-bottom: 10px; }
        .success { 
            background: #D4EDDA; 
            color: #155724; 
            padding: 15px; 
            border-radius: 8px; 
            margin: 15px 0; 
            border-left: 4px solid #28A745;
        }
        .error { 
            background: #F8D7DA; 
            color: #721C24; 
            padding: 15px; 
            border-radius: 8px; 
            margin: 15px 0; 
            border-left: 4px solid #DC3545;
        }
        .info { 
            background: #D1ECF1; 
            color: #0C5460; 
            padding: 15px; 
            border-radius: 8px; 
            margin: 15px 0; 
            border-left: 4px solid #17A2B8;
        }
        .warning {
            background: #FFF3CD;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #FFC107;
        }
        code { 
            background: #F4F4F4; 
            padding: 3px 8px; 
            border-radius: 4px; 
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        pre {
            background: #F4F4F4;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            border-left: 4px solid #8B52FA;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #8B52FA;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 5px 10px 0;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background: #7540E0;
        }
        .btn-secondary {
            background: #6B7280;
        }
        .btn-secondary:hover {
            background: #4B5563;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }
        table th {
            background: #F9FAFB;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Installation de la table IA Conversations</h1>

<?php
try {
    $pdo = getDB();
    
    // Vérifier la version MySQL
    $stmt = $pdo->query("SELECT VERSION() as version");
    $mysqlVersion = $stmt->fetchColumn();
    echo "<div class='info'><strong>ℹ️ Version MySQL:</strong> $mysqlVersion</div>";
    
    // Vérifier si les tables référencées existent
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersExists = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'ressources'");
    $ressourcesExists = $stmt->rowCount() > 0;
    
    echo "<div class='info'>";
    echo "<strong>📋 Vérification des dépendances:</strong><br>";
    echo "Table <code>users</code>: " . ($usersExists ? "✅ Existe" : "❌ Manquante") . "<br>";
    echo "Table <code>ressources</code>: " . ($ressourcesExists ? "✅ Existe" : "❌ Manquante");
    echo "</div>";
    
    if (!$usersExists || !$ressourcesExists) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ Attention:</strong> Les tables référencées n'existent pas toutes. ";
        echo "Utilisez le fichier <code>database/add_ia_conversations_no_fk.sql</code> pour créer la table sans clés étrangères.";
        echo "</div>";
    }
    
    // Vérifier si la table existe déjà
    $stmt = $pdo->query("SHOW TABLES LIKE 'ia_conversations'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "<div class='info'>";
        echo "<strong>ℹ️ Information:</strong> La table <code>ia_conversations</code> existe déjà dans la base de données.";
        echo "</div>";
        
        // Vérifier la structure
        $stmt = $pdo->query("DESCRIBE ia_conversations");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<div class='info'>";
        echo "<strong>📊 Structure de la table:</strong>";
        echo "<table>";
        echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><code>{$col['Field']}</code></td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
        
        $requiredColumns = ['id', 'user_id', 'document_id', 'document_type', 'user_message', 'ia_response', 'created_at'];
        $existingColumns = array_column($columns, 'Field');
        $missingColumns = array_diff($requiredColumns, $existingColumns);
        
        if (empty($missingColumns)) {
            // Compter les conversations
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM ia_conversations");
            $total = $stmt->fetchColumn();
            
            echo "<div class='success'>";
            echo "<strong>✅ Succès:</strong> La table est correctement configurée avec toutes les colonnes nécessaires.";
            echo "<br><strong>📊 Statistiques:</strong> Il y a actuellement <strong>$total</strong> conversation(s) enregistrée(s).";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<strong>⚠️ Attention:</strong> La table existe mais certaines colonnes manquent: " . implode(', ', $missingColumns);
            echo "</div>";
        }
    } else {
        // Créer la table
        echo "<div class='info'>Création de la table <code>ia_conversations</code>...</div>";
        
        // Désactiver temporairement les vérifications de clés étrangères si les tables n'existent pas
        if (!$usersExists || !$ressourcesExists) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        }
        
        $sql = "CREATE TABLE ia_conversations (
            id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            user_id       INT UNSIGNED     NOT NULL,
            document_id   INT UNSIGNED     NOT NULL,
            document_type ENUM('cours','td','ancienne_epreuve') NOT NULL,
            user_message  TEXT             NOT NULL,
            ia_response   TEXT             NOT NULL,
            created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id    (user_id),
            KEY idx_document   (document_id, document_type),
            KEY idx_created_at (created_at)";
        
        // Ajouter les clés étrangères seulement si les tables existent
        if ($usersExists && $ressourcesExists) {
            $sql .= ",
            FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (document_id) REFERENCES ressources(id) ON DELETE CASCADE";
        }
        
        $sql .= "
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        
        if (!$usersExists || !$ressourcesExists) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
        
        echo "<div class='success'>";
        echo "<strong>✅ Succès!</strong> La table <code>ia_conversations</code> a été créée avec succès.";
        if (!$usersExists || !$ressourcesExists) {
            echo "<br><strong>ℹ️ Note:</strong> Les clés étrangères n'ont pas été ajoutées car les tables référencées n'existent pas encore.";
            echo " Vous pourrez les ajouter plus tard avec des commandes ALTER TABLE.";
        }
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h2>📝 Prochaines étapes</h2>";
    echo "<ol>";
    echo "<li>Configurez votre clé API Gemini dans <code>includes/config.php</code></li>";
    echo "<li>Testez l'assistant en ouvrant un document et en cliquant sur le bouton 'Assistant'</li>";
    echo "</ol>";
    
    echo "<p>";
    echo "<a href='dashboard.php' class='btn'>← Retour au dashboard</a> ";
    echo "<a href='viewer.php?ressource=1' class='btn btn-secondary'>Tester l'assistant</a>";
    echo "</p>";
    
} catch (PDOException $e) {
    $errorCode = $e->getCode();
    $errorMessage = $e->getMessage();
    
    echo "<div class='error'>";
    echo "<strong>❌ Erreur SQL:</strong><br>";
    echo "<code>$errorMessage</code>";
    echo "</div>";
    
    // Suggestions selon le code d'erreur
    if ($errorCode == '42S02') {
        echo "<div class='warning'>";
        echo "<strong>💡 Solution:</strong> Une table référencée n'existe pas. ";
        echo "Utilisez le fichier <code>database/add_ia_conversations_no_fk.sql</code> pour créer la table sans clés étrangères.";
        echo "</div>";
    } elseif ($errorCode == '42S21') {
        echo "<div class='warning'>";
        echo "<strong>💡 Solution:</strong> La table ou une colonne existe déjà. ";
        echo "Vous pouvez ignorer cette erreur ou supprimer la table existante avec: <code>DROP TABLE IF EXISTS ia_conversations;</code>";
        echo "</div>";
    } elseif (strpos($errorMessage, 'FOREIGN KEY') !== false) {
        echo "<div class='warning'>";
        echo "<strong>💡 Solution:</strong> Problème avec les clés étrangères. ";
        echo "Utilisez le fichier <code>database/add_ia_conversations_no_fk.sql</code> pour créer la table sans clés étrangères.";
        echo "</div>";
    }
    
    echo "<pre>Code d'erreur: $errorCode\nMessage: $errorMessage</pre>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Erreur:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

    </div>
</body>
</html>

