<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_platform_layout.php';
require_once __DIR__ . '/../includes/saas/SubscriptionCheckout.php';
require_once __DIR__ . '/../includes/saas/SubscriptionPlan.php';
require_once __DIR__ . '/../includes/saas/PharmaSubscriptionPlan.php';
require_once __DIR__ . '/../includes/saas/PharmaCommercial.php';
require_once __DIR__ . '/../includes/saas/saas_helpers.php';
require_once __DIR__ . '/../includes/app_platform_actions.php';
require_once __DIR__ . '/_handlers.php';

app_platform_require_admin();
$postResult = admin_platform_handle_post();
extract(app_prepare_platform_context());
extract($postResult);

$checkout = new SubscriptionCheckout();
$pendingOrders = $checkout->listPendingOrders();
$totalPending = array_sum(array_column($pendingOrders, 'amount_xof'));

app_head('Paiements — Admin plateforme', ['assets/css/app-platform.css'], 'app-platform-page');
app_layout_start(['active' => 'platform', 'skip_page_header' => true]);
app_platform_shell_start(
    'payments',
    'Paiements en attente',
    count($pendingOrders) . ' commande(s) · ' . saas_format_amount((int) $totalPending) . ' à valider'
);
echo displayFlashMessages();
app_platform_alert($message, $messageType);
?>

<?php if (!empty($pendingOrders)): ?>
<div class="platform-hero platform-hero--compact">
    <div class="platform-hero-content">
        <h2><?= count($pendingOrders) ?> paiement(s) en attente</h2>
        <p>Vérifiez Mobile Money (<strong><?= htmlspecialchars(saas_get_payment_number()) ?></strong>) avant de confirmer.</p>
    </div>
    <div class="platform-hero-actions">
        <span class="platform-hero-stat"><?= saas_format_amount((int) $totalPending) ?></span>
    </div>
</div>
<?php endif; ?>

<div class="platform-card platform-card--pending">
    <div class="platform-card-head">
        <span><i class="fas fa-money-bill-wave"></i>Commandes à valider</span>
        <?php if (count($pendingOrders)): ?>
        <span class="platform-pill platform-pill--warning"><?= count($pendingOrders) ?> en attente</span>
        <?php endif; ?>
    </div>
    <div class="platform-card-body p-0">
        <?php if (empty($pendingOrders)): ?>
        <div class="platform-empty">
            <i class="fas fa-check-circle text-success"></i>
            <p>Aucun paiement en attente.</p>
            <a href="<?= htmlspecialchars(app_url('admin_platform/index.php')) ?>" class="btn btn-sm btn-outline-primary">Retour au tableau de bord</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table platform-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Établissement</th>
                        <th>Type</th>
                        <th>Licence</th>
                        <th>Montant</th>
                        <th>Date</th>
                        <th class="platform-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pendingOrders as $o): ?>
                <tr>
                    <td><code class="platform-ref"><?= htmlspecialchars($o['ref_command']) ?></code></td>
                    <td>
                        <strong><?= htmlspecialchars($o['company_name']) ?></strong>
                        <br><small class="text-muted"><?= htmlspecialchars($o['email']) ?></small>
                        <?php if (!empty($o['phone'])): ?>
                        <br><small class="text-muted"><i class="fas fa-phone fa-xs"></i> <?= htmlspecialchars($o['phone']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (saas_order_is_pharma($o)): ?>
                        <span class="platform-pill platform-pill--success">PharmaPro</span>
                        <?php endif; ?>
                        <span class="platform-pill platform-pill--muted"><?= htmlspecialchars($o['order_type']) ?></span>
                    </td>
                    <td><?= htmlspecialchars(saas_order_plan($o)['name']) ?></td>
                    <td><strong class="text-success"><?= saas_format_amount((int) $o['amount_xof']) ?></strong></td>
                    <td><small><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></small></td>
                    <td class="platform-col-actions">
                        <?= app_platform_payment_actions($o, false) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="platform-card">
    <div class="platform-card-body platform-info-box">
        <h6><i class="fas fa-info-circle text-primary me-1"></i> Procédure de validation</h6>
        <ol class="mb-0 small text-muted ps-3">
            <li>Vérifiez le virement sur <?= htmlspecialchars(saas_get_payment_methods()) ?> — <?= htmlspecialchars(saas_get_payment_number()) ?></li>
            <li>Recoupez la référence commande avec le message du client</li>
            <li>Cliquez <strong>Confirmer</strong> pour activer ou renouveler la licence automatiquement (PharmaPro ERP activé si commande officine)</li>
        </ol>
    </div>
</div>

<?php
app_platform_shell_end();
app_layout_end(['minimal_scripts' => true]);
