<?php
/**
 * Paramètres module laboratoire (parametres_systeme).
 * Même pattern que medecin_settings.php.
 */

require_once __DIR__ . '/../config/SystemParameters.php';

if (!function_exists('secretaire_labo_delete_allowed')) {
    /**
     * Paramètre établissement : le secrétaire peut-il supprimer des analyses ?
     * Activé uniquement si l'administrateur l'a accordé explicitement.
     */
    function secretaire_labo_delete_allowed(): bool
    {
        $sys = SystemParameters::getInstance();
        return $sys->get('secretaire_labo_suppression_actif', '0') === '1';
    }
}

if (!function_exists('labo_delete_allowed')) {
    /**
     * Vérifie si l'utilisateur courant est autorisé à supprimer une analyse.
     * - Admin : toujours autorisé.
     * - Secrétaire : autorisé seulement si l'admin l'a activé.
     * - Autres rôles du module labo (medecin, infirmier, laborantin, major) : non autorisés à supprimer.
     *
     * @param \Auth|null $auth Instance Auth (auto-injectée si null).
     */
    function labo_delete_allowed($auth = null): bool
    {
        if ($auth === null) {
            require_once __DIR__ . '/../config/Auth.php';
            $auth = Auth::getInstance();
        }
        if (!empty($_SESSION['is_platform_admin'])) {
            return true;
        }
        if ($auth->estAdmin()) {
            return true;
        }
        if ($auth->estSecretaire()) {
            return secretaire_labo_delete_allowed();
        }
        return false;
    }
}
