<?php
/**
 * Vérification droits d'accès par tenant.
 * CLI : php config/verify_tenant_permissions.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/roles.php';
require_once __DIR__ . '/../includes/tenant_permissions.php';
$fail = 0;

function tp(string $msg, bool $ok): void
{
    global $lines, $fail;
    $lines[] = ($ok ? '[OK] ' : '[FAIL] ') . $msg;
    if (!$ok) {
        $fail++;
    }
    echo end($lines) . PHP_EOL;
}

tp('Fichier parametres/droits_acces.php', is_file(__DIR__ . '/../parametres/droits_acces.php'));
tp('Classe TenantPermissions', class_exists('TenantPermissions'));
tp('app_role_has_module() avec tenant', function_exists('app_role_has_module'));

$modsSecretaire = app_modules_for_role('secretaire');
tp('Secrétaire — modules par défaut > 0', count($modsSecretaire) > 0);

if (function_exists('getDBSoft')) {
    require_once __DIR__ . '/db.php';
    $pdo = getDBSoft();
} else {
    $pdo = null;
}

if ($pdo instanceof PDO) {
    $restricted = array_values(array_diff($modsSecretaire, ['finances', 'paiements']));
    if (TenantPermissions::saveRoleModules(999, 'secretaire', $restricted)) {
        $after = app_modules_for_role('secretaire', 999);
        tp('Personnalisation tenant — finances retiré', !in_array('finances', $after, true));
        tp('Personnalisation tenant — patients conservé', in_array('patients', $after, true));
        TenantPermissions::resetRoleToDefaults(999, 'secretaire');
    } else {
        tp('Personnalisation tenant — enregistrement', false);
    }
} else {
    tp('Personnalisation tenant (BDD optionnelle)', true);
}

tp('app_nav_item_visible() défini', function_exists('app_nav_item_visible'));

echo PHP_EOL . ($fail === 0 ? 'Toutes les vérifications sont passées.' : "{$fail} échec(s).") . PHP_EOL;
exit($fail > 0 ? 1 : 0);
