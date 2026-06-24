<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import dans la bonne base - EfficaSante</title>
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
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        .stat-box {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            border: 1px solid #00ff00;
        }
        .stat-number { font-size: 32px; font-weight: bold; color: #00ff00; }
        .stat-label { font-size: 12px; color: #888; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Import dans la base cp2640311p29_efficasante</h1>
        
        <div class="progress">
            <div class="progress-bar" id="progressBar" style="width: 0%">0%</div>
        </div>

        <div class="log" id="log">
<?php
// Configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'cp2640311p29_efficasante'; // LA BONNE BASE
$backupFile = __DIR__ . '/backup_db_2026-01-30_15-44-08.sql';

// Augmenter les limites
set_time_limit(0);
ini_set('memory_limit', '1G');
ini_set('max_execution_time', '0');

function logMessage($message, $type = 'info') {
    $colors = ['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'];
    $class = $colors[$type] ?? 'info';
    echo "<div class='log-line $class'>" . htmlspecialchars($message) . "</div>\n";
    flush();
    ob_flush();
}

// Vérifier le fichier
if (!file_exists($backupFile)) {
    logMessage("❌ ERREUR : Fichier backup introuvable : $backupFile", 'error');
    exit;
}

logMessage("✅ Fichier backup trouvé : " . basename($backupFile), 'success');
logMessage("📊 Taille : " . number_format(filesize($backupFile) / 1024 / 1024, 2) . " MB", 'info');
logMessage("", 'info');

// Étape 0 : Créer la base si elle n'existe pas
logMessage("🔌 Connexion à MySQL...", 'info');
$connRoot = new mysqli($host, $username, $password);
if ($connRoot->connect_error) {
    logMessage("❌ ERREUR : " . $connRoot->connect_error, 'error');
    exit;
}

logMessage("✅ Connecté à MySQL", 'success');
logMessage("🔧 Création de la base si nécessaire...", 'info');
$connRoot->query("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
logMessage("✅ Base de données prête : $database", 'success');
$connRoot->close();
logMessage("", 'info');

// Connexion à la base
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    logMessage("❌ ERREUR : " . $conn->connect_error, 'error');
    exit;
}
$conn->set_charset("utf8mb4");

// Étape 1 : Supprimer les tables existantes
logMessage("🗑️ ÉTAPE 1/3 : Suppression des tables existantes...", 'warning');
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$tables = [
    'v_stats_licences', 'utilisateurs', 'tickets_consultation', 'tarifs_consultation',
    'system_logs', 'system_licenses', 'suspicious_activities', 'stocks_materiel',
    'soins_consultation', 'sejours_hospitalisation', 'roles', 'renouvellements_licences',
    'rendez_vous', 'remboursements', 'prix_licences', 'personnel', 'patients',
    'parametres_systeme', 'paiements', 'notifications', 'mouvements_stock_pharmacie',
    'modules_licences', 'messages_internes', 'medicaments', 'medecins', 'maintenance_logs',
    'login_attempts', 'lignes_commande_pharmacie', 'licences', 'interventions_maintenance',
    'horaires_personnel', 'equipements', 'ecritures_comptables', 'dossiers',
    'documents_patients', 'contrats_assurance', 'consultation_soins', 
    'consultation_hospitalisation', 'consultations', 'conges_personnel', 'comptes_comptables',
    'commandes_pharmacie', 'clients', 'categories_hospitalisation', 'budgets',
    'assurances', 'annonces', 'analyses', 'active_sessions'
];

$droppedTables = 0;
foreach ($tables as $table) {
    if ($conn->query("DROP TABLE IF EXISTS `$table`")) {
        $droppedTables++;
    }
}
logMessage("✅ $droppedTables tables supprimées", 'success');
logMessage("", 'info');

// Étape 2 : Import du backup
logMessage("📥 ÉTAPE 2/3 : Import du fichier SQL...", 'info');
$conn->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
$conn->query("SET AUTOCOMMIT = 0");
$conn->query("START TRANSACTION");

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
        if ($conn->query($templine)) {
            $queries++;
            if ($queries % 50 == 0) {
                $progress = round(($lineNumber / $totalLines) * 100);
                echo "<script>document.getElementById('progressBar').style.width = '{$progress}%'; document.getElementById('progressBar').textContent = '{$progress}%';</script>";
                logMessage("   ✓ $queries requêtes exécutées ($progress%)", 'success');
                flush();
                ob_flush();
            }
        } else {
            $errors++;
        }
        $templine = '';
    }
}

$conn->query("COMMIT");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "<script>document.getElementById('progressBar').style.width = '100%'; document.getElementById('progressBar').textContent = '100%';</script>";
logMessage("✅ Import terminé !", 'success');
logMessage("", 'info');

// Étape 3 : Vérification
logMessage("🔍 ÉTAPE 3/3 : Vérification des données...", 'info');
logMessage("", 'info');

$stats = [];
$checkTables = ['patients', 'medecins', 'consultations', 'personnel', 'paiements', 'analyses'];
foreach ($checkTables as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
    if ($result) {
        $row = $result->fetch_assoc();
        $count = number_format($row['count']);
        $stats[$table] = $row['count'];
        logMessage("   • $table : $count enregistrements", 'success');
    }
}

$conn->close();

logMessage("", 'info');
logMessage("═══════════════════════════════════════════════════", 'success');
logMessage("✅ IMPORT TERMINÉ AVEC SUCCÈS !", 'success');
logMessage("═══════════════════════════════════════════════════", 'success');
logMessage("📊 $queries requêtes exécutées", 'info');
if ($errors > 0) {
    logMessage("⚠️ $errors erreurs (généralement normales)", 'warning');
}
logMessage("", 'info');
logMessage("🎉 Votre application est maintenant prête !", 'success');
?>
        </div>

        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= number_format($stats['patients'] ?? 0) ?></div>
                <div class="stat-label">PATIENTS</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= number_format($stats['consultations'] ?? 0) ?></div>
                <div class="stat-label">CONSULTATIONS</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= number_format($stats['personnel'] ?? 0) ?></div>
                <div class="stat-label">PERSONNEL</div>
            </div>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="../index.php" style="display: inline-block; background: #00ff00; color: #1e1e1e; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                🏠 Retour à l'application
            </a>
        </div>
    </div>
</body>
</html>
