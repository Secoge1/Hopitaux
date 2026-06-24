<?php
require __DIR__ . '/../config/db.php';
$pdo = getDB();
try {
    $pdo->exec('SET innodb_lock_wait_timeout = 2');
    $ids = $pdo->query("SELECT id FROM information_schema.processlist WHERE db = DATABASE() AND command = 'Sleep' AND time > 30")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        try {
            $pdo->exec("KILL $id");
            echo "Killed connection $id\n";
        } catch (Throwable $e) {
            echo "Skip $id: " . $e->getMessage() . "\n";
        }
    }
    $pdo->exec("UPDATE subscription_orders SET payment_status = 'cancelled' WHERE payment_status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    echo "Stale pending orders cancelled\n";
} catch (Throwable $e) {
    echo $e->getMessage() . "\n";
}
