<?php
/**
 * Diagnostic qui détecte automatiquement l'environnement
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html lang='fr'><head><meta charset='UTF-8'>";
echo "<title>Diagnostic Base de Données</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;margin:10px 0;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{color:#28a745;font-weight:bold;} .error{color:#dc3545;font-weight:bold;} .warning{color:#ffc107;font-weight:bold;}";
echo "table{width:100%;border-collapse:collapse;margin:10px 0;}";
echo "th,td{padding:10px;text-align:left;border-bottom:1px solid #ddd;}";
echo "th{background:#667eea;color:white;}</style></head><body>";

echo "<h1>🔍 Diagnostic Base de Données</h1>";

// Détection de l'environnement
$isProduction = (strpos($_SERVER['HTTP_HOST'], 'secogesarl.com') !== false || 
                 strpos($_SERVER['HTTP_HOST'], 'efficasante') !== false);

echo "<div class='box'>";
echo "<h2>🌍 Environnement détecté</h2>";
if ($isProduction) {
    echo "<p class='warning'>⚠️ PRODUCTION - " . htmlspecialchars($_SERVER['HTTP_HOST']) . "</p>";
} else {
    echo "<p class='success'>✅ DÉVELOPPEMENT - localhost</p>";
}
echo "</div>";

// Configuration selon l'environnement
if ($isProduction) {
    // Configuration PRODUCTION
    echo "<div class='box'>";
    echo "<h2>🔧 Configuration Production</h2>";
    
    // Essayer de charger la configuration de production
    $configFile = __DIR__ . '/database_production.php';
    
    if (file_exists($configFile)) {
        require_once $configFile;
        
        if (class_exists('Database')) {
            $database = new Database();
            $conn = $database->getConnection();
            
            if ($conn) {
                echo "<p class='success'>✅ Connexion réussie via Database class</p>";
                
                // Tables à vérifier
                $tables = [
                    'patients' => 'Patients',
                    'medecins' => 'Médecins',
                    'consultations' => 'Consultations',
                    'personnel' => 'Personnel',
                    'rendez_vous' => 'Rendez-vous',
                    'paiements' => 'Paiements',
                    'medicaments' => 'Pharmacie',
                    'utilisateurs' => 'Utilisateurs',
                    'budgets' => 'Finances',
                    'assurances' => 'Assurances',
                    'annonces' => 'Communication',
                    'maintenance_logs' => 'Maintenance'
                ];
                
                echo "<table>";
                echo "<tr><th>Module</th><th>Table</th><th>Statut</th><th>Enregistrements</th></tr>";
                
                $totalOk = 0;
                $totalMissing = 0;
                $totalEmpty = 0;
                
                foreach ($tables as $table => $module) {
                    echo "<tr>";
                    echo "<td>$module</td>";
                    echo "<td><code>$table</code></td>";
                    
                    try {
                        // Vérifier si la table existe
                        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
                        
                        if ($stmt && $stmt->rowCount() > 0) {
                            // Compter les enregistrements
                            $countStmt = $conn->query("SELECT COUNT(*) as count FROM `$table`");
                            $count = $countStmt->fetch()['count'];
                            
                            if ($count > 0) {
                                echo "<td class='success'>✅ Existe</td>";
                                echo "<td class='success'>" . number_format($count) . "</td>";
                                $totalOk++;
                            } else {
                                echo "<td class='warning'>⚠️ Vide</td>";
                                echo "<td class='warning'>0</td>";
                                $totalEmpty++;
                            }
                        } else {
                            echo "<td class='error'>❌ Manquante</td>";
                            echo "<td class='error'>-</td>";
                            $totalMissing++;
                        }
                    } catch (Exception $e) {
                        echo "<td class='error'>❌ Erreur</td>";
                        echo "<td class='error'>" . htmlspecialchars($e->getMessage()) . "</td>";
                        $totalMissing++;
                    }
                    
                    echo "</tr>";
                }
                
                echo "</table>";
                
                echo "<div style='margin-top:20px;'>";
                echo "<p><strong>Résumé :</strong></p>";
                echo "<ul>";
                echo "<li class='success'>✅ Tables OK : $totalOk</li>";
                echo "<li class='warning'>⚠️ Tables vides : $totalEmpty</li>";
                echo "<li class='error'>❌ Tables manquantes : $totalMissing</li>";
                echo "</ul>";
                echo "</div>";
                
                if ($totalMissing > 0) {
                    echo "<div style='background:#f8d7da;padding:15px;margin:20px 0;border-radius:8px;border-left:4px solid #dc3545;'>";
                    echo "<strong>⚠️ ACTION REQUISE :</strong><br>";
                    echo "Des tables sont manquantes. Vous devez importer le backup de la base de données.";
                    echo "</div>";
                }
                
            } else {
                echo "<p class='error'>❌ Erreur de connexion à la base de données</p>";
            }
        } else {
            echo "<p class='error'>❌ Classe Database introuvable dans le fichier de configuration</p>";
        }
    } else {
        echo "<p class='error'>❌ Fichier de configuration introuvable : $configFile</p>";
    }
    
    echo "</div>";
    
} else {
    // Configuration LOCALE (localhost)
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $databases = ['cp2640311p29_efficasante', 'efficasante'];
    
    foreach ($databases as $database) {
        echo "<div class='box'>";
        echo "<h2>📊 Base: $database</h2>";
        
        $conn = @new mysqli($host, $username, $password, $database);
        
        if ($conn->connect_error) {
            echo "<p class='error'>❌ Erreur: " . htmlspecialchars($conn->connect_error) . "</p>";
            echo "</div>";
            continue;
        }
        
        echo "<p class='success'>✅ Connecté</p>";
        
        $tables = ['patients', 'medecins', 'consultations', 'personnel', 'rendez_vous', 'paiements', 'medicaments', 'utilisateurs', 'budgets', 'assurances', 'annonces', 'maintenance_logs'];
        
        echo "<table>";
        echo "<tr><th>Table</th><th>Statut</th><th>Enregistrements</th></tr>";
        
        foreach ($tables as $table) {
            echo "<tr>";
            echo "<td><code>$table</code></td>";
            
            $result = @$conn->query("SHOW TABLES LIKE '$table'");
            
            if ($result && $result->num_rows > 0) {
                echo "<td class='success'>✅ Existe</td>";
                
                $countResult = @$conn->query("SELECT COUNT(*) as count FROM `$table`");
                if ($countResult) {
                    $row = $countResult->fetch_assoc();
                    $count = $row['count'];
                    echo "<td class='success'>" . number_format($count) . "</td>";
                } else {
                    echo "<td class='error'>Erreur</td>";
                }
            } else {
                echo "<td class='error'>❌ Manquante</td>";
                echo "<td class='error'>-</td>";
            }
            
            echo "</tr>";
        }
        
        echo "</table>";
        $conn->close();
        echo "</div>";
    }
}

echo "<div class='box'>";
echo "<h2>🔗 Liens utiles</h2>";
echo "<p><a href='../index.php' style='color:#667eea;'>← Retour à l'application</a></p>";
echo "</div>";

echo "</body></html>";
?>
