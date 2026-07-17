<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';
extract(pharma_erp_context());
require_once __DIR__ . '/../../models/pharma_erp/PeCustomer.php';

$model = new PeCustomer();
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$clients = $model->getAll($page, 25, $search);
$total = $model->getCount($search);

pharma_erp_page_start([
    'active' => 'clients',
    'title' => 'Clients',
    'subtitle' => $total . ' client(s)',
    'icon' => 'fa-users',
]);
pharma_erp_toolbar([
    ['href' => pharma_erp_url('clients/ajouter.php'), 'label' => 'Nouveau client', 'icon' => 'fa-plus', 'class' => 'btn-pharma-primary'],
]);
?>
<div class="pharma-pro-panel mb-3">
    <div class="pharma-pro-panel-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-8">
                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Nom, téléphone, code…">
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-pharma-outline w-100"><i class="fas fa-search"></i></button></div>
        </form>
    </div>
</div>
<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead><tr><th>Code</th><th>Client</th><th>Téléphone</th><th>Email</th><th>Ville</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($clients)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Aucun client</td></tr>
            <?php else: foreach ($clients as $c): ?>
            <tr>
                <td><code><?= htmlspecialchars($c['code']) ?></code></td>
                <td><strong><?= htmlspecialchars(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['company_name'] ?? '—')) ?></strong></td>
                <td><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
                <td><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                <td><?= htmlspecialchars($c['city'] ?? '—') ?></td>
                <td class="text-end"><a href="<?= htmlspecialchars(pharma_erp_url('clients/modifier.php?id=' . (int) $c['id'])) ?>" class="btn btn-sm btn-pharma-outline"><i class="fas fa-edit"></i></a></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php pharma_erp_page_end(); ?>
