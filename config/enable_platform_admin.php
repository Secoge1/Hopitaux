<?php
/**
 * Active le compte admin plateforme (vendeur SaaS).
 * Usage CLI : php config/enable_platform_admin.php [nom_utilisateur]
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';

TenantSchema::ensure();

$pdo = getDB();
$username = $argv[1] ?? 'admin';

$stmt = $pdo->prepare('SELECT id, nom_utilisateur, role FROM utilisateurs WHERE nom_utilisateur = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $stmt = $pdo->query("SELECT id, nom_utilisateur, role FROM utilisateurs WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$user) {
    fwrite(STDERR, "Aucun utilisateur admin trouvé.\n");
    exit(1);
}

$upd = $pdo->prepare('UPDATE utilisateurs SET is_platform_admin = 1 WHERE id = ?');
$upd->execute([(int) $user['id']]);

echo "OK — is_platform_admin=1 pour « {$user['nom_utilisateur']} » (id {$user['id']}).\n";
echo "Reconnectez-vous pour voir « Admin plateforme » dans le menu.\n";
