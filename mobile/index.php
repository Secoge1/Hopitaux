<?php
/**
 * Entrée PWA mobile — redirige vers l'accueil en mode mobile.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/mobile_layout.php';

if (!headers_sent()) {
    $cp = mobile_layout_cookie_path();
    setcookie('efficasante_mobile', '1', time() + 86400 * 30, $cp);
}

$auth = Auth::getInstance();
if (!$auth->estConnecte()) {
    header('Location: ' . app_url('login.php') . '?redirect=' . urlencode('mobile/'));
    exit;
}

header('Location: ' . mobile_layout_url('index.php'));
exit;
