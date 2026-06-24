<?php
/**
 * Recréation complète de la table messages_internes avec TOUTES les colonnes
 */

require_once __DIR__ . '/database_production.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Recréation table messages</title>";
echo "<style>body{font-family:Arial;padding:30px;background:#f5f5f5;}";
echo ".success{color:#28a745;font-weight:bold;font-size:18px;padding:20px;background:white;border-radius:8px;margin:10px 0;}";
echo ".error{color:#dc3545;font-weight:bold;font-size:18px;padding:20px;background:white;border-radius:8px;margin:10px 0;}";
echo ".warning{color:#856404;background:#fff3cd;padding:15px;border-radius:8px;margin:10px 0;}";
echo ".info{color:#0c5460;background:#d1ecf1;padding:15px;border-radius:8px;margin:10px 0;}";
echo "a{display:inline-block;background:#667eea;color:white;padding:12px 24px;text-decoration:none;border-radius:8px;margin:10px 5px;}</style></head><body>";

echo "<h1>🔄 Recréation complète de la table messages_internes</h1>";

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo "<div class='error'>❌ Erreur de connexion</div></body></html>";
    exit;
}

try {
    echo "<div class='warning'>⚠️ Suppression de l'ancienne table...</div>";
    
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    $conn->exec("DROP TABLE IF EXISTS `messages_internes`");
    
    echo "<div class='info'>📋 Création de la nouvelle table avec TOUTES les colonnes...</div>";
    
    // Créer la table complète avec toutes les colonnes nécessaires
    $sql = "CREATE TABLE `messages_internes` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `expediteur_id` int(11) NOT NULL,
      `destinataire_id` int(11) DEFAULT NULL,
      `destinataire_role` enum('tous','medecins','infirmiers','secretaires','admins') DEFAULT NULL,
      `sujet` varchar(200) NOT NULL,
      `message` text NOT NULL,
      `lu` tinyint(1) DEFAULT 0,
      `date_lecture` datetime DEFAULT NULL,
      `archive` tinyint(1) DEFAULT 0,
      `priorite` enum('normale','haute','urgente') DEFAULT 'normale',
      `piece_jointe` varchar(255) DEFAULT NULL,
      `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
      `date_modification` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_expediteur` (`expediteur_id`),
      KEY `idx_destinataire` (`destinataire_id`),
      KEY `idx_destinataire_role` (`destinataire_role`),
      KEY `idx_lu` (`lu`),
      KEY `idx_archive` (`archive`),
      KEY `idx_date` (`date_creation`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<div class='success'>✅ Table créée avec succès !</div>";
    
    // Vérifier la structure
    echo "<div class='info'><strong>📊 Structure de la table :</strong><br><br>";
    $stmt = $conn->query("DESCRIBE messages_internes");
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table style='width:100%;border-collapse:collapse;background:white;'>";
    echo "<tr style='background:#667eea;color:white;'>";
    echo "<th style='padding:10px;text-align:left;'>Colonne</th>";
    echo "<th style='padding:10px;text-align:left;'>Type</th>";
    echo "<th style='padding:10px;text-align:left;'>Null</th>";
    echo "<th style='padding:10px;text-align:left;'>Défaut</th>";
    echo "</tr>";
    
    foreach ($structure as $col) {
        echo "<tr style='border-bottom:1px solid #ddd;'>";
        echo "<td style='padding:10px;'><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td style='padding:10px;font-family:monospace;'>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td style='padding:10px;'>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td style='padding:10px;'>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    // Liste des colonnes créées
    $colonnes = array_column($structure, 'Field');
    echo "<div class='success'>";
    echo "✅ <strong>Colonnes créées (" . count($colonnes) . ") :</strong><br><br>";
    echo implode(', ', $colonnes);
    echo "</div>";
    
    echo "<div class='success' style='font-size:20px;'>";
    echo "🎉 TERMINÉ !<br><br>";
    echo "La table <strong>messages_internes</strong> est maintenant complète avec toutes les colonnes nécessaires :<br>";
    echo "✓ expediteur_id<br>";
    echo "✓ destinataire_id<br>";
    echo "✓ destinataire_role (NOUVELLE)<br>";
    echo "✓ archive (NOUVELLE)<br>";
    echo "✓ lu, sujet, message, priorite, etc.<br><br>";
    echo "Le module Communication devrait maintenant fonctionner parfaitement !";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<div style='margin-top:30px;text-align:center;'>";
echo "<a href='diagnostic_erreur.php'>🔍 Tester à nouveau</a>";
echo "<a href='../index.php' style='background:#28a745;'>🏠 Retour à l'accueil</a>";
echo "</div>";

echo "</body></html>";
?>
