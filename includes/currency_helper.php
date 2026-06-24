<?php
/**
 * Helper monétaire global — formatage tenant + conversion depuis FCFA (stockage).
 */

if (!defined('FCFA_ACTIVE')) {
    define('FCFA_ACTIVE', true);
}

require_once __DIR__ . '/../config/CurrencyConfig.php';

/**
 * @return array{code: string, symbol: string, decimals: int, conversion: bool, base: string, rateFromBase: float, name: string}
 */
function app_currency_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $defaults = [
        'code' => CurrencyConfig::BASE_CURRENCY,
        'symbol' => CurrencyConfig::DEFAULT_SYMBOL,
        'decimals' => CurrencyConfig::DEFAULT_DECIMALS,
        'conversion' => false,
        'base' => CurrencyConfig::BASE_CURRENCY,
        'name' => 'Franc CFA (FCFA)',
    ];

    try {
        if (!class_exists('SystemParameters')) {
            require_once __DIR__ . '/../config/SystemParameters.php';
        }
        if (class_exists('SystemParameters')) {
            $sp = SystemParameters::getInstance();
            if (method_exists($sp, 'getCurrencySettings')) {
                $cache = $sp->getCurrencySettings();
                return $cache;
            }
        }
    } catch (Throwable $e) {
        error_log('app_currency_settings: ' . $e->getMessage());
    }

    $cache = $defaults;
    return $cache;
}

/**
 * Formate un montant stocké en FCFA pour l'affichage (conversion optionnelle).
 */
function formatMoney($amount, bool $showSymbol = true): string
{
    return CurrencyConfig::formatForTenant((float) $amount, app_currency_settings(), $showSymbol);
}

if (!function_exists('formatFCFA')) {
    function formatFCFA($amount)
    {
        return formatMoney($amount);
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $showSymbol = true)
    {
        return formatMoney($amount, (bool) $showSymbol);
    }
}

if (!function_exists('formatAmountOnly')) {
    function formatAmountOnly($amount)
    {
        return formatMoney($amount, false);
    }
}

if (!function_exists('isValidAmount')) {
    function isValidAmount($amount): bool
    {
        return CurrencyConfig::isValidAmount($amount);
    }
}

if (!function_exists('getCurrencyInfo')) {
    function getCurrencyInfo(): array
    {
        $s = app_currency_settings();
        return [
            'code' => $s['code'],
            'symbol' => $s['symbol'],
            'decimals' => $s['decimals'],
            'name' => $s['name'],
            'conversion' => $s['conversion'],
            'base' => $s['base'],
        ];
    }
}

/** Montant saisi (devise affichée) → FCFA pour enregistrement. */
function parseMoneyInput($amount): float
{
    if (!is_numeric($amount)) {
        return 0.0;
    }
    return CurrencyConfig::inputToStorage((float) $amount, app_currency_settings());
}

/** Config JSON pour le JavaScript (app-currency.js). */
function app_currency_js_config(): array
{
    $s = app_currency_settings();
    return [
        'code' => $s['code'],
        'symbol' => $s['symbol'],
        'decimals' => (int) $s['decimals'],
        'conversion' => !empty($s['conversion']),
        'base' => $s['base'],
        'rateFromBase' => $s['rateFromBase'],
    ];
}

function app_currency_label(): string
{
    $s = app_currency_settings();
    return $s['symbol'] . ($s['code'] !== CurrencyConfig::BASE_CURRENCY ? ' (' . $s['code'] . ')' : '');
}
