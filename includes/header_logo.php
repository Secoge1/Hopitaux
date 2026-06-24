<?php
/**
 * Inclusion du logo du système pour tous les headers
 * Ce fichier peut être inclus dans n'importe quelle page pour afficher le logo
 * Utilise le nom de l'établissement configuré dans Paramètres > Identité
 */

/**
 * Chemin URL de la racine de l'application (ex. '' ou '/efficasante').
 * Utilisé pour les URLs absolues du logo et éviter les chemins relatifs cassés par module.
 */
if (!function_exists('efficasante_web_base_path')) {
    /**
     * Chemin URL depuis la racine du domaine jusqu’au dossier de l’application (ex. '' ou '/efficasante').
     * Détection prioritaire via display_logo.php (fiable si le docroot ≠ le dossier du projet).
     */
    function efficasante_web_base_path() {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
        $displayLogoFs = realpath(__DIR__ . '/../display_logo.php');

        if ($docRoot && $displayLogoFs) {
            $d = str_replace('\\', '/', rtrim($docRoot, '/'));
            $f = str_replace('\\', '/', $displayLogoFs);
            if (strpos($f, $d) === 0) {
                $relFile = substr($f, strlen($d));
                $dir       = dirname($relFile);
                $dir       = str_replace('\\', '/', $dir);
                if ($dir === '/' || $dir === '.' || $dir === '') {
                    $cached = '';
                } else {
                    $cached = '/' . trim($dir, '/');
                }
                return $cached;
            }
        }

        $appRoot = realpath(__DIR__ . '/..');
        if ($docRoot && $appRoot) {
            $d = str_replace('\\', '/', rtrim($docRoot, '/'));
            $a = str_replace('\\', '/', $appRoot);
            if (strpos($a, $d) === 0) {
                $rel = substr($a, strlen($d));
                if ($rel === '' || $rel === '/') {
                    $cached = '';
                } else {
                    $cached = '/' . trim($rel, '/');
                }
                return $cached;
            }
        }

        if (defined('SITE_URL')) {
            $p = parse_url(SITE_URL, PHP_URL_PATH);
            if (is_string($p) && $p !== '' && $p !== '/') {
                $cached = rtrim($p, '/');
                return $cached;
            }
        }

        // Héuristique : hébergement où docroot ≠ projet mais l’app est dans un sous-dossier
        // (ex. /efficasante/parametres/... avec display_logo.php dans ce sous-dossier).
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '';
        if ($docRoot && $scriptName !== '' && preg_match('#^/([^/]+)/#', $scriptName, $m)) {
            $seg = $m[1];
            $candidate = $docRoot . DIRECTORY_SEPARATOR . $seg . DIRECTORY_SEPARATOR . 'display_logo.php';
            if (is_file($candidate)) {
                $cached = '/' . $seg;
                return $cached;
            }
        }

        $cached = '';
        return $cached;
    }
}

if (!function_exists('efficasante_login_url')) {
    function efficasante_login_url() {
        return efficasante_web_base_path() . '/login.php';
    }
}

if (!function_exists('efficasante_access_denied_url')) {
    function efficasante_access_denied_url() {
        return efficasante_web_base_path() . '/access_denied.php';
    }
}

if (!function_exists('getNomEtablissement')) {
    function getNomEtablissement() {
        static $cache = [];
        $tenantKey = 'global';
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (!empty($_SESSION['tenant_id'])) {
            $tenantKey = 't' . (int) $_SESSION['tenant_id'];
        }
        if (!isset($cache[$tenantKey])) {
            if (!class_exists('SystemParameters')) {
                require_once __DIR__ . '/../config/SystemParameters.php';
            }
            $cache[$tenantKey] = SystemParameters::getInstance()->get('nom_etablissement') ?: 'Clinique et Hôpital';
        }
        return $cache[$tenantKey];
    }
}

if (!function_exists('efficasante_has_tenant_context')) {
    function efficasante_has_tenant_context(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        return !empty($_SESSION['tenant_id']);
    }
}

if (!function_exists('efficasante_tenant_has_custom_logo')) {
    function efficasante_tenant_has_custom_logo(): bool
    {
        if (!class_exists('SystemParameters')) {
            require_once __DIR__ . '/../config/SystemParameters.php';
        }
        $systemParams = SystemParameters::getInstance();
        $logoPath = $systemParams->getLogoPath();
        $storedRel = trim((string) $systemParams->get('logo_clinique'));

        return $storedRel !== ''
            && $logoPath
            && file_exists($logoPath)
            && is_readable($logoPath)
            && strpos($storedRel, '..') === false;
    }
}

