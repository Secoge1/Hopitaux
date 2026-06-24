<?php
/**
 * Diagnostic complet de la base de données
 * Vérifie l'état des tables et des données après l'import
 */

// Configuration
$host = 'localhost';
$username = 'root';
$password = '';

// Essayer les deux bases de données possibles
$databases = ['cp2640311p29_efficasante', 'efficasante'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Base de Données - EfficaSante</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .database-section {
            margin-bottom: 40px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .database-title {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        thead {
            background: #667eea;
            color: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
        }
        tbody tr:hover {
            background: #f8f9fa;
        }
        .status-ok {
            color: #28a745;
            font-weight: bold;
        }
        .status-warning {
            color: #ffc107;
            font-weight: bold;
        }
        .status-error {
            color: #dc3545;
            font-weight: bold;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
        }
        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .alert-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        .alert-danger {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .summary-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .summary-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        .summary-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin: 10px 5px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #764ba2;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnostic de la Base de Données</h1>

<?php
foreach ($databases as $database) {
    echo "<div class='database-section'>";
    echo "<div class='database-title'>📊 Base de données : $database</div>";
    
    // Connexion
    $conn = @new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        echo "<div class='alert alert-danger'>";
        echo "❌ Impossible de se connecter à la base <strong>$database</strong><br>";
        echo "Erreur : " . $conn->connect_error;
        echo "</div>";
        continue;
    }
    
    echo "<div class='alert alert-success'>";
    echo "✅ Connexion réussie à la base <strong>$database</strong>";
    echo "</div>";
    
    // Tables importantes à vérifier
    $requiredTables = [
        'patients' => 'Patients',
        'medecins' => 'Médecins',
        'consultations' => 'Consultations',
        'personnel' => 'Personnel',
        'rendez_vous' => 'Rendez-vous',
        'paiements' => 'Paiements',
        'medicaments' => 'Médicaments (Pharmacie)',
        'utilisateurs' => 'Utilisateurs',
        'budgets' => 'Budgets (Finances)',
        'assurances' => 'Assurances',
        'annonces' => 'Annonces (Communication)',
        'maintenance_logs' => 'Maintenance'
    ];
    
    echo "<table>";
    echo "<thead>";
    echo "<tr><th>Module</th><th>Table</th><th>Statut</th><th>Nombre d'enregistrements</th></tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $totalTables = 0;
    $missingTables = [];
    $emptyTables = [];
    $summary = [];
    
    foreach ($requiredTables as $table => $module) {
        echo "<tr>";
        echo "<td>$module</td>";
        echo "<td><code>$table</code></td>";
        
        // Vérifier si la table existe
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        
        if ($result && $result->num_rows > 0) {
            $totalTables++;
            
            // Compter les enregistrements
            $countResult = $conn->query("SELECT COUNT(*) as count FROM `$table`");
            $count = 0;
            if ($countResult) {
                $row = $countResult->fetch_assoc();
                $count = $row['count'];
                $summary[$module] = $count;
            }
            
            if ($count > 0) {
                echo "<td class='status-ok'>✅ Existe</td>";
                echo "<td class='status-ok'>" . number_format($count) . " enregistrements</td>";
            } else {
                echo "<td class='status-warning'>⚠️ Vide</td>";
                echo "<td class='status-warning'>0 enregistrements</td>";
                $emptyTables[] = $module;
            }
        } else {
            echo "<td class='status-error'>❌ Manquante</td>";
            echo "<td class='status-error'>-</td>";
            $missingTables[] = $module;
        }
        
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    
    // Résumé
    if (count($missingTables) > 0) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>❌ Tables manquantes :</strong><br>";
        echo implode(', ', $missingTables);
        echo "</div>";
    }
    
    if (count($emptyTables) > 0) {
        echo "<div class='alert alert-warning'>";
        echo "<strong>⚠️ Tables vides (peuvent causer des erreurs) :</strong><br>";
        echo implode(', ', $emptyTables);
        echo "</div>";
    }
    
    if (count($missingTables) == 0 && count($emptyTables) == 0) {
        echo "<div class='alert alert-success'>";
        echo "<strong>✅ Toutes les tables nécessaires sont présentes et contiennent des données !</strong>";
        echo "</div>";
    }
    
    // Statistiques détaillées
    if (count($summary) > 0) {
        echo "<div class='summary-grid'>";
        foreach ($summary as $module => $count) {
            echo "<div class='summary-box'>";
            echo "<div class='summary-number'>" . number_format($count) . "</div>";
            echo "<div class='summary-label'>$module</div>";
            echo "</div>";
        }
        echo "</div>";
    }
    
    $conn->close();
    echo "</div>";
}
?>

        <div style="margin-top: 30px; text-align: center;">
            <a href="../index.php" class="btn btn-success">🏠 Retour à l'application</a>
            <a href="import_production.php" class="btn">🔄 Ré-importer le backup</a>
        </div>

        <div class="alert alert-warning" style="margin-top: 30px;">
            <strong>💡 Aide :</strong><br>
            • Si des tables sont <strong>manquantes</strong> : ré-importez le backup<br>
            • Si des tables sont <strong>vides</strong> : c'est normal pour certaines tables (maintenance, budgets, etc.)<br>
            • Si tout est <strong>OK</strong> mais les modules ne marchent pas : vérifiez la configuration dans config/db.php
        </div>
    </div>
</body>
</html>
