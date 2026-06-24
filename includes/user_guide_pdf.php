<?php
/**
 * Export PDF du guide utilisateur (TCPDF).
 */

require_once __DIR__ . '/user_guide_content.php';

if (!function_exists('user_guide_stream_pdf')) {
    /**
     * Envoie le guide utilisateur en téléchargement PDF.
     */
    function user_guide_stream_pdf(?int $tenantId = null, string $disposition = 'D'): void
    {
        $tcpdfPath = dirname(__DIR__) . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        if (!is_file($tcpdfPath)) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Bibliothèque PDF indisponible. Contactez l\'administrateur.';
            exit;
        }

        require_once __DIR__ . '/pdf_branding.php';
        require_once $tcpdfPath;

        if (ob_get_level()) {
            ob_end_clean();
        }

        $systemParams = pdf_tenant_system_params($tenantId);
        $meta = user_guide_meta();
        $etab = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $meta['etablissement']) ?: 'etablissement';

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator($meta['platform']);
        $pdf->SetAuthor($meta['etablissement']);
        $pdf->SetTitle('Guide utilisateur — ' . $meta['etablissement']);
        $pdf->SetSubject('Guide nouveaux utilisateurs');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 9);
        $pdf->writeHTML(user_guide_build_pdf_html($tenantId), true, false, true, false, '');

        $filename = 'Guide_Utilisateurs_' . $etab . '_' . date('Y-m-d') . '.pdf';
        $disp = in_array($disposition, ['D', 'I'], true) ? $disposition : 'D';
        $pdf->Output($filename, $disp);
        exit;
    }
}
