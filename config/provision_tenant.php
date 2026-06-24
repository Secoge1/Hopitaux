<?php
/**
 * Crée un établissement (tenant) + compte administrateur sans toucher aux autres.
 *
 * Usage CLI :
 *   php config/provision_tenant.php --company="Clinique du Sahel" --email=contact@clinique.ml --username=admin --password=MonMotDePasse --plan=lifetime
 *   php config/provision_tenant.php --company="..." --email=... --username=admin --password=... --plan=annual --max-users=15
 *
 * Plans : starter | annual | lifetime
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';
require_once __DIR__ . '/../includes/saas/SubscriptionPlan.php';
require_once __DIR__ . '/../includes/saas/SubscriptionService.php';

function ptArg(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

$company = trim((string) ptArg($argv, 'company', ''));
$email = trim((string) ptArg($argv, 'email', ''));
$username = trim((string) ptArg($argv, 'username', ''));
$password = (string) ptArg($argv, 'password', '');
$plan = SubscriptionPlan::normalizeSlug(ptArg($argv, 'plan', SubscriptionPlan::LIFETIME));
$phone = trim((string) ptArg($argv, 'phone', ''));
$maxUsersOverride = ptArg($argv, 'max-users');

if ($company === '' || $email === '' || $username === '' || $password === '') {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php config/provision_tenant.php --company=\"Nom\" --email=mail@exemple.ml --username=admin --password=Secret --plan=lifetime\n");
    exit(1);
}

TenantSchema::ensure();
$pdo = getDB();
$planDef = SubscriptionPlan::get($plan);
$maxUsers = $maxUsersOverride !== null ? max(1, (int) $maxUsersOverride) : (int) $planDef['max_users'];
$isLifetime = SubscriptionPlan::isLifetime($plan);
$expiresAt = $isLifetime ? null : date('Y-m-d', strtotime('+1 year'));
$tenantKey = 'EFS-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(2)));

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO tenants (tenant_key, company_name, email, phone, license_type, max_users, expires_at, status, is_demo, auto_renew)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 0, ?)
    ");
    $stmt->execute([
        $tenantKey,
        $company,
        $email,
        $phone !== '' ? $phone : null,
        $plan,
        $maxUsers,
        $expiresAt,
        $isLifetime ? 0 : 1,
    ]);
    $tenantId = (int) $pdo->lastInsertId();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("
        INSERT INTO utilisateurs (tenant_id, nom_utilisateur, email, mot_de_passe, role, statut)
        VALUES (?, ?, ?, ?, 'admin', 'actif')
    ")->execute([$tenantId, $username, $email, $hash]);

    $defaults = [
        'nom_etablissement' => $company,
        'devise' => 'XOF',
        'timezone' => 'Africa/Bamako',
    ];
    foreach ($defaults as $cle => $valeur) {
        $pdo->prepare(
            'INSERT INTO parametres_systeme (tenant_id, cle, valeur) VALUES (?, ?, ?)'
        )->execute([$tenantId, $cle, $valeur]);
    }

    if ($pdo->query("SHOW COLUMNS FROM tenants LIKE 'admin_login_password'")->fetch()) {
        $pdo->prepare('UPDATE tenants SET admin_login_password = ? WHERE id = ?')
            ->execute([$password, $tenantId]);
    }

    SubscriptionService::getInstance()->syncTenantToPlan($tenantId, $plan, $pdo);

    $pdo->commit();

    echo "Établissement créé avec succès.\n";
    echo "  tenant_id   : {$tenantId}\n";
    echo "  tenant_key  : {$tenantKey}\n";
    echo "  établissement : {$company}\n";
    echo "  identifiant : {$username}\n";
    echo "  mot de passe : (celui fourni en argument)\n";
    echo "  plan        : {$planDef['name']}\n";
    echo "\nÉtape suivante — importer le dump SQL :\n";
    echo "  php config/import_tenant_sql.php --tenant-id={$tenantId} --file=C:\\chemin\\dump.sql --confirm\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
