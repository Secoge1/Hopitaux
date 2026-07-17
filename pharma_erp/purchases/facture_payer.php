<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';
extract(pharma_erp_context());
require_once __DIR__ . '/../../models/pharma_erp/PeSupplierInvoice.php';

$id = (int) ($_GET['id'] ?? 0);
$model = new PeSupplierInvoice();
$invoice = $model->findById($id);
if (!$invoice || !in_array($invoice['status'], ['pending', 'partial'], true)) {
    redirectWithMessage(pharma_erp_url('purchases/factures.php'), 'Facture introuvable ou déjà soldée.', 'warning');
}
$error = '';
$reste = round((float) $invoice['amount_ttc'] - (float) $invoice['amount_paid'], 2);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $amount = (float) str_replace(',', '.', $_POST['amount'] ?? '0');
        $model->recordPayment($id, $amount, trim($_POST['reference'] ?? '') ?: null);
        redirectWithMessage(pharma_erp_url('purchases/factures.php'), 'Paiement enregistré.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start(['active' => 'purchases', 'title' => 'Payer facture', 'icon' => 'fa-money-bill']);
pharma_erp_toolbar([['href' => pharma_erp_url('purchases/factures.php'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline']]);
if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="pharma-pro-panel"><div class="pharma-pro-panel-body">
    <p><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong> — <?= htmlspecialchars($invoice['supplier_name']) ?></p>
    <p>Reste à payer : <strong><?= pharma_erp_format_money($reste) ?></strong></p>
    <form method="post" class="row g-3">
        <div class="col-md-6"><label class="form-label">Montant *</label><input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars((string) $reste) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Référence paiement</label><input type="text" name="reference" class="form-control" placeholder="Chèque, virement…"></div>
        <div class="col-12"><button type="submit" class="btn btn-pharma-primary"><i class="fas fa-check me-1"></i> Enregistrer le paiement</button></div>
    </form>
</div></div>
<?php pharma_erp_page_end(); ?>
