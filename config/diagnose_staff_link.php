<?php
/**
 * Diagnostic rattachement utilisateur ↔ fiche métier
 * Usage: php config/diagnose_staff_link.php [nom_utilisateur]
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI uniquement.\n");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/saas/TenantContext.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';
require_once __DIR__ . '/../includes/staff_link.php';

TenantSchema::ensure();
$pdo = getDB();
$login = $argv[1] ?? 'medecin';

echo "=== Diagnostic rattachement ===\n\n";

$cols = ['medecins.utilisateur_id', 'personnel.utilisateur_id', 'analyses.technicien_id'];
foreach (['medecins', 'personnel'] as $t) {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = \'utilisateur_id\''
    );
    $stmt->execute([$t]);
    echo ($stmt->fetchColumn() ? 'OK' : 'MANQUANT') . "  Colonne {$t}.utilisateur_id\n";
}

$stmt = $pdo->prepare('SELECT id, nom_utilisateur, email, role, tenant_id FROM utilisateurs WHERE nom_utilisateur = ? LIMIT 1');
$stmt->execute([$login]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo "\nUtilisateur '$login' introuvable.\n";
    exit(1);
}
echo "\nUtilisateur: #{$user['id']} {$user['nom_utilisateur']} role=[{$user['role']}] tenant={$user['tenant_id']}\n";
echo 'Type liaison attendu: ' . (StaffLink::linkTypeForRole($user['role'] ?? '') ?? 'aucun') . "\n";

if (($user['role'] ?? '') === '') {
    echo "\n*** PROBLÈME: le rôle est VIDE en base — le champ de rattachement ne s'affiche pas.\n";
    echo "    Corrigez: UPDATE utilisateurs SET role = 'medecin' WHERE id = {$user['id']};\n";
}

TenantContext::setTenantId((int) ($user['tenant_id'] ?: 1));
$_SESSION['tenant_id'] = (int) ($user['tenant_id'] ?: 1);

$meds = StaffLink::listMedecinsForSelect();
$pers = StaffLink::listPersonnelForSelect();
echo "\nFiches disponibles (tenant " . TenantContext::getTenantId() . "):\n";
echo '  Médecins: ' . count($meds) . "\n";
foreach ($meds as $m) {
    echo "    - #{$m['id']} {$m['label']}" . ($m['linked_user_id'] ? " (lié user #{$m['linked_user_id']})" : '') . "\n";
}
echo '  Personnel: ' . count($pers) . "\n";
foreach (array_slice($pers, 0, 8) as $p) {
    echo "    - #{$p['id']} {$p['label']}" . ($p['linked_user_id'] ? " (lié user #{$p['linked_user_id']})" : '') . "\n";
}

if (count($meds) === 0 && StaffLink::linkTypeForRole($user['role'] ?? '') === 'medecin') {
    echo "\n*** PROBLÈME: aucun médecin visible pour ce tenant.\n";
    $orphans = (int) $pdo->query('SELECT COUNT(*) FROM medecins WHERE tenant_id IS NULL')->fetchColumn();
    $total = (int) $pdo->query('SELECT COUNT(*) FROM medecins')->fetchColumn();
    echo "    Médecins total: $total, sans tenant_id: $orphans\n";
    if ($orphans > 0) {
        echo "    → Exécutez: UPDATE medecins SET tenant_id = {$user['tenant_id']} WHERE tenant_id IS NULL;\n";
    }
}

$link = StaffLink::getLinkForUser((int) $user['id']);
echo "\nLiaison actuelle: " . ($link['label'] ?? 'aucune') . "\n";
