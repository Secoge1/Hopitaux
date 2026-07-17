<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PePromotion.php';

$model = new PePromotion();
$promotions = $model->getAll();

pharma_erp_page_start([
    'active' => 'promotions',
    'title' => 'Promotions & fidélité',
    'subtitle' => count($promotions) . ' promotion(s)',
    'icon' => 'fa-tags',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('promotions/ajouter.php'), 'label' => 'Nouvelle promotion', 'icon' => 'fa-plus', 'class' => 'btn-pharma-primary'],
    ['href' => pharma_erp_url('pos/'), 'label' => 'Appliquer au POS', 'icon' => 'fa-cash-register', 'class' => 'btn-pharma-outline'],
]);
?>

<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead>
                <tr><th>Code</th><th>Nom</th><th>Remise</th><th>Min. achat</th><th>Période</th><th>Statut</th></tr>
            </thead>
            <tbody>
                <?php if (empty($promotions)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Aucune promotion — créez un code promo pour le POS</td></tr>
                <?php else: foreach ($promotions as $p): ?>
                <tr>
                    <td><code><?= htmlspecialchars($p['code']) ?></code></td>
                    <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                    <td>
                        <?php if ($p['discount_type'] === 'fixed'): ?>
                            <?= pharma_erp_format_money((float) $p['discount_value']) ?>
                        <?php else: ?>
                            <?= (float) $p['discount_value'] ?> %
                        <?php endif; ?>
                    </td>
                    <td><?= pharma_erp_format_money((float) $p['min_amount']) ?></td>
                    <td class="small">
                        <?= !empty($p['starts_at']) ? date('d/m/Y', strtotime($p['starts_at'])) : '—' ?>
                        →
                        <?= !empty($p['ends_at']) ? date('d/m/Y', strtotime($p['ends_at'])) : '—' ?>
                    </td>
                    <td><span class="pe-badge pe-badge--<?= $p['status'] === 'active' ? 'active' : 'warning' ?>"><?= htmlspecialchars($p['status']) ?></span></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="pharma-pro-panel mt-4">
    <div class="pharma-pro-panel-header"><i class="fas fa-star"></i> Fidélité</div>
    <div class="pharma-pro-panel-body small text-muted">
        Au POS, saisissez le téléphone client pour créditer des points (1 point / 1 000 FCFA).
        Les codes promo s'appliquent automatiquement lors de l'encaissement.
    </div>
</div>

<?php pharma_erp_page_end(); ?>
