<?php
/**
 * Vérification rigoureuse — DOM, filtre simulé, CSS, JS.
 * Usage : php config/verify_home_modules_rigorous.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

$base = dirname(__DIR__);
$ok = 0;
$fail = 0;

function rcheck(bool $cond, string $label): void
{
    global $ok, $fail;
    echo ($cond ? 'OK  ' : 'FAIL ') . "$label\n";
    $cond ? $ok++ : $fail++;
}

echo "=== Vérification rigoureuse (modules accueil / dashboard) ===\n\n";

passthru('php ' . escapeshellarg($base . '/config/verify_home_modules.php'), $baseExit);
rcheck($baseExit === 0, 'Script verify_home_modules.php — exit 0');

echo "\n--- Analyse DOM (rendu PHP) ---\n";

if (!function_exists('app_url')) {
    function app_url(string $path): string
    {
        return '/' . ltrim($path, '/');
    }
}

class RigorousVerifyAuth
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

$auth = new RigorousVerifyAuth();
$modules = app_home_modules($auth);

ob_start();
app_home_render_modules_grid($modules, 'rigorousSearch');
$html = (string) ob_get_clean();

$dom = new DOMDocument();
$loaded = @$dom->loadHTML(
    '<?xml encoding="utf-8" ?><!DOCTYPE html><html><body>' . $html . '</body></html>',
    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
);
rcheck($loaded, 'DOMDocument — parse HTML sans erreur fatale');

$xpath = new DOMXPath($dom);
/** @var DOMElement|null $grid */
$grid = $dom->getElementById('homeModulesGrid');
/** @var DOMElement|null $empty */
$empty = $dom->getElementById('homeNoResults');
/** @var DOMElement|null $search */
$search = $dom->getElementById('rigorousSearch');

rcheck($grid instanceof DOMElement, 'DOM — #homeModulesGrid présent');
rcheck($empty instanceof DOMElement, 'DOM — #homeNoResults présent');
rcheck($search instanceof DOMElement, 'DOM — input recherche personnalisé présent');

if ($grid && $empty) {
    rcheck($empty->parentNode !== $grid, 'DOM — #homeNoResults N\'EST PAS enfant direct de la grille');
    rcheck(
        $empty->parentNode instanceof DOMElement && strpos($empty->parentNode->getAttribute('class'), 'home-modules-section') !== false,
        'DOM — #homeNoResults est dans .home-modules-section'
    );

    $directMods = 0;
    foreach ($grid->childNodes as $child) {
        if ($child instanceof DOMElement && strpos($child->getAttribute('class'), 'home-mod') !== false) {
            $directMods++;
        }
        if ($child instanceof DOMElement && strpos($child->getAttribute('class'), 'home-no-results') !== false) {
            rcheck(false, 'DOM — bloc home-no-results trouvé DANS la grille (régression)');
        }
    }
    rcheck($directMods === 13, "DOM — 13 cartes .home-mod enfants directs de la grille ($directMods trouvées)");

    $sectionHeads = $xpath->query("//*[contains(@class,'home-modules-section')]//*[contains(@class,'home-section-head')]");
    rcheck($sectionHeads !== false && $sectionHeads->length === 1, 'DOM — en-tête unique dans .home-modules-section');

    rcheck($empty->hasAttribute('hidden'), 'DOM — #homeNoResults a attribut hidden par défaut');
}

if ($search) {
    rcheck($search->getAttribute('autocomplete') === 'off', 'DOM — autocomplete=off sur le champ recherche');
    rcheck($search->getAttribute('type') === 'search', 'DOM — type=search sur le champ recherche');
}

echo "\n--- Simulation filtre JavaScript ---\n";

/** @param list<DOMElement> $modEls */
function rigorous_mod_search_text(DOMElement $el): string
{
    $raw = $el->getAttribute('data-search');
    if ($raw !== '') {
        return strtolower($raw);
    }
    $title = '';
    $desc = '';
    foreach ($el->getElementsByTagName('h3') as $h3) {
        $title = trim($h3->textContent);
        break;
    }
    foreach ($el->getElementsByTagName('p') as $p) {
        $desc = trim($p->textContent);
        break;
    }
    $tag = '';
    foreach ($el->getElementsByTagName('span') as $span) {
        if (strpos($span->getAttribute('class'), 'home-mod-tag') !== false) {
            $tag = trim($span->textContent);
            break;
        }
    }

    return strtolower($title . ' ' . $desc . ' ' . $tag);
}

