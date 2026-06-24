<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Correction de la table budgets</h2>";

// Connexion à la base de données de production
$host = 'localhost';
$dbname = 'cp2640311p29_efficasante';
$username = 'cp2640311p29_efficasante';
$password = 'cp2640311p29_efficasante';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✅ Connexion à la base de données : OK</p>";
    
    // Vérifier si la colonne statut existe déjà
    $check = $pdo->query("SHOW COLUMNS FROM budgets LIKE 'statut'");
    if ($check->rowCount() > 0) {
        echo "<p>✅ La colonne <strong>statut</strong> existe déjà dans la table budgets</p>";
    } else {
        echo "<p>⚠️ La colonne <strong>statut</strong> est manquante. Ajout en cours...</p>";
        
        // Ajouter la colonne statut
        $pdo->exec("
            ALTER TABLE budgets 
            ADD COLUMN statut ENUM('planifie','approuve','en_cours','cloture') 
            DEFAULT 'planifie' 
            AFTER montant_restant
        ");
        
        echo "<p>✅ Colonne <strong>statut</strong> ajoutée avec succès !</p>";
    }
    
    // Vérifier toutes les colonnes de la table budgets
    echo "<h3>📋 Structure actuelle de la table budgets :</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Défaut</th></tr>";
    
    $columns = $pdo->query("SHOW COLUMNS FROM budgets");
    $colonnes_presentes = [];
    foreach ($columns as $col) {
        $colonnes_presentes[] = $col['Field'];
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Liste des colonnes attendues
    $colonnes_attendues = [
        'id',
        'annee',
        'departement',
        'categorie',
        'montant_alloue',
        'montant_utilise',
        'montant_restant',
        'statut',
        'notes',
        'cree_par',
        'date_creation'
    ];
    
    $colonnes_manquantes = array_diff($colonnes_attendues, $colonnes_presentes);
    
    if (empty($colonnes_manquantes)) {
        echo "<p style='color: green; font-weight: bold;'>✅ TOUTES les colonnes sont présentes !</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Colonnes manquantes : " . implode(', ', $colonnes_manquantes) . "</p>";
    }
    
    echo "<h3>🎉 TERMINÉ !</h3>";
    echo "<p>La table <strong>budgets</strong> est maintenant correctement configurée.</p>";
    echo "<p><a href='../finances/'>← Retour au module Finances</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>
