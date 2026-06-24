<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Correction de la colonne 'libelle' dans comptes_comptables</h2>";

// Utiliser la classe Database existante
if (file_exists(__DIR__ . '/database.php')) {
    require_once __DIR__ . '/database.php';
} elseif (file_exists(__DIR__ . '/database_production.php')) {
    require_once __DIR__ . '/database_production.php';
} else {
    die("❌ Fichier de configuration de base de données introuvable");
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        die("❌ Impossible de se connecter à la base de données");
    }
    
    echo "<p>✅ Connexion à la base de données : OK</p>";
    echo "<hr>";
    
    // Vérifier la structure actuelle
    echo "<h3>📋 Structure ACTUELLE de comptes_comptables :</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Colonne</th><th>Type</th></tr>";
    
    $columns = $pdo->query("SHOW COLUMNS FROM comptes_comptables");
    $has_libelle = false;
    $has_nom_compte = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] == 'libelle') {
            $has_libelle = true;
            echo "<tr style='background-color: #90EE90;'>";
        } elseif ($col['Field'] == 'nom_compte') {
            $has_nom_compte = true;
            echo "<tr style='background-color: #FFD700;'>";
        } else {
            echo "<tr>";
        }
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    
    // Correction
    if ($has_libelle) {
        echo "<p style='color: green; font-weight: bold;'>✅ La colonne <strong>libelle</strong> existe déjà !</p>";
    } elseif ($has_nom_compte) {
        echo "<p>⚠️ La colonne s'appelle <strong>nom_compte</strong> mais le code cherche <strong>libelle</strong></p>";
        echo "<p>🔧 Renommage de <strong>nom_compte</strong> en <strong>libelle</strong>...</p>";
        
        $pdo->exec("
            ALTER TABLE comptes_comptables 
            CHANGE COLUMN nom_compte libelle VARCHAR(200) NOT NULL
        ");
        
        echo "<p style='color: green; font-weight: bold;'>✅ Colonne renommée avec succès !</p>";
    } else {
        echo "<p style='color: red;'>❌ Ni 'libelle' ni 'nom_compte' n'existe. Ajout de la colonne libelle...</p>";
        
        $pdo->exec("
            ALTER TABLE comptes_comptables 
            ADD COLUMN libelle VARCHAR(200) NOT NULL AFTER numero_compte
        ");
        
        echo "<p style='color: green; font-weight: bold;'>✅ Colonne <strong>libelle</strong> ajoutée avec succès !</p>";
    }
    
    // Vérification finale
    echo "<hr>";
    echo "<h3>📋 Structure FINALE de comptes_comptables :</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Colonne</th><th>Type</th></tr>";
    
    $columns_final = $pdo->query("SHOW COLUMNS FROM comptes_comptables");
    foreach ($columns_final as $col) {
        if ($col['Field'] == 'libelle') {
            echo "<tr style='background-color: #90EE90;'>";
        } else {
            echo "<tr>";
        }
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3 style='color: green;'>✅ TERMINÉ !</h3>";
    echo "<p><strong>La table comptes_comptables a maintenant la colonne 'libelle'</strong></p>";
    echo "<p><a href='../finances/' style='font-size: 18px; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>← Retour au module Finances</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur SQL : " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>
