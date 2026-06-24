<?php
/**
 * Branding PDF / impression — paramètres et logo par établissement (tenant).
 */

require_once __DIR__ . '/../config/SystemParameters.php';

if (!function_exists('pdf_discard_output_buffers')) {
    /** Vide tous les tampons de sortie avant envoi PDF (évite l'erreur TCPDF). */
    function pdf_discard_output_buffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

if (!function_exists('pdf_tenant_id_from_session')) {
    function pdf_tenant_id_from_session(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (!empty($_SESSION['tenant_id'])) {
            return (int) $_SESSION['tenant_id'];
        }
        if (class_exists('Auth')) {
            $auth = Auth::getInstance();
            if ($auth->estConnecte()) {
                $tid = $auth->getTenantId();
                if ($tid) {
                    return (int) $tid;
                }
            }
        }

        return null;
    }
}

if (!function_exists('pdf_tenant_id_from_patient')) {
    function pdf_tenant_id_from_patient(int $patientId): ?int
    {
        if ($patientId < 1) {
            return null;
        }
        if (!function_exists('getDB')) {
            require_once __DIR__ . '/../config/db.php';
        }
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT tenant_id FROM patients WHERE id = ? LIMIT 1');
            $stmt->execute([$patientId]);
            $tid = $stmt->fetchColumn();
            return $tid ? (int) $tid : null;
        } catch (Throwable $e) {
            error_log('pdf_tenant_id_from_patient: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('pdf_tenant_system_params')) {
    /**
     * Paramètres système isolés pour l'établissement courant (PDF, tickets, dossiers).
     */
    function pdf_tenant_system_params(?int $tenantId = null): SystemParameters
    {
        if ($tenantId === null) {
            $tenantId = pdf_tenant_id_from_session();
        }
        SystemParameters::resetInstance();
        if ($tenantId !== null) {
            return SystemParameters::forTenant($tenantId);
        }

        return SystemParameters::getInstance();
    }
}
