<?php
/**
 * Création des tables manquantes - Version garantie
 * Exécute chaque CREATE TABLE individuellement
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Création tables</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#1e1e1e;color:#00ff00;}";
echo ".log{background:#2d2d2d;padding:15px;margin:10px 0;border-radius:5px;border:1px solid #00ff00;}";
echo ".success{color:#00ff00;} .error{color:#ff0000;} .info{color:#00aaff;}";
echo "a{background:#00ff00;color:#1e1e1e;padding:12px 24px;text-decoration:none;border-radius:5px;margin:10px 5px;display:inline-block;font-weight:bold;}</style></head><body>";

echo "<h1>🔧 Création des 5 tables manquantes</h1><div class='log'>";

// Connexion
require_once __DIR__ . '/database_production.php';
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo "<span class='error'>❌ Erreur de connexion</span>";
    exit;
}

echo "<span class='success'>✅ Connecté à la base de données</span><br><br>";

// Désactiver les vérifications FK
$conn->exec("SET FOREIGN_KEY_CHECKS = 0");

// Table 1: personnel
echo "<span class='info'>📋 Création de la table 'personnel'...</span><br>";
try {
    $conn->exec("DROP TABLE IF EXISTS `personnel`");
    $sql = "CREATE TABLE `personnel` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `numero_employe` varchar(50) NOT NULL,
      `nom` varchar(100) NOT NULL,
      `prenom` varchar(100) NOT NULL,
      `date_naissance` date DEFAULT NULL,
      `sexe` enum('M','F') DEFAULT NULL,
      `telephone` varchar(20) DEFAULT NULL,
      `email` varchar(100) DEFAULT NULL,
      `adresse` text DEFAULT NULL,
      `ville` varchar(100) DEFAULT NULL,
      `code_postal` varchar(10) DEFAULT NULL,
      `pays` varchar(100) DEFAULT 'Mali',
      `poste` varchar(100) NOT NULL,
      `departement` varchar(100) DEFAULT NULL,
      `date_embauche` date NOT NULL,
      `salaire` decimal(10,2) DEFAULT NULL,
      `type_contrat` enum('CDI','CDD','Stage','Interim') DEFAULT 'CDI',
      `statut` enum('actif','inactif','suspendu','licencie') DEFAULT 'actif',
      `photo` varchar(255) DEFAULT NULL,
      `notes` text DEFAULT NULL,
      `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
      `date_modification` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `numero_employe` (`numero_employe`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($sql);
    echo "<span class='success'>✅ Table 'personnel' créée</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Erreur: " . $e->getMessage() . "</span><br>";
}

// Table 2: medicaments
echo "<span class='info'>💊 Création de la table 'medicaments'...</span><br>";
try {
    $conn->exec("DROP TABLE IF EXISTS `medicaments`");
    $sql = "CREATE TABLE `medicaments` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `code_medicament` varchar(50) NOT NULL,
      `nom` varchar(200) NOT NULL,
      `forme` enum('comprime','gelule','sirop','injectable','pommade','creme','suppositoire','autre') NOT NULL,
      `dosage` varchar(50) DEFAULT NULL,
      `fabricant` varchar(100) DEFAULT NULL,
      `prix_unitaire` decimal(10,2) NOT NULL,
      `prix_vente` decimal(10,2) NOT NULL,
      `stock_actuel` int(11) DEFAULT 0,
      `stock_minimum` int(11) DEFAULT 10,
      `date_peremption` date DEFAULT NULL,
      `statut` enum('actif','inactif','perime','rupture') DEFAULT 'actif',
      `notes` text DEFAULT NULL,
      `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `code_medicament` (`code_medicament`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($sql);
    echo "<span class='success'>✅ Table 'medicaments' créée</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Erreur: " . $e->getMessage() . "</span><br>";
}

// Table 3: budgets
echo "<span class='info'>💰 Création de la table 'budgets'...</span><br>";
try {
    $conn->exec("DROP TABLE IF EXISTS `budgets`");
    $sql = "CREATE TABLE `budgets` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `annee` year(4) NOT NULL,
      `departement` varchar(100) DEFAULT NULL,
      `categorie` varchar(100) NOT NULL,
      `montant_alloue` decimal(15,2) NOT NULL,
      `montant_utilise` decimal(15,2) DEFAULT 0.00,
      `statut` enum('planifie','approuve','en_cours','cloture') DEFAULT 'planifie',
      `notes` text DEFAULT NULL,
      `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($sql);
    echo "<span class='success'>✅ Table 'budgets' créée</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Erreur: " . $e->getMessage() . "</span><br>";
}

// Table 4: assurances
echo "<span class='info'>🏥 Création de la table 'assurances'...</span><br>";
try {
    $conn->exec("DROP TABLE IF EXISTS `assurances`");
    $sql = "CREATE TABLE `assurances` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `nom` varchar(200) NOT NULL,
      `type` enum('assurance','mutuelle','autre') DEFAULT 'assurance',
      `numero_agrement` varchar(100) DEFAULT NULL,
      `telephone` varchar(20) DEFAULT NULL,
      `email` varchar(100) DEFAULT NULL,
      `adresse` text DEFAULT NULL,
      `taux_remboursement` decimal(5,2) DEFAULT 0.00,
      `statut` enum('actif','inactif') DEFAULT 'actif',
      `notes` text DEFAULT NULL,
      `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($sql);
    echo "<span class='success'>✅ Table 'assurances' créée</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Erreur: " . $e->getMessage() . "</span><br>";
}

// Table 5: annonces
echo "<span class='info'>📢 Création de la table 'annonces'...</span><br>";
try {
    $conn->exec("DROP TABLE IF EXISTS `annonces`");
    $sql = "CREATE TABLE `annonces` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `titre` varchar(200) NOT NULL,
      `contenu` text NOT NULL,
      `type` enum('information','alerte','urgence','general') DEFAULT 'information',
      `destinataires` enum('tous','medecins','infirmiers','secretaires','admins') DEFAULT 'tous',
      `date_debut` datetime NOT NULL,
      `date_fin` datetime DEFAULT NULL,
      `actif` tinyint(1) DEFAULT 1,
      `cree_par` int(11) NOT NULL,
      `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($sql);
    echo "<span class='success'>✅ Table 'annonces' créée</span><br>";
} catch (Exception $e) {
    echo "<span class='error'>❌ Erreur: " . $e->getMessage() . "</span><br>";
}

// Réactiver les vérifications FK
$conn->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "<br><br><span class='success' style='font-size:20px;'>🎉 TERMINÉ !</span><br>";
echo "<span class='info'>Toutes les tables ont été créées.</span>";

echo "</div><div style='text-align:center;margin-top:30px;'>";
echo "<a href='diagnostic_auto.php'>🔍 Vérifier les tables</a>";
echo "<a href='../index.php'>🏠 Retour à l'accueil</a>";
echo "</div></body></html>";
?>
