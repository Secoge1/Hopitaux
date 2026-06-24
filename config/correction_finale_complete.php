<?php
/**
 * Correction finale - Toutes les tables et colonnes manquantes
 */

require_once __DIR__ . '/database_production.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Correction finale</title>";
echo "<style>body{font-family:Arial;padding:30px;background:#1e1e1e;color:#00ff00;}";
echo ".log{background:#2d2d2d;padding:15px;margin:10px 0;border-radius:5px;border:1px solid #00ff00;}";
echo ".success{color:#00ff00;} .error{color:#ff0000;} .info{color:#00aaff;} .warning{color:#ffaa00;}";
echo "a{background:#00ff00;color:#1e1e1e;padding:12px 24px;text-decoration:none;border-radius:5px;margin:10px 5px;display:inline-block;font-weight:bold;}</style></head><body>";

echo "<h1>🔧 Correction finale - Toutes les tables manquantes</h1><div class='log'>";

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo "<span class='error'>❌ Erreur de connexion</span>";
    exit;
}

$conn->exec("SET FOREIGN_KEY_CHECKS = 0");

// 1. Ajouter la colonne nom_commercial à medicaments
echo "<span class='info'>📋 Vérification de la table medicaments...</span><br>";
try {
    $stmt = $conn->query("DESCRIBE medicaments");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('nom_commercial', $columns)) {
        echo "<span class='warning'>➕ Ajout de la colonne 'nom_commercial'...</span><br>";
        $conn->exec("ALTER TABLE `medicaments` ADD COLUMN `nom_commercial` varchar(200) DEFAULT NULL AFTER `nom`");
        echo "<span class='success'>✅ Colonne 'nom_commercial' ajoutée</span><br>";
    } else {
        echo "<span class='success'>✅ Colonne 'nom_commercial' existe déjà</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Erreur: " . $e->getMessage() . "</span><br>";
}

echo "<br>";

// 2. Créer la table comptes_comptables
echo "<span class='info'>💰 Création de la table 'comptes_comptables'...</span><br>";
try {
    $conn->exec("DROP TABLE IF EXISTS `comptes_comptables`");
    $sql = "CREATE TABLE `comptes_comptables` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `numero_compte` varchar(20) NOT NULL,
      `nom_compte` varchar(200) NOT NULL,
      `type_compte` enum('actif','passif','produit','charge','capitaux') NOT NULL,
      `categorie` varchar(100) DEFAULT NULL,
      `solde_debit` decimal(15,2) DEFAULT 0.00,
      `solde_credit` decimal(15,2) DEFAULT 0.00,
      `solde_actuel` decimal(15,2) GENERATED ALWAYS AS (`solde_debit` - `solde_credit`) STORED,
      `description` text DEFAULT NULL,
      `actif` tinyint(1) DEFAULT 1,
      `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `numero_compte` (`numero_compte`),
      KEY `idx_type` (`type_compte`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($sql);
    echo "<span class='success'>✅ Table 'comptes_comptables' créée</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Erreur: " . $e->getMessage() . "</span><br>";
}

// 3. Créer la table contrats_assurance
echo "<span class='info'>🏥 Création de la table 'contrats_assurance'...</span><br>";
try {
    $conn->exec("DROP TABLE IF EXISTS `contrats_assurance`");
    $sql = "CREATE TABLE `contrats_assurance` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `patient_id` int(11) NOT NULL,
      `assurance_id` int(11) NOT NULL,
      `numero_contrat` varchar(100) NOT NULL,
      `numero_adherent` varchar(100) DEFAULT NULL,
      `date_debut` date NOT NULL,
      `date_fin` date DEFAULT NULL,
      `taux_remboursement` decimal(5,2) DEFAULT 0.00,
      `plafond_annuel` decimal(10,2) DEFAULT NULL,
      `franchise` decimal(10,2) DEFAULT 0.00,
      `statut` enum('actif','suspendu','expire','resilie') DEFAULT 'actif',
      `notes` text DEFAULT NULL,
      `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `numero_contrat` (`numero_contrat`),
      KEY `idx_patient` (`patient_id`),
      KEY `idx_assurance` (`assurance_id`),
      KEY `idx_statut` (`statut`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($sql);
    echo "<span class='success'>✅ Table 'contrats_assurance' créée</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Erreur: " . $e->getMessage() . "</span><br>";
}

// 4. Créer la table equipements
echo "<span class='info'>🔧 Création de la table 'equipements'...</span><br>";
try {
    $conn->exec("DROP TABLE IF EXISTS `equipements`");
    $sql = "CREATE TABLE `equipements` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `code_equipement` varchar(50) NOT NULL,
      `nom` varchar(200) NOT NULL,
      `type_equipement` enum('medical','informatique','mobilier','autre') DEFAULT 'medical',
      `marque` varchar(100) DEFAULT NULL,
      `modele` varchar(100) DEFAULT NULL,
      `numero_serie` varchar(100) DEFAULT NULL,
      `date_acquisition` date DEFAULT NULL,
      `date_mise_en_service` date DEFAULT NULL,
      `valeur_acquisition` decimal(10,2) DEFAULT NULL,
      `fournisseur` varchar(200) DEFAULT NULL,
      `emplacement` varchar(200) DEFAULT NULL,
      `etat` enum('excellent','bon','moyen','mauvais','hors_service') DEFAULT 'bon',
      `date_derniere_maintenance` date DEFAULT NULL,
      `date_prochaine_maintenance` date DEFAULT NULL,
      `statut` enum('actif','inactif','en_maintenance','reforme') DEFAULT 'actif',
      `notes` text DEFAULT NULL,
      `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `code_equipement` (`code_equipement`),
      KEY `idx_type` (`type_equipement`),
      KEY `idx_etat` (`etat`),
      KEY `idx_statut` (`statut`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($sql);
    echo "<span class='success'>✅ Table 'equipements' créée</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Erreur: " . $e->getMessage() . "</span><br>";
}

$conn->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "<br><br><span class='success' style='font-size:20px;'>🎉 CORRECTION FINALE TERMINÉE !</span><br><br>";
echo "<span class='info'>Tables créées/corrigées :</span><br>";
echo "<span class='success'>✓ medicaments (colonne nom_commercial ajoutée)</span><br>";
echo "<span class='success'>✓ comptes_comptables (Finances)</span><br>";
echo "<span class='success'>✓ contrats_assurance (Assurances)</span><br>";
echo "<span class='success'>✓ equipements (Maintenance)</span><br>";

echo "</div><div style='text-align:center;margin-top:30px;'>";
echo "<a href='diagnostic_auto.php'>🔍 Vérifier tout</a>";
echo "<a href='../index.php' style='background:#00ff00;'>🏠 Accueil</a>";
echo "</div></body></html>";
?>
