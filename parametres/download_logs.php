<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/SystemLogs.php';

$logs = new SystemLogs();

if (isset($_GET['file'])) {
    $filename = $_GET['file'];
    $filepath = '../backups/' . $filename;
    
    // Vérifier que le fichier existe et est un fichier de journaux
    if (file_exists($filepath) && strpos($filename, 'system_logs_') === 0) {
        // Enregistrer le téléchargement dans les journaux
        $logs->addLog('download', 'Téléchargement des journaux CSV: ' . $filename);
        
        // Définir les en-têtes pour le téléchargement
        $filesize = filesize($filepath);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Lire et envoyer le fichier
        readfile($filepath);
        exit;
    } else {
        // Enregistrer l'erreur dans les journaux
        $logs->addLog('error', 'Tentative de téléchargement de fichier invalide: ' . $filename);
        
        // Rediriger avec un message d'erreur
        header('Location: journaux.php?error=' . urlencode('Fichier invalide ou introuvable'));
        exit;
    }
} else {
    // Aucun fichier spécifié
    header('Location: journaux.php?error=' . urlencode('Aucun fichier spécifié'));
    exit;
}
?>
