<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';
extract(pharma_erp_context());
require_once __DIR__ . '/../../models/pharma_erp/PeSale.php';
require_once __DIR__ . '/../../models/pharma_erp/PeReturn.php';

$saleId = (int) ($_GET['sale_id'] ?? $_POST['sale_id'] ?? 0);
$saleModel = new PeSale();
$returnModel = new PeReturn();
$sale = $saleId ? $saleModel->getById($saleId) : null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sale) {
    try {
        $lines = [];
        foreach ($_POST['qty'] ?? [] as $lineId => $qty) {
            $qty = (int) $qty;
            if ($qty > 0) {
                $lines[] = ['line_id' => (int) $lineId, 'quantity' => $qty];
            }
        }
        $result = $returnModel->createFromSale($saleId, $lines, trim($_POST['reason'] ?? '') ?: null);
        redirectWithMessage(
            pharma_erp_url('sales/retour_voir.php?id=' . (int) $result['id']),
            'Retour ' . $result['return_number'] . ' enregistré.',
            'success'
        );
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start(['active' => 'sales', 'title' => 'Enregistrer un retour', 'icon' => 'fa-undo']);
pharma_erp_toolbar([
    ['href' => pharma_erp_url('sales/'), 'label' => 'Ventes', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);
if (!$sale): ?>
<div class="alert alert-warning">Vente introuvable. <a href="<?= htmlspecialchars(pharma_erp_url('sales/')) ?>">Retour à la liste</a></div>
<?php else: ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="pharma-pro-panel mb-3"><div class="pharma-pro-panel-body">
    <strong>Vente <?= htmlspecialchars($sale['sale_number']) ?></strong> —
    <?= date('d/m/Y H:i', strtotime($sale['completed_at'])) ?> —
    Total <?= pharma_erp_format_money((float) $sale['total_ttc']) ?>
</div></div>
<form method="post">
<input type="hidden" name="sale_id" value="<?= (int) $saleId ?>">
<div class="pharma-pro-panel"><div class="table-responsive">
<table class="pharma-pro-table">
<thead><tr><th>Produit</th><th>Vendu</th><th>Déjà retourné</th><th>Qté à retourner</th><th class="text-end">P.U.</th></tr></thead>
<tbody>
<?php foreach ($sale['lines'] as $line):
    $sold = (int) $line['quantity'];
    $returned = (int) ($line['returned_quantity'] ?? 0);
    $max = $sold - $returned;
?>
<tr>
    <td><?= htmlspecialchars($line['product_name']) ?></td>
    <td><?= $sold ?></td>
    <td><?= $returned ?></td>
    <td><input type="number" name="qty[<?= (int) $line['id'] ?>]" class="form-control form-control-sm" min="0" max="<?= $max ?>" value="0" style="max-width:100px"></td>
    <td class="text-end"><?= pharma_erp_format_money((float) $line['unit_price']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="pharma-pro-panel-body border-top">
    <div class="mb-3"><label class="form-label">Motif</label><input type="text" name="reason" class="form-control" placeholder="Ex. produit périmé, erreur caisse…"></div>
    <button type="submit" class="btn btn-pharma-primary"><i class="fas fa-check me-1"></i> Valider le retour</button>
</div></div>
</form>
<?php endif; ?>
<?php pharma_erp_page_end(); ?>
