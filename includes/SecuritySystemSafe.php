<?php
/**
 * Système de Sécurité Renforcé - Version Sécurisée
 * Protection contre les attaques et amélioration de la sécurité
 * Version qui n'utilise jamais de clés étrangères pour éviter les erreurs
 */

class SecuritySystemSafe {
    private static $instance = null;
    private $pdo;
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes
    private $sessionTimeout = 28800; // 8 heures
    
    private function __construct() {
        $this->pdo = $this->getConnection();
        $this->createSecurityTables();
        $this->initSecurityHeaders();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtenir la connexion à la base de données
     */
    private function getConnection() {
        try {
            return new PDO(
                "mysql:host=localhost;dbname=efficasante;charset=utf8",
                "root",
                ""
            );
        } catch(PDOException $e) {
            return null;
        }
    }
    
    /**
     * Créer les tables de sécurité (sans clés étrangères)
     */
    private function createSecurityTables() {
        if (!$this->pdo) return;
        
        // Table des tentatives de connexion
        $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(100) NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success BOOLEAN DEFAULT FALSE,
            INDEX idx_ip_time (ip_address, attempt_time),
            INDEX idx_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
        
        // Table des sessions actives (sans clé étrangère)
        $sql = "CREATE TABLE IF NOT EXISTS active_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL UNIQUE,
            user_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            INDEX idx_user (user_id),
            INDEX idx_last_activity (last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
        
        // Table des activités suspectes
        $sql = "CREATE TABLE IF NOT EXISTS suspicious_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            activity_type VARCHAR(100) NOT NULL,
            details TEXT,
            risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_type (ip_address, activity_type),
            INDEX idx_risk_level (risk_level),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Initialiser les en-têtes de sécurité
     */
    private function initSecurityHeaders() {
        // Protection XSS
        header('X-XSS-Protection: 1; mode=block');
        
        // Protection contre le clickjacking
        header('X-Frame-Options: DENY');
        
        // Protection contre le MIME sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Référer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        require_once __DIR__ . '/security_csp.php';
        header('Content-Security-Policy: ' . app_content_security_policy());
    }
    
    /**
     * Vérifier si une IP est bloquée
     */
    public function isIPBlocked($ipAddress) {
        if (!$this->pdo) return false;
        
        try {
            $sql = "SELECT COUNT(*) as attempts FROM login_attempts 
                    WHERE ip_address = ? 
                    AND success = FALSE 
                    AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ipAddress, $this->lockoutDuration]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result && $result['attempts'] >= $this->maxLoginAttempts;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Enregistrer une tentative de connexion
     */
    public function recordLoginAttempt($ipAddress, $username, $success) {
        if (!$this->pdo) return false;
        
        try {
            $sql = "INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$ipAddress, $username, $success]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Nettoyer les anciennes tentatives de connexion
     */
    public function cleanupOldLoginAttempts() {
        if (!$this->pdo) return false;
        
        try {
            $sql = "DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Enregistrer une session active
     */
    public function recordActiveSession($sessionId, $userId, $ipAddress, $userAgent) {
        if (!$this->pdo) return false;
        
        try {
            // Supprimer l'ancienne session si elle existe
            $sql = "DELETE FROM active_sessions WHERE session_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$sessionId]);
            
            // Créer la nouvelle session
            $sql = "INSERT INTO active_sessions (session_id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$sessionId, $userId, $ipAddress, $userAgent]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Mettre à jour l'activité d'une session
     */
    public function updateSessionActivity($sessionId) {
        if (!$this->pdo) return false;
        
        try {
            $sql = "UPDATE active_sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$sessionId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Supprimer une session
     */
    public function removeSession($sessionId) {
        if (!$this->pdo) return false;
        
        try {
            $sql = "DELETE FROM active_sessions WHERE session_id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$sessionId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Nettoyer les sessions expirées
     */
    public function cleanupExpiredSessions() {
        if (!$this->pdo) return false;
        
        try {
            $sql = "DELETE FROM active_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$this->sessionTimeout]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Enregistrer une activité suspecte
     */
    public function recordSuspiciousActivity($ipAddress, $activityType, $details, $riskLevel = 'medium') {
        if (!$this->pdo) return false;
        
        try {
            $sql = "INSERT INTO suspicious_activities (ip_address, activity_type, details, risk_level) VALUES (?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$ipAddress, $activityType, $details, $riskLevel]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Vérifier la validité d'une session
     */
    public function validateSession($sessionId, $userId) {
        if (!$this->pdo) return false;
        
        try {
            $sql = "SELECT * FROM active_sessions WHERE session_id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$sessionId, $userId]);
            
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                return false;
            }
            
            // Vérifier l'expiration
            if (strtotime($session['last_activity']) < (time() - $this->sessionTimeout)) {
                $this->removeSession($sessionId);
                return false;
            }
            
            // Mettre à jour l'activité
            $this->updateSessionActivity($sessionId);
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Vérifier la sécurité d'une requête
     */
    public function validateRequest($requestData) {
        $suspicious = false;
        $riskLevel = 'low';
        
        // Vérifier les injections SQL basiques
        $sqlPatterns = [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/delete\s+from/i',
            '/insert\s+into/i',
            '/update\s+set/i',
            '/exec\s*\(/i',
            '/eval\s*\(/i'
        ];
        
        foreach ($requestData as $key => $value) {
            if (is_string($value)) {
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $suspicious = true;
                        $riskLevel = 'high';
                        break 2;
                    }
                }
                
                // Vérifier les scripts XSS
                if (preg_match('/<script|javascript:|vbscript:|onload=|onerror=/i', $value)) {
                    $suspicious = true;
                    $riskLevel = 'medium';
                }
            }
        }
        
        if ($suspicious) {
            $this->recordSuspiciousActivity(
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'suspicious_request',
                'Request data: ' . json_encode($requestData),
                $riskLevel
            );
        }
        
        return !$suspicious;
    }
    
    /**
     * Générer un token CSRF sécurisé
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Vérifier un token CSRF
     */
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Valider et nettoyer les entrées utilisateur
     */
    public function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            $cleaned = [];
            foreach ($input as $key => $value) {
                $cleaned[$key] = $this->sanitizeInput($value, $type);
            }
            return $cleaned;
        }
        
        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
                
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
                
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Valider un mot de passe
     */
    public function validatePassword($password) {
        // Au moins 8 caractères
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères'];
        }
        
        // Au moins une lettre majuscule
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'message' => 'Le mot de passe doit contenir au moins une lettre majuscule'];
        }
        
        // Au moins une lettre minuscule
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'message' => 'Le mot de passe doit contenir au moins une lettre minuscule'];
        }
        
        // Au moins un chiffre
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Le mot de passe doit contenir au moins un chiffre'];
        }
        
        // Au moins un caractère spécial
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Le mot de passe doit contenir au moins un caractère spécial'];
        }
        
        return ['valid' => true, 'message' => 'Mot de passe valide'];
    }
    
    /**
     * Hacher un mot de passe de manière sécurisée
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Vérifier un mot de passe
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Obtenir les statistiques de sécurité
     */
    public function getSecurityStats() {
        if (!$this->pdo) return [];
        
        try {
            $stats = [];
            
            // Tentatives de connexion échouées aujourd'hui
            $sql = "SELECT COUNT(*) as count FROM login_attempts 
                    WHERE success = FALSE AND DATE(attempt_time) = CURDATE()";
            $stmt = $this->pdo->query($sql);
            $stats['failed_logins_today'] = $stmt->fetch()['count'];
            
            // Sessions actives
            $sql = "SELECT COUNT(*) as count FROM active_sessions";
            $stmt = $this->pdo->query($sql);
            $stats['active_sessions'] = $stmt->fetch()['count'];
            
            // Activités suspectes aujourd'hui
            $sql = "SELECT COUNT(*) as count FROM suspicious_activities 
                    WHERE DATE(created_at) = CURDATE()";
            $stmt = $this->pdo->query($sql);
            $stats['suspicious_activities_today'] = $stmt->fetch()['count'];
            
            // Activités à haut risque
            $sql = "SELECT COUNT(*) as count FROM suspicious_activities 
                    WHERE risk_level IN ('high', 'critical') AND DATE(created_at) = CURDATE()";
            $stmt = $this->pdo->query($sql);
            $stats['high_risk_activities_today'] = $stmt->fetch()['count'];
            
            return $stats;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Nettoyer les anciennes données de sécurité
     */
    public function cleanupOldSecurityData() {
        if (!$this->pdo) return false;
        
        try {
            // Nettoyer les tentatives de connexion anciennes
            $this->cleanupOldLoginAttempts();
            
            // Nettoyer les sessions expirées
            $this->cleanupExpiredSessions();
            
            // Nettoyer les activités suspectes anciennes (plus de 30 jours)
            $sql = "DELETE FROM suspicious_activities WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>




