<?php
/**
 * Souscription SaaS — abonnement annuel ou licence à vie (Se.Santé ou PharmaPro).
 */
require_once __DIR__ . '/includes/public_layout.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/Auth.php';
require_once __DIR__ . '/includes/saas/SubscriptionPlan.php';
require_once __DIR__ . '/includes/saas/PharmaSubscriptionPlan.php';
require_once __DIR__ . '/includes/saas/PharmaCommercial.php';
require_once __DIR__ . '/includes/saas/SubscriptionCheckout.php';
require_once __DIR__ . '/includes/saas/SubscriptionService.php';
require_once __DIR__ . '/includes/saas/saas_helpers.php';

public_init();

$productLine = PharmaCommercial::normalizeProductLine(
    $_GET['product'] ?? $_POST['product'] ?? 'clinical'
);
$isPharma = PharmaCommercial::isPharmaProductLine($productLine);

if ($isPharma) {
    $planSlug = PharmaSubscriptionPlan::normalizeSlug($_GET['plan'] ?? $_POST['plan'] ?? PharmaSubscriptionPlan::ANNUAL);
    $plan = PharmaSubscriptionPlan::get($planSlug);
    $amount = SubscriptionCheckout::calculateAmount($planSlug, 'new', $productLine);
} else {
    $planSlug = SubscriptionPlan::normalizeSlug($_GET['plan'] ?? $_POST['plan'] ?? SubscriptionPlan::ANNUAL);
    $plan = SubscriptionPlan::get($planSlug);
    $amount = SubscriptionCheckout::calculateAmount($planSlug, 'new', $productLine);
}

$error = '';
$auth = Auth::getInstance();
$isLoggedIn = $auth->estConnecte();
$subSvc = SubscriptionService::getInstance();
$subSvc->loadForSession();
$currentPlan = $isLoggedIn ? $subSvc->getPlanSlug() : '';
$planRankFn = $isPharma
    ? [PharmaSubscriptionPlan::class, 'planRank']
    : [SubscriptionPlan::class, 'planRank'];
$isUpgrade = $isLoggedIn && $planRankFn($planSlug) > $planRankFn($currentPlan);

