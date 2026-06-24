<?php
/**
 * Paramètres module médecins (parametres_systeme).
 */

require_once __DIR__ . '/../config/SystemParameters.php';

if (!function_exists('secretaire_medecin_add_allowed')) {
    /**
     * Paramètre établissement : le secrétaire peut-il ajouter des professionnels ?
     */
    function secretaire_medecin_add_allowed(): bool
    {
        $sys = SystemParameters::getInstance();
        return $sys->get('secretaire_medecins_ajout_actif', '0') === '1';
    }
}

if (!function_exists('medecin_create_allowed')) {
    /**
     * Création d'une fiche professionnelle (médecin, infirmier, etc.).
     */
    function medecin_create_allowed($auth = null): bool
    {
        if ($auth === null) {
            require_once __DIR__ . '/../config/Auth.php';
            $auth = Auth::getInstance();
        }
        if ($auth->estAdmin()) {
            return true;
        }
        return $auth->estSecretaire() && secretaire_medecin_add_allowed();
    }
}

if (!function_exists('medecin_admin_actions_allowed')) {
    /**
     * Modification, suppression et gestion complète des fiches.
     */
    function medecin_admin_actions_allowed($auth = null): bool
    {
        if ($auth === null) {
            require_once __DIR__ . '/../config/Auth.php';
            $auth = Auth::getInstance();
        }
        return $auth->estAdmin();
    }
}
