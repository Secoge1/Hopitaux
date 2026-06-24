<?php
/**
 * Diagnostic : modules visibles pour un compte médecin.
 * Usage : php config/diagnose_medecin_nav.php [nom_utilisateur]
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = dirname(__DIR__);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $base . '/config/db.php';
require_once $base . '/config/Auth.php';
require_once $base . '/includes/roles.php';
require_once $base . '/includes/app_layout.php';
require_once $base . '/includes/app_home_modules.php';

$login = $argv[1] ?? 'medecin';

$pdo = getDB();
$stmt = $pdo->prepare('SELECT id, nom_utilisateur, role, statut, tenant_id FROM utilisateurs WHERE nom_utilisateur = ? LIMIT 1');
$stmt->execute([$login]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Utilisateur « {$login} » introuvable.\n";
    exit(1);
}

echo "=== Diagnostic navigation médecin ===\n\n";
echo 'Compte : #' . $user['id'] . ' ' . $user['nom_utilisateur'] . "\n";
echo 'Rôle BDD : [' . ($user['role'] ?? '') . "] (longueur " . strlen((string) ($user['role'] ?? '')) . ")\n";
echo 'Tenant : ' . ($user['tenant_id'] ?? 'null') . "\n\n";

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = $user['nom_utilisateur'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_statut'] = $user['statut'] ?? 'actif';
$_SESSION['user_connected'] = true;
$_SESSION['tenant_id'] = (int) ($user['tenant_id'] ?? 1);
$_SESSION['last_activity'] = time();

$auth = Auth::getInstance();

echo 'Connecté : ' . ($auth->estConnecte() ? 'oui' : 'non') . "\n";
echo 'Rôle session : [' . ($auth->getUserRole() ?? '') . "]\n";
echo 'estMedecin() : ' . ($auth->estMedecin() ? 'oui' : 'non') . "\n\n";

echo "--- Sidebar (app_nav_items) ---\n";
foreach (app_nav_items($auth, 0) as $item) {
    if (!empty($item['sep'])) {
        echo "  ---\n";
        continue;
    }
    if (!empty($item['roles']) && !$auth->aUnRole($item['roles'])) {
        echo '  [MASQUÉ] ' . ($item['label'] ?? '') . ' (rôles: ' . implode(',', $item['roles']) . ")\n";
        continue;
    }
    if (!empty($item['admin_only']) && !$auth->estAdmin()) {
        echo '  [MASQUÉ admin] ' . ($item['label'] ?? '') . "\n";
        continue;
    }
    echo '  [OK] ' . ($item['label'] ?? '') . ' → ' . ($item['href'] ?? '') . "\n";
}

echo "\n--- Accueil (app_home_modules) ---\n";
foreach (app_home_modules($auth) as $mod) {
    echo '  [OK] ' . $mod['title'] . ' → ' . $mod['href'] . "\n";
}

echo "\n--- Barre mobile (app_mobile_nav_items) ---\n";
foreach (app_mobile_nav_items($auth) as $item) {
    echo '  [OK] ' . $item['text'] . ' → ' . $item['url'] . "\n";
}

echo "\n--- Modules rôle (app_modules_for_role) ---\n";
$mods = app_modules_for_role((string) $user['role']);
echo '  ' . implode(', ', $mods) . "\n";

$hasConsult = in_array('consultations', $mods, true);
$hasLab = in_array('laboratoire', $mods, true);
echo "\n=== Résultat : consultations=" . ($hasConsult ? 'OK' : 'ABSENT') . ' laboratoire=' . ($hasLab ? 'OK' : 'ABSENT') . " ===\n";
