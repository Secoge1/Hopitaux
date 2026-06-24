<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Correction des tables Finances - Ajout colonne 'statut'</h2>";

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
    
    // ====================================================================
    // 1. Corriger comptes_comptables
    // ====================================================================
    echo "<h3>1️⃣ Table: comptes_comptables</h3>";
    
    $check1 = $pdo->query("SHOW COLUMNS FROM comptes_comptables LIKE 'statut'");
    if ($check1->rowCount() > 0) {
        echo "<p>✅ La colonne <strong>statut</strong> existe déjà</p>";
    } else {
        echo "<p>⚠️ Ajout de la colonne <strong>statut</strong>...</p>";
        
        $pdo->exec("
            ALTER TABLE comptes_comptables 
            ADD COLUMN statut ENUM('actif','inactif') 
            DEFAULT 'actif' 
            AFTER solde_actuel
        ");
        
        echo "<p style='color: green; font-weight: bold;'>✅ Colonne <strong>statut</strong> ajoutée avec succès !</p>";
    }
    
    // ====================================================================
    // 2. Corriger ecritures_comptables
    // ====================================================================
    echo "<hr>";
    echo "<h3>2️⃣ Table: ecritures_comptables</h3>";
    
    $check2 = $pdo->query("SHOW COLUMNS FROM ecritures_comptables LIKE 'statut'");
    if ($check2->rowCount() > 0) {
        echo "<p>✅ La colonne <strong>statut</strong> existe déjà</p>";
    } else {
        echo "<p>⚠️ Ajout de la colonne <strong>statut</strong>...</p>";
        
        $pdo->exec("
            ALTER TABLE ecritures_comptables 
            ADD COLUMN statut ENUM('brouillon','valide','cloture','annule') 
            DEFAULT 'brouillon' 
            AFTER date_validation
        ");
        
        echo "<p style='color: green; font-weight: bold;'>✅ Colonne <strong>statut</strong> ajoutée avec succès !</p>";
    }
    
    // ====================================================================
    // Vérification finale
    // ====================================================================
    echo "<hr>";
    echo "<h3>🎉 VÉRIFICATION FINALE</h3>";
    
    echo "<h4>Table: comptes_comptables</h4>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Colonne</th><th>Type</th></tr>";
    $cols1 = $pdo->query("SHOW COLUMNS FROM comptes_comptables");
    foreach ($cols1 as $col) {
        if ($col['Field'] == 'statut') {
            echo "<tr style='background-color: #90EE90;'>";
        } else {
            echo "<tr>";
        }
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h4>Table: ecritures_comptables</h4>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Colonne</th><th>Type</th></tr>";
    $cols2 = $pdo->query("SHOW COLUMNS FROM ecritures_comptables");
    foreach ($cols2 as $col) {
        if ($col['Field'] == 'statut') {
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
    echo "<p><strong>Les 2 tables Finances sont maintenant complètes avec la colonne 'statut'</strong></p>";
    echo "<p><a href='../finances/' style='font-size: 18px; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>← Retour au module Finances</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur SQL : " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>
