<?php
/**
 * Vide les données MySQL en contournant les contraintes FK (#1701 sur TRUNCATE).
 *
 * Usage CLI :
 *   php config/vider_donnees.php --metier          # garde users/tenants/paramètres
 *   php config/vider_donnees.php --tout --confirm  # vide toutes les tables
 *
 * Ne pas exposer via le web sans protection admin.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI uniquement.' . PHP_EOL);
}

require_once __DIR__ . '/db.php';

$metierOnly = in_array('--metier', $argv, true);
$tout = in_array('--tout', $argv, true);
$confirm = in_array('--confirm', $argv, true);

if (!$metierOnly && !$tout) {
    fwrite(STDERR, "Indiquez --metier ou --tout --confirm\n");
    exit(1);
}

if (!$confirm) {
    fwrite(STDERR, "Ajoutez --confirm pour exécuter (destructif).\n");
    exit(1);
}

$pdo = getDB();

$preserve = [
    'utilisateurs',
    'tenants',
    'roles',
    'parametres_systeme',
    'system_licenses',
    'prix_licences',
    'modules_licences',
    'licences',
    'renouvellements_licences',
    'subscription_orders',
];

$stmt = $pdo->query("
    SELECT TABLE_NAME
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_TYPE = 'BASE TABLE'
    ORDER BY TABLE_NAME
");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($metierOnly) {
    $tables = array_values(array_filter($tables, static fn(string $t): bool => !in_array($t, $preserve, true)));
}

echo 'Tables à vider : ' . count($tables) . PHP_EOL;

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $pdo->exec('TRUNCATE TABLE `' . str_replace('`', '``', $table) . '`');
        echo "  OK  $table\n";
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    require_once __DIR__ . '/../includes/CacheSystem.php';
    CacheSystem::getInstance()->clear();
    echo "Cache application vidé.\n";

    echo "Terminé.\n";
} catch (Throwable $e) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
