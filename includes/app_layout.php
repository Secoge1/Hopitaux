<?php
/**
 * Layout partagé — espace privé connecté (dashboard, accueil, modules)
 * Palette et structure alignées sur SeSanté (login / pages publiques).
 */

if (!function_exists('app_bind_layout_context')) {
    /** Expose le contexte layout aux fonctions qui lisent $GLOBALS (app_layout_start, etc.). */
    function app_bind_layout_context(array $ctx): array
    {
        foreach ($ctx as $key => $value) {
            $GLOBALS[$key] = $value;
        }
        return $ctx;
    }
}

if (!function_exists('app_prepare_context')) {
    function app_prepare_context(): array
    {
        $auth = Auth::getInstance();
        $auth->requireAuth();
        $utilisateur = $auth->getUtilisateur();
        $notifications = getUserNotifications($utilisateur['id']);
        $unreadCount = getUnreadNotificationCount($utilisateur['id']);
        $stats = getDashboardStats();
        $messagesNonLus = 0;

        if (!function_exists('app_module_roles')) {
            require_once __DIR__ . '/roles.php';
        }
        if ($auth->aUnRole(app_module_roles('communication'))) {
            if (!class_exists('Communication')) {
                require_once __DIR__ . '/../models/Communication.php';
            }
            $commModel = new Communication();
            $messagesNonLus = $commModel->getUnreadCount($utilisateur['id']);
        }

        return app_bind_layout_context(compact('auth', 'utilisateur', 'notifications', 'unreadCount', 'stats', 'messagesNonLus'));
    }

    function app_nav_items($auth, int $messagesNonLus = 0): array
    {
        if (!function_exists('app_module_roles')) {
            require_once __DIR__ . '/roles.php';
        }
        $items = [
            // Vue d'ensemble
            ['key' => 'home',       'href' => 'index.php',                    'icon' => 'fa-home',             'label' => 'Accueil',       'roles' => null],
            ['key' => 'dashboard',  'href' => 'dashboard.php',                'icon' => 'fa-tachometer-alt',   'label' => 'Dashboard',     'roles' => null],
            ['sep' => true],
            // Parcours patient & soins
            ['key' => 'patients',   'href' => 'patients/',                    'icon' => 'fa-user-injured',     'label' => 'Patients',      'module' => 'patients'],
            ['key' => 'rdv',        'href' => 'rendez-vous/',                 'icon' => 'fa-calendar-check',   'label' => 'Rendez-vous',   'module' => 'rdv'],
            ['key' => 'consultations', 'href' => 'consultations/',            'icon' => 'fa-stethoscope',      'label' => 'Consultations', 'module' => 'consultations'],
            ['key' => 'laboratoire','href' => 'laboratoire/',                 'icon' => 'fa-flask',            'label' => 'Laboratoire',   'module' => 'laboratoire'],
            ['key' => 'pharmacie',  'href' => 'pharmacie/',                   'icon' => 'fa-pills',            'label' => 'Pharmacie',     'module' => 'pharmacie'],
            ['sep' => true],
            // Caisse, comptabilité & assurances
            ['key' => 'paiements',  'href' => 'paiements/',                   'icon' => 'fa-credit-card',      'label' => 'Paiements',     'module' => 'paiements'],
            ['key' => 'finances',   'href' => 'finances/',                    'icon' => 'fa-calculator',       'label' => 'Finances',      'module' => 'finances'],
            ['key' => 'assurances', 'href' => 'assurances/',                  'icon' => 'fa-file-contract',    'label' => 'Assurances',    'module' => 'assurances'],
            ['sep' => true],
            // Équipe & ressources humaines
            ['key' => 'medecins',   'href' => 'medecins/',                    'icon' => 'fa-user-md',          'label' => 'Médecins',      'module' => 'medecins'],
            ['key' => 'personnel',  'href' => 'personnel/',                   'icon' => 'fa-user-tie',         'label' => 'Personnel',     'module' => 'personnel'],
            ['sep' => true],
            // Communication & technique
            ['key' => 'communication', 'href' => 'communication/',            'icon' => 'fa-comments',         'label' => 'Communication', 'module' => 'communication', 'badge' => $messagesNonLus],
            ['key' => 'maintenance','href' => 'maintenance/',                 'icon' => 'fa-tools',            'label' => 'Maintenance',   'module' => 'maintenance'],
            ['sep' => true],
            // Administration établissement
            ['key' => 'guide',      'href' => 'parametres/guide_utilisateurs.php', 'icon' => 'fa-book',        'label' => 'Guide',         'roles' => null],
            ['key' => 'utilisateurs','href' => 'parametres/utilisateurs.php','icon' => 'fa-users',            'label' => 'Utilisateurs',  'module' => 'parametres', 'admin_only' => true],
            ['key' => 'droits',     'href' => 'parametres/droits_acces.php', 'icon' => 'fa-shield-alt',       'label' => 'Droits d\'accès', 'module' => 'parametres', 'admin_only' => true],
            ['key' => 'parametres', 'href' => 'parametres/',                  'icon' => 'fa-cog',              'label' => 'Paramètres',    'module' => 'parametres', 'admin_only' => true],
        ];

        if (function_exists('saas_is_platform_admin') && saas_is_platform_admin()) {
            $items[] = ['sep' => true];
            $items[] = ['key' => 'platform', 'href' => 'admin_platform/index.php', 'icon' => 'fa-cloud', 'label' => 'Admin plateforme', 'roles' => null];
        }

        if ($auth->estClinicienScope() && !$auth->estAdmin()) {
            if (!class_exists('StaffScope')) {
                require_once __DIR__ . '/staff_scope.php';
            }
            $ctx = StaffScope::context();
            foreach ($items as &$navItem) {
                if (($navItem['key'] ?? '') !== 'medecins') {
                    continue;
                }
                $navItem['label'] = 'Mon profil';
                if (!empty($ctx['medecin_id'])) {
                    $navItem['href'] = 'medecins/voir.php?id=' . (int) $ctx['medecin_id'];
                }
            }
            unset($navItem);
        }

        return $items;
    }

    /**
     * Liens barre de navigation mobile (max. 4 : accueil + 3 modules selon le rôle).
     *
     * @return list<array{icon: string, text: string, url: string, active: string}>
     */
    function app_mobile_profil_url($auth): string
    {
        if ($auth->estClinicienScope() && !$auth->estAdmin()) {
            if (!class_exists('StaffScope')) {
                require_once __DIR__ . '/staff_scope.php';
            }
            $ctx = StaffScope::context();
            if (!empty($ctx['medecin_id'])) {
                return 'medecins/voir.php?id=' . (int) $ctx['medecin_id'];
            }
        }
        if ($auth->aAccesModule('personnel')) {
            return 'personnel/';
        }
        if ($auth->aAccesModule('medecins')) {
            return 'medecins/';
        }
        return 'index.php';
    }

    function app_mobile_nav_items($auth): array
    {
        if (!function_exists('app_module_roles')) {
            require_once __DIR__ . '/roles.php';
        }

        $defs = [
            ['icon' => 'fas fa-home',     'text' => 'Accueil',  'url' => 'index.php',      'active' => 'home',         'module' => null],
            ['icon' => 'fas fa-users',    'text' => 'Patients', 'url' => 'patients/',      'active' => 'patients',     'module' => 'patients'],
            ['icon' => 'fas fa-calendar', 'text' => 'Agenda',   'url' => 'rendez-vous/',   'active' => 'agenda',       'module' => 'rdv'],
            ['icon' => 'fas fa-folder',   'text' => 'Dossiers', 'url' => 'dossiers/',      'active' => 'dossiers',     'module' => 'dossiers'],
            ['icon' => 'fas fa-user',     'text' => 'Profil',   'url' => app_mobile_profil_url($auth), 'active' => 'profil', 'module' => null],
        ];

        $items = [];
        foreach ($defs as $def) {
            if (!empty($def['module']) && !$auth->aAccesModule($def['module'])) {
                continue;
            }
            $items[] = $def;
        }

        return $items;
    }

    function app_head(string $title, array $extraCss = [], string $bodyClass = ''): void
    {
        if (!function_exists('getNomEtablissement')) {
            require_once __DIR__ . '/header_logo.php';
        }
        if (!function_exists('platform_logo_url')) {
            require_once __DIR__ . '/platform_brand.php';
        }

        $fullTitle = $title . ' — ' . getNomEtablissement();
        $cssFiles = array_merge([
            'assets/css/modern-design.css',
            'assets/css/system_logo.css',
            'assets/css/dashboard-enhanced.css',
            'assets/css/app-shell.css',
            'assets/css/app-module.css',
            'assets/css/app-buttons.css',
        ], $extraCss);
        if (!defined('IS_MOBILE_LAYOUT') || !IS_MOBILE_LAYOUT) {
            array_unshift($cssFiles, 'assets/css/wptouch-inspired.css');
        }
        if (defined('IS_MOBILE_LAYOUT') && IS_MOBILE_LAYOUT) {
            $cssFiles[] = 'assets/css/mobile_nav.css';
        }
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= htmlspecialchars($fullTitle) ?></title>
    <?php
        require_once __DIR__ . '/pwa.php';
        pwa_render_head_tags();
    ?>
    <link rel="icon" href="<?= htmlspecialchars(efficasante_favicon_url(strpos($bodyClass, 'app-platform-page') !== false)) ?>" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php foreach ($cssFiles as $css):
        $cssNorm = str_replace('\\', '/', $css);
        $base = rtrim(efficasante_web_base_path(), '/');
        if ($base !== '' && strpos($cssNorm, $base . '/') === 0) {
            $cssNorm = substr($cssNorm, strlen($base) + 1);
        }
        $cssRel = ltrim($cssNorm, '/');
        $cssDisk = dirname(__DIR__) . '/' . $cssRel;
        $cssVer = is_file($cssDisk) ? '?v=' . filemtime($cssDisk) : '';
    ?>
    <link href="<?= htmlspecialchars(app_url($cssRel) . $cssVer) ?>" rel="stylesheet">
    <?php endforeach; ?>
    <?php
        require_once __DIR__ . '/app_mod_actions.php';
        app_render_mod_actions_script();
    ?>
</head>
<body class="app-shell<?= $bodyClass !== '' ? ' ' . htmlspecialchars($bodyClass) : '' ?><?= (defined('IS_MOBILE_LAYOUT') && IS_MOBILE_LAYOUT) ? ' body-mobile-mode' : '' ?>"
      data-base-path="<?= htmlspecialchars(rtrim(app_url(''), '/')) ?>">
<?php
        if (defined('IS_MOBILE_LAYOUT') && IS_MOBILE_LAYOUT) {
            $mobileAuth = Auth::getInstance();
            if ($mobileAuth->estConnecte() && function_exists('app_mobile_nav_items')) {
                echo '<script>window.__APP_MOBILE_NAV='
                    . json_encode(app_mobile_nav_items($mobileAuth), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
                    . ';</script>';
            }
            ?>
<script>window.__mobileToggleSidebar=function(e){if(e){e.preventDefault();e.stopPropagation();}var o=!document.body.classList.contains('app-sidebar-open');document.body.classList.toggle('app-sidebar-open',o);document.body.classList.toggle('mobile-sidebar-open',o);var m=document.getElementById('mobileHeaderMenu');if(m){m.setAttribute('aria-expanded',o?'true':'false');}};window.__mobileCloseSidebar=function(e){if(e){e.preventDefault();e.stopPropagation();}document.body.classList.remove('app-sidebar-open','mobile-sidebar-open');var m=document.getElementById('mobileHeaderMenu');if(m){m.setAttribute('aria-expanded','false');}};</script>
<?php
            if (function_exists('app_render_mobile_chrome_all')) {
                app_render_mobile_chrome_all();
            } elseif (function_exists('app_render_mobile_chrome')) {
                app_render_mobile_chrome('header');
                app_render_mobile_chrome('footer');
            }
            $pwaCookiePath = function_exists('mobile_layout_cookie_path') ? mobile_layout_cookie_path() : '/';
            ?>
<script>(function(){try{var s=(window.matchMedia&&window.matchMedia('(display-mode: standalone)').matches)||window.navigator.standalone===true;if(s){document.cookie='efficasante_pwa_standalone=1;path=<?= json_encode($pwaCookiePath) ?>;max-age=31536000;SameSite=Lax';}}catch(e){}})();</script>
        <?php
        } else {
            $pwaCookiePath = function_exists('mobile_layout_cookie_path') ? mobile_layout_cookie_path() : '/';
            ?>
<script>(function(){try{var s=(window.matchMedia&&window.matchMedia('(display-mode: standalone)').matches)||window.navigator.standalone===true;if(s){document.cookie='efficasante_pwa_standalone=1;path=<?= json_encode($pwaCookiePath) ?>;max-age=31536000;SameSite=Lax';}}catch(e){}})();</script>
        <?php
        }
    }

    function app_render_sidebar(string $active, $auth, array $utilisateur, int $messagesNonLus = 0): void
    {
        if (!function_exists('getSystemLogoHeader')) {
            require_once __DIR__ . '/header_logo.php';
        }
        if (!function_exists('platform_name')) {
            require_once __DIR__ . '/platform_brand.php';
        }

        $navItems = app_nav_items($auth, $messagesNonLus);
        ?>
<div class="app-sidebar-overlay" id="appSidebarOverlay" aria-hidden="true"></div>
<aside class="app-sidebar" id="appSidebar" aria-label="Navigation principale">
    <div class="app-sidebar-brand">
        <?= getSystemLogoHeader('sidebar') ?>
    </div>
    <nav class="app-sidebar-nav">
        <ul class="nav flex-column">
            <?php foreach ($navItems as $item): ?>
                <?php if (!empty($item['sep'])): ?>
                <li class="app-nav-sep" aria-hidden="true"></li>
                <?php continue; endif; ?>
                <?php
                if (!app_nav_item_visible($auth, $item)) {
                    continue;
                }
                $isActive = ($active === $item['key']);
                $badge = (int) ($item['badge'] ?? 0);
                ?>
            <li class="nav-item">
                <a class="app-nav-link nav-link<?= $isActive ? ' active' : '' ?>"
                   href="<?= htmlspecialchars(app_url($item['href'])) ?>">
                    <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                    <?php if ($badge > 0): ?>
                    <span class="app-nav-badge"><?= $badge ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="app-sidebar-foot">
        <div class="app-user-card">
            <div class="app-user-avatar"><i class="fas fa-user-circle"></i></div>
            <div class="app-user-info">
                <strong><?= htmlspecialchars($utilisateur['nom_utilisateur']) ?></strong>
                <span><?= ucfirst(htmlspecialchars($utilisateur['role'])) ?></span>
            </div>
            <a href="<?= htmlspecialchars(app_url('logout.php')) ?>" class="app-logout-btn" title="Déconnexion">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        <div class="app-platform-badge">
            <small><?= platform_powered_by_html() ?></small>
        </div>
    </div>
</aside>
        <?php
    }

    function app_render_page_header(array $opts): void
    {
        $icon = $opts['icon'] ?? 'fa-layer-group';
        $title = $opts['title'] ?? '';
        $subtitle = $opts['subtitle'] ?? '';
        $unreadCount = (int) ($opts['unread_count'] ?? 0);
        $stats = $opts['stats'] ?? [];
        $showRefresh = !empty($opts['show_refresh']);
        ?>
<div class="app-page-header page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="fas <?= htmlspecialchars($icon) ?> me-2"></i><?= htmlspecialchars($title) ?></h3>
            <?php if ($subtitle !== ''): ?>
            <p class="mb-0 opacity-75"><?= htmlspecialchars($subtitle) ?></p>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($showRefresh): ?>
            <button class="btn btn-light btn-sm" type="button" onclick="refreshCounters()" title="Actualiser les compteurs">
                <i class="fas fa-sync-alt"></i>
            </button>
            <?php endif; ?>
            <button class="btn btn-light btn-sm position-relative" type="button"
                    data-bs-toggle="collapse" data-bs-target="#notificationsPanel" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <?php if ($unreadCount > 0): ?>
                <span id="appNotifBadge" class="app-notif-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $unreadCount ?></span>
                <?php endif; ?>
            </button>
            <div class="text-white text-end d-none d-md-block">
                <div><i class="fas fa-clock me-2"></i><?= date('d/m/Y H:i') ?></div>
                <?php if (!empty($stats['last_updated'])): ?>
                <small class="opacity-75"><i class="fas fa-info-circle me-1"></i>Mis à jour : <span data-last-updated><?= htmlspecialchars($stats['last_updated']) ?></span></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
        <?php
    }

    function app_render_notifications_panel(array $notifications, int $unreadCount): void
    {
        ?>
<div class="collapse mb-4" id="notificationsPanel">
    <div class="card app-notifications-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h6>
            <?php if ($unreadCount > 0): ?>
            <button class="btn btn-sm btn-outline-primary" type="button" onclick="markAllNotificationsAsRead()">
                <i class="fas fa-check-double me-1"></i>Tout marquer comme lu
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body" id="notificationsPanelBody">
            <?php if (empty($notifications)): ?>
            <p class="text-muted mb-0">Aucune notification</p>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach (array_slice($notifications, 0, 5) as $notification): ?>
                <?php
                $isUnread = empty($notification['lu']) || $notification['lu'] === '0' || $notification['lu'] === 0;
                ?>
                <div class="list-group-item d-flex justify-content-between align-items-start<?= $isUnread ? ' list-group-item-primary' : '' ?>">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold"><?= htmlspecialchars($notification['titre']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($notification['message']) ?></small><br>
                        <small class="text-muted"><i class="fas fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($notification['date_creation'])) ?></small>
                    </div>
                    <?php if ($isUnread): ?>
                    <button class="btn btn-sm btn-outline-success" type="button" onclick="markNotificationAsRead(<?= (int) $notification['id'] ?>)">
                        <i class="fas fa-check"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($notifications) > 5): ?>
            <div class="text-center mt-2"><small class="text-muted">Et <?= count($notifications) - 5 ?> autres notifications…</small></div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
        <?php
    }

    function app_layout_start(array $opts): void
    {
        global $auth, $utilisateur, $notifications, $unreadCount, $stats, $messagesNonLus;

        $active = $opts['active'] ?? '';
        app_render_sidebar($active, $auth, $utilisateur, (int) ($messagesNonLus ?? 0));
        ?>
<div class="app-main">
    <header class="app-topbar">
        <button type="button" class="app-menu-toggle btn btn-link" id="appMenuToggle" aria-label="Ouvrir le menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="app-topbar-title">
            <strong><?= htmlspecialchars(getNomEtablissement()) ?></strong>
            <small>Espace de gestion</small>
        </div>
        <?php if ($auth->estAdmin()): ?>
        <a href="<?= htmlspecialchars(app_url('parametres/')) ?>" class="app-topbar-action d-none d-md-inline-flex" title="Paramètres">
            <i class="fas fa-cog"></i>
        </a>
        <?php endif; ?>
    </header>
    <div class="app-content main-content">
        <?php
        if (empty($opts['skip_page_header'])) {
            app_render_page_header(array_merge($opts, [
                'unread_count' => $unreadCount ?? 0,
                'stats' => $stats ?? [],
            ]));
        }
        if (empty($opts['skip_notifications'])) {
            app_render_notifications_panel($notifications ?? [], (int) ($unreadCount ?? 0));
        }
    }

    function app_layout_end(array $opts = []): void
    {
        global $unreadCount;
        $statsMode = $opts['stats_mode'] ?? 'live';
        $minimal = !empty($opts['minimal_scripts']);
        $initialUnreadCount = (int) ($unreadCount ?? 0);
        $initialMaxNotifId = 0;
        $navAuth = Auth::getInstance();
        if (!$minimal && $navAuth->estConnecte()) {
            require_once __DIR__ . '/NotificationSystem.php';
            $user = $navAuth->getUtilisateur();
            $initialMaxNotifId = (int) NotificationSystem::getInstance()->getMaxNotificationId((int) ($user['id'] ?? 0));
        }
        ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php
        $loadingJsPath = dirname(__DIR__) . '/assets/js/app-loading-feedback.js';
        $loadingJsVer = is_file($loadingJsPath) ? '?v=' . filemtime($loadingJsPath) : '';
?>
<script src="<?= htmlspecialchars(app_url('assets/js/app-loading-feedback.js') . $loadingJsVer) ?>"></script>
<?php if (function_exists('app_currency_js_config')): ?>
<script>window.APP_CURRENCY=<?= json_encode(app_currency_js_config(), JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= htmlspecialchars(app_url('assets/js/app-currency.js')) ?>"></script>
<?php endif; ?>
<?php
        $navAuth = Auth::getInstance();
        if ($navAuth->estConnecte() && function_exists('app_mobile_nav_items')):
            ?>
<script>window.__APP_MOBILE_NAV=<?= json_encode(app_mobile_nav_items($navAuth), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php endif; ?>
<script src="<?= htmlspecialchars(app_url('assets/js/logo-handler.js')) ?>"></script>
<script src="<?= htmlspecialchars(app_url('assets/js/wptouch-inspired.js')) ?>"></script>
<?php
        if (defined('IS_MOBILE_LAYOUT') && IS_MOBILE_LAYOUT) {
            $mobileJsPath = dirname(__DIR__) . '/assets/js/mobile-pwa-chrome.js';
            $mobileJsVer = is_file($mobileJsPath) ? '?v=' . filemtime($mobileJsPath) : '';
            ?>
<script src="<?= htmlspecialchars(app_url('assets/js/mobile-pwa-chrome.js') . $mobileJsVer) ?>"></script>
<?php
        }
?>
<?php if (!empty($GLOBALS['app_page_scripts'])): ?>
<?= $GLOBALS['app_page_scripts'] ?>
<?php endif; ?>
<?php
        if (!$minimal && $navAuth->estConnecte()):
            require_once __DIR__ . '/notification_sound_settings.php';
            $soundsJsPath = dirname(__DIR__) . '/assets/js/app-notification-sounds.js';
            $soundsJsVer = is_file($soundsJsPath) ? '?v=' . filemtime($soundsJsPath) : '';
?>
<script>window.APP_NOTIFICATION_SOUNDS=<?= json_encode(notification_sound_js_config(), JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= htmlspecialchars(app_url('assets/js/app-notification-sounds.js') . $soundsJsVer) ?>"></script>
<?php endif; ?>
<script>
(function () {
    var toggle = document.getElementById('appMenuToggle');
    var overlay = document.getElementById('appSidebarOverlay');
    function closeSidebar() {
        document.body.classList.remove('app-sidebar-open', 'mobile-sidebar-open');
    }
    if (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            document.body.classList.toggle('app-sidebar-open');
        });
    }
    var mobileHeaderMenu = document.getElementById('mobileHeaderMenu');
    if (mobileHeaderMenu && !mobileHeaderMenu.dataset.layoutBound) {
        mobileHeaderMenu.dataset.layoutBound = '1';
        mobileHeaderMenu.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof window.__mobileToggleSidebar === 'function') {
                window.__mobileToggleSidebar(e);
            } else {
                document.body.classList.toggle('app-sidebar-open');
                document.body.classList.toggle('mobile-sidebar-open');
            }
        });
    }
    if (overlay && !overlay.dataset.layoutBound) {
        overlay.dataset.layoutBound = '1';
        overlay.addEventListener('click', function (e) {
            if (typeof window.__mobileCloseSidebar === 'function') {
                window.__mobileCloseSidebar(e);
            } else {
                document.body.classList.remove('app-sidebar-open', 'mobile-sidebar-open');
            }
        });
    }
})();

