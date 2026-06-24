<?php
/**
 * Configuration devise — compatibilité legacy, délègue au helper tenant.
 */
if (!function_exists('formatMoney')) {
    require_once __DIR__ . '/../includes/currency_helper.php';
}

if (!defined('CURRENCY_CODE')) {
    $ci = function_exists('getCurrencyInfo')
        ? getCurrencyInfo()
        : ['code' => 'XOF', 'symbol' => 'FCFA', 'name' => 'Franc CFA', 'decimals' => 0];
    define('CURRENCY_CODE', $ci['code']);
    define('CURRENCY_SYMBOL', $ci['symbol']);
    define('CURRENCY_NAME', $ci['name']);
    define('CURRENCY_DECIMALS', (int) $ci['decimals']);
}

if (!defined('CURRENCY_FORMAT')) {
    define('CURRENCY_FORMAT', '%s %s');
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $showSymbol = true)
    {
        return formatMoney($amount, (bool) $showSymbol);
    }
}

if (!function_exists('amountToCents')) {
    function amountToCents($amount)
    {
        return (int) round((float) $amount * 100);
    }
}

if (!function_exists('centsToAmount')) {
    function centsToAmount($cents)
    {
        return (int) $cents / 100;
    }
}

if (!function_exists('isValidAmount')) {
    function isValidAmount($amount)
    {
        return is_numeric($amount) && (float) $amount >= 0;
    }
}

if (!function_exists('getCurrencyInfo')) {
    function getCurrencyInfo()
    {
        return [
            'code' => CURRENCY_CODE,
            'symbol' => CURRENCY_SYMBOL,
            'name' => CURRENCY_NAME,
            'decimals' => CURRENCY_DECIMALS,
            'format' => CURRENCY_FORMAT,
        ];
    }
}
