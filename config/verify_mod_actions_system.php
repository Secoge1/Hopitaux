<?php
/**
 * Vérification système — menus Actions (mod-actions).
 * Usage CLI : php config/verify_mod_actions_system.php
 * Usage web  : /config/verify_mod_actions_system.php (à supprimer en production publique)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$isCli = PHP_SAPI === 'cli';

function out(string $msg, string $level = 'info'): void
{
    global $isCli;
    switch ($level) {
        case 'ok':
            $prefix = '[OK] ';
            break;
        case 'warn':
            $prefix = '[WARN] ';
            break;
        case 'err':
            $prefix = '[ERR] ';
            break;
        default:
            $prefix = '[INFO] ';
    }
    $line = $prefix . $msg;
    if ($isCli) {
        echo $line . PHP_EOL;
        return;
    }
    switch ($level) {
        case 'ok':
            $class = 'text-success';
            break;
        case 'warn':
            $class = 'text-warning';
            break;
        case 'err':
            $class = 'text-danger';
            break;
        default:
            $class = '';
    }
    echo '<div class="' . htmlspecialchars($class) . '">' . htmlspecialchars($line) . '</div>';
}

$errors = 0;
$warnings = 0;

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Vérif. mod-actions</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>';
    echo '<body class="p-4"><h1>Vérification menus Actions</h1><div class="font-monospace small">';
}

out('Racine projet : ' . $root);

/* ── Fichiers cœur ── */
$coreFiles = [
    'includes/app_mod_actions.php' => 'Injection script head',
    'includes/app_layout.php' => 'Layout principal',
    'includes/app_module_list.php' => 'Template dropdown',
    'assets/js/app-mod-actions.js' => 'Script complet',
    'assets/css/app-module.css' => 'Styles mod-actions',
    'sw.js' => 'Service worker PWA',
];

foreach ($coreFiles as $rel => $label) {
    $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        out("$label manquant ($rel)", 'err');
        $errors++;
        continue;
    }
    out("$label présent ($rel)", 'ok');
}

/* ── app_layout.php intègre le head ── */
$layout = file_get_contents($root . '/includes/app_layout.php') ?: '';
if (strpos($layout, 'app_render_mod_actions_script') === false) {
    out('app_layout.php n\'appelle pas app_render_mod_actions_script() dans app_head', 'err');
    $errors++;
} else {
    out('app_head() injecte app_render_mod_actions_script()', 'ok');
}

if (strpos($layout, 'bootModActions') === false) {
    out('bootModActions absent de app_layout_end', 'warn');
    $warnings++;
} else {
    out('bootModActions présent dans app_layout_end', 'ok');
}

if (strpos($layout, 'appModSubmitActionForm') === false) {
    out('appModSubmitActionForm absent du layout', 'warn');
    $warnings++;
} else {
    out('appModSubmitActionForm défini dans le layout', 'ok');
}

/* ── Template dropdown ── */
$listPhp = file_get_contents($root . '/includes/app_module_list.php') ?: '';
$templateChecks = [
    'dropdown mod-actions' => 'Classe conteneur',
    'mod-actions-btn' => 'Classe bouton',
    'AppModActions.toggle(this, event)' => 'onclick inline',
    'mod-actions-menu' => 'Classe menu',
    'app_mod_actions_dropdown' => 'Fonction PHP',
];
foreach ($templateChecks as $needle => $label) {
    if (strpos($listPhp, $needle) === false) {
        out("Template : $label manquant ($needle)", 'err');
        $errors++;
    } else {
        out("Template : $label OK", 'ok');
    }
}

/* ── JS complet ── */
$js = file_get_contents($root . '/assets/js/app-mod-actions.js') ?: '';
if (strpos($js, 'window.AppModActions') === false) {
    out('app-mod-actions.js n\'exporte pas AppModActions', 'err');
    $errors++;
}
if (strpos($js, 'function toggle') === false && strpos($js, 'toggle(btn') === false) {
    out('app-mod-actions.js : toggle() introuvable', 'err');
    $errors++;
} else {
    out('app-mod-actions.js : toggle() présent', 'ok');
}

/* ── Service worker ── */
$sw = file_get_contents($root . '/sw.js') ?: '';
if (preg_match('/caches\.match.*\|\|\s*undefined/s', $sw) || preg_match('/catch\s*\(\s*\(\)\s*=>\s*caches\.match/s', $sw)) {
    out('sw.js : risque Response undefined (ancienne version)', 'warn');
    $warnings++;
} elseif (strpos($sw, 'Response.error()') !== false) {
    out('sw.js : gestion Response.error() OK', 'ok');
} else {
    out('sw.js : vérifier manuellement la gestion fetch', 'warn');
    $warnings++;
}