if (!function_exists('efficasante_logo_url')) {
    /**
     * URL du logo établissement (fichier uploadé en priorité, sinon display_logo.php).
     * Le logo plateforme (SeSanté) n'est jamais utilisé dans l'espace d'un abonné.
     */
    function efficasante_logo_url(int $retinaW = 0, int $retinaH = 0): string
    {
        if (!class_exists('SystemParameters')) {
            require_once __DIR__ . '/../config/SystemParameters.php';
        }

        $systemParams = SystemParameters::getInstance();
        $logoPath = $systemParams->getLogoPath();
        $storedRel = str_replace('\\', '/', (string) $systemParams->get('logo_clinique'));
        $base = rtrim(efficasante_web_base_path(), '/');

        if ($logoPath && file_exists($logoPath) && is_readable($logoPath) && $storedRel !== '' && strpos($storedRel, '..') === false) {
            if (strpos($storedRel, 'uploads/') !== 0) {
                $uploadsIndex = strpos($storedRel, 'uploads/');
                if ($uploadsIndex !== false) {
                    $storedRel = substr($storedRel, $uploadsIndex);
                }
            }
            if (strpos($storedRel, 'uploads/') === 0) {
                $v = (int) @filemtime($logoPath);
                if ($v < 1) {
                    $v = time();
                }
                return $base . '/' . $storedRel . '?t=' . $v;
            }
        }

        $qsParts = [];
        if ($retinaW > 0 && $retinaH > 0) {
            $qsParts[] = 'w=' . $retinaW;
            $qsParts[] = 'h=' . $retinaH;
        }
        if (efficasante_has_tenant_context()) {
            $qsParts[] = 'tenant=1';
        }
        $qs = $qsParts !== [] ? ('?' . implode('&', $qsParts)) : '';
        return $base . '/display_logo.php' . $qs;
    }
}

if (!function_exists('efficasante_favicon_url')) {
    /** Favicon : logo établissement pour les espaces abonnés, logo plateforme pour l'admin vendeur. */
    function efficasante_favicon_url(bool $platformContext = false): string
    {
        if ($platformContext) {
            if (!function_exists('platform_logo_path')) {
                require_once __DIR__ . '/platform_brand.php';
            }
            $base = rtrim(efficasante_web_base_path(), '/');
            return $base . '/' . ltrim(platform_logo_path(), '/');
        }
        return efficasante_logo_url(32, 32);
    }
}

if (!function_exists('efficasante_logo_fit_box')) {
    /**
     * Calcule les dimensions d'affichage en respectant le ratio natif du fichier.
     *
     * @return array{w:int,h:int,profile:string,native_w:int,native_h:int}
     */
    function efficasante_logo_fit_box(?string $path, int $maxW, int $maxH): array
    {
        $fallback = [
            'w' => min(56, $maxW),
            'h' => min(56, $maxH),
            'profile' => 'square',
            'native_w' => 0,
            'native_h' => 0,
        ];

        if (!$path || !is_readable($path)) {
            $fallback['w'] = min($maxW, (int) round($maxH * 2.4));
            $fallback['h'] = $maxH;
            $fallback['profile'] = 'landscape';
            return $fallback;
        }

        $info = @getimagesize($path);
        if ($info === false || (int) $info[0] <= 0 || (int) $info[1] <= 0) {
            $fallback['w'] = $maxW;
            $fallback['h'] = $maxH;
            $fallback['profile'] = 'landscape';
            return $fallback;
        }

        $nativeW = (int) $info[0];
        $nativeH = (int) $info[1];
        $ratio = $nativeW / $nativeH;
        $profile = $ratio > 1.15 ? 'landscape' : ($ratio < 0.9 ? 'portrait' : 'square');
        $scale = min($maxW / $nativeW, $maxH / $nativeH, 1.0);

        return [
            'w' => max(1, (int) round($nativeW * $scale)),
            'h' => max(1, (int) round($nativeH * $scale)),
            'profile' => $profile,
            'native_w' => $nativeW,
            'native_h' => $nativeH,
        ];
    }
}

