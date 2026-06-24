<?php
/**
 * Vérification ciblée rôle major (supervision laboratoire).
 * Usage : php config/verify_major_scope.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/roles.php';
require_once __DIR__ . '/../includes/staff_link.php';
require_once __DIR__ . '/../includes/staff_scope.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';
require_once __DIR__ . '/../includes/saas/TenantContext.php';
require_once __DIR__ . '/../models/Analyse.php';

TenantSchema::ensure();
$pdo = getDB();

$ok = 0;
$fail = 0;

function mjcheck(bool $cond, string $label): void
{
    global $ok, $fail;
    echo ($cond ? 'OK  ' : 'FAIL ') . "$label\n";
    $cond ? $ok++ : $fail++;
}

echo "=== Vérification major (laboratoire) ===\n\n";

mjcheck(in_array('major', app_role_keys(), true), 'Rôle major dans APP_ROLE_LABELS');
mjcheck(in_array('major', app_module_roles('laboratoire'), true), 'Module laboratoire');
mjcheck(in_array('major', app_module_roles('communication'), true), 'Module communication');
mjcheck(count(app_modules_for_role('major')) === 2, 'Major — exactement 2 modules (' . count(app_modules_for_role('major')) . ')');
mjcheck(StaffLink::linkTypeForRole('major') === null, 'Pas de rattachement fiche obligatoire');

$enum = $pdo->query("SHOW COLUMNS FROM utilisateurs LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
mjcheck(
    $enum && strpos((string) ($enum['Type'] ?? ''), 'major') !== false,
    'ENUM BDD utilisateurs.role contient major'
);

$reflection = new ReflectionClass(StaffScope::class);
$scoped = $reflection->getConstant('SCOPED_ROLES');
mjcheck(is_array($scoped) && !in_array('major', $scoped, true), 'major absent de SCOPED_ROLES (vue établissement labo)');

// Compte major simulé : même périmètre analyses que admin
$tenantId = (int) $pdo->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn();

StaffScope::reset();
$_SESSION['user_connected'] = true;
$_SESSION['is_platform_admin'] = false;
$_SESSION['tenant_id'] = $tenantId;
$_SESSION['user_id'] = 999999;
$_SESSION['user_role'] = 'major';
$_SESSION['user_name'] = 'major_test';
TenantContext::setTenantId($tenantId);

$ctx = StaffScope::context();
mjcheck($ctx['active'] === false, 'StaffScope inactif pour major (pas de filtre personnel)');

$adminCount = null;
StaffScope::reset();
$_SESSION['user_role'] = 'admin';
$_SESSION['user_id'] = (int) $pdo->query(
    "SELECT id FROM utilisateurs WHERE role = 'admin' AND tenant_id = {$tenantId} LIMIT 1"
)->fetchColumn();
$adminCount = count((new Analyse())->getAll(1, 1000));

StaffScope::reset();
$_SESSION['user_role'] = 'major';
$_SESSION['user_id'] = 999999;
$majorCount = count((new Analyse())->getAll(1, 1000));

mjcheck($majorCount === $adminCount, "Analyses major ($majorCount) = admin ($adminCount)");

mjcheck(StaffScope::canPickTechnicienOnAnalyse(), 'Major peut assigner un technicien sur analyse');

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
