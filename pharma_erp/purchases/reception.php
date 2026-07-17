<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PePurchase.php';
require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';

$purchaseModel = new PePurchase();
$pharmacyModel = new PePharmacy();

$orderId = (int) ($_GET['id'] ?? 0);
$order = $purchaseModel->findOrder($orderId);
$error = '';

if (!$order) {
    redirectWithMessage(pharma_erp_url('purchases/'), 'Commande introuvable.', 'warning');
}

$lines = $purchaseModel->getOrderLines($orderId);
$pharmacy = $pharmacyModel->getDefault();
$depositId = $pharmacy ? $pharmacyModel->getDefaultDepositId((int) $pharmacy['id']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$depositId) {
            throw new RuntimeException('Dépôt non configuré.');
        }
        $receiptLines = [];
        foreach ($lines as $line) {
            $lineId = (int) $line['id'];
            $qty = (int) ($_POST['qty_' . $lineId] ?? 0);
            if ($qty > 0) {
                $receiptLines[] = [
                    'line_id' => $lineId,
                    'quantity' => $qty,
                    'lot_number' => trim($_POST['lot_' . $lineId] ?? ''),
                    'expiry_date' => $_POST['expiry_' . $lineId] ?? null,
                ];
            }
        }
        $result = $purchaseModel->receiveGoods(
            $orderId,
            $depositId,
            $receiptLines,
            trim($_POST['invoice_number'] ?? '') ?: null
        );
        redirectWithMessage(
            pharma_erp_url('purchases/'),
            'Réception ' . $result['receipt_number'] . ' validée — écriture comptable générée.',
            'success'
        );
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start([
    'active' => 'purchases',
    'title' => 'Réception marchandise',
    'subtitle' => $order['order_number'] . ' — ' . $order['supplier_name'],
    'icon' => 'fa-truck-loading',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('purchases/'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);

if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="pharma-pro-panel">
    <div class="pharma-pro-panel-body">
        <form method="post">
            <div class="mb-3">
                <label class="form-label">N° facture fournisseur (optionnel)</label>
                <input type="text" name="invoice_number" class="form-control" placeholder="Auto si vide">
            </div>
            <div class="table-responsive">
                <table class="pharma-pro-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Commandé</th>
                            <th>Déjà reçu</th>
                            <th>À recevoir</th>
                            <th>N° lot</th>
                            <th>Péremption</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $line):
                            $remaining = (int) $line['quantity_ordered'] - (int) $line['quantity_received'];
                            if ($remaining <= 0) continue;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($line['product_name']) ?></td>
                            <td><?= (int) $line['quantity_ordered'] ?></td>
                            <td><?= (int) $line['quantity_received'] ?></td>
                            <td><input type="number" name="qty_<?= (int) $line['id'] ?>" class="form-control form-control-sm" min="0" max="<?= $remaining ?>" value="<?= $remaining ?>"></td>
                            <td><input type="text" name="lot_<?= (int) $line['id'] ?>" class="form-control form-control-sm"></td>
                            <td><input type="date" name="expiry_<?= (int) $line['id'] ?>" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+2 years')) ?>"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-pharma-secondary mt-3"><i class="fas fa-check me-1"></i> Valider la réception</button>
        </form>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
