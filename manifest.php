<?php
/**
 * Manifest PWA dynamique (chemins adaptés au sous-dossier d'installation).
 */
require_once __DIR__ . '/includes/pwa.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo json_encode(
    pwa_manifest_data(),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);
