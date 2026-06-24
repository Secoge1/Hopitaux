<?php
/**
 * Vérifie les droits du rôle comptable (modules + écriture finances / paiements).
 * Usage : php config/verify_comptable_access.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

$base = dirname(__DIR__);
require_once $base . '/includes/roles.php';
require_once $base . '/includes/module_guard.php';

$ok = 0;
$fail = 0;

function ccheck(bool $cond, string $label): void
{
    global $ok, $fail;
    echo ($cond ? 'OK  ' : 'FAIL ') . "$label\n";
    $cond ? $ok++ : $fail++;
}

echo "=== Vérification espace comptable ===\n\n";

foreach (['paiements', 'finances', 'communication'] as $mod) {
    ccheck(in_array('comptable', app_module_roles($mod), true), "Module $mod accessible");
}

ccheck(in_array('comptable', app_role_finance_write_roles(), true), 'Comptable — écriture finances');
ccheck(in_array('comptable', app_role_paiements_write_roles(), true), 'Comptable — écriture paiements');
ccheck(!in_array('secretaire', app_role_finance_write_roles(), true), 'Secrétaire — finances en lecture seule');
ccheck(in_array('secretaire', app_role_paiements_write_roles(), true), 'Secrétaire — écriture paiements');

$files = [
    'finances/nouvelle_ecriture.php' => 'module_require_write',
    'finances/nouveau_compte.php' => 'module_require_write',
    'finances/modifier_ecriture.php' => 'module_require_write',
    'finances/supprimer_ecriture.php' => 'module_require_write',
    'finances/valider_ecriture.php' => 'module_require_write',
    'finances/index.php' => 'peutEcrireFinances',
    'config/Auth.php' => 'peutEcrireFinances',
];

foreach ($files as $rel => $needle) {
    $path = $base . '/' . $rel;
    ccheck(is_file($path) && strpos(file_get_contents($path) ?: '', $needle) !== false, "$rel — $needle");
}

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
