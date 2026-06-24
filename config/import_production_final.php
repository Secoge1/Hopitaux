<?php
/**
 * Import automatique du backup sur le serveur de PRODUCTION
 * Utilise les credentials de production
 */

set_time_limit(0);
ini_set('memory_limit', '1G');
ini_set('max_execution_time', '0');

// Charger la configuration de production
require_once __DIR__ . '/database_production.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Production - EfficaSante</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #00ff00;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #2d2d2d;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,255,0,0.3);
        }
        h1 { color: #00ff00; margin-bottom: 20px; text-shadow: 0 0 10px rgba(0,255,0,0.5); }
        .log {
            background: #1e1e1e;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #00ff00;
        }
        .log-line { margin: 5px 0; font-size: 14px; }
        .success { color: #00ff00; }
        .error { color: #ff0000; }
        .warning { color: #ffaa00; }
        .info { color: #00aaff; }
        .progress {
            background: #1e1e1e;
            height: 30px;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
            border: 1px solid #00ff00;
        }
        .progress-bar {
            background: linear-gradient(90deg, #00ff00, #00aa00);
            height: 100%;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e1e1e;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Import du backup en PRODUCTION</h1>
        
        <div class="progress">
            <div class="progress-bar" id="progressBar" style="width: 0%">0%</div>
        </div>

        <div class="log" id="log">
<?php

function logMessage($message, $type = 'info') {
    $colors = ['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'];
    $class = $colors[$type] ?? 'info';
    echo "<div class='log-line $class'>" . htmlspecialchars($message) . "</div>\n";
    flush();
    ob_flush();
}

$backupFile = __DIR__ . '/backup_db_2026-01-30_15-44-08.sql';

// Vérifier le fichier
if (!file_exists($backupFile)) {
    logMessage("❌ ERREUR : Fichier backup introuvable : $backupFile", 'error');
    logMessage("", 'info');
    logMessage("Le fichier backup doit être uploadé sur le serveur dans le dossier config/", 'warning');
    exit;
}

logMessage("✅ Fichier backup trouvé : " . basename($backupFile), 'success');
logMessage("📊 Taille : " . number_format(filesize($backupFile) / 1024 / 1024, 2) . " MB", 'info');
logMessage("", 'info');

// Connexion via la classe Database
logMessage("🔌 Connexion à la base de données...", 'info');
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    logMessage("❌ ERREUR : Impossible de se connecter à la base de données", 'error');
    exit;
}

logMessage("✅ Connecté à la base de données de production", 'success');
logMessage("", 'info');

// Étape 1 : Supprimer les tables manquantes uniquement (pour éviter de perdre les données existantes)
logMessage("🔧 ÉTAPE 1/3 : Préparation de la base...", 'info');
$conn->exec("SET FOREIGN_KEY_CHECKS = 0");

// Tables à créer/recréer (celles qui manquent)
$tablesToDrop = ['personnel', 'medicaments', 'budgets', 'assurances', 'annonces'];

foreach ($tablesToDrop as $table) {
    $conn->exec("DROP TABLE IF EXISTS `$table`");
    logMessage("   Préparation de la table $table...", 'info');
}

logMessage("✅ Base préparée", 'success');
logMessage("", 'info');

// Étape 2 : Import du backup
logMessage("📥 ÉTAPE 2/3 : Import du fichier SQL...", 'info');
$conn->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
$conn->beginTransaction();

$sql = file_get_contents($backupFile);
$lines = explode("\n", $sql);
$totalLines = count($lines);
$queries = 0;
$errors = 0;
$templine = '';

logMessage("📋 Lignes à traiter : " . number_format($totalLines), 'info');
logMessage("⏳ Import en cours...", 'info');

foreach ($lines as $lineNumber => $line) {
    if (substr(trim($line), 0, 2) == '--' || trim($line) == '' || substr(trim($line), 0, 2) == '/*') {
        continue;
    }
    
    $templine .= $line;
    
    if (substr(trim($line), -1, 1) == ';') {
        try {
            $conn->exec($templine);
            $queries++;
            
            if ($queries % 50 == 0) {
                $progress = round(($lineNumber / $totalLines) * 100);
                echo "<script>document.getElementById('progressBar').style.width = '{$progress}%'; document.getElementById('progressBar').textContent = '{$progress}%';</script>";
                logMessage("   ✓ $queries requêtes exécutées ($progress%)", 'success');
                flush();
                ob_flush();
            }
        } catch (PDOException $e) {
            $errors++;
            // Ignorer les erreurs de tables déjà existantes
        }
        $templine = '';
    }
}

$conn->commit();
$conn->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "<script>document.getElementById('progressBar').style.width = '100%'; document.getElementById('progressBar').textContent = '100%';</script>";
logMessage("✅ Import terminé !", 'success');
logMessage("", 'info');

// Étape 3 : Vérification
logMessage("🔍 ÉTAPE 3/3 : Vérification des tables...", 'info');
logMessage("", 'info');

$checkTables = [
    'personnel' => 'Personnel',
    'medicaments' => 'Pharmacie',
    'budgets' => 'Finances',
    'assurances' => 'Assurances',
    'annonces' => 'Communication'
];

$allOk = true;

foreach ($checkTables as $table => $module) {
    try {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        
        if ($stmt && $stmt->rowCount() > 0) {
            $countStmt = $conn->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $countStmt->fetch()['count'];
            logMessage("   ✅ $module ($table) : " . number_format($count) . " enregistrements", 'success');
        } else {
            logMessage("   ❌ $module ($table) : MANQUANTE", 'error');
            $allOk = false;
        }
    } catch (Exception $e) {
        logMessage("   ❌ $module ($table) : " . $e->getMessage(), 'error');
        $allOk = false;
    }
}

logMessage("", 'info');
logMessage("═══════════════════════════════════════════════════", 'success');

if ($allOk) {
    logMessage("✅ IMPORT RÉUSSI !", 'success');
    logMessage("═══════════════════════════════════════════════════", 'success');
    logMessage("🎉 Tous les modules sont maintenant fonctionnels !", 'success');
} else {
    logMessage("⚠️ IMPORT PARTIEL", 'warning');
    logMessage("═══════════════════════════════════════════════════", 'warning');
    logMessage("Certaines tables n'ont pas pu être créées.", 'warning');
}

logMessage("", 'info');
logMessage("📊 $queries requêtes exécutées", 'info');

?>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="diagnostic_auto.php" style="display: inline-block; background: #00aaff; color: #1e1e1e; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 5px;">
                🔍 Vérifier à nouveau
            </a>
            <a href="../index.php" style="display: inline-block; background: #00ff00; color: #1e1e1e; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 5px;">
                🏠 Retour à l'application
            </a>
        </div>
    </div>
</body>
</html>
