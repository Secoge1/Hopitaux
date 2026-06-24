<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Diagnostic complet du module FINANCES</h2>";

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
    
    // Liste des tables du module Finances
    $tables_finances = [
        'budgets',
        'comptes_comptables',
        'ecritures_comptables'
    ];
    
    echo "<h3>📊 Vérification des colonnes 'statut' dans les tables Finances :</h3>";
    
    foreach ($tables_finances as $table) {
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
        echo "<h4>Table: <strong>$table</strong></h4>";
        
        // Vérifier si la table existe
        $check_table = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($check_table->rowCount() == 0) {
            echo "<p style='color: red;'>❌ La table <strong>$table</strong> n'existe pas !</p>";
            echo "</div>";
            continue;
        }
        
        echo "<p style='color: green;'>✅ La table existe</p>";
        
        // Afficher toutes les colonnes
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Défaut</th></tr>";
        
        $columns = $pdo->query("SHOW COLUMNS FROM $table");
        $has_statut = false;
        
        foreach ($columns as $col) {
            if ($col['Field'] == 'statut') {
                $has_statut = true;
                echo "<tr style='background-color: #90EE90;'>";
            } else {
                echo "<tr>";
            }
            echo "<td><strong>{$col['Field']}</strong></td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if ($has_statut) {
            echo "<p style='color: green; font-weight: bold;'>✅ La colonne 'statut' est présente</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>❌ La colonne 'statut' est MANQUANTE</p>";
        }
        
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h3>🎯 Résumé</h3>";
    echo "<p>Si une table affiche '❌ La colonne statut est MANQUANTE', dites-moi laquelle et je la corrigerai !</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>
