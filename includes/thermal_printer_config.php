<?php
/**
 * Configuration imprimante thermique (parametres_systeme).
 */

require_once __DIR__ . '/../config/SystemParameters.php';

if (!function_exists('thermal_printer_settings')) {
    function thermal_printer_settings(): array
    {
        $sys = SystemParameters::getInstance();
        return [
            'actif'      => $sys->get('thermal_printer_actif', '1') === '1',
            'ip'         => trim((string) $sys->get('thermal_printer_ip', '')),
            'port'       => (int) $sys->get('thermal_printer_port', 9100) ?: 9100,
            'largeur_mm' => (int) $sys->get('thermal_printer_width_mm', 80) ?: 80,
            'modele'     => trim((string) $sys->get('thermal_printer_model', 'Xprinter XP-80TS')),
        ];
    }
}

if (!function_exists('thermal_printer_line_width')) {
    function thermal_printer_line_width(int $widthMm = 80): int
    {
        return $widthMm >= 80 ? 48 : 32;
    }
}

if (!function_exists('thermal_printer_is_configured')) {
    function thermal_printer_is_configured(): bool
    {
        $s = thermal_printer_settings();
        return $s['actif'] && $s['ip'] !== '';
    }
}
