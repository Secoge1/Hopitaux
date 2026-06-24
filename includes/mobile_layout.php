<?php
/**
 * Layout mobile global — bandeau haut + barre de navigation bas.
 * Utilisé par init.php (fallback OB) et app_layout.php (rendu direct).
 */

if (!function_exists('mobile_layout_cookie_path')) {
    function mobile_layout_cookie_path(): string
    {
        $base = function_exists('efficasante_web_base_path')
            ? efficasante_web_base_path()
            : (defined('BASE_PATH') ? BASE_PATH : '');
        $base = rtrim(str_replace('\\', '/', (string) $base), '/');
        return $base === '' ? '/' : $base;
    }
}

if (!function_exists('mobile_layout_is_hub_page')) {
    /** Page d'accueil mobile (/mobile/) — UI dédiée, sans chrome global injecté. */
    function mobile_layout_is_hub_page(): bool
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        return (bool) preg_match('#/mobile(?:/index\.php)?$#', $script);
    }
}

if (!function_exists('mobile_layout_base_path')) {
    function mobile_layout_base_path(): string
    {
        if (function_exists('efficasante_web_base_path')) {
            return rtrim(efficasante_web_base_path(), '/');
        }
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $parts = array_filter(explode('/', $script));
        if (count($parts) > 1) {
            array_pop($parts);
            return '/' . implode('/', $parts);
        }
        return '';
    }
}

if (!function_exists('mobile_layout_url')) {
    function mobile_layout_url(string $path, bool $withMobile = true): string
    {
        $base = mobile_layout_base_path();
        $path = ltrim($path, '/');
        $url = ($base === '' ? '/' : $base . '/') . $path;
        if ($withMobile) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . 'mobile=1';
        }
        return $url;
    }
}

if (!function_exists('mobile_layout_page_title')) {
    function mobile_layout_page_title(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $map = [
            'patients'      => 'Patients',
            'rendez-vous'   => 'Agenda',
            'dossiers'      => 'Dossiers',
            'consultations' => 'Consultations',
            'laboratoire'   => 'Laboratoire',
            'dashboard'     => 'Dashboard',
            'communication' => 'Messages',
            'paiements'     => 'Paiements',
            'pharmacie'     => 'Pharmacie',
            'personnel'     => 'Profil',
            'medecins'      => 'Profil',
            'parametres'    => 'Paramètres',
        ];
        foreach ($map as $segment => $label) {
            if (strpos($uri, '/' . $segment) !== false) {
                return $label;
            }
        }
        return 'Accueil';
    }
}

if (!function_exists('getMobileHeaderHtml')) {
    function getMobileHeaderHtml($basePath, array $opts = []): string
    {
        $title = $opts['title'] ?? mobile_layout_page_title();
        $unread = (int) ($opts['unread'] ?? 0);

        return '<header class="mobile-global-header">'
            . '<button type="button" class="mobile-header-btn mobile-header-menu" id="mobileHeaderMenu" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="appSidebar"'
            . ' onclick="window.__mobileToggleSidebar&&window.__mobileToggleSidebar(event)">'
            . '<i class="fas fa-bars"></i></button>'
            . '<h1 class="mobile-header-title">' . htmlspecialchars($title) . '</h1>'
            . '<button type="button" class="mobile-header-btn mobile-header-bell" id="mobileHeaderBell"'
            . ' data-bs-toggle="collapse" data-bs-target="#notificationsPanel" aria-expanded="false" aria-label="Notifications">'
            . '<i class="fas fa-bell"></i>'
            . ($unread > 0 ? '<span class="mobile-header-badge app-notif-badge">' . $unread . '</span>' : '')
            . '</button>'
            . '</header>';
    }
}

