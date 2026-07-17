<?php
/**
 * Profil site — production PharmaPro ERP (pharmasmart.secogesarl.com).
 */

if (!defined('APP_PHARMA_HOST')) {
    define('APP_PHARMA_HOST', true);
}

if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://pharmasmart.secogesarl.com');
}

if (!defined('PLATFORM_NAME')) {
    define('PLATFORM_NAME', 'PharmaPro ERP');
}
if (!defined('PLATFORM_TAGLINE')) {
    define('PLATFORM_TAGLINE', 'ERP de gestion de pharmacie — moderne, intuitif, rentable');
}
if (!defined('PLATFORM_COMPANY')) {
    define('PLATFORM_COMPANY', 'Secogesarl');
}
if (!defined('PLATFORM_VENDOR_URL')) {
    define('PLATFORM_VENDOR_URL', 'https://www.secogesarl.com');
}

if (!defined('PHARMA_CONTACT_EMAIL')) {
    define('PHARMA_CONTACT_EMAIL', 'contact@secogesarl.com');
}
if (!defined('PHARMA_CONTACT_WEBSITE')) {
    define('PHARMA_CONTACT_WEBSITE', 'https://pharmasmart.secogesarl.com');
}

/** Site dédié officine — shell PharmaPro partout (pas le HIS clinique). */
if (!defined('PHARMA_ERP_STANDALONE')) {
    define('PHARMA_ERP_STANDALONE', true);
}

/** Forcer HTTPS sur ce domaine. */
if (!defined('APP_FORCE_HTTPS')) {
    define('APP_FORCE_HTTPS', true);
}
