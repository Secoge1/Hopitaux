<?php
/**
 * Diagnostic d'erreur - Affiche les erreurs de la page d'accueil
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Diagnostic Erreur</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;margin:10px 0;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".error{color:#dc3545;background:#f8d7da;padding:15px;border-left:4px solid #dc3545;margin:10px 0;border-radius:5px;}";
echo ".success{color:#28a745;background:#d4edda;padding:15px;border-left:4px solid #28a745;margin:10px 0;border-radius:5px;}";
echo ".info{color:#0c5460;background:#d1ecf1;padding:15px;border-left:4px solid #17a2b8;margin:10px 0;border-radius:5px;}";
echo "pre{background:#f8f9fa;padding:15px;border-radius:5px;overflow-x:auto;}";
echo "a{display:inline-block;background:#667eea;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;margin:5px;}";
echo "</style></head><body>";

echo "<h1>🔍 Diagnostic des Erreurs</h1>";

// Test 1 : Connexion à la base de données
echo "<div class='box'><h2>1️⃣ Test de connexion</h2>";

try {
    require_once __DIR__ . '/database_production.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<div class='success'>✅ Connexion à la base de données : OK</div>";
        
        // Test 2 : Vérifier les tables
        echo "</div><div class='box'><h2>2️⃣ Vérification des tables</h2>";
        
        $tables = ['patients', 'medecins', 'consultations', 'personnel', 'medicaments', 'budgets', 'assurances', 'annonces', 'utilisateurs'];
        $missing = [];
        
        foreach ($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() == 0) {
                $missing[] = $table;
                echo "<div class='error'>❌ Table manquante : $table</div>";
            }
        }
        
        if (empty($missing)) {
            echo "<div class='success'>✅ Toutes les tables essentielles existent</div>";
        }
        
        // Test 3 : Tester l'inclusion du fichier index
        echo "</div><div class='box'><h2>3️⃣ Test de la page d'accueil</h2>";
        echo "<div class='info'>Tentative de chargement de index.php...</div>";
        
        ob_start();
        try {
            include __DIR__ . '/../index.php';
            $output = ob_get_clean();
            
            if (!empty($output)) {
                echo "<div class='success'>✅ La page index.php se charge correctement</div>";
                echo "<div class='info'>Les 100 premiers caractères :<br><pre>" . htmlspecialchars(substr($output, 0, 100)) . "...</pre></div>";
            } else {
                echo "<div class='error'>⚠️ La page index.php ne produit aucune sortie</div>";
            }
            
        } catch (Exception $e) {
            ob_end_clean();
            echo "<div class='error'>❌ Erreur lors du chargement de index.php :<br><strong>" . htmlspecialchars($e->getMessage()) . "</strong></div>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
        
    } else {
        echo "<div class='error'>❌ Impossible de se connecter à la base de données</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ ERREUR CRITIQUE :<br><strong>" . htmlspecialchars($e->getMessage()) . "</strong></div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</div>";

// Test 4 : Vérifier les fichiers de configuration
echo "<div class='box'><h2>4️⃣ Fichiers de configuration</h2>";

$configFiles = [
    'database.php' => __DIR__ . '/database.php',
    'database_production.php' => __DIR__ . '/database_production.php',
    'db.php' => __DIR__ . '/db.php',
];

foreach ($configFiles as $name => $path) {
    if (file_exists($path)) {
        echo "<div class='success'>✅ $name existe</div>";
    } else {
        echo "<div class='error'>❌ $name manquant</div>";
    }
}

echo "</div>";

// Actions recommandées
echo "<div class='box'><h2>🔧 Actions</h2>";
echo "<a href='creer_tables_rapide.php'>Créer les tables manquantes</a>";
echo "<a href='diagnostic_auto.php'>Diagnostic complet</a>";
echo "<a href='../index.php'>Réessayer l'accueil</a>";
echo "</div>";

echo "</body></html>";
?>
