<?php

require_once __DIR__ . '/../../includes/init.php';

require_once __DIR__ . '/../../includes/pharma_erp/layout.php';



extract(pharma_erp_context());



require_once __DIR__ . '/../../models/pharma_erp/PeSale.php';



$saleModel = new PeSale();

$page = max(1, (int) ($_GET['page'] ?? 1));

$search = trim($_GET['search'] ?? '');

$sales = $saleModel->getAll($page, 25, $search);

$total = $saleModel->getCount($search);



pharma_erp_page_start([

    'active' => 'sales',

    'title' => 'Historique des ventes',

    'subtitle' => $total . ' vente(s) enregistrée(s)',

    'icon' => 'fa-receipt',

]);



pharma_erp_toolbar([
    ['href' => pharma_erp_url('pos/'), 'label' => 'Nouvelle vente', 'icon' => 'fa-cash-register', 'class' => 'btn-pharma-primary'],
    ['href' => pharma_erp_url('sales/retours.php'), 'label' => 'Retours', 'icon' => 'fa-undo', 'class' => 'btn-pharma-outline'],
]);

?>



<div class="pharma-pro-panel mb-3">

    <div class="pharma-pro-panel-body">

        <form method="get" class="row g-2 align-items-end">

            <div class="col-md-6">

                <label class="form-label small">Rechercher</label>

                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="N° ticket ou client…">

            </div>

            <div class="col-md-2">

                <button type="submit" class="btn btn-pharma-outline w-100"><i class="fas fa-search me-1"></i> Filtrer</button>

            </div>

        </form>

    </div>

</div>



<div class="pharma-pro-panel">

    <div class="table-responsive">

        <table class="pharma-pro-table">

            <thead>

                <tr>

                    <th>N° vente</th>

                    <th>Date</th>

                    <th>Client</th>

                    <th>Caisse</th>

                    <th class="text-end">Total TTC</th>

                    <th class="text-end">Marge</th>

                    <th></th>

                </tr>

            </thead>

            <tbody>

                <?php if (empty($sales)): ?>

                <tr><td colspan="7" class="text-center text-muted py-4">Aucune vente</td></tr>

                <?php else: foreach ($sales as $s): ?>

                <tr>

                    <td><code><?= htmlspecialchars($s['sale_number']) ?></code></td>

                    <td><?= date('d/m/Y H:i', strtotime($s['completed_at'])) ?></td>

                    <td><?= htmlspecialchars($s['customer_name'] ?: '—') ?></td>

                    <td><?= htmlspecialchars($s['register_name'] ?? '—') ?></td>

                    <td class="text-end"><strong><?= pharma_erp_format_money((float) $s['total_ttc']) ?></strong></td>

                    <td class="text-end text-success"><?= pharma_erp_format_money((float) $s['profit_amount']) ?></td>

                    <td class="text-end">
                        <a href="<?= htmlspecialchars(pharma_erp_url('sales/ticket.php?id=' . (int) $s['id'])) ?>"
                           class="btn btn-sm btn-pharma-outline" target="_blank" title="Ticket thermique">
                            <i class="fas fa-print"></i>
                        </a>
                        <a href="<?= htmlspecialchars(pharma_erp_url('sales/retour.php?sale_id=' . (int) $s['id'])) ?>"
                           class="btn btn-sm btn-pharma-outline" title="Retour">
                            <i class="fas fa-undo"></i>
                        </a>
                    </td>

                </tr>

                <?php endforeach; endif; ?>

            </tbody>

        </table>

    </div>

</div>



<?php pharma_erp_page_end(); ?>

