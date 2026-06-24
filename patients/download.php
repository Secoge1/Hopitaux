<?php
/**
 * Téléchargement sécurisé des documents patients
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
$auth = Auth::getInstance();
module_require_roles('patients');

require_once __DIR__ . '/../models/Patient.php';

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$filename = isset($_GET['file']) ? $_GET['file'] : '';
$original_name = isset($_GET['original_name']) ? $_GET['original_name'] : '';

if (!$patient_id || !$filename) {
    http_response_code(400);
    die('Paramètres manquants');
}

$patientModel = new Patient();
if (!$patientModel->getById($patient_id)) {
    http_response_code(403);
    die('Accès interdit');
}

// Sécuriser le nom de fichier
$filename = basename($filename);
$filepath = __DIR__ . '/../uploads/patients/' . $patient_id . '/' . $filename;

// Vérifier que le fichier existe et est dans le bon dossier
if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    die('Fichier non trouvé');
}

// Vérifier que le fichier est bien dans le dossier du patient
$realpath = realpath($filepath);
$expected_dir = realpath(__DIR__ . '/../uploads/patients/' . $patient_id);
if (strpos($realpath, $expected_dir) !== 0) {
    http_response_code(403);
    die('Accès interdit');
}

// Déterminer le type MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Définir les types MIME autorisés
$allowed_mimes = [
    'application/pdf',
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain'
];

if (!in_array($mime_type, $allowed_mimes)) {
    http_response_code(415);
    die('Type de fichier non supporté');
}

// Priorité au nom original fourni en paramètre
$display_name = $filename;

if ($original_name) {
    $display_name = $original_name;
} else {
    // Essayer de récupérer le nom original depuis le fichier .info
    $infoFile = "../uploads/patients/{$patient_id}/{$filename}.info";
    if (file_exists($infoFile)) {
        $metadata = json_decode(file_get_contents($infoFile), true);
        if ($metadata && isset($metadata['original_name'])) {
            $display_name = $metadata['original_name'];
        }
    }
}

// En-têtes de téléchargement
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $display_name . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Lire et envoyer le fichier
readfile($filepath);
exit;
?>

