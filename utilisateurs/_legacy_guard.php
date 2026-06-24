<?php
/**
 * Ancien module utilisateurs/ — redirection vers parametres/utilisateurs.php (admin uniquement).
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';
require_once __DIR__ . '/../includes/header_logo.php';

app_parametres_require_admin();

$target = function_exists('app_url')
    ? app_url('parametres/utilisateurs.php')
    : (efficasante_web_base_path() . '/parametres/utilisateurs.php');

header('Location: ' . $target);
exit;