/* Modals dans .app-content : les déplacer sur body pour un positionnement correct */
document.addEventListener('show.bs.modal', function (ev) {
    var modalEl = ev.target;
    if (modalEl && modalEl.classList.contains('modal') && modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
}, true);

/* Soumission manuelle des formulaires dans les menus Actions (évite le blocage Bootstrap) */
window.appModSubmitActionForm = function (btn) {
    if (typeof window.AppModActions !== 'undefined' && typeof window.AppModActions.closeAll === 'function') {
        window.AppModActions.closeAll();
    }
    var form = btn && btn.closest ? btn.closest('.mod-actions-form') : null;
    if (!form) return;
    var msg = btn.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) return;
    form.submit();
};

/* Menus Actions — scan après tous les scripts (gestion manuelle, sans Bootstrap Dropdown) */
(function () {
    function bootModActions() {
        if (typeof window.AppModActions === 'undefined') {
            return;
        }
        if (typeof window.AppModActions.scan === 'function') {
            window.AppModActions.scan(document);
        }
        if (typeof window.AppModActions.bindDeleteModals === 'function') {
            window.AppModActions.bindDeleteModals();
        }
    }
    bootModActions();
    document.addEventListener('DOMContentLoaded', bootModActions);
    window.addEventListener('load', bootModActions);
})();

