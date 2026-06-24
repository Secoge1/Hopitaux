<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/SystemBackup.php';
require_once '../config/SystemLogs.php';

$backup = new SystemBackup();
$logs = new SystemLogs();

if (isset($_GET['file'])) {
    $filename = $_GET['file'];
    
    // Vérifier et télécharger la sauvegarde
    $result = $backup->downloadBackup($filename);
    
    if ($result['success']) {
        // Enregistrer le téléchargement dans les journaux
        $logs->addLog('download', 'Téléchargement de la sauvegarde: ' . $filename);
        
        // Définir les en-têtes pour le téléchargement
        $filepath = $result['filepath'];
        $filesize = filesize($filepath);
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Lire et envoyer le fichier
        readfile($filepath);
        exit;
    } else {
        // Enregistrer l'erreur dans les journaux
        $logs->addLog('error', 'Tentative de téléchargement échouée: ' . $filename . ' - ' . $result['message']);
        
        // Rediriger avec un message d'erreur
        header('Location: sauvegardes.php?error=' . urlencode($result['message']));
        exit;
    }
} else {
    // Aucun fichier spécifié
    header('Location: sauvegardes.php?error=' . urlencode('Aucun fichier spécifié'));
    exit;
}
?>
