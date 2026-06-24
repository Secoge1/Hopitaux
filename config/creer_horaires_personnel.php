<?php
/**
 * Ajout rapide de la table horaires_personnel
 */

require_once __DIR__ . '/database_production.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Ajout horaires_personnel</title>";
echo "<style>body{font-family:Arial;padding:30px;background:#f5f5f5;}";
echo ".success{color:#28a745;font-weight:bold;font-size:18px;padding:20px;background:white;border-radius:8px;margin:10px 0;}";
echo ".error{color:#dc3545;font-weight:bold;font-size:18px;padding:20px;background:white;border-radius:8px;margin:10px 0;}";
echo "a{display:inline-block;background:#667eea;color:white;padding:12px 24px;text-decoration:none;border-radius:8px;margin:10px 5px;}</style></head><body>";

echo "<h1>➕ Création de la table horaires_personnel</h1>";

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo "<div class='error'>❌ Erreur de connexion</div></body></html>";
    exit;
}

try {
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Créer la table horaires_personnel
    $sql = "CREATE TABLE IF NOT EXISTS `horaires_personnel` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `personnel_id` int(11) NOT NULL,
      `jour_semaine` enum('lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche') NOT NULL,
      `heure_debut` time NOT NULL,
      `heure_fin` time NOT NULL,
      `pause_debut` time DEFAULT NULL,
      `pause_fin` time DEFAULT NULL,
      `actif` tinyint(1) DEFAULT 1,
      `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_personnel` (`personnel_id`),
      KEY `idx_jour` (`jour_semaine`),
      KEY `idx_actif` (`actif`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<div class='success'>";
    echo "✅ SUCCÈS !<br><br>";
    echo "La table <strong>horaires_personnel</strong> a été créée avec succès.<br><br>";
    echo "🎉 Le module Personnel fonctionne maintenant complètement !";
    echo "</div>";
    
    // Vérifier que la table existe
    $stmt = $conn->query("SHOW TABLES LIKE 'horaires_personnel'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='success'>✅ Vérification : La table existe bien</div>";
        
        // Afficher la structure
        echo "<div style='background:white;padding:20px;border-radius:8px;margin:20px 0;'>";
        echo "<h3>📋 Structure de la table :</h3>";
        $stmt = $conn->query("DESCRIBE horaires_personnel");
        $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<table style='width:100%;border-collapse:collapse;'>";
        echo "<tr style='background:#667eea;color:white;'><th style='padding:10px;text-align:left;'>Colonne</th><th style='padding:10px;text-align:left;'>Type</th></tr>";
        foreach ($structure as $col) {
            echo "<tr style='border-bottom:1px solid #ddd;'>";
            echo "<td style='padding:10px;'>" . htmlspecialchars($col['Field']) . "</td>";
            echo "<td style='padding:10px;font-family:monospace;'>" . htmlspecialchars($col['Type']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<div style='margin-top:30px;text-align:center;'>";
echo "<a href='../personnel/voir.php?id=1'>🔍 Tester Personnel</a>";
echo "<a href='../index.php'>🏠 Retour à l'accueil</a>";
echo "</div>";

echo "</body></html>";
?>
