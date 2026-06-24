<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';
TenantSchema::ensure();
$pdo = getDB();
foreach (['medecins', 'personnel', 'analyses', 'consultation_soins', 'consultations', 'rendez_vous'] as $t) {
    $s = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $s->execute([$t]);
    echo "=== $t ===\n" . implode(', ', $s->fetchAll(PDO::FETCH_COLUMN)) . "\n\n";
}
