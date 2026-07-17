<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeProduct.php';

$model = new PeProduct();
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$limit = 20;
$products = $model->getAll($page, $limit, $search);
$total = $model->getCount($search);
$totalPages = max(1, (int) ceil($total / $limit));

pharma_erp_page_start([
    'active' => 'products',
    'title' => 'Produits',
    'subtitle' => $total . ' produit(s) en catalogue',
    'icon' => 'fa-capsules',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('products/ajouter.php'), 'label' => 'Nouveau produit', 'icon' => 'fa-plus', 'class' => 'btn-pharma-primary'],
    ['href' => pharma_erp_url('stock/entree.php'), 'label' => 'Entrée stock', 'icon' => 'fa-arrow-down', 'class' => 'btn-pharma-outline'],
]);
?>

<form method="get" class="mb-3">
    <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="Rechercher par nom, SKU, code-barres…" value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-pharma-primary" type="submit"><i class="fas fa-search"></i></button>
    </div>
</form>

<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Produit</th>
                    <th>Prix achat</th>
                    <th>Prix vente</th>
                    <th>Stock</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun produit. <a href="<?= pharma_erp_url('products/ajouter.php') ?>">Ajouter le premier</a></td></tr>
                <?php else: foreach ($products as $p): ?>
                <tr>
                    <td><code><?= htmlspecialchars($p['sku']) ?></code></td>
                    <td>
                        <strong><?= htmlspecialchars($p['name']) ?></strong>
                        <?php if ($p['generic_name']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($p['generic_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= pharma_erp_format_money((float) $p['purchase_price']) ?></td>
                    <td><strong><?= pharma_erp_format_money((float) $p['sale_price']) ?></strong></td>
                    <td>
                        <?php $stock = (int) $p['stock_total']; ?>
                        <span class="pe-badge <?= $stock <= (int) $p['reorder_level'] && $p['reorder_level'] > 0 ? 'pe-badge--danger' : 'pe-badge--active' ?>">
                            <?= $stock ?>
                        </span>
                    </td>
                    <td><span class="pe-badge pe-badge--active"><?= htmlspecialchars($p['status']) ?></span></td>
                    <td class="text-end">
                        <a href="<?= pharma_erp_url('products/modifier.php?id=' . (int) $p['id']) ?>" class="btn btn-sm btn-pharma-outline"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php pharma_erp_page_end(); ?>
