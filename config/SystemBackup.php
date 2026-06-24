<?php
/**
 * Classe SystemBackup
 * Gestion des sauvegardes du système
 */

class SystemBackup {
    private $pdo;
    private $backupDir;
    private $maxBackups = 10; // Nombre maximum de sauvegardes à conserver
    
    public function __construct() {
        $this->pdo = $this->getConnection();
        $this->backupDir = '../backups/';
        $this->createBackupDirectory();
    }
    
    /**
     * Obtenir la connexion à la base de données
     */
    private function getConnection() {
        try {
            if (!function_exists('getDB')) {
                require_once __DIR__ . '/db.php';
            }
            return getDB();
        } catch (Exception $e) {
            error_log('SystemBackup connexion: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Créer le répertoire de sauvegarde s'il n'existe pas
     */
    private function createBackupDirectory() {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        
        // Créer un fichier .htaccess pour sécuriser le dossier
        $htaccess = $this->backupDir . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order Deny,Allow\nDeny from all");
        }
    }
    
    /**
     * Créer une sauvegarde complète de la base de données
     */
    public function createDatabaseBackup() {
        if (!$this->pdo) {
            return ['success' => false, 'message' => 'Impossible de se connecter à la base de données'];
        }
        
        try {
            // Obtenir la liste des tables
            $tables = [];
            $stmt = $this->pdo->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            if (empty($tables)) {
                return ['success' => false, 'message' => 'Aucune table trouvée dans la base de données'];
            }
            
            $backup = "-- Sauvegarde de la base de données clinique_hopital\n";
            $backup .= "-- Date de création : " . date('Y-m-d H:i:s') . "\n";
            $backup .= "-- Version : 1.0\n\n";
            
            // Désactiver les contraintes de clés étrangères
            $backup .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
            
            foreach ($tables as $table) {
                // Structure de la table
                $stmt = $this->pdo->query("SHOW CREATE TABLE `$table`");
                $row = $stmt->fetch(PDO::FETCH_NUM);
                $backup .= $row[1] . ";\n\n";
                
                // Données de la table
                $stmt = $this->pdo->query("SELECT * FROM `$table`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $backup .= "-- Données pour la table `$table`\n";
                    foreach ($rows as $row) {
                        $backup .= "INSERT INTO `$table` VALUES (";
                        $values = [];
                        foreach ($row as $value) {
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = "'" . addslashes($value) . "'";
                            }
                        }
                        $backup .= implode(', ', $values) . ");\n";
                    }
                    $backup .= "\n";
                }
            }
            
            // Réactiver les contraintes de clés étrangères
            $backup .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            
            // Générer le nom du fichier
            $filename = 'backup_db_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $this->backupDir . $filename;
            
            // Sauvegarder le fichier
            if (file_put_contents($filepath, $backup)) {
                // Nettoyer les anciennes sauvegardes
                $this->cleanOldBackups();
                
                // Enregistrer dans les journaux
                $this->logBackup('database', $filename, filesize($filepath));
                
                return [
                    'success' => true, 
                    'message' => 'Sauvegarde de la base de données créée avec succès',
                    'filename' => $filename,
                    'size' => $this->formatBytes(filesize($filepath)),
                    'path' => $filepath
                ];
            } else {
                return ['success' => false, 'message' => 'Erreur lors de l\'écriture du fichier de sauvegarde'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage()];
        }
    }
    
    /**
     * Créer une sauvegarde des fichiers uploadés
     */
    public function createFilesBackup() {
        $uploadsDir = '../uploads/';
        if (!is_dir($uploadsDir)) {
            return ['success' => false, 'message' => 'Le répertoire uploads n\'existe pas'];
        }
        
        try {
            $filename = 'backup_files_' . date('Y-m-d_H-i-s') . '.zip';
            $filepath = $this->backupDir . $filename;
            
            $zip = new ZipArchive();
            if ($zip->open($filepath, ZipArchive::CREATE) !== TRUE) {
                return ['success' => false, 'message' => 'Impossible de créer l\'archive ZIP'];
            }
            
            $this->addFolderToZip($zip, $uploadsDir, 'uploads');
            $zip->close();
            
            // Nettoyer les anciennes sauvegardes
            $this->cleanOldBackups();
            
            // Enregistrer dans les journaux
            $this->logBackup('files', $filename, filesize($filepath));
            
            return [
                'success' => true, 
                'message' => 'Sauvegarde des fichiers créée avec succès',
                'filename' => $filename,
                'size' => $this->formatBytes(filesize($filepath)),
                'path' => $filepath
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la sauvegarde des fichiers : ' . $e->getMessage()];
        }
    }
    
    /**
     * Ajouter un dossier à l'archive ZIP
     */
    private function addFolderToZip($zip, $folder, $relativePath) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativeFilePath = $relativePath . '/' . substr($filePath, strlen($folder));
                $zip->addFile($filePath, $relativeFilePath);
            }
        }
    }
    