/* ── Listes modules utilisant app_mod_actions_dropdown ── */
$listViews = glob($root . '/*/_list_view.php') ?: [];
$listViews = array_merge($listViews, glob($root . '/*/index.php') ?: []);
$listViews = array_unique($listViews);

$modulesWithDropdown = [];
$modulesMissingCell = [];

foreach ($listViews as $file) {
    $content = file_get_contents($file) ?: '';
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
    $rel = str_replace('\\', '/', $rel);
    if (strpos($content, 'app_mod_actions_dropdown') !== false) {
        $modulesWithDropdown[] = $rel;
        if (strpos($content, 'mod-actions-cell') === false && strpos($content, 'mod-actions') === false) {
            $modulesMissingCell[] = $rel;
        }
    }
}

sort($modulesWithDropdown);
out('Modules avec app_mod_actions_dropdown : ' . count($modulesWithDropdown), 'info');
foreach ($modulesWithDropdown as $m) {
    out('  → ' . $m, 'ok');
}

$extraDropdownFiles = [
    'finances/index.php',
    'finances/comptes.php',
    'laboratoire/rapport.php',
    'maintenance/intervention.php',
    'parametres/utilisateurs.php',
];
foreach ($extraDropdownFiles as $rel) {
    $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        continue;
    }
    $content = file_get_contents($path) ?: '';
    if (strpos($content, 'app_mod_actions_dropdown') !== false) {
        out('  → ' . $rel . ' (page liste)', 'ok');
    }
}

if ($modulesMissingCell !== []) {
    foreach ($modulesMissingCell as $m) {
        out("Dropdown sans mod-actions-cell : $m", 'warn');
        $warnings++;
    }
}

/* ── Pages app_head sans app_module_layout (doivent quand même avoir le script via app_head) ── */
$headPages = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false
        || strpos($path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR) !== false
        || strpos($path, DIRECTORY_SEPARATOR . 'efficasante_web' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }
    $content = file_get_contents($path) ?: '';
    if (strpos($content, 'app_head(') === false) {
        continue;
    }
    if (strpos($content, 'app_render_mod_actions_script') !== false) {
        continue;
    }
    if (strpos($content, 'require') !== false && strpos($content, 'app_layout.php') !== false) {
        $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
        $headPages[] = str_replace('\\', '/', $rel);
    }
}
sort($headPages);
if ($headPages === []) {
    out('Toutes les pages via app_layout.php héritent du script head', 'ok');
} else {
    out('Pages app_head via app_layout (script injecté indirectement) : ' . count($headPages), 'ok');
}

/* ── Ancien data-bs-toggle dans listes ── */
$legacyDropdown = [];
foreach (glob($root . '/**/*.php', GLOB_BRACE) ?: [] as $path) {
    if (!is_file($path)) {
        continue;
    }
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    if (strpos($rel, 'vendor') !== false || strpos($rel, 'diagnostic_') !== false || strpos($rel, 'verify_mod_actions') !== false) {
        continue;
    }
    $content = file_get_contents($path) ?: '';
    if (strpos($content, 'data-bs-toggle="dropdown"') !== false && strpos($content, 'mod-actions') !== false) {
        $legacyDropdown[] = str_replace('\\', '/', $rel);
    }
}
if ($legacyDropdown === []) {
    out('Aucun conflit data-bs-toggle + mod-actions', 'ok');
} else {
    foreach ($legacyDropdown as $f) {
        out("data-bs-toggle dropdown + mod-actions : $f", 'warn');
        $warnings++;
    }
}

/* ── Admin plateforme (UI séparée) ── */
if (is_file($root . '/includes/app_platform_actions.php')) {
    out('Admin plateforme : platform-action-btns (UI distincte, hors mod-actions)', 'info');
}

/* ── Résumé ── */
out('─────────────────────────────', 'info');
if ($errors === 0 && $warnings === 0) {
    out('Résultat : tout est cohérent (' . count($modulesWithDropdown) . ' listes mod-actions)', 'ok');
} elseif ($errors === 0) {
    out("Résultat : OK avec $warnings avertissement(s)", 'warn');
} else {
    out("Résultat : $errors erreur(s), $warnings avertissement(s)", 'err');
}

if (!$isCli) {
    echo '</div><p class="mt-3 text-muted small">Supprimez ce fichier après vérification en production.</p></body></html>';
}

exit($errors > 0 ? 1 : 0);
