<?php
/**
 * Paramètres module patients (parametres_systeme).
 */

require_once __DIR__ . '/../config/SystemParameters.php';

if (!function_exists('patient_deletion_allowed')) {
    /**
     * Indique si la suppression de patients est autorisée pour cet établissement.
     */
    function patient_deletion_allowed(): bool
    {
        $sys = SystemParameters::getInstance();
        return $sys->get('patients_suppression_actif', '1') === '1';
    }
}
