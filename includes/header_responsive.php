<?php
/**
 * Header responsive commun pour toutes les pages
 * Inclut tous les fichiers CSS et JS nécessaires pour le système responsive
 */
if (!function_exists('efficasante_web_base_path')) {
    require_once __DIR__ . '/header_logo.php';
}
$assetBase = rtrim(efficasante_web_base_path(), '/') . '/assets';
?>
<!-- CSS Responsive -->
<link href="<?= htmlspecialchars($assetBase) ?>/css/wptouch-inspired.css" rel="stylesheet">
<link href="<?= htmlspecialchars($assetBase) ?>/css/modern-design.css" rel="stylesheet">
<link href="<?= htmlspecialchars($assetBase) ?>/css/system_logo.css" rel="stylesheet">
<link href="<?= htmlspecialchars($assetBase) ?>/css/app-shell.css" rel="stylesheet">

<!-- JavaScript Responsive -->
<script src="<?= htmlspecialchars($assetBase) ?>/js/wptouch-inspired.js"></script>

