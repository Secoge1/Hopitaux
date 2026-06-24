<?php
/**
 * Diagnostic — menus Actions (boutons « … ») dans les listes modules.
 *
 * Usage : uploadez à la racine de l'application puis ouvrez :
 *   https://votre-domaine/chemin-app/diagnostic_menus_actions.php
 *
 * Supprimez ce fichier après résolution du problème (contient des infos système).
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$appRoot = __DIR__;
$selfUrl = basename(__FILE__);

// ── Détection chemin web (comme header_logo.php) ─────────────────────────
function diag_web_base_path(): string
{
    if (function_exists('efficasante_web_base_path')) {
        return efficasante_web_base_path();
    }

    $headerLogo = __DIR__ . '/includes/header_logo.php';
    if (is_file($headerLogo)) {
        require_once $headerLogo;
        if (function_exists('efficasante_web_base_path')) {
            return efficasante_web_base_path();
        }
    }

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $selfFs = realpath(__DIR__);
    if ($docRoot && $selfFs) {
        $d = str_replace('\\', '/', rtrim($docRoot, '/'));
        $a = str_replace('\\', '/', $selfFs);
        if (strpos($a, $d) === 0) {
            $rel = substr($a, strlen($d));
            if ($rel === '' || $rel === '/') {
                return '';
            }
            return '/' . trim($rel, '/');
        }
    }

    return '';
}

function diag_url(string $path, string $base): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = rtrim($base, '/');
    if ($base === '') {
        return '/' . $path;
    }
    return $base . '/' . $path;
}

function diag_http_probe(string $url): array
{
    $result = [
        'url' => $url,
        'ok' => false,
        'status' => null,
        'bytes' => 0,
        'error' => null,
        'snippet' => '',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Hopitaux-ModActions-Diagnostic/1.0',
        ]);
        $body = curl_exec($ch);
        $result['status'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false) {
            $result['error'] = curl_error($ch);
        } else {
            $result['bytes'] = strlen($body);
            $result['snippet'] = substr($body, 0, 200);
            $result['ok'] = $result['status'] >= 200 && $result['status'] < 400 && $result['bytes'] > 0;
        }
        curl_close($ch);
        return $result;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'ignore_errors' => true,
            'header' => "User-Agent: Hopitaux-ModActions-Diagnostic/1.0\r\n",
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
        $result['status'] = (int) $m[0];
    }
    if ($body === false) {
        $result['error'] = 'file_get_contents a échoué (allow_url_fopen ? pare-feu ?)';
        return $result;
    }
    $result['bytes'] = strlen($body);
    $result['snippet'] = substr($body, 0, 200);
    $result['ok'] = $result['status'] !== null && $result['status'] >= 200 && $result['status'] < 400 && $result['bytes'] > 0;

    return $result;
}

function diag_status(bool $ok, ?bool $warn = null): string
{
    if ($warn) {
        return 'warn';
    }
    return $ok ? 'ok' : 'fail';
}

$base = diag_web_base_path();
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$origin = $scheme . '://' . $host;

$filesToCheck = [
    'assets/js/app-mod-actions.js' => 'Script menus Actions',
    'assets/css/app-module.css' => 'Styles modules (mod-actions)',
    'includes/app_mod_actions.php' => 'Injection script head',
    'includes/app_layout.php' => 'Layout principal',
    'includes/app_module_list.php' => 'Template dropdown PHP',
    'sw.js' => 'Service worker PWA',
];

$fileResults = [];
foreach ($filesToCheck as $rel => $label) {
    $abs = $appRoot . '/' . $rel;
    $exists = is_file($abs);
    $size = $exists ? filesize($abs) : 0;
    $mtime = $exists ? date('Y-m-d H:i:s', filemtime($abs)) : '—';
    $content = $exists ? (string) file_get_contents($abs) : '';
    $flags = [];
    if ($rel === 'assets/js/app-mod-actions.js') {
        if (strpos($content, 'popperConfig: false') !== false) {
            $flags[] = 'Contient popperConfig: false (invalide Bootstrap 5.3)';
        }
        if (strpos($content, 'AppModActions') === false) {
            $flags[] = 'AppModActions non exporté';
        }
        if (strpos($content, 'function toggle') === false && strpos($content, 'toggle(btn') === false) {
            $flags[] = 'toggle() absent';
        }
    }
    if ($rel === 'includes/app_mod_actions.php') {
        if (strpos($content, 'app_render_mod_actions_script') === false) {
            $flags[] = 'Fonction app_render_mod_actions_script absente';
        }
    }
    if ($rel === 'includes/app_layout.php') {
        if (strpos($content, 'app_render_mod_actions_script') === false) {
            $flags[] = 'Injection head absente (app_render_mod_actions_script)';
        }
        if (strpos($content, 'bootModActions') === false) {
            $flags[] = 'bootModActions() absent';
        }
        if (strpos($content, 'app-module.css') === false) {
            $flags[] = 'app-module.css absent du head global';
        }
    }
    if ($rel === 'includes/app_module_list.php') {
        if (strpos($content, 'mod-actions') === false) {
            $flags[] = 'Classe mod-actions absente';
        }
        if (strpos($content, 'AppModActions.toggle') === false) {
            $flags[] = 'onclick AppModActions.toggle absent';
        }
    }
    if ($rel === 'sw.js') {
        if (strpos($content, 'Response.error()') === false) {
            $flags[] = 'sw.js sans Response.error() (risque erreur PWA)';
        }
    }
    $fileResults[] = compact('rel', 'label', 'exists', 'size', 'mtime', 'flags');
}

$assetsHttp = [];
foreach (['assets/js/app-mod-actions.js', 'assets/css/app-module.css'] as $rel) {
    $url = $origin . diag_url($rel, $base);
    $ver = '';
    $disk = $appRoot . '/' . $rel;
    if (is_file($disk)) {
        $ver = '?v=' . filemtime($disk);
    }
    $assetsHttp[] = diag_http_probe($url . $ver);
}

$bootstrapCdn = diag_http_probe('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js');
$bootstrapCssCdn = diag_http_probe('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');

$layoutSnippet = '';
$layoutPath = $appRoot . '/includes/app_layout.php';
if (is_file($layoutPath)) {
    $layoutContent = file_get_contents($layoutPath);
    if (strpos($layoutContent, 'app_render_mod_actions_script') !== false) {
        $layoutSnippet = 'Script mod-actions injecté dans app_head() via app_mod_actions.php (OK)';
    } elseif (strpos($layoutContent, 'app-mod-actions.js') !== false) {
        $layoutSnippet = 'Script mod-actions en fin de page (ancienne version — migrer vers head)';
    }
}

$jsUrl = diag_url('assets/js/app-mod-actions.js', $base);
$cssUrl = diag_url('assets/css/app-module.css', $base);
$patientsUrl = diag_url('patients/index.php', $base);

$serverChecks = [
    ['label' => 'Racine application (filesystem)', 'value' => $appRoot, 'status' => is_dir($appRoot) ? 'ok' : 'fail'],
    ['label' => 'Chemin web détecté (base URL)', 'value' => $base === '' ? '(racine domaine)' : $base, 'status' => 'ok'],
    ['label' => 'PHP', 'value' => PHP_VERSION, 'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'ok' : 'warn'],
    ['label' => 'DOCUMENT_ROOT', 'value' => $_SERVER['DOCUMENT_ROOT'] ?? '—', 'status' => 'ok'],
    ['label' => 'allow_url_fopen', 'value' => ini_get('allow_url_fopen') ? 'activé' : 'désactivé', 'status' => ini_get('allow_url_fopen') ? 'ok' : 'warn'],
    ['label' => 'extension cURL', 'value' => function_exists('curl_init') ? 'disponible' : 'absente', 'status' => function_exists('curl_init') ? 'ok' : 'warn'],
    ['label' => 'OPcache', 'value' => function_exists('opcache_get_status') && @opcache_get_status(false) ? 'actif (vider cache si fichiers JS/CSS récents)' : 'inactif / inconnu', 'status' => 'ok'],
];

$issues = [];
foreach ($fileResults as $f) {
    if (!$f['exists']) {
        $issues[] = 'Fichier manquant : ' . $f['rel'];
    }
    foreach ($f['flags'] as $flag) {
        $issues[] = $flag . ' (' . $f['rel'] . ')';
    }
}
foreach ($assetsHttp as $probe) {
    if (!$probe['ok']) {
        $issues[] = 'Asset inaccessible en HTTP : ' . $probe['url'] . ($probe['error'] ? ' — ' . $probe['error'] : ' — HTTP ' . ($probe['status'] ?? '?'));
    }
}
if (!$bootstrapCdn['ok']) {
    $issues[] = 'CDN Bootstrap JS inaccessible depuis le serveur (connexion Internet / pare-feu ?)';
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic — Menus Actions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= htmlspecialchars(diag_url('assets/css/app-module.css', $base)) ?>" rel="stylesheet">
    <style>
        body { background: #f1f5f9; font-family: Inter, system-ui, sans-serif; }
        .diag-wrap { max-width: 960px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        .diag-hero { background: linear-gradient(135deg, #1b4f9b, #1b8fad); color: #fff; border-radius: 14px; padding: 1.5rem; margin-bottom: 1.25rem; }
        .diag-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; box-shadow: 0 4px 14px rgba(15,41,66,.06); }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-fail { background: #fee2e2; color: #991b1b; }
        .badge-warn { background: #fef3c7; color: #92400e; }
        .diag-table { font-size: 0.9rem; }
        .diag-table td, .diag-table th { vertical-align: top; }
        code { font-size: 0.82rem; word-break: break-all; }
        .test-table-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 10px; }
        #diagLog { font-family: ui-monospace, monospace; font-size: 0.8rem; max-height: 220px; overflow: auto; background: #0f172a; color: #e2e8f0; padding: 0.75rem; border-radius: 8px; }
        .log-err { color: #fca5a5; }
        .log-ok { color: #86efac; }
        .log-warn { color: #fde047; }
    </style>
    <?php
        require_once __DIR__ . '/includes/app_mod_actions.php';
        app_render_mod_actions_script();
    ?>
</head>
<body data-base-path="<?= htmlspecialchars(rtrim($base, '/')) ?>">
<div class="diag-wrap">
    <div class="diag-hero">
        <h1 class="h3 mb-2"><i class="fas fa-stethoscope me-2"></i>Diagnostic menus Actions</h1>
        <p class="mb-0 opacity-90">Script autonome — <?= htmlspecialchars(date('d/m/Y H:i:s')) ?></p>
        <p class="mb-0 small mt-2"><strong>Important :</strong> supprimez <code><?= htmlspecialchars($selfUrl) ?></code> après usage.</p>
    </div>

    <?php if ($issues): ?>
    <div class="diag-card border-danger">
        <h2 class="h5 text-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= count($issues) ?> problème(s) détecté(s) côté serveur</h2>
        <ul class="mb-0">
            <?php foreach ($issues as $issue): ?>
            <li><?= htmlspecialchars($issue) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php else: ?>
    <div class="diag-card border-success">
        <h2 class="h5 text-success mb-0"><i class="fas fa-check-circle me-2"></i>Aucun problème évident côté serveur/fichiers</h2>
        <p class="small text-muted mb-0 mt-2">Poursuivez avec le test interactif ci-dessous (navigateur).</p>
    </div>
    <?php endif; ?>

    <div class="diag-card">
        <h2 class="h5">Environnement serveur</h2>
        <table class="table diag-table mb-0">
            <?php foreach ($serverChecks as $row): ?>
            <tr>
                <th style="width:34%"><?= htmlspecialchars($row['label']) ?></th>
                <td><code><?= htmlspecialchars((string) $row['value']) ?></code></td>
                <td style="width:90px"><span class="badge badge-<?= htmlspecialchars($row['status']) ?>"><?= strtoupper($row['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="diag-card">
        <h2 class="h5">Fichiers locaux</h2>
        <table class="table diag-table mb-0">
            <thead><tr><th>Fichier</th><th>Taille</th><th>Modifié</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach ($fileResults as $f): ?>
            <tr>
                <td>
                    <div><?= htmlspecialchars($f['label']) ?></div>
                    <code><?= htmlspecialchars($f['rel']) ?></code>
                    <?php if ($f['flags']): ?>
                    <ul class="small text-danger mb-0 ps-3"><?php foreach ($f['flags'] as $flag): ?><li><?= htmlspecialchars($flag) ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </td>
                <td><?= $f['exists'] ? number_format($f['size']) . ' o' : '—' ?></td>
                <td><?= htmlspecialchars($f['mtime']) ?></td>
                <td><span class="badge badge-<?= $f['exists'] && !$f['flags'] ? 'ok' : ($f['exists'] ? 'warn' : 'fail') ?>"><?= $f['exists'] ? ($f['flags'] ? 'WARN' : 'OK') : 'ABSENT' ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="diag-card">
        <h2 class="h5">Accessibilité HTTP (depuis le serveur)</h2>
        <table class="table diag-table mb-0">
            <thead><tr><th>URL</th><th>HTTP</th><th>Taille</th><th>Statut</th></tr></thead>
            <tbody>
            <?php
            $httpRows = array_merge($assetsHttp, [$bootstrapCdn, $bootstrapCssCdn]);
            foreach ($httpRows as $probe):
                $st = $probe['ok'] ? 'ok' : 'fail';
            ?>
            <tr>
                <td><code><?= htmlspecialchars($probe['url']) ?></code></td>
                <td><?= $probe['status'] ?? '—' ?></td>
                <td><?= $probe['bytes'] ? number_format($probe['bytes']) . ' o' : '—' ?></td>
                <td>
                    <span class="badge badge-<?= $st ?>"><?= $probe['ok'] ? 'OK' : 'ÉCHEC' ?></span>
                    <?php if ($probe['error']): ?><div class="small text-danger"><?= htmlspecialchars($probe['error']) ?></div><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="diag-card">
        <h2 class="h5">URLs utiles (navigateur)</h2>
        <ul>
            <li>Script Actions : <a href="<?= htmlspecialchars($jsUrl) ?>" target="_blank" rel="noopener"><code><?= htmlspecialchars($jsUrl) ?></code></a></li>
            <li>CSS module : <a href="<?= htmlspecialchars($cssUrl) ?>" target="_blank" rel="noopener"><code><?= htmlspecialchars($cssUrl) ?></code></a></li>
            <li>Liste patients (test réel) : <a href="<?= htmlspecialchars($patientsUrl) ?>" target="_blank" rel="noopener"><code><?= htmlspecialchars($patientsUrl) ?></code></a></li>
        </ul>
    </div>

    <div class="diag-card">
        <h2 class="h5">Test interactif (navigateur)</h2>
        <p class="text-muted small">Reproduit le markup des listes modules. Cliquez sur « … » puis consultez le journal.</p>

        <div class="app-mod-table-wrap patients-table-wrap mb-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 patients-table mod-list-table">
                    <thead>
                        <tr>
                            <th>Patient test</th>
                            <th>Statut</th>
                            <th class="text-end mod-actions-cell">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Patient Diagnostic</td>
                            <td><span class="mod-badge mod-badge--actif">Actif</span></td>
                            <td class="text-end mod-actions-cell">
                                <div class="dropdown mod-actions" id="diagDropdown">
                                    <button type="button"
                                            class="btn mod-actions-btn dropdown-toggle"
                                            id="diagToggle"
                                            aria-expanded="false"
                                            aria-haspopup="true"
                                            onclick="return AppModActions.toggle(this, event)"
                                            title="Actions">
                                        <i class="fas fa-ellipsis-h mod-actions-btn-icon"></i>
                                        <span class="mod-actions-btn-text">Actions</span>
                                    </button>
                                    <ul class="dropdown-menu mod-actions-menu shadow dropdown-menu-end" id="diagMenu">
                                        <li class="dropdown-header mod-actions-header">Actions</li>
                                        <li><a class="dropdown-item mod-actions-item mod-actions-item--primary" href="#" onclick="return false;"><span class="mod-actions-icon"><i class="fas fa-eye"></i></span><span class="mod-actions-text">Voir la fiche</span></a></li>
                                        <li><a class="dropdown-item mod-actions-item mod-actions-item--warning" href="#" onclick="return false;"><span class="mod-actions-icon"><i class="fas fa-edit"></i></span><span class="mod-actions-text">Modifier</span></a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="button" class="btn btn-primary btn-sm" id="btnRunTest"><i class="fas fa-play me-1"></i>Lancer le test auto</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClearLog"><i class="fas fa-eraser me-1"></i>Effacer journal</button>
        </div>

        <div id="diagSummary" class="alert alert-secondary small mb-2">En attente du test…</div>
        <div id="diagLog"></div>
    </div>

    <div class="diag-card">
        <h2 class="h5">Copier le rapport</h2>
        <textarea class="form-control font-monospace small" rows="10" readonly id="diagReport"><?php
echo "=== Diagnostic menus Actions ===\n";
echo 'Date: ' . date('c') . "\n";
echo 'Base URL: ' . $base . "\n";
echo 'Origin: ' . $origin . "\n";
echo 'App root: ' . $appRoot . "\n\n";
echo "--- Problèmes serveur ---\n";
echo $issues ? implode("\n", $issues) : "(aucun)\n";
echo "\n\n--- Fichiers ---\n";
foreach ($fileResults as $f) {
    echo $f['rel'] . ' | ' . ($f['exists'] ? 'OK' : 'ABSENT') . ' | ' . $f['size'] . " o\n";
    foreach ($f['flags'] as $flag) {
        echo '  ! ' . $flag . "\n";
    }
}
echo "\n--- HTTP ---\n";
foreach ($httpRows as $probe) {
    echo $probe['url'] . ' | ' . ($probe['status'] ?? '?') . ' | ' . ($probe['ok'] ? 'OK' : 'FAIL') . "\n";
}
?></textarea>
        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="navigator.clipboard.writeText(document.getElementById('diagReport').value); alert('Rapport copié');">Copier</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    var logEl = document.getElementById('diagLog');
    var summaryEl = document.getElementById('diagSummary');
    var lines = [];

    function log(msg, type) {
        var t = type || 'info';
        lines.push('[' + t.toUpperCase() + '] ' + msg);
        var div = document.createElement('div');
        div.className = t === 'err' ? 'log-err' : (t === 'ok' ? 'log-ok' : (t === 'warn' ? 'log-warn' : ''));
        div.textContent = new Date().toLocaleTimeString('fr-FR') + ' — ' + msg;
        logEl.appendChild(div);
        logEl.scrollTop = logEl.scrollHeight;
    }

    window.addEventListener('error', function (e) {
        log('Erreur JS: ' + (e.message || e) + (e.filename ? ' @ ' + e.filename + ':' + e.lineno : ''), 'err');
    });
    window.addEventListener('unhandledrejection', function (e) {
        log('Promise rejetée: ' + e.reason, 'err');
    });

    document.getElementById('btnClearLog').addEventListener('click', function () {
        logEl.innerHTML = '';
        lines = [];
        summaryEl.className = 'alert alert-secondary small mb-2';
        summaryEl.textContent = 'Journal effacé.';
    });

    function cssOverflowChain(el) {
        var chain = [];
        while (el && el !== document.documentElement) {
            var st = window.getComputedStyle(el);
            var ox = st.overflowX;
            var oy = st.overflowY;
            if ((ox && ox !== 'visible') || (oy && oy !== 'visible')) {
                chain.push(el.tagName.toLowerCase() + (el.className ? '.' + String(el.className).trim().split(/\s+/).slice(0, 2).join('.') : '') + ' → overflow-x:' + ox + ', overflow-y:' + oy);
            }
            el = el.parentElement;
        }
        return chain;
    }

    function runDiagnostic() {
        logEl.innerHTML = '';
        lines = [];
        var failures = 0;

        log('Démarrage test navigateur…');

        if (typeof bootstrap === 'undefined') {
            log('bootstrap non défini — CDN Bootstrap JS bloqué ou hors ligne', 'err');
            failures++;
        } else {
            log('Bootstrap ' + (bootstrap.Dropdown ? 'OK (Dropdown disponible)' : 'partiel (Dropdown absent)'), bootstrap.Dropdown ? 'ok' : 'err');
            if (!bootstrap.Dropdown) failures++;
        }

        if (typeof window.AppModActions === 'undefined') {
            log('AppModActions absent — app-mod-actions.js non chargé ou erreur de syntaxe', 'err');
            failures++;
        } else {
            log('AppModActions.scan disponible', 'ok');
            try {
                window.AppModActions.scan(document);
                log('AppModActions.scan(document) exécuté', 'ok');
            } catch (e) {
                log('AppModActions.scan() a levé: ' + e.message, 'err');
                failures++;
            }
        }

        var toggle = document.getElementById('diagToggle');
        var menu = document.getElementById('diagMenu');
        var wrap = document.querySelector('.test-table-wrap, .table-responsive');

        if (!toggle || !menu) {
            log('Éléments test #diagToggle / #diagMenu introuvables', 'err');
            failures++;
        } else {
            var overflow = cssOverflowChain(toggle);
            if (overflow.length) {
                log('Overflow sur ancêtres (peut masquer le menu): ' + overflow.join(' | '), 'warn');
            } else {
                log('Aucun overflow:hidden/auto sur la chaîne DOM du bouton', 'ok');
            }

            try {
                var inst = bootstrap.Dropdown.getOrCreateInstance(toggle, { display: 'static', autoClose: true });
                log('Dropdown.getOrCreateInstance OK', 'ok');
                /* inst.show() différé : évite que le clic sur « Lancer le test » ne ferme le menu via clearMenus */
                setTimeout(function () {
                    inst.show();
                    setTimeout(function () {
                        var shown = menu.classList.contains('show');
                        var rect = menu.getBoundingClientRect();
                        var portal = menu.classList.contains('mod-actions-menu--portal');
                        var inBody = menu.parentElement === document.body;

                        if (shown) {
                            log('Menu .show présent (test auto différé)', 'ok');
                        } else {
                            log('Menu sans .show — fermeture immédiate (clearMenus ou JS obsolète)', 'err');
                            log('→ Uploadez la dernière version de assets/js/app-mod-actions.js (OPEN_GUARD_MS + portal différé)', 'warn');
                            failures++;
                        }

                        log('Menu parent: ' + (menu.parentElement ? menu.parentElement.tagName + (menu.parentElement.id ? '#' + menu.parentElement.id : '') : '?') +
                            ' | portal=' + portal + ' | inBody=' + inBody, portal && inBody ? 'ok' : (shown ? 'warn' : 'err'));

                        log('Position menu: top=' + Math.round(rect.top) + ' left=' + Math.round(rect.left) +
                            ' w=' + Math.round(rect.width) + ' h=' + Math.round(rect.height) +
                            ' visible=' + (rect.width > 0 && rect.height > 0 && rect.bottom > 0 && rect.right > 0),
                            (rect.width > 0 && rect.height > 0) ? 'ok' : 'err');

                        if (rect.width <= 0 || rect.height <= 0) failures++;

                        var st = window.getComputedStyle(menu);
                        log('CSS menu: display=' + st.display + ' visibility=' + st.visibility + ' opacity=' + st.opacity + ' z-index=' + st.zIndex);

                        if (shown) {
                            inst.hide();
                        }

                        var scriptEl = document.querySelector('script[src*="app-mod-actions"]');
                        var scriptUrl = scriptEl ? scriptEl.getAttribute('src') : '<?= htmlspecialchars($jsUrl, ENT_QUOTES) ?>';
                        fetch(scriptUrl, { method: 'HEAD', cache: 'no-store' })
                            .then(function (r) {
                                log('HEAD app-mod-actions.js → HTTP ' + r.status, r.ok ? 'ok' : 'err');
                                if (!r.ok) failures++;
                                finish();
                            })
                            .catch(function (e) {
                                log('HEAD app-mod-actions.js échoué: ' + e.message, 'err');
                                failures++;
                                finish();
                            });
                    }, 250);
                }, 50);
            } catch (e) {
                log('Erreur Dropdown: ' + e.message, 'err');
                failures++;
                finish();
            }
        }

        function finish() {
            if (failures === 0) {
                summaryEl.className = 'alert alert-success small mb-2';
                summaryEl.innerHTML = '<strong>Test navigateur réussi.</strong> Si les listes réelles échouent encore : cache navigateur (Ctrl+F5), Service Worker PWA, ou anciens fichiers sur le serveur.';
            } else {
                summaryEl.className = 'alert alert-danger small mb-2';
                summaryEl.innerHTML = '<strong>' + failures + ' échec(s)</strong> — copiez le rapport ci-dessous et transmettez-le au support.';
            }

            var report = document.getElementById('diagReport');
            if (report) {
                report.value += '\n\n--- Test navigateur ---\n' + lines.join('\n');
            }
        }

        if (!toggle || !menu) finish();
    }

    document.getElementById('btnRunTest').addEventListener('click', runDiagnostic);

    document.getElementById('diagToggle').addEventListener('shown.bs.dropdown', function () {
        log('Événement shown.bs.dropdown (clic manuel)', 'ok');
    });
    document.getElementById('diagToggle').addEventListener('hide.bs.dropdown', function (e) {
        var menu = document.getElementById('diagMenu');
        var guarded = menu && Date.now() < (menu._modActionsGuardUntil || 0);
        log('Événement hide.bs.dropdown' + (e.defaultPrevented ? ' (bloqué par garde)' : '') + (guarded ? ' [période de grâce]' : ''), guarded || e.defaultPrevented ? 'warn' : 'info');
    });
    document.getElementById('diagToggle').addEventListener('hidden.bs.dropdown', function () {
        log('Événement hidden.bs.dropdown — menu fermé', 'info');
    });

    if (typeof window.AppModActions !== 'undefined') {
        try { window.AppModActions.scan(document); } catch (e) { log('Scan initial: ' + e.message, 'warn'); }
    }

    log('Prêt. Cliquez « Lancer le test auto » ou le bouton « … » ci-dessus.');
})();
</script>
</body>
</html>