$prefill = [];
if ($isLoggedIn) {
    $pdo = getDB();
    $tenantId = $auth->getTenantId();
    $tenant = $pdo->prepare('SELECT * FROM tenants WHERE id = ?');
    $tenant->execute([$tenantId]);
    $tenantRow = $tenant->fetch(PDO::FETCH_ASSOC);
    $user = $auth->getUtilisateur();
    $prefill = [
        'company_name' => $tenantRow['company_name'] ?? '',
        'email' => $user['email'] ?? '',
        'nom_complet' => $user['nom_utilisateur'] ?? '',
        'nom_utilisateur' => $user['nom_utilisateur'] ?? '',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'product_line' => $productLine,
        'license_type' => $planSlug,
        'company_name' => trim($_POST['company_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'nom_complet' => trim($_POST['nom_complet'] ?? ''),
        'tenant_id' => $isLoggedIn ? $auth->getTenantId() : null,
        'order_type' => $isUpgrade ? 'upgrade' : ($isLoggedIn && !$isPharma && SubscriptionPlan::isAnnual($planSlug) && $planSlug === $currentPlan ? 'renewal' : 'new'),
    ];

    if (!$isLoggedIn) {
        $data['nom_utilisateur'] = trim($_POST['nom_utilisateur'] ?? '');
        $data['password'] = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if ($data['company_name'] === '' || $data['email'] === '') {
            $error = $isPharma
                ? 'Veuillez renseigner le nom de la pharmacie et l\'email.'
                : 'Veuillez renseigner le nom de l\'établissement et l\'email.';
        } elseif ($data['nom_utilisateur'] === '' || $data['password'] === '') {
            $error = 'Veuillez choisir un identifiant et un mot de passe administrateur.';
        } elseif ($data['password'] !== $confirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($data['password']) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères.';
        }
    } elseif ($data['company_name'] === '' || $data['email'] === '') {
        $error = $isPharma
            ? 'Veuillez renseigner le nom de la pharmacie et l\'email.'
            : 'Veuillez renseigner le nom de l\'établissement et l\'email.';
    }

    if ($error === '') {
        $checkout = new SubscriptionCheckout();
        $result = $checkout->createOrder($data);
        if ($result['success']) {
            $redirect = $checkout->getMobileMoneyInstructionsUrl($result['order_id']);
            if ($redirect['success']) {
                if (!empty($redirect['ref_command'])) {
                    $_SESSION['saas_pending_order_ref'] = $redirect['ref_command'];
                }
                header('Location: ' . $redirect['redirect_url']);
                exit;
            }
            $error = $redirect['message'] ?? 'Erreur de redirection.';
        } else {
            $error = $result['message'] ?? 'Impossible de créer la commande.';
        }
    }
}

$pageTitle = $isUpgrade
    ? 'Passer à une formule supérieure'
    : ($isPharma ? 'Souscrire — ' . $plan['name'] : 'Souscrire — ' . $plan['name']);
$tarifsBackUrl = $isPharma ? public_url('tarifs_pharma.php') : public_url('tarifs.php');
$isLifetimePlan = $isPharma
    ? PharmaSubscriptionPlan::isLifetime($planSlug)
    : SubscriptionPlan::isLifetime($planSlug);

public_head(
    $pageTitle . ' — ' . ($isPharma ? PharmaCommercial::brandName() : platform_name()),
    $isPharma ? 'pub-subscribe pub-pharma-page' : 'pub-subscribe',
    $isPharma ? ['assets/css/pharma-public.css'] : []
);
public_nav($isPharma ? 'pharma' : 'tarifs');
public_hero($pageTitle, $isPharma ? 'Activation PharmaPro sous 24 h après paiement Mobile Money' : '', true);
?>

<section class="pub-main">
    <div class="container pub-main-narrow">
        <a href="<?= htmlspecialchars($tarifsBackUrl) ?>" class="pub-back-link">
            <i class="fas fa-arrow-left"></i> Retour aux tarifs
        </a>

        <div class="pub-card">
            <div class="pub-card-header">
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                <?php if ($isPharma): ?>
                <p class="text-muted small mb-0"><?= htmlspecialchars(PharmaCommercial::brandName()) ?> — ERP officine autonome</p>
                <?php endif; ?>
            </div>
            <div class="pub-card-body">
                <div class="pub-alert-info">
                    <strong>Montant :</strong> <?= saas_format_amount($amount) ?>
                    <?php if ($isLifetimePlan): ?>
                    <span class="text-muted">(paiement unique)</span>
                    <?php else: ?>
                    <span class="text-muted">(abonnement 12 mois)</span>
                    <?php endif; ?>
                    <br><span class="text-muted" style="font-size:0.88rem;">
                        <?php if ($isPharma): ?>
                        Inclus : POS, stocks, achats, comptabilité SYSCOHADA, mises à jour, sauvegardes et support.
                        PharmaPro ERP est activé automatiquement dès confirmation du paiement.
                        <?php else: ?>
                        Inclus : sync Paiements · Finances · Analyses (activée pour votre établissement après souscription).
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ($error): ?>
                <div class="pub-alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="plan" value="<?= htmlspecialchars($planSlug) ?>">
                    <input type="hidden" name="product" value="<?= htmlspecialchars($productLine) ?>">

                    <div class="pub-field">
                        <label><?= $isPharma ? 'Nom de la pharmacie *' : 'Nom de l\'établissement *' ?></label>
                        <input type="text" name="company_name" required
                               value="<?= htmlspecialchars($_POST['company_name'] ?? $prefill['company_name'] ?? '') ?>">
                    </div>
                    <div class="pub-field">
                        <label>Email *</label>
                        <input type="email" name="email" required
                               value="<?= htmlspecialchars($_POST['email'] ?? $prefill['email'] ?? '') ?>">
                    </div>
                    <div class="pub-field">
                        <label>Téléphone (Mobile Money)</label>
                        <input type="tel" name="phone"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                               placeholder="+223 XX XX XX XX">
                    </div>
                    <div class="pub-field">
                        <label>Nom du responsable</label>
                        <input type="text" name="nom_complet"
                               value="<?= htmlspecialchars($_POST['nom_complet'] ?? $prefill['nom_complet'] ?? '') ?>">
                    </div>

                    <?php if (!$isLoggedIn): ?>
                    <hr class="my-4">
                    <p class="text-muted small mb-3"><?= $isPharma ? 'Compte gérant PharmaPro' : 'Compte administrateur de l\'établissement' ?></p>
                    <div class="pub-field">
                        <label>Identifiant *</label>
                        <input type="text" name="nom_utilisateur" required
                               value="<?= htmlspecialchars($_POST['nom_utilisateur'] ?? '') ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="pub-field">
                                <label>Mot de passe *</label>
                                <input type="password" name="password" required minlength="6">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="pub-field">
                                <label>Confirmer *</label>
                                <input type="password" name="password_confirm" required>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="pub-submit">
                        <i class="fas fa-mobile-alt me-2"></i>Continuer vers le paiement
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php
public_footer();
public_scripts();