if (!function_exists('getMobileFooterHtml')) {
    function getMobileFooterHtml($basePath, $currentUri = ''): string
    {
        $uri = $currentUri ?: ($_SERVER['REQUEST_URI'] ?? '');
        $navDefs = [];

        try {
            if (class_exists('Auth')) {
                $auth = Auth::getInstance();
                if ($auth->estConnecte()) {
                    if (!function_exists('app_mobile_nav_items')) {
                        require_once __DIR__ . '/app_layout.php';
                    }
                    if (function_exists('app_mobile_nav_items')) {
                        $navDefs = app_mobile_nav_items($auth);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[mobile_layout] footer nav : ' . $e->getMessage());
        }

        if ($navDefs === []) {
            $navDefs = [
                ['icon' => 'fas fa-home', 'text' => 'Accueil', 'url' => 'index.php', 'active' => 'home'],
            ];
        }

        $active = static function (string $slug) use ($uri): string {
            if ($slug === 'home') {
                $path = parse_url($uri, PHP_URL_PATH) ?: $uri;
                $base = mobile_layout_base_path();
                $isHome = ($path === $base || $path === $base . '/' || preg_match('#/index\.php$#', $path) || preg_match('#/mobile/?$#', $path))
                    && strpos($uri, 'dashboard') === false
                    && strpos($uri, '/patients') === false
                    && strpos($uri, '/rendez-vous') === false
                    && strpos($uri, '/dossiers') === false
                    && strpos($uri, '/consultations') === false
                    && strpos($uri, '/laboratoire') === false
                    && strpos($uri, '/medecins') === false
                    && strpos($uri, '/personnel') === false;
                return $isHome ? ' active' : '';
            }
            if ($slug === 'agenda' && strpos($uri, 'rendez-vous') !== false) {
                return ' active';
            }
            if ($slug === 'profil') {
                if (preg_match('#/medecins/voir\.php#', $uri)) {
                    return ' active';
                }
                if (strpos($uri, '/personnel') !== false) {
                    return ' active';
                }
                if (strpos($uri, '/medecins') !== false && strpos($uri, 'voir.php') === false) {
                    return ' active';
                }
                return '';
            }
            if (strpos($uri, $slug) !== false) {
                return ' active';
            }
            return '';
        };

        $links = [];
        foreach ($navDefs as $def) {
            $path = $def['url'];
            if (substr($path, -1) === '/') {
                $path .= 'index.php';
            }
            $links[] = [
                'url'   => mobile_layout_url($path),
                'icon'  => str_replace('fas ', '', $def['icon']),
                'label' => $def['text'],
                'slug'  => $def['active'],
            ];
        }

        $out = '<nav class="mobile-global-nav" aria-label="Navigation mobile">';
        foreach ($links as $link) {
            $out .= '<a href="' . htmlspecialchars($link['url']) . '" class="nav-item' . $active($link['slug']) . '">'
                . '<i class="fas ' . htmlspecialchars($link['icon']) . '"></i>'
                . '<span class="nav-label">' . htmlspecialchars($link['label']) . '</span>'
                . '</a>';
        }
        $out .= '</nav>';

        return $out;
    }
}

if (!function_exists('mobile_layout_should_ob_inject')) {
    /** Injection OB uniquement si le chrome mobile est incomplet dans le HTML. */
    function mobile_layout_should_ob_inject(string $html = ''): bool
    {
        if (!defined('IS_MOBILE_LAYOUT') || !IS_MOBILE_LAYOUT) {
            return false;
        }
        if (mobile_layout_is_hub_page()) {
            return false;
        }
        if ($html === '' || strpos($html, '<html') === false) {
            return false;
        }
        $hasHeader = strpos($html, 'mobile-global-header') !== false;
        $hasFooter = strpos($html, 'mobile-global-nav') !== false;
        return !$hasHeader || !$hasFooter;
    }
}

if (!function_exists('mobile_layout_inject_html')) {
    function mobile_layout_inject_html($html)
    {
        try {
            if (!is_string($html) || $html === '') {
                return $html;
            }
            if (strpos($html, '<html') === false || !mobile_layout_should_ob_inject($html)) {
                return $html;
            }

            $basePath = defined('BASE_PATH') ? BASE_PATH : mobile_layout_base_path();
            $cssUrl = rtrim($basePath, '/') . '/assets/css/mobile_nav.css';
            $cssDisk = dirname(__DIR__) . '/assets/css/mobile_nav.css';
            $cssVer = is_file($cssDisk) ? '?v=' . filemtime($cssDisk) : '';
            $mobileCss = '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl . $cssVer) . '" id="mobile-nav-css">';

            if (strpos($html, 'id="mobile-nav-css"') === false) {
                $replaced = preg_replace('/(<head\b[^>]*>)/i', '$1' . $mobileCss, $html, 1);
                if (is_string($replaced)) {
                    $html = $replaced;
                }
            }

            if (strpos($html, 'mobile-global-header') === false
                && preg_match('/<body\b([^>]*)>/is', $html, $m, PREG_OFFSET_CAPTURE)) {
                $bodyOpen = $m[0][0];
                $attrs = $m[1][0];
                if (preg_match('/\bclass="([^"]*)"/i', $attrs, $cm)) {
                    $classes = trim($cm[1]);
                    if (strpos($classes, 'body-mobile-mode') === false) {
                        $classes = trim($classes . ' body-mobile-mode');
                    }
                    $attrs = preg_replace('/\bclass="[^"]*"/i', 'class="' . $classes . '"', $attrs, 1);
                } else {
                    $attrs .= ' class="body-mobile-mode"';
                }
                $replacement = '<body' . $attrs . '>' . getMobileHeaderHtml($basePath);
                $html = substr_replace($html, $replacement, $m[0][1], strlen($bodyOpen));
            }

            if (strpos($html, 'mobile-global-nav') === false) {
                $html = str_replace('</body>', getMobileFooterHtml($basePath, $_SERVER['REQUEST_URI'] ?? '') . '</body>', $html);
            }

            return $html;
        } catch (Throwable $e) {
            error_log('[mobile_layout] inject_html : ' . $e->getMessage());
            return is_string($html) ? $html : '';
        }
    }
}

if (!function_exists('app_render_mobile_chrome')) {
    /** Bandeau + barre bas — pages app_layout uniquement. */
    function app_render_mobile_chrome($position)
    {
        try {
            if (!defined('IS_MOBILE_LAYOUT') || !IS_MOBILE_LAYOUT || mobile_layout_is_hub_page()) {
                return;
            }
            $basePath = defined('BASE_PATH') ? BASE_PATH : mobile_layout_base_path();
            if ($position === 'header') {
                if (!empty($GLOBALS['mobile_chrome_header_done'])) {
                    return;
                }
                global $unreadCount;
                echo getMobileHeaderHtml($basePath, [
                    'unread' => (int) ($unreadCount ?? 0),
                ]);
                $GLOBALS['mobile_chrome_header_done'] = true;
                return;
            }
            if ($position === 'footer') {
                if (!empty($GLOBALS['mobile_chrome_footer_done'])) {
                    return;
                }
                echo getMobileFooterHtml($basePath, $_SERVER['REQUEST_URI'] ?? '');
                $GLOBALS['mobile_chrome_footer_done'] = true;
            }
        } catch (Throwable $e) {
            error_log('[mobile_layout] chrome : ' . $e->getMessage());
        }
    }
}

if (!function_exists('app_render_mobile_chrome_all')) {
    /** Bandeau haut + barre bas dès l'ouverture du body (évite barre manquante). */
    function app_render_mobile_chrome_all(): void
    {
        app_render_mobile_chrome('header');
        app_render_mobile_chrome('footer');
    }
}
