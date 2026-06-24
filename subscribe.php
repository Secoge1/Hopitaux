<?php
/**
 * Souscription SaaS — abonnement annuel ou licence à vie.
 */
require_once __DIR__ . '/includes/public_layout.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/Auth.php';
require_once __DIR__ . '/includes/saas/SubscriptionPlan.php';
require_once __DIR__ . '/includes/saas/SubscriptionCheckout.php';
require_once __DIR__ . '/includes/saas/SubscriptionService.php';
require_once __DIR__ . '/includes/saas/saas_helpers.php';

public_init();

$planSlug = SubscriptionPlan::normalizeSlug($_GET['plan'] ?? $_POST['plan'] ?? SubscriptionPlan::ANNUAL);
$plan = SubscriptionPlan::get($planSlug);
$amount = SubscriptionCheckout::calculateAmount($planSlug);

$error = '';
$auth = Auth::getInstance();
$isLoggedIn = $auth->estConnecte();
$subSvc = SubscriptionService::getInstance();
$subSvc->loadForSession();
$currentPlan = $isLoggedIn ? $subSvc->getPlanSlug() : '';
$isUpgrade = $isLoggedIn && SubscriptionPlan::planRank($planSlug) > SubscriptionPlan::planRank($currentPlan);

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
        'license_type' => $planSlug,
        'company_name' => trim($_POST['company_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'nom_complet' => trim($_POST['nom_complet'] ?? ''),
        'tenant_id' => $isLoggedIn ? $auth->getTenantId() : null,
        'order_type' => $isUpgrade ? 'upgrade' : ($isLoggedIn && SubscriptionPlan::isAnnual($planSlug) && $planSlug === $currentPlan ? 'renewal' : 'new'),
    ];

    if (!$isLoggedIn) {
        $data['nom_utilisateur'] = trim($_POST['nom_utilisateur'] ?? '');
        $data['password'] = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if ($data['company_name'] === '' || $data['email'] === '') {
            $error = 'Veuillez renseigner le nom de l\'établissement et l\'email.';
        } elseif ($data['nom_utilisateur'] === '' || $data['password'] === '') {
            $error = 'Veuillez choisir un identifiant et un mot de passe administrateur.';
        } elseif ($data['password'] !== $confirm) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($data['password']) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères.';
        }
    } elseif ($data['company_name'] === '' || $data['email'] === '') {
        $error = 'Veuillez renseigner le nom de l\'établissement et l\'email.';
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

$pageTitle = $isUpgrade ? 'Passer à la licence à vie' : 'Souscrire — ' . $plan['name'];
public_head($pageTitle . ' — ' . platform_name(), 'pub-subscribe');
public_nav('tarifs');
public_hero($pageTitle, '', true);
?>

<section class="pub-main">
    <div class="container pub-main-narrow">
        <a href="<?= public_url('tarifs.php') ?>" class="pub-back-link">
            <i class="fas fa-arrow-left"></i> Retour aux tarifs
        </a>

        <div class="pub-card">
            <div class="pub-card-header">
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
            <div class="pub-card-body">
                <div class="pub-alert-info">
                    <strong>Montant :</strong> <?= saas_format_amount($amount) ?>
                    <?php if (SubscriptionPlan::isLifetime($planSlug)): ?>
                    <span class="text-muted">(paiement unique)</span>
                    <?php else: ?>
                    <span class="text-muted">(abonnement 12 mois)</span>
                    <?php endif; ?>
                    <br><span class="text-muted" style="font-size:0.88rem;">
                        Inclus : sync Paiements · Finances · Analyses (activée pour votre établissement après souscription).
                    </span>
                </div>

                <?php if ($error): ?>
                <div class="pub-alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="plan" value="<?= htmlspecialchars($planSlug) ?>">

                    <div class="pub-field">
                        <label>Nom de l'établissement *</label>
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
                    <p class="text-muted small mb-3">Compte administrateur de l'établissement</p>
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
