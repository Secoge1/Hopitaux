<?php
// Configuration du fuseau horaire Afrique de l'Ouest
date_default_timezone_set('Africa/Dakar');

/**
 * Configuration de la base de données - Version unifiée
 * Utilise database_production.php si disponible, sinon configuration locale
 */

// Éviter la redéclaration de la classe
if (!class_exists('Database')) {
    
    // Si database_production.php existe, l'utiliser
    $prodFile = __DIR__ . '/database_production.php';
    if (file_exists($prodFile)) {
        require_once $prodFile;
    } else {
        // Configuration via constantes DB_* (db.php / db.pharma.production.php)
        class Database {
            private $host;
            private $db_name;
            private $username;
            private $password;
            private $conn;

            public function __construct()
            {
                $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
                $this->db_name = defined('DB_NAME') ? DB_NAME : 'cp2640311p29_efficasante';
                $this->username = defined('DB_USER') ? DB_USER : 'root';
                $this->password = defined('DB_PASS') ? DB_PASS : '';
            }

            public function getConnection() {
                $this->conn = null;

                try {
                    $this->conn = new PDO(
                        "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                        $this->username,
                        $this->password
                    );
                    $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                } catch(PDOException $exception) {
                    echo "Erreur de connexion: " . $exception->getMessage();
                }

                return $this->conn;
            }
        }
    }
}
