<?php
/**
 * Téléchargement PDF — guide utilisateur complet.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';
require_once __DIR__ . '/../includes/user_guide_pdf.php';

app_parametres_require_user();

$auth = Auth::getInstance();
$tenantId = $auth->getTenantId();

user_guide_stream_pdf($tenantId ? (int) $tenantId : null, 'D');
