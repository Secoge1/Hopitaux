<?php
/**
 * Vérifie que app_layout.php charge bien après init.php (app_urls.php + app_prepare_context).
 * Usage : php config/verify_app_layout_boot.php
 */
declare(strict_types=1);

$base = dirname(__DIR__);
$ok = 0;
$fail = 0;

function lbok(string $m): void { global $ok; $ok++; echo "[OK] $m\n"; }
function lbfail(string $m): void { global $fail; $fail++; echo "[FAIL] $m\n"; }

echo "=== Vérification boot app_layout ===\n\n";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['user_connected'] = true;
$_SESSION['tenant_id'] = 1;

$_SERVER['SCRIPT_NAME'] = '/dashboard.php';
$_SERVER['HTTP_HOST'] = 'localhost';

require_once $base . '/includes/init.php';
require_once $base . '/includes/app_layout.php';

function_exists('app_url') ? lbok('app_url défini') : lbfail('app_url manquant');
function_exists('app_prepare_context') ? lbok('app_prepare_context défini') : lbfail('app_prepare_context manquant');
function_exists('app_layout_start') ? lbok('app_layout_start défini') : lbfail('app_layout_start manquant');
function_exists('app_head') ? lbok('app_head défini') : lbfail('app_head manquant');

$init = file_get_contents($base . '/includes/init.php') ?: '';
strpos($init, 'app_urls.php') !== false ? lbok('init.php charge app_urls.php') : lbfail('init.php sans app_urls.php');

$layout = file_get_contents($base . '/includes/app_layout.php') ?: '';
strpos($layout, "if (!function_exists('app_prepare_context'))") !== false
    ? lbok('app_layout garde app_prepare_context (pas app_url)')
    : lbfail('app_layout encore lié à app_url pour tout le bloc');

echo "\n--- Résumé ---\nOK: $ok | FAIL: $fail\n";
exit($fail > 0 ? 1 : 0);
