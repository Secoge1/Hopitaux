<?php
/**
 * Layout PharmaPro ERP — shell premium indépendant.
 */

require_once __DIR__ . '/../app_urls.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../flash_messages.php';

if (!function_exists('pharma_erp_nav_items')) {
    /** @return list<array<string, mixed>> */
    function pharma_erp_nav_items(): array
    {
        $items = [
            ['key' => 'dashboard', 'href' => 'pharma_erp/', 'icon' => 'fa-chart-line', 'label' => 'Dashboard'],
            ['sep' => true],
            ['key' => 'pos', 'href' => 'pharma_erp/pos/', 'icon' => 'fa-cash-register', 'label' => 'Point de vente'],
            ['key' => 'sales', 'href' => 'pharma_erp/sales/', 'icon' => 'fa-receipt', 'label' => 'Ventes'],
            ['key' => 'clients', 'href' => 'pharma_erp/clients/', 'icon' => 'fa-users', 'label' => 'Clients'],
            ['key' => 'products', 'href' => 'pharma_erp/products/', 'icon' => 'fa-capsules', 'label' => 'Produits'],
            ['key' => 'stock', 'href' => 'pharma_erp/stock/', 'icon' => 'fa-boxes-stacked', 'label' => 'Stock'],
            ['sep' => true],
            ['key' => 'suppliers', 'href' => 'pharma_erp/suppliers/', 'icon' => 'fa-truck', 'label' => 'Fournisseurs'],
            ['key' => 'purchases', 'href' => 'pharma_erp/purchases/', 'icon' => 'fa-file-invoice', 'label' => 'Achats'],
            ['key' => 'promotions', 'href' => 'pharma_erp/promotions/', 'icon' => 'fa-tags', 'label' => 'Promotions'],
            ['key' => 'medical', 'href' => 'pharma_erp/medical/', 'icon' => 'fa-file-medical', 'label' => 'Ordonnances'],
            ['key' => 'accounting', 'href' => 'pharma_erp/accounting/', 'icon' => 'fa-book', 'label' => 'Comptabilité'],
            ['key' => 'hr', 'href' => 'pharma_erp/hr/', 'icon' => 'fa-users', 'label' => 'RH'],
            ['key' => 'settings', 'href' => 'pharma_erp/settings/', 'icon' => 'fa-sliders', 'label' => 'Paramètres'],
        ];

        if (function_exists('saas_is_platform_admin') && saas_is_platform_admin()) {
            $items[] = ['sep' => true];
            $items[] = ['key' => 'platform', 'href' => 'admin_platform/index.php', 'icon' => 'fa-cloud', 'label' => 'Admin plateforme'];
        }

        if (!class_exists('Auth')) {
            require_once __DIR__ . '/../../config/Auth.php';
        }
        $auth = Auth::getInstance();
        if ($auth->aUnRole(['pharma_cashier']) && !$auth->aUnRole(['admin', 'pharmacien', 'pharma_manager', 'comptable'])) {
            $allowed = ['pos', 'sales', 'platform'];
            return array_values(array_filter($items, function ($item) use ($allowed) {
                if (!empty($item['sep'])) {
                    return true;
                }
                return in_array($item['key'] ?? '', $allowed, true);
            }));
        }

        return $items;
    }
}

if (!function_exists('pharma_erp_context')) {
    function pharma_erp_context(): array
    {
        pharma_erp_require_role();
        $auth = Auth::getInstance();
        $utilisateur = $auth->getUtilisateur();
        if (empty($utilisateur['nom_complet'])) {
            $utilisateur['nom_complet'] = $utilisateur['nom_utilisateur'] ?? 'Utilisateur';
        }
        foreach (compact('auth', 'utilisateur') as $key => $value) {
            $GLOBALS[$key] = $value;
        }
        return compact('auth', 'utilisateur');
    }
}

