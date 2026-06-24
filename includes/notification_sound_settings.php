<?php
/**
 * Paramètres des notifications sonores (assignation patient, messages internes).
 */

if (!function_exists('notification_sounds_enabled')) {
    function notification_sounds_enabled(): bool
    {
        if (!class_exists('SystemParameters')) {
            require_once __DIR__ . '/../config/SystemParameters.php';
        }
        return SystemParameters::getInstance()->get('notifications_sonores_actif', '1') === '1';
    }
}

if (!function_exists('notification_sound_modules')) {
    /** @return list<string> */
    function notification_sound_modules(): array
    {
        return ['patients', 'communication'];
    }
}

if (!function_exists('notification_sound_js_config')) {
    /** @return array{enabled: bool, modules: list<string>, pollInterval: int} */
    function notification_sound_js_config(): array
    {
        return [
            'enabled' => notification_sounds_enabled(),
            'modules' => notification_sound_modules(),
            'pollInterval' => 15000,
        ];
    }
}
