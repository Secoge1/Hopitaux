<?php
require __DIR__ . '/db.php';
$pdo = getDB();
foreach (['patients', 'consultations', 'analyses', 'rendez_vous'] as $t) {
    echo "=== $t ===\n";
    if (!$pdo->query("SHOW TABLES LIKE '$t'")->fetch()) {
        echo "  (table absente)\n";
        continue;
    }
    foreach ($pdo->query("SHOW COLUMNS FROM `$t`") as $c) {
        echo '  ' . $c['Field'] . ' (' . $c['Type'] . ")\n";
    }
}