function markNotificationAsRead(notificationId) {
    fetch('<?= htmlspecialchars(app_url('includes/notification_actions.php')) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_read&notification_id=' + notificationId
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.success) location.reload();
        else alert('Erreur lors de la mise à jour de la notification');
    }).catch(function () { alert('Erreur de communication avec le serveur'); });
}

function markAllNotificationsAsRead() {
    fetch('<?= htmlspecialchars(app_url('includes/notification_actions.php')) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_read'
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (data.success) location.reload();
        else alert('Erreur lors de la mise à jour des notifications');
    }).catch(function () { alert('Erreur de communication avec le serveur'); });
}

<?php if (!$minimal && $navAuth->estConnecte()): ?>
(function () {
    var appLastUnreadCount = <?= $initialUnreadCount ?>;
    var appLastNotificationId = <?= (int) $initialMaxNotifId ?>;
    var pollMs = (window.APP_NOTIFICATION_SOUNDS && window.APP_NOTIFICATION_SOUNDS.pollInterval) || 15000;

    function updateNotifBadge(count) {
        document.querySelectorAll('[data-bs-target="#notificationsPanel"]').forEach(function (btn) {
            var badge = btn.querySelector('.app-notif-badge, .mobile-header-badge, #appNotifBadge');
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = btn.id === 'mobileHeaderBell'
                        ? 'mobile-header-badge app-notif-badge'
                        : 'app-notif-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                    btn.appendChild(badge);
                }
                badge.textContent = count;
            } else if (badge) {
                badge.remove();
            }
        });
    }

    window.appRefreshNotificationsPanel = function () {
        var body = document.getElementById('notificationsPanelBody');
        if (!body) {
            return Promise.resolve();
        }
        return fetch('<?= htmlspecialchars(app_url('includes/notification_actions.php')) ?>?action=list')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    return;
                }
                updateNotifBadge(typeof data.count === 'number' ? data.count : 0);
                if (typeof window.appRenderNotificationsBody === 'function') {
                    window.appRenderNotificationsBody(data.notifications || [], data.count || 0);
                }
            })
            .catch(function () {});
    };

    window.appRenderNotificationsBody = function (notifications, unreadCount) {
        var body = document.getElementById('notificationsPanelBody');
        if (!body) {
            return;
        }
        function esc(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }
        function isUnread(n) {
            return !n.lu || n.lu === '0' || n.lu === 0 || n.lu === false;
        }
        if (!notifications.length) {
            body.innerHTML = '<p class="text-muted mb-0">Aucune notification</p>';
            return;
        }
        var html = '<div class="list-group list-group-flush">';
        notifications.slice(0, 5).forEach(function (n) {
            html += '<div class="list-group-item d-flex justify-content-between align-items-start'
                + (isUnread(n) ? ' list-group-item-primary' : '') + '">'
                + '<div class="ms-2 me-auto">'
                + '<div class="fw-bold">' + esc(n.titre) + '</div>'
                + '<small class="text-muted">' + esc(n.message) + '</small><br>'
                + '<small class="text-muted"><i class="fas fa-clock me-1"></i>' + esc(n.date_creation) + '</small>'
                + '</div>';
            if (isUnread(n)) {
                html += '<button class="btn btn-sm btn-outline-success" type="button" onclick="markNotificationAsRead('
                    + parseInt(n.id, 10) + ')"><i class="fas fa-check"></i></button>';
            }
            html += '</div>';
        });
        html += '</div>';
        if (notifications.length > 5) {
            html += '<div class="text-center mt-2"><small class="text-muted">Et '
                + (notifications.length - 5) + ' autres notifications…</small></div>';
        }
        body.innerHTML = html;
    };

    document.addEventListener('DOMContentLoaded', function () {
        var panel = document.getElementById('notificationsPanel');
        if (!panel) {
            return;
        }
        panel.addEventListener('show.bs.collapse', function () {
            window.appRefreshNotificationsPanel();
        });
    });

    setInterval(function () {
        fetch('<?= htmlspecialchars(app_url('includes/notification_actions.php')) ?>?action=check_new&last_count='
            + appLastUnreadCount + '&last_id=' + appLastNotificationId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var count = typeof data.count === 'number' ? data.count : appLastUnreadCount;
                if (typeof data.lastId === 'number' && data.lastId > appLastNotificationId) {
                    appLastNotificationId = data.lastId;
                }
                if (data.hasNewNotifications || count !== appLastUnreadCount) {
                    appLastUnreadCount = count;
                    updateNotifBadge(count);
                    if (window.AppNotificationSounds && data.items && data.items.length) {
                        window.AppNotificationSounds.playForItems(data.items);
                    }
                }
            })
            .catch(function () {});
    }, pollMs);
})();
<?php endif; ?>

