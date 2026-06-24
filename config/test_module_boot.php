<?php
/**
 * Teste le démarrage des modules consultations / laboratoire (détecte erreurs fatales).
 * Usage : php config/test_module_boot.php [consultations|laboratoire]
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base = dirname(__DIR__);
$module = $argv[1] ?? 'consultations';

$pdo = require $base . '/config/db.php';
if (!($pdo instanceof PDO)) {
    $pdo = getDB();
}

$stmt = $pdo->query("SELECT id, nom_utilisateur, role, tenant_id FROM utilisateurs WHERE role = 'medecin' AND statut = 'actif' LIMIT 1");
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo "Aucun médecin actif en BDD.\n";
    exit(1);
}

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = $user['nom_utilisateur'];
$_SESSION['user_email'] = '';
$_SESSION['user_role'] = 'medecin';
$_SESSION['user_statut'] = 'actif';
$_SESSION['user_connected'] = true;
$_SESSION['tenant_id'] = (int) ($user['tenant_id'] ?? 1);
$_SESSION['last_activity'] = time();

$index = $base . '/' . $module . '/index.php';
if (!is_file($index)) {
    echo "Fichier absent : $index\n";
    exit(1);
}

echo "=== Boot test: $module (user {$user['nom_utilisateur']}) ===\n";

$_SERVER['PHP_SELF'] = "/$module/index.php";
$_SERVER['SCRIPT_NAME'] = "/$module/index.php";
$_SERVER['REQUEST_URI'] = "/$module/";
$_SERVER['HTTP_HOST'] = 'localhost';
$_GET = [];
$_POST = [];

ob_start();
try {
    include $index;
    $out = ob_get_clean();
    $len = strlen($out);
    echo "OK — sortie HTML : {$len} octets\n";
    if ($len < 500) {
        echo "--- début sortie ---\n" . substr($out, 0, 2000) . "\n";
    } elseif (strpos($out, '<html') === false) {
        echo "WARN — pas de balise <html> dans la sortie\n";
        echo substr($out, 0, 500) . "\n";
    } else {
        echo "Contient <html> : oui\n";
        if (preg_match('/<title>([^<]+)<\/title>/i', $out, $m)) {
            echo 'Titre : ' . trim($m[1]) . "\n";
        }
    }
} catch (Throwable $e) {
    ob_end_clean();
    echo "FATAL : " . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}
