<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_platform_layout.php';
require_once __DIR__ . '/../includes/saas/PlatformAdminStats.php';
require_once __DIR__ . '/../includes/saas/SubscriptionInvoice.php';
require_once __DIR__ . '/../includes/saas/SubscriptionPlan.php';
require_once __DIR__ . '/../includes/platform_brand.php';
require_once __DIR__ . '/../includes/saas/saas_helpers.php';
require_once __DIR__ . '/../includes/app_platform_actions.php';
require_once __DIR__ . '/_handlers.php';

app_platform_require_admin();
$postResult = admin_platform_handle_post();
extract(app_prepare_platform_context());
extract($postResult);

$statsService = new PlatformAdminStats();
$stats = $statsService->getDashboardStats();
$recentPending = $statsService->getRecentPendingOrders(8);
$expiring = $statsService->getExpiringTenants(8);
$recentPaid = $statsService->getRecentPaidOrders(8);
$invoiceSvc = new SubscriptionInvoice();
$invoiceByOrder = [];
foreach ($recentPaid as $o) {
    $inv = $invoiceSvc->getByOrderId((int) $o['id']);
    if ($inv) {
        $invoiceByOrder[(int) $o['id']] = $inv;
    }
}

$headerActions = '
    <a href="' . htmlspecialchars(app_url('admin_platform/payments.php')) . '" class="btn btn-success btn-sm">'
    . '<i class="fas fa-check-circle me-1"></i>Traiter les paiements'
    . ($stats['pending_count'] > 0 ? ' <span class="badge bg-white text-success ms-1">' . (int) $stats['pending_count'] . '</span>' : '')
    . '</a>
    <a href="' . htmlspecialchars(app_url('admin_platform/tenants.php')) . '" class="btn btn-outline-primary btn-sm">'
    . '<i class="fas fa-building me-1"></i>Établissements</a>';

app_head('Admin plateforme', ['assets/css/app-platform.css'], 'app-platform-page');
app_layout_start(['active' => 'platform', 'skip_page_header' => true]);
app_platform_shell_start(
    'dashboard',
    'Tableau de bord vendeur',
    'Vue d\'ensemble des abonnements, revenus et établissements ' . platform_name(),
    $headerActions
);
app_platform_alert($message, $messageType);
?>

<div class="platform-hero">
    <div class="platform-hero-content">
        <h2>Gestion des abonnés <?= htmlspecialchars(platform_name()) ?></h2>
        <p>
            <?= (int) $stats['tenants_active'] ?> établissement(s) actif(s) ·
            <?= (int) $stats['pending_count'] ?> paiement(s) à valider ·
            <?= saas_format_amount((int) $stats['pending_amount']) ?> en attente
        </p>
    </div>
    <div class="platform-hero-actions">
        <a href="<?= htmlspecialchars(app_url('admin_platform/payments.php')) ?>" class="platform-hero-btn platform-hero-btn--primary">
            <i class="fas fa-money-bill-wave"></i>
            <span>Valider paiements<?php if ($stats['pending_count'] > 0): ?> (<?= (int) $stats['pending_count'] ?>)<?php endif; ?></span>
        </a>
        <a href="<?= htmlspecialchars(app_url('admin_platform/tenants.php')) ?>" class="platform-hero-btn">
            <i class="fas fa-building"></i>
            <span>Gérer établissements</span>
        </a>
        <a href="<?= htmlspecialchars(app_url('tarifs.php')) ?>" class="platform-hero-btn" target="_blank" rel="noopener">
            <i class="fas fa-tags"></i>
            <span>Page tarifs</span>
        </a>
    </div>
</div>

<div class="platform-kpi-grid">
    <a href="<?= htmlspecialchars(app_url('admin_platform/tenants.php')) ?>" class="platform-kpi platform-kpi--link">
        <div class="platform-kpi-icon platform-kpi-icon--blue"><i class="fas fa-building"></i></div>
        <div class="platform-kpi-val"><?= (int) $stats['tenants_total'] ?></div>
        <div class="platform-kpi-label">Établissements</div>
        <div class="platform-kpi-sub"><?= (int) $stats['tenants_active'] ?> actifs</div>
    </a>
    <div class="platform-kpi">
        <div class="platform-kpi-icon platform-kpi-icon--green"><i class="fas fa-coins"></i></div>
        <div class="platform-kpi-val"><?= saas_format_amount((int) $stats['revenue_month']) ?></div>
        <div class="platform-kpi-label">Revenus ce mois</div>
        <div class="platform-kpi-sub"><?= (int) $stats['paid_orders'] ?> commandes payées</div>
    </div>
    <div class="platform-kpi">
        <div class="platform-kpi-icon platform-kpi-icon--purple"><i class="fas fa-chart-line"></i></div>
        <div class="platform-kpi-val"><?= saas_format_amount((int) $stats['revenue_total']) ?></div>
        <div class="platform-kpi-label">Revenus cumulés</div>
    </div>
    <a href="<?= htmlspecialchars(app_url('admin_platform/payments.php')) ?>" class="platform-kpi platform-kpi--link platform-kpi--alert">
        <div class="platform-kpi-icon platform-kpi-icon--amber"><i class="fas fa-clock"></i></div>
        <div class="platform-kpi-val"><?= (int) $stats['pending_count'] ?></div>
        <div class="platform-kpi-label">Paiements en attente</div>
        <div class="platform-kpi-sub"><?= saas_format_amount((int) $stats['pending_amount']) ?></div>
    </a>
    <a href="<?= htmlspecialchars(app_url('admin_platform/tenants.php')) ?>" class="platform-kpi platform-kpi--link">
        <div class="platform-kpi-icon platform-kpi-icon--teal"><i class="fas fa-calendar-times"></i></div>
        <div class="platform-kpi-val"><?= (int) $stats['tenants_expiring_soon'] ?></div>
        <div class="platform-kpi-label">Expirent sous 30 j</div>
    </a>
    <div class="platform-kpi">
        <div class="platform-kpi-icon platform-kpi-icon--blue"><i class="fas fa-infinity"></i></div>
        <div class="platform-kpi-val"><?= (int) $stats['tenants_lifetime'] ?> / <?= (int) $stats['tenants_annual'] ?></div>
        <div class="platform-kpi-label">À vie / Annuels</div>
    </div>
    <a href="<?= htmlspecialchars(app_url('admin_platform/facturation.php')) ?>" class="platform-kpi platform-kpi--link">
        <div class="platform-kpi-icon platform-kpi-icon--purple"><i class="fas fa-file-invoice"></i></div>
        <div class="platform-kpi-val"><?= (int) ($stats['invoices_count'] ?? 0) ?></div>
        <div class="platform-kpi-label">Factures émises</div>
    </a>
