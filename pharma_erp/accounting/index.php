<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeAccounting.php';
require_once __DIR__ . '/../../models/pharma_erp/PeAccountingEngine.php';
require_once __DIR__ . '/../../models/pharma_erp/PeHisFinanceBridge.php';

PeAccountingEngine::ensureSeed();

$model = new PeAccounting();
$stats = $model->getStats();
$entries = $model->getEntries(1, 10);
$hisSync = PeHisFinanceBridge::isEnabled();

pharma_erp_page_start([
    'active' => 'accounting',
    'title' => 'Comptabilité SYSCOHADA',
    'subtitle' => 'Écritures automatiques ventes & achats',
    'icon' => 'fa-book',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('accounting/plan.php'), 'label' => 'Plan comptable', 'icon' => 'fa-list', 'class' => 'btn-pharma-outline'],
    ['href' => pharma_erp_url('accounting/journaux.php'), 'label' => 'Journaux', 'icon' => 'fa-book', 'class' => 'btn-pharma-outline'],
    ['href' => pharma_erp_url('accounting/grand_livre.php'), 'label' => 'Grand Livre', 'icon' => 'fa-book-open', 'class' => 'btn-pharma-outline'],
    ['href' => pharma_erp_url('accounting/bilan.php'), 'label' => 'Bilan PDF', 'icon' => 'fa-file-pdf', 'class' => 'btn-pharma-outline'],
    ['href' => pharma_erp_url('accounting/balance.php'), 'label' => 'Balance', 'icon' => 'fa-balance-scale', 'class' => 'btn-pharma-primary'],
    ['href' => pharma_erp_url('accounting/banque.php'), 'label' => 'Banque', 'icon' => 'fa-university', 'class' => 'btn-pharma-outline'],
    ['href' => pharma_erp_url('accounting/immobilisations.php'), 'label' => 'Immobilisations', 'icon' => 'fa-building', 'class' => 'btn-pharma-outline'],
    ['href' => pharma_erp_url('accounting/tva.php'), 'label' => 'TVA', 'icon' => 'fa-percent', 'class' => 'btn-pharma-outline'],
]);
?>

<?php pharma_erp_kpi_cards([
    ['value' => (string) ($stats['accounts'] ?? 0), 'label' => 'Comptes actifs', 'icon' => 'fa-list'],
    ['value' => (string) ($stats['entries'] ?? 0), 'label' => 'Écritures comptabilisées', 'icon' => 'fa-pen', 'mod' => 'green'],
    ['value' => pharma_erp_format_money($stats['treasury'] ?? 0), 'label' => 'Trésorerie (cl. 5)', 'icon' => 'fa-wallet', 'mod' => 'green'],
    ['value' => pharma_erp_format_money($stats['supplier_debt'] ?? 0), 'label' => 'Dettes fournisseurs', 'icon' => 'fa-truck', 'mod' => 'amber'],
]); ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header"><i class="fas fa-clock-rotate-left"></i> Dernières écritures</div>
            <div class="table-responsive">
                <table class="pharma-pro-table">
                    <thead>
                        <tr><th>N°</th><th>Date</th><th>Journal</th><th>Libellé</th><th class="text-end">Montant</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entries)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune écriture — effectuez une vente ou une réception</td></tr>
                        <?php else: foreach ($entries as $e): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($e['entry_number']) ?></code></td>
                            <td><?= date('d/m/Y', strtotime($e['entry_date'])) ?></td>
                            <td><span class="pe-badge pe-badge--active"><?= htmlspecialchars($e['journal_code']) ?></span></td>
                            <td><?= htmlspecialchars($e['label']) ?></td>
                            <td class="text-end"><?= pharma_erp_format_money((float) $e['total_debit']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header"><i class="fas fa-robot"></i> Écritures automatiques</div>
            <div class="pharma-pro-panel-body">
                <p class="small text-muted">Chaque opération PharmaPro génère une écriture équilibrée :</p>
                <ul class="small">
                    <li><strong>Vente POS</strong> → Débit Caisse/Banque · Crédit Ventes + TVA</li>
                    <li><strong>Réception achat</strong> → Débit Stock + TVA déductible · Crédit Fournisseurs</li>
                    <li><strong>Paie validée</strong> → Débit Charges · Crédit Personnel + Retenues</li>
                </ul>
                <hr>
                <p class="mb-2 small">
                    <strong>Pont HIS Finances</strong> :
                    <?php if ($hisSync): ?>
                        <span class="pe-badge pe-badge--active">Actif</span>
                        — les écritures sont répliquées dans <code>finances/</code> (feature <code>payment_finance_sync</code>).
                    <?php else: ?>
                        <span class="pe-badge pe-badge--warning">Inactif</span>
                        — activer « Sync Paiements · Finances » dans Admin plateforme.
                    <?php endif; ?>
                </p>
                <p class="mb-0 small"><strong>Plan SYSCOHADA</strong> pré-chargé : 571, 521, 401, 310, 641, 421, 707, 445…</p>
            </div>
        </div>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
