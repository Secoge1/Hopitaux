<?php
/**
 * Vérification grille modules accueil/dashboard (fix vide mobile).
 * Usage : php config/verify_home_modules.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

$base = dirname(__DIR__);
$ok = 0;
$fail = 0;

function hcheck(bool $cond, string $label): void
{
    global $ok, $fail;
    echo ($cond ? 'OK  ' : 'FAIL ') . "$label\n";
    $cond ? $ok++ : $fail++;
}

echo "=== Vérification grille modules (accueil / dashboard) ===\n\n";

$files = [
    'includes/app_home_modules.php',
    'assets/css/app-home.css',
    'index.php',
    'dashboard.php',
];
foreach ($files as $rel) {
    hcheck(is_file($base . '/' . $rel), "Fichier $rel");
    if (is_file($base . '/' . $rel)) {
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($base . '/' . $rel) . ' 2>&1', $out, $code);
        hcheck($code === 0, 'Syntaxe PHP ' . $rel);
    }
}

$php = file_get_contents($base . '/includes/app_home_modules.php') ?: '';
$css = file_get_contents($base . '/assets/css/app-home.css') ?: '';
$index = file_get_contents($base . '/index.php') ?: '';
$dash = file_get_contents($base . '/dashboard.php') ?: '';

hcheck(strpos($php, 'home-modules-section') !== false, 'Wrapper .home-modules-section présent');
hcheck(strpos($php, 'home-modules-section">') !== false && strpos($php, 'home-section-head') > strpos($php, 'home-modules-section">'), 'En-tête modules dans .home-modules-section');
hcheck(strpos($php, 'id="homeNoResults" hidden') !== false, '#homeNoResults avec attribut hidden par défaut');
hcheck(
    preg_match('/<div class="home-modules-grid" id="homeModulesGrid">[\s\S]*?<\/div>\s*<div class="home-no-results"/', $php) === 1,
    '#homeNoResults placé APRÈS la fermeture de .home-modules-grid (source PHP)'
);
hcheck(
    strpos($php, 'home-modules-grid" id="homeModulesGrid">') !== false
    && strpos($php, 'home-no-results" id="homeNoResults"') !== false
    && strpos($php, 'home-no-results" id="homeNoResults"') > strpos($php, 'home-modules-grid" id="homeModulesGrid">'),
    '#homeNoResults après #homeModulesGrid dans le fichier source'
);
hcheck(strpos($css, '.home-modules-section') !== false, 'CSS .home-modules-section');
hcheck(strpos($css, 'repeat(auto-fit, minmax(220px, 1fr))') !== false, 'Grille auto-fit (pas auto-fill) base');
hcheck(
    preg_match('/@media \(max-width: 991\.98px\)[\s\S]*?\.home-modules-grid[\s\S]*?flex-direction:\s*column/', $css) === 1,
    'Mobile/tablette ≤991px : colonne flex (plus de grille fantôme)'
);
hcheck(strpos($css, '.home-no-results[hidden]') !== false, 'CSS masque [hidden] sur .home-no-results');
hcheck(strpos($css, 'grid-column') === false || strpos($css, 'grid-column: 1 / -1') === false, 'Plus de grid-column sur .home-no-results');

foreach (['index.php' => $index, 'dashboard.php' => $dash] as $name => $src) {
    hcheck(strpos($src, 'function filterModules()') !== false, "$name — fonction filterModules()");
    hcheck(strpos($src, "addEventListener('search', filterModules)") !== false, "$name — écoute événement search");
    hcheck(strpos($src, 'filterModules();') !== false, "$name — réinitialisation au chargement");
    hcheck(strpos($src, 'empty.hidden = !showEmpty') !== false, "$name — sync attribut hidden");
    hcheck(strpos($src, "querySelectorAll('.home-mod')") !== false, "$name — filtre uniquement .home-mod");
}

echo "\n--- Rendu HTML simulé (admin, 13 modules) ---\n";

if (!function_exists('app_url')) {
    function app_url(string $path): string
    {
        return '/' . ltrim($path, '/');
    }
}

/** @phpstan-ignore-next-line */
class VerifyHomeAuth
{
    public function aUnRole($roles): bool
    {
        return true;
    }

    public function estAdmin(): bool
    {
        return true;
    }

    public function aRole(string $role): bool
    {
        return $role === 'admin';
    }
    public function estClinicienScope(): bool
    {
        return false;
    }
}

require_once $base . '/includes/app_home_modules.php';

$auth = new VerifyHomeAuth();
$modules = app_home_modules($auth);
hcheck(count($modules) === 13, 'Admin voit 13 modules (' . count($modules) . ' trouvés)');

ob_start();
app_home_render_modules_grid($modules, 'verifyModuleSearch');
$html = (string) ob_get_clean();

hcheck(strpos($html, 'home-modules-section') !== false, 'HTML contient .home-modules-section');
hcheck(substr_count($html, 'class="home-mod ') === 13, 'HTML contient 13 cartes .home-mod');
hcheck(strpos($html, 'id="homeModulesGrid"') !== false, 'HTML contient #homeModulesGrid');
hcheck(strpos($html, 'id="homeNoResults"') !== false, 'HTML contient #homeNoResults');

$gridStart = strpos($html, 'id="homeModulesGrid"');
$noResultsPos = strpos($html, 'id="homeNoResults"');
$gridClose = strpos($html, '</div>', (int) strpos($html, '</div>', $gridStart + 1) + 1);
hcheck($gridStart !== false && $noResultsPos !== false && $noResultsPos > $gridStart, '#homeNoResults après le début de la grille');

$gridInner = '';
if (preg_match('/<div class="home-modules-grid" id="homeModulesGrid">(.*?)<\/div>\s*<div class="home-no-results"/s', $html, $m)) {
    $gridInner = $m[1];
}
hcheck($gridInner !== '' && strpos($gridInner, 'home-no-results') === false, 'Rendu : #homeNoResults hors grille');
hcheck(strpos($html, 'data-search=') !== false, 'Cartes avec attribut data-search pour filtre');
hcheck(strpos($html, 'data-search=""') === false, 'Aucun data-search vide (UTF-8 / mb_strtolower)');

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