</div>

<div class="platform-grid-2">
    <div class="platform-card platform-card--pending">
        <div class="platform-card-head">
            <span><i class="fas fa-hourglass-half"></i>Paiements en attente</span>
            <div class="platform-card-head-actions">
                <?php if ($stats['pending_count'] > 0): ?>
                <span class="platform-pill platform-pill--warning"><?= (int) $stats['pending_count'] ?> à traiter</span>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(app_url('admin_platform/payments.php')) ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-list me-1"></i>Tout voir
                </a>
            </div>
        </div>
        <div class="platform-card-body p-0">
            <?php if (empty($recentPending)): ?>
            <div class="platform-empty">
                <i class="fas fa-check-circle text-success"></i>
                <p>Aucun paiement en attente.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table platform-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Réf.</th>
                            <th>Établissement</th>
                            <th>Montant</th>
                            <th class="platform-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentPending as $o): ?>
                    <tr>
                        <td><code class="platform-ref"><?= htmlspecialchars($o['ref_command']) ?></code></td>
                        <td>
                            <strong><?= htmlspecialchars($o['company_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($o['email']) ?></small>
                        </td>
                        <td><strong class="text-success"><?= saas_format_amount((int) $o['amount_xof']) ?></strong></td>
                        <td class="platform-col-actions">
                            <?= app_platform_payment_actions($o, true) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="platform-card platform-card--expiring">
        <div class="platform-card-head">
            <span><i class="fas fa-exclamation-triangle"></i>Expirations proches</span>
            <a href="<?= htmlspecialchars(app_url('admin_platform/tenants.php')) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-building me-1"></i>Établissements
            </a>
        </div>
        <div class="platform-card-body p-0">
            <?php if (empty($expiring)): ?>
            <div class="platform-empty">
                <i class="fas fa-calendar-check text-success"></i>
                <p>Aucune expiration dans les 30 prochains jours.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table platform-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Établissement</th>
                            <th>Expiration</th>
                            <th>Users</th>
                            <th class="platform-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($expiring as $t): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['company_name']) ?></strong></td>
                        <td><span class="platform-pill platform-pill--warning"><?= date('d/m/Y', strtotime($t['expires_at'])) ?></span></td>
                        <td><?= (int) ($t['users_count'] ?? 0) ?>/<?= (int) $t['max_users'] ?></td>
                        <td class="platform-col-actions">
                            <?= app_platform_tenant_actions($t, true) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="platform-card">
    <div class="platform-card-head">
        <span><i class="fas fa-receipt"></i>Derniers paiements confirmés</span>
        <div class="platform-card-head-actions">
            <a href="<?= htmlspecialchars(app_url('admin_platform/facturation.php')) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-file-invoice me-1"></i>Facturation
            </a>
            <a href="<?= htmlspecialchars(app_url('admin_platform/payments.php')) ?>" class="btn btn-sm btn-outline-primary">Historique</a>
        </div>
    </div>
    <div class="platform-card-body p-0">
        <?php if (empty($recentPaid)): ?>
        <div class="platform-empty">
            <i class="fas fa-inbox text-muted"></i>
            <p>Aucun paiement enregistré pour le moment.</p>
            <a href="<?= htmlspecialchars(app_url('admin_platform/payments.php')) ?>" class="btn btn-sm btn-success">
                Traiter les paiements en attente
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table platform-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Réf.</th>
                        <th>Établissement</th>
                        <th>Licence</th>
                        <th>Montant</th>
                        <th class="platform-col-actions">Facture</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentPaid as $o): ?>
                <?php $inv = $invoiceByOrder[(int) $o['id']] ?? null; ?>
                <tr>
                    <td><small><?= $o['paid_at'] ? date('d/m/Y H:i', strtotime($o['paid_at'])) : '—' ?></small></td>
                    <td><code class="platform-ref"><?= htmlspecialchars($o['ref_command']) ?></code></td>
                    <td><?= htmlspecialchars($o['company_name']) ?></td>
                    <td><?= htmlspecialchars(SubscriptionPlan::get($o['license_type'])['name']) ?></td>
                    <td><strong><?= saas_format_amount((int) $o['amount_xof']) ?></strong></td>
                    <td class="platform-col-actions">
                        <?php if ($inv): ?>
                        <a href="<?= htmlspecialchars(app_url('admin_platform/facture_abonnement.php?id=' . (int) $inv['id'])) ?>"
                           class="btn btn-sm btn-outline-primary" title="Voir facture">
                            <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($inv['invoice_number']) ?>
                        </a>
                        <?php else: ?>
                        <span class="text-muted small">—</span>
                        <?php endif; ?>
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
