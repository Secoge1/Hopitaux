<?php
/**
 * Vérification affichage badge / bandeau sync Paiements-Finances.
 * Usage : php config/verify_payment_sync_badge.php
 */
declare(strict_types=1);

$base = dirname(__DIR__);
$ok = 0;
$fail = 0;
$warn = 0;

function bsok(string $m): void { global $ok; $ok++; echo "[OK] $m\n"; }
function bsfail(string $m): void { global $fail; $fail++; echo "[FAIL] $m\n"; }
function bswarn(string $m): void { global $warn; $warn++; echo "[WARN] $m\n"; }

echo "=== Vérification badge sync Paiements / Finances ===\n\n";

$files = [
    'includes/payment_sync_badge.php',
    'includes/app_module_layout.php',
    'consultations/voir.php',
    'laboratoire/voir.php',
    'paiements/voir.php',
    'paiements/index.php',
    'includes/saas/PlatformTenantFeatures.php',
    'includes/saas/saas_helpers.php',
];

foreach ($files as $f) {
    is_file("$base/$f") ? bsok("Fichier $f") : bsfail("Manquant: $f");
}

$checks = [
    ['includes/payment_sync_badge.php', 'app_payment_sync_global_banner', 'Bandeau global'],
    ['includes/payment_sync_badge.php', 'app_payment_sync_new_badge', 'Badge carte'],
    ['includes/payment_sync_badge.php', 'localStorage', 'Persistance localStorage'],
    ['includes/payment_sync_badge.php', 'data-feature-stamp', 'Empreinte activation'],
    ['includes/payment_sync_badge.php', 'payment_finance_sync_enabled', 'Gate feature'],
    ['includes/app_module_layout.php', 'app_payment_sync_global_banner', 'Injection layout module'],
    ['consultations/voir.php', 'app_payment_sync_new_badge', 'Badge fiche consultation'],
    ['consultations/voir.php', 'paymentSyncEnabled', 'Gate UI consultation'],
    ['laboratoire/voir.php', 'app_payment_sync_new_badge', 'Badge fiche analyse'],
    ['laboratoire/voir.php', 'paymentSyncEnabled', 'Gate UI laboratoire'],
    ['paiements/voir.php', 'app_payment_sync_new_badge', 'Badge fiche paiement'],
    ['paiements/index.php', 'app_module_page_start', 'Bandeau liste paiements'],
];

foreach ($checks as [$file, $needle, $label]) {
    $c = is_file("$base/$file") ? file_get_contents("$base/$file") : '';
    strpos($c, $needle) !== false ? bsok($label) : bsfail("$label ($file)");
}

// Pages sans bandeau (comportement attendu)
$noBanner = ['index.php', 'dashboard.php'];
foreach ($noBanner as $f) {
    if (!is_file("$base/$f")) {
        continue;
    }
    $c = file_get_contents("$base/$f");
    strpos($c, 'app_module_page_start') === false
        ? bsok("Pas de bandeau auto sur $f (normal)")
        : bswarn("$f utilise app_module_page_start — bandeau possible");
}

// App mobile native / React — badge via API tenant/notices
$appChecks = [
    ['efficasante_app/lib/widgets/payment_sync_notice_banner.dart', 'PaymentSyncNoticeBanner', 'Flutter bannière sync'],
    ['efficasante_app/lib/services/api_service.dart', 'getTenantNotices', 'Flutter API notices'],
    ['efficasante_app/lib/screens/home_screen.dart', 'PaymentSyncNoticeBanner', 'Flutter intégration HomeScreen'],
    ['efficasante_web/src/components/PaymentSyncNoticeBanner.tsx', 'payment_finance_sync', 'React bannière sync'],
    ['efficasante_web/src/services/api.ts', 'getTenantNotices', 'React API notices'],
    ['efficasante_web/src/components/layout/AdaptiveShell.tsx', 'PaymentSyncNoticeBanner', 'React intégration shell'],
];

foreach ($appChecks as [$file, $needle, $label]) {
    $c = is_file("$base/$file") ? file_get_contents("$base/$file") : '';
    strpos($c, $needle) !== false ? bsok($label) : bsfail("$label ($file)");
}

// API REST — endpoint feature notices
$api = is_file("$base/api/rest/index.php") ? file_get_contents("$base/api/rest/index.php") : '';
strpos($api, 'doTenantNotices') !== false && strpos($api, 'tenant/notices') !== false
    ? bsok('API REST endpoint tenant/notices')
    : bsfail('API REST — endpoint tenant/notices manquant');
strpos($api, 'bindApiUserContext') !== false
    ? bsok('API REST — contexte tenant utilisateur')
    : bsfail('API REST — bindApiUserContext manquant');

echo "\n--- Conditions d'affichage WEB / APP ---\n";
echo "1. Feature payment_finance_sync activée (Admin plateforme)\n";
echo "2. WEB : page module paiements | consultations | laboratoire | finances\n";
echo "2. APP : écran principal après connexion (Flutter + React PWA)\n";
echo "3. Utilisateur connecté\n";
echo "4. Stockage local sans empreinte pour cette activation (localStorage / SharedPreferences)\n";
echo "5. Bandeau visible 10 s puis masqué ; réapparaît si réactivation feature\n";
echo "6. API : GET /api/rest/index.php?path=tenant/notices (Bearer token)\n";

echo "\n--- Résumé ---\n";
echo "OK: $ok | WARN: $warn | FAIL: $fail\n";
exit($fail > 0 ? 1 : 0);
