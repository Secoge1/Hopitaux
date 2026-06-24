<?php
/**
 * Renouvellement abonnement annuel ou passage licence à vie.
 */
require_once __DIR__ . '/includes/public_layout.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/Auth.php';
require_once __DIR__ . '/includes/saas/SubscriptionPlan.php';
require_once __DIR__ . '/includes/saas/SubscriptionService.php';
require_once __DIR__ . '/includes/saas/saas_helpers.php';

public_init();

$auth = Auth::getInstance();
$subSvc = SubscriptionService::getInstance();
$subSvc->loadForSession();

$tenantRow = $subSvc->getTenantRow();
$planSlug = $subSvc->getPlanSlug();
$plan = SubscriptionPlan::get($planSlug);
$isLifetime = $subSvc->isLifetime();
$check = $subSvc->checkTenantStatus();

$expiresLabel = '—';
if ($tenantRow && !empty($tenantRow['expires_at'])) {
    $expiresLabel = date('d/m/Y', strtotime($tenantRow['expires_at']));
}

public_head('Renouvellement — ' . platform_name(), 'pub-renew');
public_nav('tarifs');
public_hero('Votre licence ' . platform_name(), 'Renouvellement ou passage à la licence à vie', true);
?>

<section class="pub-main">
    <div class="container pub-main-narrow">
        <div class="pub-card">
            <div class="pub-card-body">
                <?php if (!$check['valid']): ?>
                <div class="pub-alert-error mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($check['message']) ?>
                </div>
                <?php endif; ?>

                <?php if ($tenantRow): ?>
                <table class="table table-sm mb-4">
                    <tr><th>Établissement</th><td><?= htmlspecialchars($tenantRow['company_name']) ?></td></tr>
                    <tr><th>Type</th><td><?= htmlspecialchars($plan['name']) ?></td></tr>
                    <?php if (!$isLifetime): ?>
                    <tr><th>Expiration</th><td><?= $expiresLabel ?></td></tr>
                    <?php else: ?>
                    <tr><th>Validité</th><td><span class="badge bg-success">Licence à vie — sans expiration</span></td></tr>
                    <?php endif; ?>
                </table>
                <?php endif; ?>

                <?php if ($isLifetime): ?>
                <div class="pub-alert-info">
                    <i class="fas fa-infinity me-2"></i>
                    Votre licence à vie est active. Aucun renouvellement n'est nécessaire.
                </div>
                <?php if ($auth->estConnecte()): ?>
                <a href="<?= public_url('dashboard.php') ?>" class="pub-btn pub-btn-primary btn mt-3">Retour au tableau de bord</a>
                <?php else: ?>
                <a href="<?= public_url('login.php') ?>" class="pub-btn pub-btn-primary btn mt-3">Se connecter</a>
                <?php endif; ?>

                <?php else: ?>
                <?php
                $renewalPrice = (int) ($plan['renewal_price_xof'] ?? $plan['price_xof']);
                $lifetimePlan = SubscriptionPlan::get(SubscriptionPlan::LIFETIME);
                ?>
                <p class="text-muted mb-4">Renouvelez votre abonnement ou passez à la licence à vie.</p>
                <div class="d-grid gap-2 d-md-flex">
                    <a href="<?= public_url('subscribe.php?plan=' . urlencode($planSlug)) ?>" class="pub-btn pub-btn-primary btn flex-fill">
                        <i class="fas fa-sync me-2"></i>Renouveler — <?= SubscriptionPlan::formatPrice($renewalPrice) ?>/an
                    </a>
                    <a href="<?= public_url('subscribe.php?plan=lifetime') ?>" class="pub-btn pub-btn-outline btn flex-fill">
                        <i class="fas fa-crown me-2"></i>Licence à vie — <?= SubscriptionPlan::formatPrice((int) $lifetimePlan['price_xof']) ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php
public_footer();
public_scripts();
