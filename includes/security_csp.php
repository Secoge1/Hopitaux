<?php
/**
 * En-tête Content-Security-Policy partagé (SecuritySystem + SecuritySystemSafe).
 */
if (!function_exists('app_content_security_policy')) {
    function app_content_security_policy(): string
    {
        return implode(' ', [
            "default-src 'self';",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com;",
            "img-src 'self' data: https:;",
            "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com;",
            /* connect-src : fetch/XHR + source maps CDN (Bootstrap .map) */
            "connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com;",
        ]);
    }
}
