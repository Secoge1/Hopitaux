<?php
/**
 * Audit scripts d'action / redirections — app_url, init, catch Throwable.
 * Usage : php config/verify_action_scripts.php
 */
declare(strict_types=1);

$base = dirname(__DIR__);
$ok = 0;
$warn = 0;
$fail = 0;

function vok(string $m): void { global $ok; $ok++; echo "[OK] $m\n"; }
function vwarn(string $m): void { global $warn; $warn++; echo "[WARN] $m\n"; }
function vfail(string $m): void { global $fail; $fail++; echo "[FAIL] $m\n"; }

echo "=== Audit scripts d'action / redirections ===\n\n";

$skipDirs = ['vendor', 'node_modules', 'efficasante_app', 'efficasante_web', '.git'];

/** @var list<string> */
$actionCandidates = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    foreach ($skipDirs as $skip) {
        if (strpos(str_replace('\\', '/', $path), "/$skip/") !== false) {
            continue 2;
        }
    }
    $rel = str_replace('\\', '/', substr($path, strlen($base) + 1));
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }

    $usesAppUrl = strpos($content, 'app_url(') !== false;
    $hasRedirect = (bool) preg_match('/header\s*\(\s*[\'"]Location:/i', $content);
    $isAction = preg_match('#/(actions|creer_depuis_|enregistrer_|export_|api_|_handlers)\.#', $rel)
        || preg_match('#/(actions|creer_depuis_|enregistrer_|export_)[^/]+\.php$#', $rel)
        || basename($rel) === 'actions.php';

    if (!$usesAppUrl && !$hasRedirect && !$isAction) {
        continue;
    }

    if (!$usesAppUrl && !$hasRedirect) {
        continue;
    }

    $actionCandidates[] = $rel;

    $hasInit = strpos($content, 'init.php') !== false;
    $hasLayout = strpos($content, 'app_layout.php') !== false
        || strpos($content, 'app_module_layout.php') !== false
        || strpos($content, 'app_platform_layout.php') !== false;
    $hasUrls = strpos($content, 'app_urls.php') !== false;
    $hasCatchThrowable = strpos($content, 'catch (Throwable') !== false;

    if ($usesAppUrl && !$hasInit && !$hasLayout && !$hasUrls) {
        if (strpos($content, 'function app_url') !== false) {
            vok("$rel (définit app_url)");
            continue;
        }
        if (strpos($content, "function_exists('app_url')") !== false) {
            vok("$rel (garde function_exists app_url)");
            continue;
        }
        vfail("$rel — app_url() sans init.php / app_layout / app_urls");
        continue;
    }

    if ($usesAppUrl && $hasInit && !$hasLayout && !$hasUrls) {
        // OK si init.php charge app_urls.php
        if (!is_file("$base/includes/init.php") || strpos(file_get_contents("$base/includes/init.php"), 'app_urls.php') === false) {
            vfail("$rel — init sans app_urls.php");
            continue;
        }
    }

    $isCreerScript = strpos($rel, 'creer_depuis_') !== false || strpos($rel, 'enregistrer_') !== false;
    if ($isCreerScript && !$hasCatchThrowable) {
        vwarn("$rel — pas de catch Throwable (erreurs fatales possibles)");
    } else {
        vok($rel);
    }
}

echo "\n--- Fichiers ciblés (paiements / labo / patients) ---\n";

$critical = [
    'paiements/creer_depuis_consultation.php',
    'paiements/creer_depuis_analyse.php',
    'includes/init.php',
    'includes/app_urls.php',
    'models/Paiement.php',
    'includes/PaymentAudit.php',
    'patients/enregistrer_analyse.php',
    'patients/enregistrer_consultation.php',
    'consultations/actions.php',
    'rendez-vous/actions.php',
    'laboratoire/export_analyse_pdf.php',
    'laboratoire/export_analyse_html.php',
    'paiements/export_paiement_individual_pdf.php',
    'admin_platform/_handlers.php',
];

foreach ($critical as $rel) {
    if (!is_file("$base/$rel")) {
        vfail("Fichier manquant: $rel");
        continue;
    }
    exec('php -l ' . escapeshellarg("$base/$rel") . ' 2>&1', $out, $code);
    $code === 0 ? vok("Syntaxe $rel") : vfail("Syntaxe $rel: " . implode(' ', $out));

    $c = file_get_contents("$base/$rel");
    if (strpos($c, 'app_url(') !== false) {
        $safe = strpos($c, 'init.php') !== false
            || strpos($c, 'app_layout.php') !== false
            || strpos($c, 'app_module_layout.php') !== false
            || strpos($c, 'app_urls.php') !== false;
        $safe ? vok("app_url protégé: $rel") : vfail("app_url NON protégé: $rel");
    }
}

$init = file_get_contents("$base/includes/init.php");
strpos($init, 'app_urls.php') !== false ? vok('init.php charge app_urls.php') : vfail('init.php ne charge pas app_urls.php');

echo "\n--- Résumé ---\n";
echo "Scripts analysés: " . count($actionCandidates) . " | OK: $ok | WARN: $warn | FAIL: $fail\n";
exit($fail > 0 ? 1 : 0);