    /**
     * Nettoyer les anciennes sauvegardes
     */
    private function cleanOldBackups() {
        $files = glob($this->backupDir . 'backup_*');
        if (count($files) > $this->maxBackups) {
            // Trier par date de modification
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Supprimer les plus anciennes
            $toDelete = array_slice($files, 0, count($files) - $this->maxBackups);
            foreach ($toDelete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Lister toutes les sauvegardes disponibles
     */
    public function listBackups() {
        $backups = [];
        $files = glob($this->backupDir . 'backup_*');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $type = strpos($filename, 'backup_db_') === 0 ? 'database' : 'files';
            $date = date('Y-m-d H:i:s', filemtime($file));
            $size = $this->formatBytes(filesize($file));
            
            $backups[] = [
                'filename' => $filename,
                'type' => $type,
                'date' => $date,
                'size' => $size,
                'path' => $file
            ];
        }
        
        // Trier par date (plus récent en premier)
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $backups;
    }
    
    /**
     * Télécharger une sauvegarde
     */
    public function downloadBackup($filename) {
        $filepath = $this->backupDir . $filename;
        
        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'Fichier de sauvegarde introuvable'];
        }
        
        // Vérifier que c'est bien un fichier de sauvegarde
        if (strpos($filename, 'backup_') !== 0) {
            return ['success' => false, 'message' => 'Fichier non autorisé'];
        }
        
        return [
            'success' => true,
            'filepath' => $filepath,
            'filename' => $filename
        ];
    }
    
    /**
     * Supprimer une sauvegarde
     */
    public function deleteBackup($filename) {
        $filepath = $this->backupDir . $filename;
        
        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'Fichier de sauvegarde introuvable'];
        }
        
        // Vérifier que c'est bien un fichier de sauvegarde
        if (strpos($filename, 'backup_') !== 0) {
            return ['success' => false, 'message' => 'Fichier non autorisé'];
        }
        
        if (unlink($filepath)) {
            $this->logBackup('delete', $filename, 0);
            return ['success' => true, 'message' => 'Sauvegarde supprimée avec succès'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la suppression'];
        }
    }
    
    /**
     * Enregistrer une action de sauvegarde dans les journaux
     */
    private function logBackup($action, $filename, $size) {
        try {
            $sql = "INSERT INTO system_logs (action, details, user_id, ip_address, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $details = "Sauvegarde $action: $filename" . ($size > 0 ? " ($size bytes)" : "");
            $stmt->execute([$action, $details, $_SESSION['user_id'] ?? 1, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
        } catch (Exception $e) {
            // Ignorer les erreurs de journalisation
        }
    }
    
    /**
     * Formater la taille en bytes
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Obtenir les statistiques des sauvegardes
     */
    public function getBackupStats() {
        $backups = $this->listBackups();
        $totalSize = 0;
        $dbCount = 0;
        $filesCount = 0;
        
        foreach ($backups as $backup) {
            $filepath = $backup['path'];
            if (file_exists($filepath)) {
                $totalSize += filesize($filepath);
            }
            
            if ($backup['type'] === 'database') {
                $dbCount++;
            } else {
                $filesCount++;
            }
        }
        
        return [
            'total_backups' => count($backups),
            'database_backups' => $dbCount,
            'files_backups' => $filesCount,
            'total_size' => $this->formatBytes($totalSize),
            'last_backup' => !empty($backups) ? $backups[0]['date'] : 'Aucune'
        ];
    }
}
?>
