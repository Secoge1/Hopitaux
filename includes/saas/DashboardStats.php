<?php
/**
 * Statistiques globales accueil / dashboard — filtrées par tenant.
 */

require_once __DIR__ . '/TenantContext.php';
require_once __DIR__ . '/TenantScope.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Patient.php';

class DashboardStats
{
    public static function get(): array
    {
        TenantContext::bindFromSession();
        $db = getDB();

        return [
            'patients'                  => Patient::countForDashboard(),
            'consultations_aujourd_hui' => TenantScope::count($db, 'consultations', ['DATE(date_consultation) = CURDATE()']),
            'rdv_aujourd_hui'           => TenantScope::count($db, 'rendez_vous', ["DATE(date_rdv) = CURDATE()", "statut != 'supprime'"]),
            'analyses_en_cours'         => TenantScope::count($db, 'analyses', ["statut = 'en_cours'"]),
            'paiements_en_attente'      => TenantScope::count($db, 'paiements', ["statut = 'en_attente'"]),
            'paiements_total'           => TenantScope::count($db, 'paiements'),
            'medecins_actifs'           => TenantScope::count($db, 'medecins', ["statut != 'supprime'"]),
            'utilisateurs_actifs'       => TenantScope::count($db, 'utilisateurs', ["statut = 'actif'"]),
            'last_updated'              => date('Y-m-d H:i:s'),
        ];
    }

    /** @return array<string, mixed> */
    public static function cacheParams(): array
    {
        TenantContext::bindFromSession();
        return ['tenant_id' => TenantContext::getTenantId() ?? 0];
    }
}
