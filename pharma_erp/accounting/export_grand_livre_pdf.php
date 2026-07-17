<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/bootstrap.php';

pharma_erp_require_role();

require_once __DIR__ . '/../../models/pharma_erp/PeReporting.php';
require_once __DIR__ . '/../../includes/pharma_erp/pdf_reports.php';

$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$grouped = (new PeReporting())->getGrandLivreGrouped($dateFrom, $dateTo);
pharma_erp_render_grand_livre_pdf($grouped, $dateFrom, $dateTo);
