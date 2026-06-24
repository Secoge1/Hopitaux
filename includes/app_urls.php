<?php
/**
 * Helpers URL application — disponibles après init.php (scripts d'action, redirections).
 */

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        if (!function_exists('efficasante_web_base_path')) {
            require_once __DIR__ . '/header_logo.php';
        }
        $base = efficasante_web_base_path();
        if ($path === '') {
            return $base === '' ? '/' : $base;
        }
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}