if (!function_exists('pharma_erp_head')) {
    function pharma_erp_head(string $title, array $extraCss = [], string $bodyClass = ''): void
    {
        $fullTitle = $title . ' · PharmaPro ERP';
        $cssFiles = array_merge(['assets/css/pharma-erp/pharma-pro.css'], $extraCss);
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($fullTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php foreach ($cssFiles as $css):
        $cssRel = ltrim(str_replace('\\', '/', $css), '/');
        $cssDisk = dirname(__DIR__, 2) . '/' . $cssRel;
        $cssVer = is_file($cssDisk) ? '?v=' . filemtime($cssDisk) : '';
    ?>
    <link href="<?= htmlspecialchars(app_url($cssRel) . $cssVer) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<body class="<?= htmlspecialchars(trim('pharma-pro-page ' . $bodyClass)) ?>"
      data-base-path="<?= htmlspecialchars(rtrim(app_url(''), '/')) ?>">
        <?php
    }
}

if (!function_exists('pharma_erp_page_start')) {
    function pharma_erp_page_start(array $opts): void
    {
        global $auth, $utilisateur;

        $extraCss = array_merge([
            'assets/css/pharma-erp/pharma-pro.css',
        ], $opts['extra_css'] ?? []);

        $bodyClass = trim('pharma-pro-page ' . ($opts['body_class'] ?? ''));
        pharma_erp_head($opts['title'], $extraCss, $bodyClass);
        ?>
<link rel="manifest" href="<?= htmlspecialchars(app_url('pharma_erp/manifest.webmanifest')) ?>">
<meta name="theme-color" content="#059669">
<meta name="mobile-web-app-capable" content="yes">
        <?php
        $active = $opts['active'] ?? 'dashboard';
        $navItems = pharma_erp_nav_items();
        $theme = $_COOKIE['pharma_pro_theme'] ?? 'light';
        ?>
<div class="pharma-pro-shell" data-theme="<?= htmlspecialchars($theme) ?>">
    <div class="pharma-pro-bg" aria-hidden="true"></div>
    <aside class="pharma-pro-sidebar" id="pharmaProSidebar">
        <div class="pharma-pro-brand">
            <div class="pharma-pro-brand-icon"><i class="fas fa-prescription-bottle-medical"></i></div>
            <div>
                <div class="pharma-pro-brand-name">PharmaPro</div>
                <div class="pharma-pro-brand-tag">ERP Pharmacie Premium</div>
            </div>
        </div>
        <nav class="pharma-pro-nav">
            <?php foreach ($navItems as $item): ?>
                <?php if (!empty($item['sep'])): ?>
                    <div class="pharma-pro-nav-sep"></div>
                    <?php continue; endif; ?>
                <a href="<?= htmlspecialchars(app_url($item['href'])) ?>"
                   class="pharma-pro-nav-link<?= ($active === ($item['key'] ?? '')) ? ' active' : '' ?>">
                    <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="pharma-pro-sidebar-footer">
            <button type="button" class="pharma-pro-theme-toggle" id="pharmaProThemeToggle" title="Thème">
                <i class="fas fa-moon"></i>
            </button>
            <a href="<?= htmlspecialchars(app_url('logout.php')) ?>" class="pharma-pro-logout" title="Déconnexion">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </aside>
    <div class="pharma-pro-main">
        <header class="pharma-pro-header">
            <button type="button" class="pharma-pro-menu-btn" id="pharmaProMenuBtn" aria-label="Menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="pharma-pro-header-text">
                <h1 class="pharma-pro-title">
                    <?php if (!empty($opts['icon'])): ?>
                        <i class="fas <?= htmlspecialchars($opts['icon']) ?> me-2"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($opts['title']) ?>
                </h1>
                <?php if (!empty($opts['subtitle'])): ?>
                    <p class="pharma-pro-subtitle"><?= htmlspecialchars($opts['subtitle']) ?></p>
                <?php endif; ?>
            </div>
            <div class="pharma-pro-header-user">
                <span class="pharma-pro-user-name"><?= htmlspecialchars($utilisateur['nom_complet'] ?? 'Utilisateur') ?></span>
                <span class="pharma-pro-user-role"><?php
                    if (!function_exists('app_role_label')) {
                        require_once __DIR__ . '/../roles.php';
                    }
                    echo htmlspecialchars(app_role_label($auth->getUserRole()));
                ?></span>
            </div>
        </header>
        <main class="pharma-pro-content">
        <?php
        $flash = displayFlashMessages();
        if ($flash !== '') {
            echo $flash;
        }
    }
}

if (!function_exists('pharma_erp_toolbar')) {
    /** @param list<array{href: string, label: string, icon?: string, class?: string}> $actions */
    function pharma_erp_toolbar(array $actions): void
    {
        if (empty($actions)) {
            return;
        }
        echo '<div class="pharma-pro-toolbar">';
        foreach ($actions as $action) {
            $cls = $action['class'] ?? 'btn-pharma-primary';
            $icon = $action['icon'] ?? 'fa-plus';
            $target = !empty($action['target']) ? ' target="' . htmlspecialchars($action['target']) . '" rel="noopener noreferrer"' : '';
            echo '<a href="' . htmlspecialchars($action['href']) . '" class="btn ' . htmlspecialchars($cls) . ' btn-sm"' . $target . '>';
            echo '<i class="fas ' . htmlspecialchars($icon) . ' me-1"></i>' . htmlspecialchars($action['label']);
            echo '</a>';
        }
        echo '</div>';
    }
}

if (!function_exists('pharma_erp_page_end')) {
    function pharma_erp_page_end(array $opts = []): void
    {
        ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
        $jsPath = dirname(__DIR__, 2) . '/assets/js/pharma-erp/pharma-pro.js';
        $jsVer = is_file($jsPath) ? '?v=' . filemtime($jsPath) : '';
        ?>
<script src="<?= htmlspecialchars(app_url('assets/js/pharma-erp/pharma-pro.js') . $jsVer) ?>"></script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= htmlspecialchars(app_url('pharma_erp/sw.js')) ?>').catch(function () {});
}
</script>
<?php if (!empty($opts['extra_js'])): ?>
<script src="<?= htmlspecialchars(app_url($opts['extra_js'])) ?>"></script>
<?php endif; ?>
</body>
</html>
        <?php
    }
}

if (!function_exists('pharma_erp_kpi_cards')) {
    /** @param list<array{value: string, label: string, icon?: string, mod?: string, trend?: string}> $items */
    function pharma_erp_kpi_cards(array $items): void
    {
        echo '<div class="pharma-pro-kpi-grid">';
        foreach ($items as $item) {
            $mod = !empty($item['mod']) ? ' pharma-pro-kpi--' . $item['mod'] : '';
            echo '<div class="pharma-pro-kpi' . $mod . '">';
            echo '<div class="pharma-pro-kpi-icon"><i class="fas ' . htmlspecialchars($item['icon'] ?? 'fa-chart-bar') . '"></i></div>';
            echo '<div class="pharma-pro-kpi-body">';
            echo '<div class="pharma-pro-kpi-value">' . htmlspecialchars($item['value']) . '</div>';
            echo '<div class="pharma-pro-kpi-label">' . htmlspecialchars($item['label']) . '</div>';
            if (!empty($item['trend'])) {
                echo '<div class="pharma-pro-kpi-trend">' . htmlspecialchars($item['trend']) . '</div>';
            }
            echo '</div></div>';
        }
        echo '</div>';
    }
}

if (!function_exists('pharma_erp_format_money')) {
    function pharma_erp_format_money(float $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }
}
