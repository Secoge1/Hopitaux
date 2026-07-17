<?php
/**
 * Configuration imprimante thermique (parametres_systeme).
 */

require_once __DIR__ . '/../config/SystemParameters.php';

if (!function_exists('thermal_printer_normalize_paper_mm')) {
    /** Largeur rouleau standard Xprinter : 80 mm ou 58 mm uniquement. */
    function thermal_printer_normalize_paper_mm(int $widthMm): int
    {
        return $widthMm <= 58 ? 58 : 80;
    }
}

if (!function_exists('thermal_printer_printable_width_mm')) {
    /** Zone imprimable utile (80 mm → 72 mm, 58 mm → 48 mm). */
    function thermal_printer_printable_width_mm(int $paperMm): int
    {
        return thermal_printer_normalize_paper_mm($paperMm) >= 80 ? 72 : 48;
    }
}

if (!function_exists('thermal_printer_raster_max_px')) {
    /** Largeur max image ESC/POS (203 dpi) — 576 px @ 80 mm, 384 px @ 58 mm. */
    function thermal_printer_raster_max_px(int $paperMm): int
    {
        return thermal_printer_normalize_paper_mm($paperMm) >= 80 ? 576 : 384;
    }
}

if (!function_exists('thermal_printer_settings')) {
    function thermal_printer_settings(): array
    {
        $sys = SystemParameters::getInstance();
        $paperMm = thermal_printer_normalize_paper_mm(
            (int) $sys->get('thermal_printer_width_mm', 80) ?: 80
        );
        return [
            'actif'      => $sys->get('thermal_printer_actif', '1') === '1',
            'ip'         => trim((string) $sys->get('thermal_printer_ip', '')),
            'port'       => (int) $sys->get('thermal_printer_port', 9100) ?: 9100,
            'largeur_mm' => $paperMm,
            'modele'     => trim((string) $sys->get('thermal_printer_model', 'Xprinter XP-80TS')),
        ];
    }
}

if (!function_exists('thermal_printer_line_width')) {
    function thermal_printer_line_width(int $widthMm = 80): int
    {
        return thermal_printer_normalize_paper_mm($widthMm) >= 80 ? 48 : 32;
    }
}

if (!function_exists('thermal_printer_is_configured')) {
    function thermal_printer_is_configured(): bool
    {
        $s = thermal_printer_settings();
        return $s['actif'] && $s['ip'] !== '';
    }
}
