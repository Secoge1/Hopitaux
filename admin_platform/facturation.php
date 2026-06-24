<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_platform_layout.php';
require_once __DIR__ . '/../includes/saas/SubscriptionInvoice.php';
require_once __DIR__ . '/../includes/saas/SubscriptionPlan.php';
require_once __DIR__ . '/../includes/saas/saas_helpers.php';
require_once __DIR__ . '/_handlers.php';

app_platform_require_admin();
$postResult = admin_platform_handle_post();
extract(app_prepare_platform_context());
extract($postResult);

$invoiceSvc = new SubscriptionInvoice();
$search = trim($_GET['q'] ?? '');
$invoices = $invoiceSvc->listInvoices(200, $search !== '' ? $search : null);
$totalInvoices = $invoiceSvc->countInvoices();
$totalAmount = array_sum(array_column($invoices, 'amount_xof'));

// Rétro-génération des factures pour commandes payées sans facture
$backfill = $invoiceSvc->backfillMissing();
if ($backfill > 0 && $message === '') {
    $message = $backfill . ' facture(s) générée(s) pour des paiements antérieurs.';
    $messageType = 'success';
    $invoices = $invoiceSvc->listInvoices(200, $search !== '' ? $search : null);
    $totalInvoices = $invoiceSvc->countInvoices();
    $totalAmount = array_sum(array_column($invoices, 'amount_xof'));
}

app_head('Facturation — Admin plateforme', ['assets/css/app-platform.css'], 'app-platform-page');
app_layout_start(['active' => 'platform', 'skip_page_header' => true]);
app_platform_shell_start(
    'billing',
    'Facturation abonnements',
    $totalInvoices . ' facture(s) émise(s)'
);
echo displayFlashMessages();
app_platform_alert($message, $messageType);
?>

<div class="platform-hero platform-hero--compact">
    <div class="platform-hero-content">
        <h2>Factures d'abonnement</h2>
        <p>Générées automatiquement à chaque confirmation de paiement Mobile Money.</p>
    </div>
    <div class="platform-hero-actions">
        <span class="platform-hero-stat"><?= saas_format_amount((int) $totalAmount) ?></span>
    </div>
</div>

<div class="platform-card">
    <div class="platform-card-head">
        <span><i class="fas fa-file-invoice"></i>Historique des factures</span>
        <form method="get" class="d-flex gap-2 align-items-center">
            <input type="search" name="q" class="form-control form-control-sm" style="min-width:220px"
                   placeholder="N° facture, client, réf…" value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
            <?php if ($search !== ''): ?>
            <a href="<?= htmlspecialchars(app_url('admin_platform/facturation.php')) ?>" class="btn btn-sm btn-outline-secondary">Effacer</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="platform-card-body p-0">
        <?php if (empty($invoices)): ?>
        <div class="platform-empty">
            <i class="fas fa-file-invoice text-muted"></i>
            <p>Aucune facture pour le moment.</p>
            <a href="<?= htmlspecialchars(app_url('admin_platform/payments.php')) ?>" class="btn btn-sm btn-success">
                Valider un paiement
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table platform-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>N° facture</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Réf. commande</th>
                        <th>Licence</th>
                        <th>Montant</th>
                        <th class="platform-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><code class="platform-ref"><?= htmlspecialchars($inv['invoice_number']) ?></code></td>
                    <td><small><?= date('d/m/Y H:i', strtotime($inv['issued_at'])) ?></small></td>
                    <td>
                        <strong><?= htmlspecialchars($inv['buyer_company']) ?></strong>
                        <br><small class="text-muted"><?= htmlspecialchars($inv['buyer_email']) ?></small>
                    </td>
                    <td><small><?= htmlspecialchars($inv['ref_command']) ?></small></td>
                    <td><?= htmlspecialchars(SubscriptionPlan::get($inv['license_type'])['name']) ?></td>
                    <td><strong class="text-success"><?= saas_format_amount((int) $inv['amount_xof']) ?></strong></td>
                    <td class="platform-col-actions">
                        <div class="btn-group btn-group-sm">
                            <a href="<?= htmlspecialchars(app_url('admin_platform/facture_abonnement.php?id=' . (int) $inv['id'])) ?>"
                               class="btn btn-outline-primary" title="Voir">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="<?= htmlspecialchars(app_url('admin_platform/facture_abonnement.php?id=' . (int) $inv['id'] . '&print=1')) ?>"
                               class="btn btn-outline-secondary" title="Imprimer" target="_blank">
                                <i class="fas fa-print"></i>
                            </a>
                            <a href="<?= htmlspecialchars(app_url('admin_platform/facture_abonnement_pdf.php?id=' . (int) $inv['id'])) ?>"
                               class="btn btn-outline-dark" title="PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
app_platform_shell_end();
app_layout_end(['minimal_scripts' => true]);
