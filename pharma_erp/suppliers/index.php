<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeSupplier.php';

$model = new PeSupplier();
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$suppliers = $model->getAll($page, 20, $search);
$total = $model->getCount($search);

pharma_erp_page_start([
    'active' => 'suppliers',
    'title' => 'Fournisseurs',
    'subtitle' => $total . ' fournisseur(s)',
    'icon' => 'fa-truck',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('suppliers/ajouter.php'), 'label' => 'Nouveau fournisseur', 'icon' => 'fa-plus', 'class' => 'btn-pharma-primary'],
]);
?>

<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead>
                <tr><th>Code</th><th>Raison sociale</th><th>Contact</th><th>Téléphone</th><th>Délai paiement</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun fournisseur</td></tr>
                <?php else: foreach ($suppliers as $s): ?>
                <tr>
                    <td><code><?= htmlspecialchars($s['code']) ?></code></td>
                    <td><strong><?= htmlspecialchars($s['company_name']) ?></strong></td>
                    <td><?= htmlspecialchars($s['contact_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
                    <td><?= (int) $s['payment_terms_days'] ?> jours</td>
                    <td><span class="pe-badge pe-badge--active"><?= htmlspecialchars($s['status']) ?></span></td>
                    <td class="text-end">
                        <a href="<?= htmlspecialchars(pharma_erp_url('suppliers/modifier.php?id=' . (int) $s['id'])) ?>" class="btn btn-sm btn-pharma-outline"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
