<?php
/**
 * Vérification SEO du site public (CLI).
 */

$checks = [];
$basePath = dirname(__DIR__);

// Vérifier robots.txt
$robotsPath = $basePath . '/robots.txt';
$checks[] = [
    'name' => 'robots.txt présent',
    'ok' => file_exists($robotsPath),
    'detail' => file_exists($robotsPath) ? 'OK' : 'Manquant',
];

if (file_exists($robotsPath)) {
    $robotsContent = file_get_contents($robotsPath);
    $checks[] = [
        'name' => 'robots.txt autorise pages publiques',
        'ok' => strpos($robotsContent, 'Allow: /home.php') !== false
            && strpos($robotsContent, 'Allow: /tarifs.php') !== false,
        'detail' => 'OK',
    ];
    
    $checks[] = [
        'name' => 'robots.txt bloque pages privées',
        'ok' => strpos($robotsContent, 'Disallow: /patients/') !== false
            && strpos($robotsContent, 'Disallow: /config/') !== false,
        'detail' => 'OK',
    ];
}

// Vérifier sitemap.xml
$sitemapPath = $basePath . '/sitemap.xml';
$checks[] = [
    'name' => 'sitemap.xml présent',
    'ok' => file_exists($sitemapPath),
    'detail' => file_exists($sitemapPath) ? 'OK' : 'Manquant',
];

// Vérifier métadonnées dans public_layout.php
$layoutPath = $basePath . '/includes/public_layout.php';
if (file_exists($layoutPath)) {
    $layoutContent = file_get_contents($layoutPath);
    
    $checks[] = [
        'name' => 'Meta description ajoutée',
        'ok' => strpos($layoutContent, 'name="description"') !== false,
        'detail' => 'OK',
    ];
    
    $checks[] = [
        'name' => 'Meta keywords ajoutées',
        'ok' => strpos($layoutContent, 'name="keywords"') !== false,
        'detail' => 'OK',
    ];
    
    $checks[] = [
        'name' => 'Open Graph tags',
        'ok' => strpos($layoutContent, 'property="og:title"') !== false
            && strpos($layoutContent, 'property="og:description"') !== false,
        'detail' => 'OK',
    ];
    
    $checks[] = [
        'name' => 'Twitter Cards',
        'ok' => strpos($layoutContent, 'name="twitter:card"') !== false,
        'detail' => 'OK',
    ];
    
    $checks[] = [
        'name' => 'URL canonique',
        'ok' => strpos($layoutContent, 'rel="canonical"') !== false,
        'detail' => 'OK',
    ];
}

// Vérifier pages publiques avec SEO
$publicPages = [
    'home.php' => 'Page d\'accueil',
    'tarifs.php' => 'Tarifs',
    'documentation.php' => 'Documentation',
];

foreach ($publicPages as $file => $label) {
    $filePath = $basePath . '/' . $file;
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $checks[] = [
            'name' => "$label - Métadonnées SEO personnalisées",
            'ok' => strpos($content, "'description' =>") !== false
                && strpos($content, "'keywords' =>") !== false,
            'detail' => 'OK',
        ];
    }
}

// Vérifier .htaccess compression
$htaccessPath = $basePath . '/.htaccess';
if (file_exists($htaccessPath)) {
    $htaccessContent = file_get_contents($htaccessPath);
    $checks[] = [
        'name' => 'Compression GZIP activée',
        'ok' => strpos($htaccessContent, 'mod_deflate') !== false,
        'detail' => 'OK',
    ];
    
    $checks[] = [
        'name' => 'Cache headers configurés',
        'ok' => strpos($htaccessContent, 'Cache-Control') !== false,
        'detail' => 'OK',
    ];
}

// Vérifier structure HTML sémantique
$checks[] = [
    'name' => 'HTML5 sémantique (lang="fr")',
    'ok' => isset($layoutContent) && strpos($layoutContent, 'lang="fr"') !== false,
    'detail' => 'OK',
];

$checks[] = [
    'name' => 'Viewport mobile responsive',
    'ok' => isset($layoutContent) && strpos($layoutContent, 'viewport') !== false,
    'detail' => 'OK',
];

// Affichage résultats
$failed = 0;
echo "=== Vérification SEO ===\n";
foreach ($checks as $c) {
    $status = $c['ok'] ? 'PASS' : 'FAIL';
    if (!$c['ok']) {
        $failed++;
    }
    echo "[$status] {$c['name']}";
    if (!empty($c['detail'])) {
        echo " — {$c['detail']}";
    }
    echo "\n";
}

echo "\n";
if ($failed === 0) {
    echo "✅ Tous les tests SEO passent.\n";
    echo "\n📈 Améliorations recommandées :\n";
    echo "• Ajouter données structurées Schema.org (LocalBusiness, MedicalOrganization)\n";
    echo "• Créer un blog/actualités pour du contenu frais\n";
    echo "• Optimiser les images (alt text, format WebP)\n";
    echo "• Mesurer Core Web Vitals (PageSpeed Insights)\n";
} else {
    echo "❌ $failed test(s) en échec.\n";
}

exit($failed > 0 ? 1 : 0);