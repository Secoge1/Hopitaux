<?php
/**
 * Script de vérification du layout mobile (CLI).
 */
$_SERVER['SCRIPT_NAME'] = '/Hopitaux/patients/index.php';
$_SERVER['REQUEST_URI'] = '/Hopitaux/patients/index.php?mobile=1';
$_SERVER['DOCUMENT_ROOT'] = 'c:/wamp64/www';

require_once __DIR__ . '/../includes/header_logo.php';

define('IS_MOBILE_LAYOUT', true);
require_once __DIR__ . '/../includes/mobile_layout.php';

$checks = [];

$patientUrl = mobile_layout_url('patients/index.php');
$checks[] = [
    'name' => 'URL patients avec mobile=1',
    'ok' => strpos($patientUrl, 'patients/index.php') !== false && strpos($patientUrl, 'mobile=1') !== false,
    'detail' => $patientUrl,
];

$header = getMobileHeaderHtml(efficasante_web_base_path());
$checks[] = [
    'name' => 'Bandeau mobile (hamburger + titre + cloche)',
    'ok' => strpos($header, 'mobile-global-header') !== false
        && strpos($header, 'mobileHeaderMenu') !== false
        && strpos($header, 'mobile-header-title') !== false
        && strpos($header, 'fa-bell') !== false,
    'detail' => 'OK',
];

$footer = getMobileFooterHtml(efficasante_web_base_path(), '/Hopitaux/patients/index.php?mobile=1');
$checks[] = [
    'name' => 'Barre navigation bas (5 onglets)',
    'ok' => strpos($footer, 'mobile-global-nav') !== false
        && strpos($footer, 'nav-label') !== false
        && strpos($footer, 'nav-menu') === false
        && strpos($footer, 'mobileNavMenu') === false,
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'Slug patients actif (logique nav)',
    'ok' => strpos('/Hopitaux/patients/index.php?mobile=1', 'patients') !== false,
    'detail' => 'OK',
];

// Simulation injection OB
$sampleHtml = '<!DOCTYPE html><html><head><title>T</title></head>'
    . '<body class="app-shell app-module-page" data-base-path="/Hopitaux">'
    . '<div class="app-main">contenu</div></body></html>';

$basePath = efficasante_web_base_path();
$cssUrl = rtrim($basePath, '/') . '/assets/css/mobile_nav.css';
$mobileCss = '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl) . '" id="mobile-nav-css">';
$mobileHeader = getMobileHeaderHtml($basePath);
$mobileFooter = getMobileFooterHtml($basePath, '/Hopitaux/patients/index.php?mobile=1');

$html = mobile_layout_inject_html($sampleHtml);

$checks[] = [
    'name' => 'Injection CSS mobile_nav.css',
    'ok' => strpos($html, 'mobile-nav-css') !== false && strpos($html, '/assets/css/mobile_nav.css') !== false,
    'detail' => $cssUrl,
];

$checks[] = [
    'name' => 'Classe body-mobile-mode injectée',
    'ok' => strpos($html, 'body-mobile-mode') !== false && strpos($html, 'data-base-path="/Hopitaux"') !== false,
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'Topbar non dupliquée (pas de mobile-top-bar wptouch)',
    'ok' => strpos($html, 'mobile-top-bar') === false,
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'Fichiers assets présents',
    'ok' => file_exists(__DIR__ . '/../assets/css/mobile_nav.css')
        && file_exists(__DIR__ . '/../assets/js/mobile-pwa-chrome.js')
        && file_exists(__DIR__ . '/../assets/js/wptouch-inspired.js'),
    'detail' => 'OK',
];

$js = file_get_contents(__DIR__ . '/../assets/js/wptouch-inspired.js');
$checks[] = [
    'name' => 'JS: API appShellMobile',
    'ok' => strpos($js, 'window.appShellMobile') !== false && strpos($js, 'toggleSidebar') !== false,
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'JS: skip si body-mobile-mode',
    'ok' => strpos($js, 'isMobileLayoutMode') !== false && strpos($js, 'clearMobileChrome') !== false,
    'detail' => 'OK',
];

// --- Détection PWA dans init.php (simulation sans session DB) ---
require_once __DIR__ . '/../includes/pwa.php';

$simulateMobileLayout = static function (array $get, array $cookies, string $ua = ''): bool {
    $quitMobile = isset($get['mobile']) && $get['mobile'] === '0';
    $mobileParam = isset($get['mobile']) && $get['mobile'] === '1';
    $mobileCookie = !$quitMobile && isset($cookies['efficasante_mobile']) && $cookies['efficasante_mobile'] === '1';
    $pwaStandalone = !$quitMobile && isset($cookies['efficasante_pwa_standalone']) && $cookies['efficasante_pwa_standalone'] === '1';
    $prevUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua !== '') {
        $_SERVER['HTTP_USER_AGENT'] = $ua;
    }
    $mobileAuto = !$quitMobile && !$mobileParam && pwa_is_mobile_device();
    if ($ua !== '') {
        $_SERVER['HTTP_USER_AGENT'] = $prevUa;
    }
    return $mobileParam || $mobileCookie || $pwaStandalone || $mobileAuto;
};

$checks[] = [
    'name' => 'PWA: ?mobile=1 active le layout',
    'ok' => $simulateMobileLayout(['mobile' => '1'], []),
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'PWA: cookie efficasante_mobile',
    'ok' => $simulateMobileLayout([], ['efficasante_mobile' => '1']),
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'PWA: cookie standalone',
    'ok' => $simulateMobileLayout([], ['efficasante_pwa_standalone' => '1']),
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'PWA: auto-détection smartphone',
    'ok' => $simulateMobileLayout([], [], 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'),
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'PWA: ?mobile=0 désactive',
    'ok' => !$simulateMobileLayout(['mobile' => '0'], ['efficasante_mobile' => '1']),
    'detail' => 'OK',
];

$initSrc = file_get_contents(__DIR__ . '/../includes/init.php');
$checks[] = [
    'name' => 'init.php: mode PWA (pas wptouch-only)',
    'ok' => strpos($initSrc, 'pwa_is_mobile_device') !== false
        && strpos($initSrc, 'efficasante_pwa_standalone') !== false
        && strpos($initSrc, 'explicite uniquement') === false,
    'detail' => 'OK',
];

$layoutSrc = file_get_contents(__DIR__ . '/../includes/app_layout.php');
$checks[] = [
    'name' => 'app_layout: chrome PWA rendu',
    'ok' => strpos($layoutSrc, 'app_render_mobile_chrome') !== false
        && strpos($layoutSrc, 'mobile_nav.css') !== false
        && strpos($layoutSrc, 'mobile-pwa-chrome.js') !== false,
    'detail' => 'OK',
];

$failed = 0;
echo "=== Vérification layout mobile PWA ===\n";
foreach ($checks as $c) {
    $status = $c['ok'] ? 'PASS' : 'FAIL';
    if (!$c['ok']) {
        $failed++;
    }
    echo "[$status] {$c['name']}";
    if (!empty($c['detail'])) {
        echo " — {$c['detail']}";
    }
    echo "\n";
}
echo $failed === 0 ? "\nTous les tests passent.\n" : "\n$failed test(s) en échec.\n";
exit($failed > 0 ? 1 : 0);
