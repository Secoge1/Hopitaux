<?php
/**
 * Vérifie les en-têtes et chemins pour l'aperçu document patient.
 * Usage : php config/verify_patient_document_view.php
 */
declare(strict_types=1);

$base = dirname(__DIR__);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['user_connected'] = true;
$_SESSION['tenant_id'] = 1;

$_SERVER['SCRIPT_NAME'] = '/Hopitaux/patients/stream_document.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/Hopitaux/patients/stream_document.php?patient_id=1&file=test.pdf';
$_GET['patient_id'] = 1;
$_GET['file'] = 'test.pdf';

$patientDir = $base . '/uploads/patients/1';
if (!is_dir($patientDir)) {
    mkdir($patientDir, 0755, true);
}
$testFile = $patientDir . '/test.pdf';
if (!is_file($testFile)) {
    file_put_contents($testFile, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
}

ob_start();
try {
    include $base . '/patients/stream_document.php';
} catch (Throwable $e) {
    ob_end_clean();
    echo "[FAIL] Exception stream_document : " . $e->getMessage() . "\n";
    exit(1);
}
$output = ob_get_clean();

$headers = headers_list();
echo "=== En-têtes stream_document ===\n";
if (empty($headers)) {
    echo "[WARN] Aucun en-tête\n";
    echo "Sortie : " . substr($output, 0, 200) . "\n";
    exit(1);
}
foreach ($headers as $h) {
    echo $h . "\n";
}

$frameOpts = array_filter($headers, static function ($h) {
    return stripos($h, 'X-Frame-Options') === 0;
});
if (count($frameOpts) !== 1) {
    echo "\n[FAIL] X-Frame-Options ambigu ou absent (" . count($frameOpts) . " valeurs)\n";
    exit(1);
}
$frame = strtolower((string) reset($frameOpts));
if (strpos($frame, 'sameorigin') === false) {
    echo "\n[FAIL] X-Frame-Options doit être SAMEORIGIN pour l'iframe PDF, reçu : $frame\n";
    exit(1);
}
echo "\n[OK] X-Frame-Options SAMEORIGIN\n";

if (function_exists('str_ends_with')) {
    echo "[OK] str_ends_with disponible (PHP 8+)\n";
} else {
    echo "[WARN] str_ends_with absent (PHP 7.4) — gerer_documents.php plantera au listage\n";
}

exit(0);
