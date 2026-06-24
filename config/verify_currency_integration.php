<?php
/**
 * Vérification rapide du module devise unifié.
 * Usage : php config/verify_currency_integration.php
 */
require_once __DIR__ . '/CurrencyConfig.php';

$errors = [];
$ok = 0;

function assert_eq($label, $expected, $actual, array &$errors, int &$ok): void
{
    if ($expected === $actual) {
        $ok++;
        return;
    }
    $errors[] = "$label — attendu: " . var_export($expected, true) . ', obtenu: ' . var_export($actual, true);
}

// Conversion EUR
assert_eq('655 FCFA → EUR', 0.9956, round(CurrencyConfig::convertFromFCFA(655, 'EUR'), 4), $errors, $ok);
assert_eq('1 EUR → FCFA', round(1 / 0.00152), round(CurrencyConfig::convertToFCFA(1, 'EUR')), $errors, $ok);

// Format sans conversion (FCFA)
$fcfa = CurrencyConfig::formatForTenant(125000, ['code' => 'XOF', 'symbol' => 'FCFA', 'decimals' => 0, 'conversion' => false]);
assert_eq('Format FCFA', 'FCFA 125 000', $fcfa, $errors, $ok);

// Format avec conversion EUR
$eur = CurrencyConfig::formatForTenant(125000, ['code' => 'EUR', 'symbol' => '€', 'decimals' => 2, 'conversion' => true]);
assert_eq('Format EUR converti contient €', true, strpos($eur, '€') !== false, $errors, $ok);
assert_eq('Format EUR converti ≠ brut FCFA', true, $eur !== 'FCFA 125 000', $errors, $ok);

// Code EUR sans conversion → affichage FCFA
$noConv = CurrencyConfig::formatForTenant(1000, ['code' => 'EUR', 'symbol' => '€', 'decimals' => 2, 'conversion' => false]);
assert_eq('EUR sans conversion reste FCFA', 'FCFA 1 000', $noConv, $errors, $ok);

// inputToStorage
$stored = CurrencyConfig::inputToStorage(1, ['code' => 'EUR', 'conversion' => true]);
assert_eq('Saisie 1 EUR → FCFA', round(1 / 0.00152), round($stored), $errors, $ok);

echo "Tests devise : $ok OK";
if ($errors) {
    echo ', ' . count($errors) . " échec(s)\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
    exit(1);
}
echo "\n";
