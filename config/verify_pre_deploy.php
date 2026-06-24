<?php
/**
 * Vérification pré-déploiement web — lancer avant import production.
 * Usage: php config/verify_pre_deploy.php
 */

$base = dirname(__DIR__);
$ok = 0;
$warn = 0;
$fail = 0;

function vok(string $msg): void
{
    global $ok;
    $ok++;
    echo "[OK] $msg\n";
}

function vwarn(string $msg): void
{
    global $warn;
    $warn++;
    echo "[WARN] $msg\n";
}

function vfail(string $msg): void
{
    global $fail;
    $fail++;
    echo "[FAIL] $msg\n";
}

echo "=== Vérification pré-déploiement Hopitaux / SeSanté ===\n\n";

require_once $base . '/config/db.php';

try {
    $pdo = getDB();
    vok('Connexion MySQL');
} catch (Throwable $e) {
    vfail('Connexion MySQL: ' . $e->getMessage());
    exit(1);
}

require_once $base . '/includes/saas/TenantSchema.php';
TenantSchema::ensure();
vok('TenantSchema::ensure()');

$schemaChecks = [
    'tenants' => 'tenants',
    'subscription_orders' => 'subscription_orders',
    'password_initial' => "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'subscription_orders' AND column_name = 'password_initial'",
    'admin_login_password' => "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tenants' AND column_name = 'admin_login_password'",
];
foreach ($schemaChecks as $label => $sql) {
    if (strpos($sql, 'SELECT') === 0) {
        $pdo->query($sql)->fetchColumn() ? vok("Colonne/schéma $label") : vfail("Colonne/schéma $label manquant");
    } else {
        $pdo->query("SHOW TABLES LIKE '$sql'")->fetch() ? vok("Table $label") : vfail("Table $label manquante");
    }
}

$tenantCount = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
$tenantCount > 0 ? vok("$tenantCount établissement(s) (tenants)") : vfail('Aucun tenant');

$orphanUsers = (int) $pdo->query('SELECT COUNT(*) FROM utilisateurs WHERE tenant_id IS NULL')->fetchColumn();
$orphanUsers === 0 ? vok('Utilisateurs tous rattachés à un tenant') : vfail("$orphanUsers utilisateur(s) sans tenant_id");

$files = [
    'includes/pdf_branding.php',
    'includes/saas/TenantScope.php',
    'admin_platform/tenants.php',
    'admin_platform/payments.php',
    'admin_platform/fonctionnalites.php',
    'config/verify_payment_finance_sync.php',
    'vendor/tecnickcom/tcpdf/tcpdf.php',
    'assets/images/brand/sesante-logo.png',
];
foreach ($files as $rel) {
    is_file($base . '/' . $rel) ? vok("Fichier $rel") : vfail("Fichier manquant: $rel");
}

$dirs = ['uploads', 'uploads/logos', 'uploads/patients'];
foreach ($dirs as $rel) {
    $path = $base . '/' . $rel;
    if (!is_dir($path)) {
        vfail("Dossier absent: $rel");
    } elseif (!is_writable($path)) {
        vwarn("Dossier non inscriptible (chmod requis en prod): $rel");
    } else {
        vok("Dossier inscriptible: $rel");
    }
}

require_once $base . '/config/SystemParameters.php';
require_once $base . '/includes/pdf_branding.php';
$params = pdf_tenant_system_params(1);
$html = $params->getPdfLogoBlockHtml();
strlen($html) > 20 ? vok('PDF branding — getPdfLogoBlockHtml()') : vfail('PDF branding — sortie vide');

$trailing = file_get_contents($base . '/config/SystemParameters.php');
if (preg_match('/\?>\s*$/', $trailing)) {
    vwarn('SystemParameters.php se termine par ?> (risque headers already sent)');
} else {
    vok('SystemParameters.php sans fermeture PHP parasite');
}

if (is_file($base . '/config/config.php')) {
    $cfg = file_get_contents($base . '/config/config.php');
    if (strpos($cfg, "SITE_URL', 'http://localhost") !== false) {
        vwarn('SITE_URL pointe encore vers localhost — à modifier sur le serveur web');
    } else {
        vok('SITE_URL ne semble pas être localhost');
    }
    if (preg_match("/define\('DB_PASS',\s*''\)/", $cfg)) {
        vwarn('DB_PASS vide dans config.php — définir le mot de passe MySQL production');
    }
}

if (is_dir($base . '/.git')) {
    vok('Dépôt git présent');
} else {
    vwarn('Pas de dépôt git (optionnel)');
}

$configScripts = [
    'verify_saas.php',
    'verify_tenant_modules.php',
    'verify_mobile_layout.php',
    'verify_platform_admin.php',
];
echo "\n--- Scripts automatisés ---\n";
foreach ($configScripts as $script) {
    $path = $base . '/config/' . $script;
    if (!is_file($path)) {
        vfail("Script manquant: $script");
        continue;
    }
    exec('php ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    $text = implode("\n", $out);
    $out = [];
    if (preg_match('/FAIL:\s*(\d+)/', $text, $m) && (int) $m[1] > 0) {
        vfail("$script — échecs détectés");
    } elseif (preg_match('/FAIL:\s*0/', $text) || strpos($text, 'Tous les tests passent') !== false) {
        vok("$script");
    } else {
        vwarn("$script — résultat ambigu (vérifier manuellement)");
    }
}

echo "\n=== Résumé pré-déploiement ===\n";
echo "OK: $ok | WARN: $warn | FAIL: $fail\n";
exit($fail > 0 ? 1 : 0);
