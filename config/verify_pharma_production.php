<?php
/**
 * Vérification configuration production PharmaPro — pharma.secogesarl.com
 * Usage CLI : php config/verify_pharma_production.php
 */
$_SERVER['HTTP_HOST'] = 'pharma.secogesarl.com';
$_SERVER['HTTPS'] = 'on';

require_once __DIR__ . '/environment.php';
require_once __DIR__ . '/db.php';

$ok = 0;
$fail = 0;

function vp_check(string $label, bool $pass, string $detail = ''): void
{
    global $ok, $fail;
    if ($pass) {
        $ok++;
        echo "[OK] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    } else {
        $fail++;
        echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }
}

echo "=== Vérification PharmaPro production ===" . PHP_EOL;

vp_check('Host pharma détecté', app_is_pharma_production_host());
vp_check('APP_PHARMA_HOST', defined('APP_PHARMA_HOST') && APP_PHARMA_HOST);
vp_check('SITE_URL', defined('SITE_URL') && SITE_URL === 'https://pharma.secogesarl.com', SITE_URL ?? '');
vp_check('PLATFORM_NAME', defined('PLATFORM_NAME') && strpos(PLATFORM_NAME, 'Pharma') !== false, PLATFORM_NAME ?? '');
vp_check('DB_NAME', defined('DB_NAME') && DB_NAME === 'cp2640311p29_pharma', DB_NAME ?? '');
vp_check('DB_USER', defined('DB_USER') && DB_USER === 'cp2640311p29_pharma', DB_USER ?? '');
vp_check('DB_PASS défini', defined('DB_PASS') && DB_PASS !== '');

try {
    $pdo = getDBSoft();
    if (!$pdo instanceof PDO) {
        vp_check('Connexion MySQL', false, 'Base inaccessible (normal en local si cp2640311p29_pharma absente)');
    } else {
        vp_check('Connexion MySQL', true, DB_NAME);
        $tables = ['tenants', 'utilisateurs', 'subscription_orders', 'platform_tenant_features', 'pe_pharmacies'];
        foreach ($tables as $t) {
            $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
            vp_check("Table {$t}", (bool) $stmt->fetchColumn());
        }
    }
} catch (Throwable $e) {
    vp_check('Connexion MySQL', false, $e->getMessage());
}

echo PHP_EOL . "Résultat : {$ok} OK, {$fail} échec(s)" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
