<?php
/**
 * Instructions de paiement Mobile Money.
 */
require_once __DIR__ . '/includes/public_layout.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/saas/SubscriptionPlan.php';
require_once __DIR__ . '/includes/saas/SubscriptionCheckout.php';
require_once __DIR__ . '/includes/saas/saas_helpers.php';

public_init();

$checkout = new SubscriptionCheckout();
$ref = trim((string) ($_GET['ref'] ?? ''));

if ($ref === '' && isset($_GET['order_id'])) {
    $orderById = $checkout->getOrder((int) $_GET['order_id']);
    if ($orderById && !empty($orderById['ref_command'])) {
        header('Location: ' . saas_payment_instructions_url($orderById['ref_command']));
        exit;
    }
}

if ($ref === '' && !empty($_SESSION['saas_pending_order_ref'])) {
    header('Location: ' . saas_payment_instructions_url((string) $_SESSION['saas_pending_order_ref']));
    exit;
}

$order = null;
$error = '';

if ($ref === '') {
    $error = 'missing_ref';
} else {
    $order = $checkout->getOrderByRef($ref);
    if (!$order) {
        unset($_SESSION['saas_pending_order_ref']);
        $error = 'not_found';
    } else {
        $_SESSION['saas_pending_order_ref'] = $ref;
    }
}

$paymentNumber = saas_get_payment_number();
$paymentMethods = saas_get_payment_methods();
$paymentTel = preg_replace('/\s+/', '', $paymentNumber);
$plan = $order ? SubscriptionPlan::get($order['license_type']) : null;
$amountFmt = $order ? saas_format_amount((int) $order['amount_xof']) : '';
$isPaid = $order && $order['payment_status'] === 'paid';
if ($isPaid) {
    unset($_SESSION['saas_pending_order_ref']);
}
$typeLabel = ($order && SubscriptionPlan::isLifetime($order['license_type'])) ? 'licence à vie' : 'abonnement annuel';

public_head('Instructions de paiement — ' . platform_name(), 'pub-payment');
public_nav('tarifs');
public_hero('Paiement Mobile Money', 'Orange Money ou Wave — activation sous 24 h', true);
?>

<section class="pub-main">
    <div class="container pub-payment-card">
        <?php if ($error): ?>
        <div class="pub-alert-error">
            <?php if ($error === 'missing_ref'): ?>
            Référence de commande manquante. Cette page s'affiche automatiquement après une souscription.
            <?php else: ?>
            Commande introuvable ou référence invalide.
            <?php endif; ?>
        </div>
        <p class="text-muted small mb-3">
            Si vous venez de souscrire, reprenez le parcours depuis les tarifs. Sinon, contactez le support avec votre email de commande.
        </p>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= public_url('tarifs.php') ?>" class="pub-btn pub-btn-primary btn">Voir les tarifs</a>
            <a href="<?= public_url('subscribe.php?plan=annual') ?>" class="pub-btn pub-btn-outline btn">Souscrire</a>
        </div>

        <?php elseif ($isPaid): ?>
        <div class="pub-card text-center p-4">
            <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
            <h1 class="h4 fw-bold">Paiement confirmé</h1>
            <p class="text-muted mb-4">Votre licence <?= htmlspecialchars(platform_name()) ?> est active. Vous pouvez vous connecter.</p>
            <a href="<?= public_url('login.php') ?>" class="pub-btn pub-btn-primary btn">Se connecter</a>
        </div>

        <?php else: ?>
        <a href="<?= public_url('subscribe.php?plan=' . urlencode($order['license_type'])) ?>" class="pub-back-link">
            <i class="fas fa-arrow-left"></i> Modifier la commande
        </a>
        <div class="pub-card">
            <div class="pub-card-body">
                <p class="text-muted small mb-4">Effectuez le virement puis conservez la référence de commande.</p>

                <div class="saas-amount-box text-center mb-4">
                    <div class="text-muted small text-uppercase fw-bold">Montant à payer</div>
                    <div class="saas-amount-display"><?= $amountFmt ?></div>
                    <div class="text-muted"><?= htmlspecialchars($plan['name_full'] ?? '') ?> — <?= $typeLabel ?></div>
                </div>

                <div class="saas-phone-box text-center text-white mb-4">
                    <div class="small opacity-75 text-uppercase"><?= htmlspecialchars($paymentMethods) ?></div>
                    <a href="tel:<?= htmlspecialchars($paymentTel) ?>" class="saas-phone-link"><?= htmlspecialchars($paymentNumber) ?></a>
                </div>

                <ol class="saas-steps">
                    <li>Ouvrez Orange Money ou Wave sur votre téléphone</li>
                    <li>Envoyez <strong><?= $amountFmt ?></strong> au numéro ci-dessus</li>
                    <li>Indiquez en motif : <strong><?= htmlspecialchars($ref) ?></strong></li>
                    <li>Votre espace sera activé sous 24 h après vérification</li>
                </ol>

                <div class="bg-light rounded p-3 mb-3">
                    <small class="text-muted d-block">Référence commande</small>
                    <strong class="user-select-all"><?= htmlspecialchars($ref) ?></strong>
                </div>

                <p class="small text-muted mb-0">
                    Besoin d'aide ? <a href="mailto:contact@secogesarl.com">contact@secogesarl.com</a>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php
public_footer();
public_scripts();
