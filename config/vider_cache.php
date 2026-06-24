<?php
/**
 * Vide le cache fichier (compteurs Accueil/Dashboard, listes patients/médecins).
 *
 * Usage : php config/vider_cache.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI uniquement.' . PHP_EOL);
}

require_once __DIR__ . '/../includes/CacheSystem.php';

$cache = CacheSystem::getInstance();
$cache->clear();
echo "Cache vidé avec succès.\n";
