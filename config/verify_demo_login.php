<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';
require_once __DIR__ . '/../includes/saas/SubscriptionService.php';
require_once __DIR__ . '/../includes/saas/PharmaCommercial.php';

try {
    TenantSchema::ensure();
    $clinical = SubscriptionService::getInstance()->ensureDemoTenant();
    echo "Clinical demo: " . json_encode($clinical) . PHP_EOL;
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, nom_utilisateur, role, mot_de_passe FROM utilisateurs WHERE nom_utilisateur=? AND tenant_id=?');
    $stmt->execute([$clinical['username'], $clinical['tenant_id']]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    echo 'Clinical verify: ' . (password_verify($clinical['password'], $u['mot_de_passe'] ?? '') ? 'OK' : 'FAIL') . PHP_EOL;

    $pharma = PharmaCommercial::ensurePharmaDemoTenant();
    echo "Pharma demo: " . json_encode($pharma) . PHP_EOL;
    $stmt->execute([$pharma['username'], $pharma['tenant_id']]);
    $u2 = $stmt->fetch(PDO::FETCH_ASSOC);
    echo 'Pharma verify: ' . (password_verify($pharma['password'], $u2['mot_de_passe'] ?? '') ? 'OK' : 'FAIL') . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
