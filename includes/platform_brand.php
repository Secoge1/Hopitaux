<?php
/**
 * Marque plateforme Se.Santé — pages publiques et login.
 * Distinct du branding tenant (logo/nom de chaque établissement abonné).
 */

if (!function_exists('platform_name')) {
    function platform_name(): string
    {
        if (!class_exists('PlatformBranding', false)) {
            require_once __DIR__ . '/PlatformBranding.php';
        }
        return PlatformBranding::getName();
    }

    function platform_tagline(): string
    {
        return defined('PLATFORM_TAGLINE') ? (string) PLATFORM_TAGLINE : 'Votre système de santé unique';
    }

    function platform_company(): string
    {
        return defined('PLATFORM_COMPANY') ? (string) PLATFORM_COMPANY : 'Secogesarl';
    }

    function platform_vendor_name(): string
    {
        return defined('PLATFORM_VENDOR_NAME') ? (string) PLATFORM_VENDOR_NAME : 'Secoge';
    }

    function platform_vendor_url(): string
    {
        return defined('PLATFORM_VENDOR_URL') ? (string) PLATFORM_VENDOR_URL : 'https://www.secogesarl.com';
    }

    /** HTML « Propulsé par … » avec lien vers le site éditeur. */
    function platform_powered_by_html(string $linkClass = 'app-platform-vendor-link'): string
    {
        $name = htmlspecialchars(platform_vendor_name(), ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars(platform_vendor_url(), ENT_QUOTES, 'UTF-8');
        $class = htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8');

        return 'Propulsé par <a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="' . $class . '">' . $name . '</a>';
    }

    function platform_logo_path(): string
    {
        if (!class_exists('PlatformBranding', false)) {
            require_once __DIR__ . '/PlatformBranding.php';
        }
        return PlatformBranding::getLogoPath();
    }

    function platform_logo_url(): string
    {
        $path = platform_logo_path();
        if (function_exists('public_url')) {
            return public_url($path);
        }
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    function platform_logo_exists(): bool
    {
        $root = dirname(__DIR__);
        $rel = ltrim(str_replace('\\', '/', platform_logo_path()), '/');
        return is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    }

    /**
     * @param 'nav'|'footer'|'login'|'login-compact' $variant
     */
    function platform_brand_html(string $variant = 'nav'): string
    {
        $name = platform_name();
        $tagline = platform_tagline();
        $logoUrl = htmlspecialchars(platform_logo_url(), ENT_QUOTES, 'UTF-8');
        $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $tagEsc = htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8');

        if (!platform_logo_exists()) {
            return '<span class="pub-brand-icon"><i class="fas fa-hospital"></i></span>'
                . '<span class="pub-brand-text"><strong>' . $nameEsc . '</strong>'
                . '<small>' . $tagEsc . '</small></span>';
        }

        switch ($variant) {
            case 'footer':
                return '<img src="' . $logoUrl . '" alt="' . $nameEsc . '" class="pub-brand-logo pub-brand-logo-footer" height="56">';

            case 'login':
                return '<img src="' . $logoUrl . '" alt="' . $nameEsc . '" class="platform-logo platform-logo-login">';

            case 'login-compact':
                return '<img src="' . $logoUrl . '" alt="' . $nameEsc . '" class="platform-logo platform-logo-compact">';

            case 'nav':
            default:
                return '<img src="' . $logoUrl . '" alt="' . $nameEsc . '" class="pub-brand-logo pub-brand-logo-nav" height="48">';
        }
    }
}
