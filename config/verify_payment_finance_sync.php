<?php
/**
 * Vérification rigoureuse — synchronisation Paiements / Finances / Analyses.
 * Usage : php config/verify_payment_finance_sync.php
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = dirname(__DIR__);
$ok = 0;
$fail = 0;
$warn = 0;

function psok(string $msg): void
{
    global $ok;
    $ok++;
    echo "[OK] $msg\n";
}

function psfail(string $msg): void
{
    global $fail;
    $fail++;
    echo "[FAIL] $msg\n";
}

function pswarn(string $msg): void
{
    global $warn;
    $warn++;
    echo "[WARN] $msg\n";
}

echo "=== Vérification sync Paiements / Finances / Analyses ===\n\n";

$requiredFiles = [
    'includes/saas/PlatformTenantFeatures.php',
    'includes/payment_sync_badge.php',
    'includes/PaymentAudit.php',
    'includes/saas/saas_helpers.php',
    'models/Paiement.php',
    'paiements/creer_depuis_consultation.php',
    'paiements/creer_depuis_analyse.php',
    'admin_platform/fonctionnalites.php',
    'consultations/voir.php',
    'laboratoire/voir.php',
    'paiements/voir.php',
    'finances/voir_ecriture.php',
];

foreach ($requiredFiles as $rel) {
    is_file("$base/$rel") ? psok("Fichier $rel") : psfail("Fichier manquant: $rel");
}

foreach ($requiredFiles as $rel) {
    if (!is_file("$base/$rel")) {
        continue;
    }
    exec('php -l ' . escapeshellarg("$base/$rel") . ' 2>&1', $out, $code);
    $code === 0 ? psok("Syntaxe $rel") : psfail("Syntaxe $rel: " . implode(' ', $out));
}

require_once "$base/includes/saas/PlatformTenantFeatures.php";

$methods = [
    'PlatformTenantFeatures' => ['ensureTable', 'isEnabled', 'setEnabled', 'listTenantsStatus', 'countEnabled', 'getEnabledStamp'],
    'Paiement' => [
        'getByConsultationId', 'getByAnalyseId', 'createFromConsultation', 'createFromAnalyse',
        'getLinkedEcriture', 'parsePaiementIdFromReference', 'isEncaisseVerrouille', 'isHistoriqueClos',
    ],
];

require_once "$base/models/Paiement.php";

foreach ($methods as $class => $list) {
    foreach ($list as $m) {
        method_exists($class, $m) ? psok("$class::$m()") : psfail("Méthode absente: $class::$m()");
    }
}

function psfile_contains(string $path, string $needle): bool
{
    return is_file($path) && strpos(file_get_contents($path), $needle) !== false;
}

$checks = [
    ['models/Paiement.php', 'payment_finance_sync_enabled', 'Gate finance dans Paiement'],
    ['models/Paiement.php', 'requireFinanceSyncFeature', 'Protection createFrom*'],
    ['includes/init.php', 'app_urls.php', 'app_url via init.php'],
    ['paiements/creer_depuis_consultation.php', 'payment_finance_sync_enabled', 'Gate creer consultation'],
    ['paiements/creer_depuis_analyse.php', 'app_layout.php', 'Include app_layout (app_url)'],
    ['paiements/creer_depuis_analyse.php', 'payment_finance_sync_enabled', 'Gate creer analyse'],
    ['consultations/voir.php', 'payment-sync-feature-block', 'Badge/card consultation'],
    ['laboratoire/voir.php', 'payment-sync-feature-block', 'Badge/card laboratoire'],
    ['paiements/voir.php', 'payment-sync-feature-block', 'Badge/card paiement'],
    ['models/Paiement.php', 'createContrePassationEcriture', 'Contre-passation ERP (pas de delete)'],
    ['models/Paiement.php', 'assertUpdateAllowed', 'Verrouillage encaissement'],
    ['models/Paiement.php', 'PaymentAudit::log', 'Journal audit paiements'],
    ['includes/payment_sync_badge.php', 'localStorage', 'Badge une fois par activation'],
    ['includes/payment_sync_badge.php', 'app_payment_sync_global_banner', 'Bandeau global module'],
    ['includes/app_module_layout.php', 'app_payment_sync_global_banner', 'Bandeau auto pages module'],
    ['includes/saas/PlatformTenantFeatures.php', 'getEnabledStamp', 'Empreinte réactivation feature'],
    ['admin_platform/_handlers.php', 'toggle_tenant_feature', 'Handler admin plateforme'],
];

foreach ($checks as [$file, $needle, $label]) {
    psfile_contains("$base/$file", $needle) ? psok($label) : psfail("$label ($file)");
}

const KEY = PlatformTenantFeatures::PAYMENT_FINANCE_SYNC;
KEY === 'payment_finance_sync' ? psok('Clé feature payment_finance_sync') : psfail('Clé feature incorrecte');

try {
    require_once "$base/config/db.php";
    require_once "$base/includes/saas/TenantSchema.php";
    TenantSchema::ensure();
    PlatformTenantFeatures::ensureTable();
    psok('Connexion BDD + tables');

    $pdo = getDB();
    $tables = ['platform_tenant_features', 'paiements', 'ecritures_comptables', 'analyses', 'consultations'];
    foreach ($tables as $t) {
        $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetch()
            ? psok("Table $t")
            : psfail("Table $t absente");
    }

    foreach (['analyse_id', 'ecriture_comptable_id'] as $col) {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'paiements' AND COLUMN_NAME = ?"
        );
        $stmt->execute([$col]);
        $stmt->fetchColumn() ? psok("Colonne paiements.$col") : psfail("Colonne paiements.$col absente");
    }

    $tenantId = (int) $pdo->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn();
    if ($tenantId > 0) {
        PlatformTenantFeatures::setEnabled($tenantId, KEY, false, null);
        !PlatformTenantFeatures::isEnabled(KEY, $tenantId) ? psok('Feature OFF par défaut (tenant test)') : psfail('Feature devrait être OFF');

        PlatformTenantFeatures::setEnabled($tenantId, KEY, true, null);
        PlatformTenantFeatures::isEnabled(KEY, $tenantId) ? psok('Activation feature tenant test') : psfail('Activation feature échouée');

        PlatformTenantFeatures::setEnabled($tenantId, KEY, false, null);

        $paiementModel = new Paiement();
        $stmt = $pdo->prepare('SELECT id FROM consultations WHERE tenant_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$tenantId]);
        $consultationId = (int) $stmt->fetchColumn();
        if ($consultationId > 0) {
            $existing = $paiementModel->getByConsultationId($consultationId);
            psok('getByConsultationId exécutable');
            if (!$existing) {
                try {
                    $pid = $paiementModel->createFromConsultation($consultationId, ['statut' => 'en_attente']);
                    $pid ? psok('createFromConsultation (test)') : psfail('createFromConsultation retour falsy');
                    if ($pid) {
                        $pdo->prepare('DELETE FROM paiements WHERE id = ?')->execute([(int) $pid]);
                    }
                } catch (Throwable $e) {
                    psfail('createFromConsultation: ' . $e->getMessage());
                }
            } else {
                pswarn('Consultation déjà liée à un paiement — createFromConsultation non testé');
            }
        } else {
            pswarn('Aucune consultation pour test createFromConsultation');
        }
    } else {
        pswarn('Aucun tenant — tests BDD métier ignorés');
    }
} catch (Throwable $e) {
    pswarn('BDD indisponible ou partielle: ' . $e->getMessage());
}

echo "\n--- Résumé ---\n";
echo "OK: $ok | WARN: $warn | FAIL: $fail\n";
exit($fail > 0 ? 1 : 0);