function getSystemLogo($size = 'medium', $class = '') {
    // Tailles d'affichage CSS (pixels logiques)
    $sizes = [
        'small'   => ['width' => '32',  'height' => '32'],
        'medium'  => ['width' => '64',  'height' => '64'],
        'large'   => ['width' => '128', 'height' => '128'],
        'header'  => ['width' => '200', 'height' => '60'],
        'navbar'  => ['width' => '202', 'height' => '85'],
        'sidebar' => ['width' => '220', 'height' => '56'],
    ];

    // Résolutions 2× demandées à display_logo.php (netteté Retina / HiDPI)
    $retinaW = ['small' => 64,  'medium' => 128, 'large' => 256, 'header' => 400, 'navbar' => 404, 'sidebar' => 440];
    $retinaH = ['small' => 64,  'medium' => 128, 'large' => 256, 'header' => 120, 'navbar' => 170, 'sidebar' => 112];

    $dimensions = $sizes[$size] ?? $sizes['medium'];
    $width  = (int) $dimensions['width'];
    $height = (int) $dimensions['height'];
    $rW     = $retinaW[$size] ?? 128;
    $rH     = $retinaH[$size] ?? 128;

    $logoPath = null;
    if (class_exists('SystemParameters')) {
        $logoPath = SystemParameters::getInstance()->getLogoPath();
    }

    $fit = null;
    if ($size === 'sidebar') {
        $fit = efficasante_logo_fit_box($logoPath, $width, $height);
        $width = $fit['w'];
        $height = $fit['h'];
        $rW = min(600, max($width * 2, 120));
        $rH = min(200, max($height * 2, 80));
    }

    // Classes CSS
    $defaultClass   = 'system-logo';
    $logoClass      = $class ? $defaultClass . ' ' . $class : $defaultClass;
    $containerClass = 'logo-image-container';
    $containerStyle = 'display: inline-flex; align-items: center; justify-content: center; line-height: 0;';

    if ($size === 'sidebar' && $fit !== null) {
        $containerClass .= ' app-sidebar-logo-frame app-sidebar-logo-frame--' . $fit['profile'];
    }

    // URL du logo : fichier uploadé direct ou script de service
    $logoUrl = efficasante_logo_url($rW, $rH);

    // Fallback SVG affiché si l'image échoue
    $svgFallback = '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 64 64" class="' . $logoClass . ' logo-fallback" style="display: none;">
        <circle cx="32" cy="32" r="30" fill="#17A1B8" stroke="#0F7A8A" stroke-width="2"/>
        <path d="M20 28H24V32H28V28H32V24H28V20H24V24H20V28ZM36 28V24H44V28H36ZM36 32V36H44V32H36Z" fill="white"/>
        <circle cx="28" cy="48" r="2" fill="white"/>
        <circle cx="36" cy="48" r="2" fill="white"/>
    </svg>';

    $imgStyle = 'max-width: ' . $width . 'px; max-height: ' . $height . 'px; width: auto; height: auto; object-fit: contain; object-position: center; display: block;';
    $imgClass = $logoClass . ' app-logo-adaptive';

    return '<span class="' . $containerClass . '" style="' . $containerStyle . '">
        <img src="' . $logoUrl . '" alt="Logo Clinique" class="' . $imgClass . '" style="' . $imgStyle . '" width="' . $width . '" height="' . $height . '" onerror="this.style.display=\'none\'; this.parentElement.querySelector(\'.logo-fallback\').style.display=\'inline-block\';">
        ' . $svgFallback . '
    </span>';
}

function getSystemLogoWithText($size = 'medium', $showText = true, $textClass = '') {
    $logo = getSystemLogo($size, 'me-2');

    if (!$showText) {
        return $logo;
    }

    $textDefaultClass = 'logo-text fw-bold text-primary';
    $textClass        = $textClass ? $textDefaultClass . ' ' . $textClass : $textDefaultClass;
    $nomEtablissement = htmlspecialchars(getNomEtablissement());

    return sprintf(
        '<div class="d-flex align-items-center">
            %s
            <div class="%s">
                <div class="mb-0">%s</div>
                <small class="text-muted">Système de Gestion</small>
            </div>
        </div>',
        $logo,
        $textClass,
        $nomEtablissement
    );
}

function getSystemLogoHeader($variant = 'default') {
    $variants = [
        'default' => [
            'logo_size'       => 'header',
            'show_text'       => true,
            'container_class' => 'd-flex align-items-center',
            'text_class'      => 'ms-3'
        ],
        'compact' => [
            'logo_size'       => 'medium',
            'show_text'       => true,
            'container_class' => 'd-flex align-items-center',
            'text_class'      => 'ms-2'
        ],
        'logo_only' => [
            'logo_size'       => 'medium',
            'show_text'       => false,
            'container_class' => 'd-flex align-items-center justify-content-center',
            'text_class'      => ''
        ],
        'sidebar' => [
            'logo_size'       => 'sidebar',
            'show_text'       => true,
            'container_class' => 'app-sidebar-brand-inner sidebar-logo-wrapper',
            'text_class'      => 'app-sidebar-brand-text'
        ]
    ];

    $config = $variants[$variant] ?? $variants['default'];
    $logo   = getSystemLogo($config['logo_size'], 'mb-0');

    if (!$config['show_text']) {
        return sprintf('<div class="%s">%s</div>', $config['container_class'], $logo);
    }

    $nomEtablissement = htmlspecialchars(getNomEtablissement());

    return sprintf(
        '<div class="%s">
            %s
            <div class="%s">
                <div class="mb-0 fw-bold">%s</div>
                <small class="opacity-75">Système de Gestion</small>
            </div>
        </div>',
        $config['container_class'],
        $logo,
        $config['text_class'],
        $nomEtablissement
    );
}
?>