/** @param list<DOMElement> $modEls */
function simulate_filter(array $modEls, string $q): array
{
    $q = strtolower(trim($q));
    $visible = 0;
    foreach ($modEls as $el) {
        $data = rigorous_mod_search_text($el);
        $match = $q === '' || strpos($data, $q) !== false;
        if ($match) {
            $visible++;
        }
    }
    $showEmpty = $visible === 0 && $q !== '';

    return ['visible' => $visible, 'showEmpty' => $showEmpty];
}

$modEls = [];
if ($grid) {
    foreach ($grid->childNodes as $child) {
        if ($child instanceof DOMElement && strpos($child->getAttribute('class'), 'home-mod') !== false) {
            $modEls[] = $child;
        }
    }
}

$emptyQuery = simulate_filter($modEls, '');
rcheck($emptyQuery['visible'] === 13 && !$emptyQuery['showEmpty'], 'Filtre — requête vide : 13 visibles, pas de message vide');

$patientsQuery = simulate_filter($modEls, 'patients');
rcheck($patientsQuery['visible'] >= 1 && !$patientsQuery['showEmpty'], 'Filtre — "patients" : au moins 1 visible, pas de message vide');

$nomatchQuery = simulate_filter($modEls, 'zzzzzzzzz');
rcheck($nomatchQuery['visible'] === 0 && $nomatchQuery['showEmpty'], 'Filtre — terme inconnu : 0 visible, message vide attendu');

$partialQuery = simulate_filter($modEls, 'soins');
rcheck($partialQuery['visible'] >= 1 && !$partialQuery['showEmpty'], 'Filtre — "soins" (groupe) : modules visibles');

echo "\n--- Analyse CSS ---\n";

$css = file_get_contents($base . '/assets/css/app-home.css') ?: '';

rcheck(strpos($css, 'auto-fill') === false, 'CSS — aucun auto-fill dans app-home.css');
rcheck(
    preg_match('/\.home-no-results[^{]*\{[^}]*grid-column/s', $css) !== 1,
    'CSS — pas de grid-column sur .home-no-results'
);

$pos991 = strpos($css, '@media (max-width: 991.98px)');
$pos575 = strpos($css, '@media (max-width: 575.98px)');
$posFlex = $pos991 !== false ? strpos($css, 'flex-direction: column', $pos991) : false;
rcheck($pos991 !== false && $posFlex !== false && $posFlex < ($pos575 ?: PHP_INT_MAX), 'CSS — flex colonne mobile défini dans le breakpoint ≤991px');

$css575 = $pos575 !== false ? substr($css, $pos575) : '';
rcheck(
    $css575 === '' || !preg_match('/\.home-modules-grid[\s\S]*?display:\s*grid/', $css575),
    'CSS — breakpoint ≤575px ne réactive pas display:grid sur .home-modules-grid'
);

rcheck(
    preg_match('/\.home-mod\.hidden-by-search[\s\S]*?display:\s*none\s*!important/', $css) === 1,
    'CSS — .hidden-by-search utilise display:none !important'
);

rcheck(
    preg_match('/\.home-no-results\[hidden\][\s\S]*?display:\s*none\s*!important/', $css) === 1,
    'CSS — .home-no-results[hidden] force display:none !important'
);

echo "\n--- Analyse JavaScript (index + dashboard) ---\n";

foreach (['index.php', 'dashboard.php'] as $page) {
    $src = file_get_contents($base . '/' . $page) ?: '';
    rcheck(strpos($src, "addEventListener('pageshow', filterModules)") !== false, "$page — écoute pageshow (cache navigateur)");
    rcheck(strpos($src, 'empty.hidden = !showEmpty') !== false, "$page — sync attribut hidden sur #homeNoResults");
    rcheck(strpos($src, "visible === 0 && q.length > 0") !== false, "$page — message vide seulement si recherche non vide");
    rcheck(strpos($src, "querySelectorAll('.home-mod')") !== false, "$page — cible uniquement .home-mod (pas #homeNoResults)");
}

echo "\n--- Cohérence IDs ---\n";

rcheck(substr_count($html, 'id="homeModulesGrid"') === 1, 'Rendu — un seul #homeModulesGrid');
rcheck(substr_count($html, 'id="homeNoResults"') === 1, 'Rendu — un seul #homeNoResults');

$emptySearchCount = substr_count($html, 'data-search=""');
rcheck($emptySearchCount === 0, "Rendu — aucun data-search vide ($emptySearchCount trouvé(s))");

echo "\n=== Résultat rigoureux : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
