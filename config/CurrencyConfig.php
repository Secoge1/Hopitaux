<?php
/**
 * Configuration monétaire — FCFA (XOF) en devise de stockage, conversion à l'affichage.
 */
class CurrencyConfig
{
    public const BASE_CURRENCY = 'XOF';
    public const DEFAULT_SYMBOL = 'FCFA';
    public const DEFAULT_DECIMALS = 0;

    /**
     * Taux : montant_en_devise = montant_fcfa * RATE[from_fcfa_to_currency]
     * Ex. 655 FCFA * 0.00152 ≈ 1 EUR
     */
    public const EXCHANGE_RATES = [
        'XOF' => 1.0,
        'EUR' => 0.00152,
        'USD' => 0.00167,
        'GBP' => 0.00131,
    ];

    public const CURRENCY_PRESETS = [
        'XOF' => ['symbol' => 'FCFA', 'decimals' => 0, 'name' => 'Franc CFA'],
        'EUR' => ['symbol' => '€', 'decimals' => 2, 'name' => 'Euro'],
        'USD' => ['symbol' => '$', 'decimals' => 2, 'name' => 'Dollar US'],
        'GBP' => ['symbol' => '£', 'decimals' => 2, 'name' => 'Livre sterling'],
    ];

    public static function isFCFA(string $currency): bool
    {
        return strtoupper($currency) === self::BASE_CURRENCY;
    }

    public static function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        return $code !== '' ? $code : self::BASE_CURRENCY;
    }

    public static function getRateFromFCFA(string $toCurrency): float
    {
        $code = self::normalizeCode($toCurrency);
        return (float) (self::EXCHANGE_RATES[$code] ?? 1.0);
    }

    public static function convertToFCFA(float $amount, string $fromCurrency = 'XOF'): float
    {
        $from = self::normalizeCode($fromCurrency);
        if (self::isFCFA($from)) {
            return $amount;
        }
        $rate = self::getRateFromFCFA($from);
        if ($rate <= 0) {
            return $amount;
        }
        return $amount / $rate;
    }

    public static function convertFromFCFA(float $amount, string $toCurrency = 'XOF'): float
    {
        $to = self::normalizeCode($toCurrency);
        if (self::isFCFA($to)) {
            return $amount;
        }
        return $amount * self::getRateFromFCFA($to);
    }

    public static function convertAmount(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $from = self::normalizeCode($fromCurrency);
        $to = self::normalizeCode($toCurrency);
        if ($from === $to) {
            return $amount;
        }
        $inFcfa = self::convertToFCFA($amount, $from);
        return self::convertFromFCFA($inFcfa, $to);
    }

    /**
     * Montant saisi par l'utilisateur → montant stocké en FCFA.
     *
     * @param array{code?: string, conversion?: bool} $settings
     */
    public static function inputToStorage(float $displayAmount, array $settings): float
    {
        $code = self::normalizeCode($settings['code'] ?? self::BASE_CURRENCY);
        $convert = !empty($settings['conversion']) && !self::isFCFA($code);
        if (!$convert) {
            return $displayAmount;
        }
        return self::convertToFCFA($displayAmount, $code);
    }

    /**
     * Montant stocké (FCFA) → montant affiché selon paramètres tenant.
     *
     * @param array{code?: string, symbol?: string, decimals?: int, conversion?: bool} $settings
     */
    public static function storageToDisplay(float $amountInXOF, array $settings): float
    {
        $code = self::normalizeCode($settings['code'] ?? self::BASE_CURRENCY);
        $convert = !empty($settings['conversion']) && !self::isFCFA($code);
        if (!$convert) {
            return $amountInXOF;
        }
        return self::convertFromFCFA($amountInXOF, $code);
    }

    /**
     * @param array{code?: string, symbol?: string, decimals?: int, conversion?: bool} $settings
     */
    public static function formatForTenant(float $amountInXOF, array $settings, bool $showSymbol = true): string
    {
        if (!is_numeric($amountInXOF)) {
            $amountInXOF = 0.0;
        }

        $code = self::normalizeCode($settings['code'] ?? self::BASE_CURRENCY);
        $symbol = trim((string) ($settings['symbol'] ?? ''));
        $decimals = isset($settings['decimals']) ? (int) $settings['decimals'] : self::DEFAULT_DECIMALS;
        $convert = !empty($settings['conversion']) && !self::isFCFA($code);

        if ($convert) {
            $displayAmount = self::convertFromFCFA((float) $amountInXOF, $code);
            if ($symbol === '') {
                $symbol = self::CURRENCY_PRESETS[$code]['symbol'] ?? $code;
            }
        } else {
            $displayAmount = (float) $amountInXOF;
            if (self::isFCFA($code)) {
                if ($symbol === '') {
                    $symbol = self::DEFAULT_SYMBOL;
                }
                if ($decimals < 0) {
                    $decimals = self::DEFAULT_DECIMALS;
                }
            } else {
                $symbol = self::DEFAULT_SYMBOL;
                $decimals = self::DEFAULT_DECIMALS;
            }
        }

        $formatted = number_format($displayAmount, max(0, $decimals), ',', ' ');
        if (!$showSymbol) {
            return $formatted;
        }
        return trim($symbol . ' ' . $formatted);
    }

    public static function formatAmount($amount, $showSymbol = true): string
    {
        return self::formatForTenant(
            (float) $amount,
            ['code' => self::BASE_CURRENCY, 'symbol' => self::DEFAULT_SYMBOL, 'decimals' => self::DEFAULT_DECIMALS, 'conversion' => false],
            $showSymbol
        );
    }

    public static function formatFCFA($amount): string
    {
        return self::formatAmount($amount, true);
    }

    public static function formatAmountOnly($amount): string
    {
        return self::formatAmount($amount, false);
    }

    public static function isValidAmount($amount): bool
    {
        return is_numeric($amount) && (float) $amount >= 0;
    }

    public static function getCurrencyConfig(): array
    {
        return [
            'code' => self::BASE_CURRENCY,
            'symbol' => self::DEFAULT_SYMBOL,
            'decimals' => self::DEFAULT_DECIMALS,
            'exchange_rates' => self::EXCHANGE_RATES,
            'base' => self::BASE_CURRENCY,
        ];
    }

    public static function generateCurrencySelector($selectedCurrency = 'XOF', $name = 'currency'): string
    {
        $html = '<select name="' . htmlspecialchars($name) . '" class="form-select">';
        foreach (self::CURRENCY_PRESETS as $code => $preset) {
            $selected = self::normalizeCode((string) $selectedCurrency) === $code ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($code) . '" ' . $selected . '>'
                . htmlspecialchars($preset['name'] . ' (' . $code . ')') . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    public static function getCurrencyHelpText(): string
    {
        return 'Les montants sont enregistrés en FCFA (XOF). Si une autre devise est choisie avec conversion activée, l\'affichage est converti automatiquement.';
    }

    public static function getCurrencyName($currency = 'XOF'): string
    {
        $code = self::normalizeCode((string) $currency);
        return self::CURRENCY_PRESETS[$code]['name'] ?? 'Devise inconnue';
    }
}
