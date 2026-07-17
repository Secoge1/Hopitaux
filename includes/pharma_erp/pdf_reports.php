<?php
/**
 * Export PDF PharmaPro — Grand Livre & Bilan SYSCOHADA.
 */

require_once __DIR__ . '/../pdf_branding.php';

if (!function_exists('pharma_erp_tcpdf_path')) {
    function pharma_erp_tcpdf_path(): ?string
    {
        $path = dirname(__DIR__, 2) . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        return is_file($path) ? $path : null;
    }
}

if (!function_exists('pharma_erp_pdf_establishment_name')) {
    function pharma_erp_pdf_establishment_name(): string
    {
        if (function_exists('getNomEtablissement')) {
            return getNomEtablissement();
        }
        return 'PharmaPro ERP';
    }
}

if (!function_exists('pharma_erp_render_grand_livre_pdf')) {
    /** @param list<array<string, mixed>> $grouped */
    function pharma_erp_render_grand_livre_pdf(array $grouped, string $dateFrom, string $dateTo): void
    {
        $tcpdf = pharma_erp_tcpdf_path();
        if (!$tcpdf) {
            http_response_code(500);
            exit('TCPDF non installé.');
        }
        require_once $tcpdf;
        pdf_discard_output_buffers();

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('PharmaPro ERP');
        $pdf->SetTitle('Grand Livre SYSCOHADA');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, pharma_erp_pdf_establishment_name() . ' — Grand Livre', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, 'Période : ' . date('d/m/Y', strtotime($dateFrom)) . ' au ' . date('d/m/Y', strtotime($dateTo)), 0, 1, 'C');
        $pdf->Ln(4);

        foreach ($grouped as $account) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(14, 165, 233);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 7, $account['account_number'] . ' — ' . $account['account_label'], 0, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(22, 6, 'Date', 1, 0, 'C');
            $pdf->Cell(28, 6, 'N° écriture', 1, 0, 'C');
            $pdf->Cell(12, 6, 'Jnl', 1, 0, 'C');
            $pdf->Cell(90, 6, 'Libellé', 1, 0, 'C');
            $pdf->Cell(30, 6, 'Débit', 1, 0, 'R');
            $pdf->Cell(30, 6, 'Crédit', 1, 0, 'R');
            $pdf->Cell(30, 6, 'Solde prog.', 1, 1, 'R');
            $pdf->SetFont('helvetica', '', 8);

            $running = 0.0;
            foreach ($account['lines'] as $line) {
                $running += (float) $line['debit'] - (float) $line['credit'];
                $pdf->Cell(22, 5, date('d/m/Y', strtotime($line['entry_date'])), 1, 0);
                $pdf->Cell(28, 5, $line['entry_number'], 1, 0);
                $pdf->Cell(12, 5, $line['journal_code'], 1, 0, 'C');
                $pdf->Cell(90, 5, mb_substr($line['line_label'] ?: $line['entry_label'], 0, 55), 1, 0);
                $pdf->Cell(30, 5, number_format((float) $line['debit'], 0, ',', ' '), 1, 0, 'R');
                $pdf->Cell(30, 5, number_format((float) $line['credit'], 0, ',', ' '), 1, 0, 'R');
                $pdf->Cell(30, 5, number_format($running, 0, ',', ' '), 1, 1, 'R');
            }
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(152, 6, 'Totaux compte', 1, 0, 'R');
            $pdf->Cell(30, 6, number_format((float) $account['total_debit'], 0, ',', ' '), 1, 0, 'R');
            $pdf->Cell(30, 6, number_format((float) $account['total_credit'], 0, ',', ' '), 1, 0, 'R');
            $pdf->Cell(30, 6, number_format($running, 0, ',', ' '), 1, 1, 'R');
            $pdf->Ln(3);
        }

        $pdf->Output('PharmaPro_Grand_Livre_' . date('Ymd') . '.pdf', 'I');
        exit;
    }
}

if (!function_exists('pharma_erp_render_bilan_pdf')) {
    /** @param array<string, mixed> $bilan */
    function pharma_erp_render_bilan_pdf(array $bilan): void
    {
        $tcpdf = pharma_erp_tcpdf_path();
        if (!$tcpdf) {
            http_response_code(500);
            exit('TCPDF non installé.');
        }
        require_once $tcpdf;
        pdf_discard_output_buffers();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('PharmaPro ERP');
        $pdf->SetTitle('Bilan SYSCOHADA');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, pharma_erp_pdf_establishment_name(), 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Bilan & Compte de résultat SYSCOHADA', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, 'Exercice : ' . date('d/m/Y', strtotime($bilan['date_from'])) . ' — ' . date('d/m/Y', strtotime($bilan['date_to'])), 0, 1, 'C');
        $pdf->Ln(6);

        $fmt = static fn($n) => number_format((float) $n, 0, ',', ' ') . ' FCFA';

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(16, 185, 129);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(95, 8, 'ACTIF', 1, 0, 'C', true);
        $pdf->Cell(95, 8, 'PASSIF', 1, 1, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 10);

        $pdf->Cell(70, 7, 'Immobilisations & stocks', 1, 0);
        $pdf->Cell(25, 7, $fmt($bilan['actif']), 1, 0, 'R');
        $pdf->Cell(70, 7, 'Capitaux propres', 1, 0);
        $pdf->Cell(25, 7, $fmt($bilan['capitaux']), 1, 1, 'R');

        $pdf->Cell(70, 7, '', 1, 0);
        $pdf->Cell(25, 7, '', 1, 0);
        $pdf->Cell(70, 7, 'Dettes (cl. 4)', 1, 0);
        $pdf->Cell(25, 7, $fmt($bilan['passif'] - $bilan['capitaux'] - $bilan['resultat']), 1, 1, 'R');

        $pdf->Cell(70, 7, '', 1, 0);
        $pdf->Cell(25, 7, '', 1, 0);
        $pdf->Cell(70, 7, 'Résultat de l\'exercice', 1, 0);
        $pdf->Cell(25, 7, $fmt($bilan['resultat']), 1, 1, 'R');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(70, 8, 'TOTAL ACTIF', 1, 0);
        $pdf->Cell(25, 8, $fmt($bilan['actif']), 1, 0, 'R');
        $pdf->Cell(70, 8, 'TOTAL PASSIF', 1, 0);
        $pdf->Cell(25, 8, $fmt($bilan['passif']), 1, 1, 'R');

        $pdf->Ln(8);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Compte de résultat', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(120, 7, 'Produits (cl. 7)', 1, 0);
        $pdf->Cell(70, 7, $fmt($bilan['produits']), 1, 1, 'R');
        $pdf->Cell(120, 7, 'Charges (cl. 6)', 1, 0);
        $pdf->Cell(70, 7, $fmt($bilan['charges']), 1, 1, 'R');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(120, 8, 'RÉSULTAT NET', 1, 0);
        $pdf->Cell(70, 8, $fmt($bilan['resultat']), 1, 1, 'R');

        $pdf->Ln(4);
        $pdf->SetFont('helvetica', 'I', 8);
        $status = !empty($bilan['equilibre']) ? 'Bilan équilibré' : 'Écart détecté — vérifier les écritures';
        $pdf->Cell(0, 5, $status . ' — Généré le ' . date('d/m/Y H:i'), 0, 1, 'C');

        $pdf->Output('PharmaPro_Bilan_' . date('Ymd') . '.pdf', 'I');
        exit;
    }
}
