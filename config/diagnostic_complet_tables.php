<?php
/**
 * Diagnostic COMPLET - Trouve TOUTES les tables manquantes
 */

require_once __DIR__ . '/database_production.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnostic Complet</title>";
echo "<style>body{font-family:Arial;padding:30px;background:#f5f5f5;}";
echo ".success{color:#28a745;padding:10px;background:#d4edda;border-left:4px solid #28a745;margin:10px 0;}";
echo ".error{color:#dc3545;padding:10px;background:#f8d7da;border-left:4px solid #dc3545;margin:10px 0;}";
echo ".warning{color:#856404;padding:10px;background:#fff3cd;border-left:4px solid #ffc107;margin:10px 0;}";
echo "table{width:100%;border-collapse:collapse;background:white;margin:20px 0;}";
echo "th,td{padding:12px;text-align:left;border-bottom:1px solid #ddd;}";
echo "th{background:#667eea;color:white;}";
echo "a{display:inline-block;background:#667eea;color:white;padding:12px 24px;text-decoration:none;border-radius:8px;margin:10px 5px;}</style></head><body>";

echo "<h1>🔍 Diagnostic Complet de la Base de Données</h1>";

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo "<div class='error'>❌ Erreur de connexion</div></body></html>";
    exit;
}

// Liste COMPLÈTE de toutes les tables utilisées dans l'application
$allTables = [
    'active_sessions' => 'Système',
    'analyses' => 'Laboratoire',
    'annonces' => 'Communication',
    'assurances' => 'Assurances',
    'budgets' => 'Finances',
    'categories_hospitalisation' => 'Hospitalisation',
    'clients' => 'Licences',
    'commandes_pharmacie' => 'Pharmacie',
    'comptes_comptables' => 'Finances',
    'conges_personnel' => 'Personnel',
    'consultations' => 'Consultations',
    'consultation_hospitalisation' => 'Hospitalisation',
    'consultation_soins' => 'Soins',
    'contrats_assurance' => 'Assurances',
    'documents_patients' => 'Patients',
    'dossiers' => 'Dossiers',
    'ecritures_comptables' => 'Finances',
    'equipements' => 'Maintenance',
    'horaires_personnel' => 'Personnel',
    'interventions_maintenance' => 'Maintenance',
    'licences' => 'Système',
    'lignes_commande_pharmacie' => 'Pharmacie',
    'login_attempts' => 'Sécurité',
    'maintenance_logs' => 'Maintenance',
    'medecins' => 'Médecins',
    'medicaments' => 'Pharmacie',
    'messages_internes' => 'Communication',
    'modules_licences' => 'Système',
    'mouvements_stock_pharmacie' => 'Pharmacie',
    'notifications' => 'Système',
    'paiements' => 'Paiements',
    'parametres_systeme' => 'Système',
    'patients' => 'Patients',
    'personnel' => 'Personnel',
    'prix_licences' => 'Système',
    'remboursements' => 'Assurances',
    'rendez_vous' => 'Rendez-vous',
    'renouvellements_licences' => 'Système',
    'roles' => 'Utilisateurs',
    'sejours_hospitalisation' => 'Hospitalisation',
    'soins_consultation' => 'Soins',
    'stocks_materiel' => 'Stocks',
    'suspicious_activities' => 'Sécurité',
    'system_licenses' => 'Système',
    'system_logs' => 'Système',
    'tarifs_consultation' => 'Consultations',
    'tickets_consultation' => 'Consultations',
    'utilisateurs' => 'Utilisateurs'
];

echo "<h2>📊 Vérification de toutes les tables</h2>";

$existing = [];
$missing = [];

foreach ($allTables as $table => $module) {
    $stmt = $conn->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() > 0) {
        $existing[$table] = $module;
    } else {
        $missing[$table] = $module;
    }
}

// Afficher les tables existantes
echo "<div class='success'>";
echo "<strong>✅ Tables existantes (" . count($existing) . "/" . count($allTables) . ") :</strong>";
echo "</div>";

echo "<table>";
echo "<tr><th>Table</th><th>Module</th></tr>";
foreach ($existing as $table => $module) {
    echo "<tr><td><code>$table</code></td><td>$module</td></tr>";
}
echo "</table>";

// Afficher les tables manquantes
if (!empty($missing)) {
    echo "<div class='error'>";
    echo "<strong>❌ Tables MANQUANTES (" . count($missing) . ") :</strong>";
    echo "</div>";
    
    echo "<table>";
    echo "<tr><th>Table</th><th>Module</th></tr>";
    foreach ($missing as $table => $module) {
        echo "<tr style='background:#f8d7da;'><td><strong><code>$table</code></strong></td><td>$module</td></tr>";
    }
    echo "</table>";
    
    echo "<div class='warning'>";
    echo "<strong>⚠️ ACTION REQUISE :</strong><br>";
    echo "Vous devez créer ces " . count($missing) . " tables manquantes pour que l'application fonctionne complètement.";
    echo "</div>";
} else {
    echo "<div class='success' style='font-size:20px;'>";
    echo "🎉 PARFAIT ! Toutes les tables existent !";
    echo "</div>";
}

echo "<div style='margin-top:30px;text-align:center;'>";
echo "<a href='../index.php'>🏠 Retour à l'accueil</a>";
if (!empty($missing)) {
    echo "<a href='#' onclick='alert(\"Copiez la liste des tables manquantes et créez-les dans phpMyAdmin\");' style='background:#dc3545;'>📋 Liste des tables manquantes</a>";
}
echo "</div>";

echo "</body></html>";
?>