<?php if (!$minimal): ?>
<?php if ($statsMode === 'live'): ?>
function updateStatsFromData(stats) {
    if (!stats) return;
    var fields = {
        'stat-patients': stats.patients,
        'stat-consultations': stats.consultations_aujourd_hui,
        'stat-consultations-aujourd-hui': stats.consultations_aujourd_hui,
        'stat-rdv': stats.rdv_aujourd_hui,
        'stat-rdv-aujourd-hui': stats.rdv_aujourd_hui,
        'stat-analyses': stats.analyses_en_cours,
        'stat-analyses-en-cours': stats.analyses_en_cours,
        'stat-medecins': stats.medecins_actifs,
        'stat-medecins-actifs': stats.medecins_actifs,
        'stat-paiements': stats.paiements_total,
        'stat-paiements-total': stats.paiements_total
    };
    for (var id in fields) {
        var el = document.getElementById(id);
        if (el && fields[id] !== undefined) el.textContent = fields[id];
    }
    document.querySelectorAll('[data-stat-key]').forEach(function (el) {
        var key = el.getAttribute('data-stat-key');
        if (key && stats[key] !== undefined) el.textContent = stats[key];
    });
    var attente = stats.paiements_en_attente || 0;
    var badgeEl = document.getElementById('stat-paiements-attente');
    var valEl = document.getElementById('stat-paiements-attente-val');
    if (badgeEl && valEl) {
        valEl.textContent = attente;
        badgeEl.style.display = attente > 0 ? '' : 'none';
    }
    var tsEl = document.querySelector('[data-last-updated]');
    if (tsEl && stats.last_updated) tsEl.textContent = stats.last_updated;
}

