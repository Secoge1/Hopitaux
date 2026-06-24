<?php
/**
 * Vérification cloche notifications (CLI).
 */
require_once __DIR__ . '/../includes/header_logo.php';
require_once __DIR__ . '/../includes/mobile_layout.php';

if (!function_exists('app_render_notifications_panel')) {
    require_once __DIR__ . '/../includes/app_layout.php';
}

$checks = [];

$header = getMobileHeaderHtml(efficasante_web_base_path(), ['unread' => 2]);
$checks[] = [
    'name' => 'Cloche mobile (#notificationsPanel)',
    'ok' => strpos($header, 'id="mobileHeaderBell"') !== false
        && strpos($header, 'data-bs-target="#notificationsPanel"') !== false,
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'Badge mobile avec compteur',
    'ok' => strpos($header, 'mobile-header-badge') !== false && strpos($header, '>2<') !== false,
    'detail' => 'OK',
];

ob_start();
app_render_notifications_panel([
    [
        'id' => 1,
        'titre' => 'Notification test',
        'message' => 'Message de vérification',
        'date_creation' => '2026-06-16 10:00:00',
        'lu' => 0,
    ],
    [
        'id' => 2,
        'titre' => 'Déjà lue',
        'message' => 'Ancienne',
        'date_creation' => '2026-06-15 09:00:00',
        'lu' => 1,
    ],
], 1);
$panelHtml = ob_get_clean();

$checks[] = [
    'name' => 'Panneau #notificationsPanel présent',
    'ok' => strpos($panelHtml, 'id="notificationsPanel"') !== false,
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'Corps #notificationsPanelBody',
    'ok' => strpos($panelHtml, 'id="notificationsPanelBody"') !== false,
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'Notification non lue surlignée',
    'ok' => strpos($panelHtml, 'Notification test') !== false
        && strpos($panelHtml, 'list-group-item-primary') !== false,
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'Bouton marquer comme lu',
    'ok' => strpos($panelHtml, 'markNotificationAsRead(1)') !== false,
    'detail' => 'OK',
];

$actionsSrc = file_get_contents(__DIR__ . '/../includes/notification_actions.php');
$checks[] = [
    'name' => 'API AJAX action=list',
    'ok' => strpos($actionsSrc, "case 'list':") !== false,
    'detail' => 'OK',
];

$layoutSrc = file_get_contents(__DIR__ . '/../includes/app_layout.php');
$checks[] = [
    'name' => 'Rafraîchissement au clic (appRefreshNotificationsPanel)',
    'ok' => strpos($layoutSrc, 'appRefreshNotificationsPanel') !== false
        && strpos($layoutSrc, 'show.bs.collapse') !== false,
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'Badge synchronisé (app-notif-badge)',
    'ok' => strpos($layoutSrc, 'app-notif-badge') !== false
        && strpos($layoutSrc, 'mobile-header-badge') !== false,
    'detail' => 'OK',
];

$mobileJs = file_get_contents(__DIR__ . '/../assets/js/mobile-pwa-chrome.js');
$checks[] = [
    'name' => 'Panneau mobile repositionné (setupNotificationsPanel)',
    'ok' => strpos($mobileJs, 'setupNotificationsPanel') !== false,
    'detail' => 'OK',
];

$css = file_get_contents(__DIR__ . '/../assets/css/mobile_nav.css');
$checks[] = [
    'name' => 'CSS panneau visible sous bandeau',
    'ok' => strpos($css, '#notificationsPanel.collapse.show') !== false
        && strpos($css, 'z-index: 1325') !== false,
    'detail' => 'OK',
];

$initSrc = file_get_contents(__DIR__ . '/../includes/init.php');
$checks[] = [
    'name' => 'getUserNotifications() disponible',
    'ok' => strpos($initSrc, 'function getUserNotifications') !== false,
    'detail' => 'OK',
];

$failed = 0;
echo "=== Vérification cloche notifications ===\n";
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
