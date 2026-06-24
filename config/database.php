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
        // Sinon, créer la classe avec la config locale
        class Database {
            private $host = 'localhost';
            private $db_name = 'cp2640311p29_efficasante';
            private $username = 'root';
            private $password = '';
            private $conn;

            public function getConnection() {
                $this->conn = null;

                try {
                    $this->conn = new PDO(
                        "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
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
