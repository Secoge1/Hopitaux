<?php
/**
 * Création de la table messages_internes
 */

require_once __DIR__ . '/database_production.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Création table messages</title>";
echo "<style>body{font-family:Arial;padding:30px;background:#f5f5f5;}";
echo ".success{color:#28a745;font-weight:bold;font-size:18px;padding:20px;background:white;border-radius:8px;margin:10px 0;}";
echo ".error{color:#dc3545;font-weight:bold;font-size:18px;padding:20px;background:white;border-radius:8px;margin:10px 0;}";
echo "a{display:inline-block;background:#667eea;color:white;padding:12px 24px;text-decoration:none;border-radius:8px;margin:10px 5px;}</style></head><body>";

echo "<h1>📧 Création de la table messages_internes</h1>";

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo "<div class='error'>❌ Erreur de connexion</div></body></html>";
    exit;
}

try {
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Créer la table messages_internes
    $sql = "CREATE TABLE IF NOT EXISTS `messages_internes` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `expediteur_id` int(11) NOT NULL,
      `destinataire_id` int(11) NOT NULL,
      `sujet` varchar(200) NOT NULL,
      `message` text NOT NULL,
      `lu` tinyint(1) DEFAULT 0,
      `date_lecture` datetime DEFAULT NULL,
      `priorite` enum('normale','haute','urgente') DEFAULT 'normale',
      `piece_jointe` varchar(255) DEFAULT NULL,
      `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_expediteur` (`expediteur_id`),
      KEY `idx_destinataire` (`destinataire_id`),
      KEY `idx_lu` (`lu`),
      KEY `idx_date` (`date_creation`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<div class='success'>";
    echo "✅ SUCCÈS !<br><br>";
    echo "La table <strong>messages_internes</strong> a été créée avec succès.<br><br>";
    echo "🎉 Le module Communication fonctionne maintenant !";
    echo "</div>";
    
    // Vérifier que la table existe
    $stmt = $conn->query("SHOW TABLES LIKE 'messages_internes'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='success'>✅ Vérification : La table existe bien dans la base de données</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<div style='margin-top:30px;text-align:center;'>";
echo "<a href='diagnostic_erreur.php'>🔍 Tester à nouveau</a>";
echo "<a href='../index.php'>🏠 Retour à l'accueil</a>";
echo "</div>";

echo "</body></html>";
?>
