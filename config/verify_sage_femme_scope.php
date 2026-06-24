<?php
/**
 * Vérification ciblée rôle sage-femme (filtrage = médecin).
 * Usage : php config/verify_sage_femme_scope.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/roles.php';
require_once __DIR__ . '/../includes/staff_link.php';
require_once __DIR__ . '/../includes/staff_scope.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';

TenantSchema::ensure();
$pdo = getDB();

$ok = 0;
$fail = 0;

function sfcheck(bool $cond, string $label): void
{
    global $ok, $fail;
    echo ($cond ? 'OK  ' : 'FAIL ') . "$label\n";
    $cond ? $ok++ : $fail++;
}

echo "=== Vérification sage-femme ===\n\n";

sfcheck(in_array('sage_femme', app_role_keys(), true), 'Rôle sage_femme dans APP_ROLE_LABELS');
sfcheck(app_role_has_medecin_scope('sage_femme'), 'app_role_has_medecin_scope(sage_femme)');
sfcheck(StaffLink::linkTypeForRole('sage_femme') === 'medecin', 'Rattachement fiche médecin');
sfcheck(in_array('sage_femme', app_module_roles('patients'), true), 'Module patients');
sfcheck(in_array('sage_femme', app_module_roles('consultations'), true), 'Module consultations');
sfcheck(in_array('sage_femme', app_module_roles('rdv'), true), 'Module rendez-vous');
sfcheck(in_array('sage_femme', app_module_roles('medecins'), true), 'Module médecins (mon profil)');

$enum = $pdo->query("SHOW COLUMNS FROM utilisateurs LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
sfcheck(
    $enum && strpos((string) ($enum['Type'] ?? ''), 'sage_femme') !== false,
    'ENUM BDD utilisateurs.role contient sage_femme'
);

$reflection = new ReflectionClass(StaffScope::class);
$scoped = $reflection->getConstant('SCOPED_ROLES');
sfcheck(is_array($scoped) && in_array('sage_femme', $scoped, true), 'sage_femme dans SCOPED_ROLES');

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
