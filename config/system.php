<?php
/**
 * Configuration système - Paramètres dynamiques
 * Permet de récupérer les paramètres stockés en base de données
 */

require_once __DIR__ . '/db.php';

/**
 * Récupère un paramètre système
 * @param string $key La clé du paramètre
 * @param mixed $default Valeur par défaut si le paramètre n'existe pas
 * @return mixed La valeur du paramètre ou la valeur par défaut
 */
function getSystemParam($key, $default = null) {
    static $params = null;
    
    // Cache les paramètres pour éviter les requêtes multiples
    if ($params === null) {
        try {
            $pdo = getDB();
            
            // Créer la table si elle n'existe pas
            $sql = "CREATE TABLE IF NOT EXISTS parametres_systeme (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cle VARCHAR(100) UNIQUE NOT NULL,
                valeur TEXT,
                description TEXT,
                date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $pdo->exec($sql);
            
            // Récupérer tous les paramètres
            $stmt = $pdo->query("SELECT cle, valeur FROM parametres_systeme");
            $params = [];
            while ($row = $stmt->fetch()) {
                $params[$row['cle']] = $row['valeur'];
            }
        } catch (Exception $e) {
            $params = [];
        }
    }
    
    return $params[$key] ?? $default;
}

/**
 * Définit un paramètre système
 * @param string $key La clé du paramètre
 * @param mixed $value La valeur du paramètre
 * @param string $description Description du paramètre
 * @return bool True si succès, false sinon
 */
function setSystemParam($key, $value, $description = '') {
    try {
        $pdo = getDB();
        
        $sql = "INSERT INTO parametres_systeme (cle, valeur, description) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE valeur = ?, description = ?";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$key, $value, $description, $value, $description]);
        
        // Mettre à jour le cache
        if ($result) {
            static $params = null;
            if ($params !== null) {
                $params[$key] = $value;
            }
        }
        
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Récupère l'identité de l'établissement
 * @return array Informations sur l'établissement
 */
function getEtablissementInfo() {
    return [
        'nom' => getSystemParam('nom_etablissement', 'Clinique et Hôpital'),
        'adresse' => getSystemParam('adresse', ''),
        'ville' => getSystemParam('ville', ''),
        'telephone' => getSystemParam('telephone', ''),
        'email' => getSystemParam('email', ''),
        'logo' => getSystemParam('logo', '')
    ];
}

/**
 * Récupère la configuration de la devise
 * @return array Configuration de la devise
 */
function getCurrencyConfig() {
    return [
        'code' => getSystemParam('devise_code', 'XOF'),
        'symbol' => getSystemParam('devise_symbole', 'FCFA'),
        'decimals' => (int)getSystemParam('devise_decimaux', 0),
        'name' => getSystemParam('devise_name', 'Franc CFA')
    ];
}

/**
 * Récupère les paramètres système généraux
 * @return array Paramètres système
 */
function getSystemConfig() {
    return [
        'langue' => getSystemParam('langue', 'fr'),
        'theme' => getSystemParam('theme', 'default'),
        'timezone' => getSystemParam('timezone', 'Africa/Abidjan'),
        'date_format' => getSystemParam('date_format', 'd/m/Y'),
        'time_format' => getSystemParam('time_format', 'H:i')
    ];
}

/**
 * Formate un montant selon la devise configurée
 * @param float $amount Le montant à formater
 * @param bool $showSymbol Afficher le symbole de la devise
 * @return string Le montant formaté
 */
function formatSystemCurrency($amount, $showSymbol = true) {
    $currency = getCurrencyConfig();
    
    $formattedAmount = number_format($amount, $currency['decimals'], ',', ' ');
    
    if ($showSymbol) {
        return $currency['symbol'] . ' ' . $formattedAmount;
    }
    
    return $formattedAmount;
}

/**
 * Récupère tous les paramètres système
 * @return array Tous les paramètres
 */
function getAllSystemParams() {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT cle, valeur, description FROM parametres_systeme ORDER BY cle");
        $params = [];
        while ($row = $stmt->fetch()) {
            $params[$row['cle']] = [
                'valeur' => $row['valeur'],
                'description' => $row['description']
            ];
        }
        return $params;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Supprime un paramètre système
 * @param string $key La clé du paramètre à supprimer
 * @return bool True si succès, false sinon
 */
function deleteSystemParam($key) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("DELETE FROM parametres_systeme WHERE cle = ?");
        $result = $stmt->execute([$key]);
        
        // Mettre à jour le cache
        if ($result) {
            static $params = null;
            if ($params !== null && isset($params[$key])) {
                unset($params[$key]);
            }
        }
        
        return $result;
    } catch (Exception $e) {
        return false;
    }
}
?>




