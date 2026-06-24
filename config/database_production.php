<?php
/**
 * Configuration de la base de données pour la production
 * À renommer en database.php après l'upload
 */

class Database {
    private $host = "localhost"; // Votre serveur MySQL
    private $db_name = "cp2640311p29_efficasante"; // Votre nom de base
    private $username = "root"; // Votre utilisateur
    private $password = ""; // À remplacer par votre mot de passe
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                                $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch(PDOException $exception) {
            echo "Erreur de connexion : " . $exception->getMessage();
        }

        return $this->conn;
    }
}
