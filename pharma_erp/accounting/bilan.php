<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeReporting.php';

$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$bilan = (new PeReporting())->getBilan($dateFrom, $dateTo);

pharma_erp_page_start([
    'active' => 'accounting',
    'title' => 'Bilan SYSCOHADA',
    'subtitle' => 'Situation patrimoniale PharmaPro',
    'icon' => 'fa-balance-scale',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('accounting/export_bilan_pdf.php?date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo)), 'label' => 'Export PDF', 'icon' => 'fa-file-pdf', 'class' => 'btn-pharma-primary', 'target' => '_blank'],
    ['href' => pharma_erp_url('accounting/'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);
?>

<?php pharma_erp_kpi_cards([
    ['value' => pharma_erp_format_money($bilan['actif']), 'label' => 'Total actif', 'icon' => 'fa-building', 'mod' => 'green'],
    ['value' => pharma_erp_format_money($bilan['passif']), 'label' => 'Total passif', 'icon' => 'fa-landmark', 'mod' => 'amber'],
    ['value' => pharma_erp_format_money($bilan['resultat']), 'label' => 'Résultat net', 'icon' => 'fa-chart-line', 'mod' => ($bilan['resultat'] >= 0 ? 'green' : 'rose')],
    ['value' => !empty($bilan['equilibre']) ? 'OK' : 'Écart', 'label' => 'Équilibre bilan', 'icon' => 'fa-check-double', 'mod' => !empty($bilan['equilibre']) ? 'green' : 'rose'],
]); ?>

<div class="row g-4">
    <div class="col-md-6">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">Actif & Passif</div>
            <div class="pharma-pro-panel-body">
                <p>Produits : <strong><?= pharma_erp_format_money($bilan['produits']) ?></strong></p>
                <p>Charges : <strong><?= pharma_erp_format_money($bilan['charges']) ?></strong></p>
                <p>Capitaux : <strong><?= pharma_erp_format_money($bilan['capitaux']) ?></strong></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">Détail des comptes</div>
            <div class="table-responsive" style="max-height:320px;overflow:auto">
                <table class="pharma-pro-table">
                    <thead><tr><th>Compte</th><th class="text-end">Solde</th></tr></thead>
                    <tbody>
                        <?php foreach ($bilan['details'] as $d): if ((float)$d['balance'] == 0) continue; ?>
                        <tr>
                            <td><code><?= htmlspecialchars($d['number']) ?></code> <?= htmlspecialchars($d['label']) ?></td>
                            <td class="text-end"><?= pharma_erp_format_money((float) $d['balance']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
