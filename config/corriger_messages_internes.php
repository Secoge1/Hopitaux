<?php
/**
 * Correction de la table messages_internes - Ajout de la colonne manquante
 */

require_once __DIR__ . '/database_production.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Correction table messages</title>";
echo "<style>body{font-family:Arial;padding:30px;background:#f5f5f5;}";
echo ".success{color:#28a745;font-weight:bold;font-size:18px;padding:20px;background:white;border-radius:8px;margin:10px 0;}";
echo ".error{color:#dc3545;font-weight:bold;font-size:18px;padding:20px;background:white;border-radius:8px;margin:10px 0;}";
echo ".info{color:#0c5460;background:#d1ecf1;padding:15px;border-radius:8px;margin:10px 0;}";
echo "a{display:inline-block;background:#667eea;color:white;padding:12px 24px;text-decoration:none;border-radius:8px;margin:10px 5px;}</style></head><body>";

echo "<h1>🔧 Correction de la table messages_internes</h1>";

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo "<div class='error'>❌ Erreur de connexion</div></body></html>";
    exit;
}

try {
    echo "<div class='info'>📋 Vérification de la structure actuelle...</div>";
    
    // Vérifier les colonnes actuelles
    $stmt = $conn->query("DESCRIBE messages_internes");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<div class='info'>Colonnes actuelles : " . implode(', ', $columns) . "</div>";
    
    // Ajouter la colonne destinataire_role si elle n'existe pas
    if (!in_array('destinataire_role', $columns)) {
        echo "<div class='info'>➕ Ajout de la colonne 'destinataire_role'...</div>";
        $conn->exec("ALTER TABLE `messages_internes` ADD COLUMN `destinataire_role` enum('tous','medecins','infirmiers','secretaires','admins') DEFAULT NULL AFTER `destinataire_id`");
        echo "<div class='success'>✅ Colonne 'destinataire_role' ajoutée</div>";
    } else {
        echo "<div class='success'>✅ La colonne 'destinataire_role' existe déjà</div>";
    }
    
    // Vérifier les autres colonnes nécessaires
    $requiredColumns = [
        'id', 'expediteur_id', 'destinataire_id', 'destinataire_role', 
        'sujet', 'message', 'lu', 'date_lecture', 'priorite', 
        'piece_jointe', 'date_creation'
    ];
    
    $missing = array_diff($requiredColumns, $columns);
    
    if (empty($missing)) {
        echo "<div class='success'>";
        echo "🎉 SUCCÈS !<br><br>";
        echo "La table <strong>messages_internes</strong> a toutes les colonnes nécessaires.<br><br>";
        echo "Le module Communication devrait maintenant fonctionner correctement !";
        echo "</div>";
    } else {
        echo "<div class='error'>⚠️ Colonnes manquantes : " . implode(', ', $missing) . "</div>";
    }
    
    // Afficher la structure finale
    echo "<div class='info'><strong>Structure finale de la table :</strong><br><br>";
    $stmt = $conn->query("DESCRIBE messages_internes");
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table style='width:100%;border-collapse:collapse;'>";
    echo "<tr style='background:#667eea;color:white;'><th style='padding:10px;text-align:left;'>Colonne</th><th style='padding:10px;text-align:left;'>Type</th></tr>";
    foreach ($structure as $col) {
        echo "<tr style='border-bottom:1px solid #ddd;'>";
        echo "<td style='padding:10px;'>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td style='padding:10px;'>" . htmlspecialchars($col['Type']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<div style='margin-top:30px;text-align:center;'>";
echo "<a href='diagnostic_erreur.php'>🔍 Tester à nouveau</a>";
echo "<a href='../index.php'>🏠 Retour à l'accueil</a>";
echo "</div>";

echo "</body></html>";
?>