setInterval(function () {
    fetch('<?= htmlspecialchars(app_url('includes/cache_actions.php')) ?>?action=get_stats')
        .then(function (r) { return r.json(); })
        .then(function (data) { if (data.success) updateStatsFromData(data.stats); });
}, 120000);

function refreshCounters() {
    var btn = document.querySelector('[onclick="refreshCounters()"]');
    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('<?= htmlspecialchars(app_url('includes/cache_actions.php')) ?>?action=refresh_cache')
        .then(function (r) { return r.json(); })
        .then(function () { return fetch('<?= htmlspecialchars(app_url('includes/cache_actions.php')) ?>?action=get_stats'); })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) updateStatsFromData(data.stats);
            if (btn) btn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        })
        .catch(function () { if (btn) btn.innerHTML = '<i class="fas fa-sync-alt"></i>'; });
}
<?php else: ?>
function refreshCounters() {
    var btn = document.querySelector('[onclick="refreshCounters()"]');
    if (btn) btn.querySelector('i').classList.add('fa-spin');
    fetch('<?= htmlspecialchars(app_url('includes/cache_actions.php')) ?>?action=refresh_cache')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) location.reload();
            else alert('Erreur lors de l\'actualisation');
        })
        .catch(function () { alert('Erreur de communication avec le serveur'); });
}
<?php endif; ?>
<?php endif; ?>
</script>
<?php
        pwa_render_sw_script();
        if (defined('IS_MOBILE_LAYOUT') && IS_MOBILE_LAYOUT && function_exists('app_render_mobile_chrome')) {
            app_render_mobile_chrome('footer');
        }
        ?>
</body>
</html>
        <?php
    }
}
