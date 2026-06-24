<?php
/**
 * Diagnostic simplifié avec affichage des erreurs
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html lang='fr'><head><meta charset='UTF-8'>";
echo "<title>Diagnostic Simple</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;margin:10px 0;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{color:#28a745;font-weight:bold;} .error{color:#dc3545;font-weight:bold;} .warning{color:#ffc107;font-weight:bold;}";
echo "table{width:100%;border-collapse:collapse;margin:10px 0;}";
echo "th,td{padding:10px;text-align:left;border-bottom:1px solid #ddd;}";
echo "th{background:#667eea;color:white;}</style></head><body>";

echo "<h1>🔍 Diagnostic Base de Données</h1>";

// Configuration
$host = 'localhost';
$username = 'root';
$password = '';
$databases = ['cp2640311p29_efficasante', 'efficasante'];

foreach ($databases as $database) {
    echo "<div class='box'>";
    echo "<h2>📊 Base: $database</h2>";
    
    // Test de connexion
    echo "<p>🔌 Tentative de connexion...</p>";
    
    $conn = @new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        echo "<p class='error'>❌ Erreur: " . htmlspecialchars($conn->connect_error) . "</p>";
        echo "</div>";
        continue;
    }
    
    echo "<p class='success'>✅ Connecté avec succès</p>";
    
    // Tables à vérifier
    $tables = [
        'patients', 'medecins', 'consultations', 'personnel', 
        'rendez_vous', 'paiements', 'medicaments', 'utilisateurs',
        'budgets', 'assurances', 'annonces', 'maintenance_logs'
    ];
    
    echo "<table>";
    echo "<tr><th>Table</th><th>Statut</th><th>Enregistrements</th></tr>";
    
    foreach ($tables as $table) {
        echo "<tr>";
        echo "<td><code>$table</code></td>";
        
        // Vérifier si la table existe
        $result = @$conn->query("SHOW TABLES LIKE '$table'");
        
        if ($result && $result->num_rows > 0) {
            echo "<td class='success'>✅ Existe</td>";
            
            // Compter les enregistrements
            $countResult = @$conn->query("SELECT COUNT(*) as count FROM `$table`");
            if ($countResult) {
                $row = $countResult->fetch_assoc();
                $count = $row['count'];
                
                if ($count > 0) {
                    echo "<td class='success'>$count</td>";
                } else {
                    echo "<td class='warning'>0 (vide)</td>";
                }
            } else {
                echo "<td class='error'>Erreur comptage</td>";
            }
        } else {
            echo "<td class='error'>❌ Manquante</td>";
            echo "<td class='error'>-</td>";
        }
        
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Vérifier quelle base est configurée dans l'application
    $configFile = '../config/database_production.php';
    if (file_exists($configFile)) {
        $configContent = file_get_contents($configFile);
        if (strpos($configContent, $database) !== false) {
            echo "<p class='success'>✅ Cette base est configurée dans l'application</p>";
        }
    }
    
    $conn->close();
    echo "</div>";
}

echo "<div class='box'>";
echo "<h2>🔧 Actions recommandées</h2>";
echo "<p>Si des tables sont manquantes, <a href='import_production.php'>cliquez ici pour importer le backup</a></p>";
echo "<p><a href='../index.php'>Retour à l'application</a></p>";
echo "</div>";

echo "</body></html>";
?>
