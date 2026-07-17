<?php
/**
 * Page tarifs PharmaPro ERP — officine autonome (parcours commercial type affiche).
 */
require_once __DIR__ . '/includes/public_layout.php';
require_once __DIR__ . '/includes/saas/PharmaSubscriptionPlan.php';
require_once __DIR__ . '/includes/saas/PharmaCommercial.php';

public_init();

$brand = PharmaCommercial::brandName();
$modules = PharmaSubscriptionPlan::getModuleShowcase();
$phones = PharmaCommercial::contactPhones();
$email = PharmaCommercial::contactEmail();
$website = PharmaCommercial::contactWebsite();
$demoUrl = PharmaCommercial::demoLoginUrl();
$qrUrl = PharmaCommercial::qrCodeImageUrl($demoUrl);

public_head($brand . ' — Tarifs & démo', 'pub-pharma-page', ['assets/css/pharma-public.css'], [
    'description' => 'ERP de gestion de pharmacie : POS, stocks, achats, comptabilité SYSCOHADA. Abonnement 70 000 ou 100 000 FCFA/an, licence à vie 350 000 FCFA. Démo gratuite 15 jours.',
    'keywords' => 'logiciel pharmacie, ERP officine, POS pharmacie, gestion stock médicaments, SYSCOHADA pharmacie, Mobile Money, Mali, Afrique',
]);
public_nav('pharma');
?>

<section class="pub-pharma-hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="pub-pharma-eyebrow"><i class="fas fa-prescription-bottle-medical me-1"></i> ERP officine</span>
                <h1><?= htmlspecialchars($brand) ?></h1>
                <p class="pub-pharma-lead"><?= htmlspecialchars(PharmaCommercial::brandTagline()) ?></p>
                <p class="pub-pharma-sub">
                    Interface moderne, gain de temps, moins d'erreurs, meilleure gestion des stocks et rentabilité accrue.
                    Plus de 300 officines font confiance à ce type de solution professionnelle.
                </p>
                <div class="pub-pharma-hero-actions">
                    <a href="<?= htmlspecialchars($demoUrl) ?>" class="pub-pharma-btn-primary btn btn-lg">
                        <i class="fas fa-play-circle me-2"></i>Démonstration gratuite
                    </a>
                    <a href="#offres" class="pub-pharma-btn-outline btn btn-lg">
                        <i class="fas fa-tags me-2"></i>Voir nos offres
                    </a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="pub-pharma-hero-panel">
                    <div class="pub-pharma-panel-title"><i class="fas fa-star me-1"></i> Fonctionnalités clés</div>
                    <ul class="pub-pharma-feature-list">
                        <li><i class="fas fa-cash-register"></i> Caisse POS &amp; scan codes-barres</li>
                        <li><i class="fas fa-boxes-stacked"></i> Stocks, lots &amp; péremption</li>
                        <li><i class="fas fa-calculator"></i> Comptabilité SYSCOHADA intégrée</li>
                        <li><i class="fas fa-users-cog"></i> Multi-utilisateurs &amp; droits</li>
                        <li><i class="fas fa-cloud"></i> Cloud — accessible partout</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="pub-main pub-pharma-modules">
    <div class="container">
        <div class="text-center mb-4">
            <span class="pub-pharma-eyebrow">Modules inclus</span>
            <h2 class="pub-pharma-section-title">Tout pour piloter votre officine</h2>
        </div>
        <div class="pub-pharma-module-grid">
            <?php foreach ($modules as $mod): ?>
            <div class="pub-pharma-module-card">
                <i class="fas <?= htmlspecialchars($mod['icon']) ?>"></i>
                <span><?= htmlspecialchars($mod['label']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="pub-main pub-pharma-offers" id="offres">
    <div class="container">
        <div class="text-center mb-4">
            <span class="pub-pharma-eyebrow">Nos offres</span>
            <h2 class="pub-pharma-section-title">Choisissez la formule adaptée à votre officine</h2>
            <p class="text-muted">Accès complet, mises à jour, sauvegardes et support inclus sur chaque formule.</p>
        </div>
        <?php public_pharma_plan_cards(); ?>
    </div>
</section>

<section class="pub-main">
    <div class="container">
        <?php public_pharma_how_it_works(); ?>
    </div>
</section>

<section class="pub-main pub-pharma-trust">
    <div class="container">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="pub-pharma-trust-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Paiement sécurisé</h3>
                    <p>Vos transactions Mobile Money sont vérifiées manuellement avant activation.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="pub-pharma-trust-card">
                    <i class="fas fa-bolt"></i>
                    <h3>Installation rapide</h3>
                    <p>Mise en place en moins de 24 h après confirmation du paiement.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="pub-pharma-trust-card">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>Formation incluse</h3>
                    <p>Onboarding et suivi personnalisés pour votre équipe officinale.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="pub-main pub-pharma-contact" id="contact-pharma">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="pub-pharma-eyebrow">Contact &amp; démo</span>
                <h2 class="pub-pharma-section-title">Contactez-nous pour une démonstration gratuite&nbsp;!</h2>
                <p class="text-muted mb-4">
                    Essayez <?= htmlspecialchars($brand) ?> pendant 15 jours sans engagement,
                    ou souscrivez directement en ligne.
                </p>
                <ul class="pub-pharma-contact-list">
                    <li>
                        <i class="fas fa-globe"></i>
                        <a href="<?= htmlspecialchars($website) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(parse_url($website, PHP_URL_HOST) ?: $website) ?></a>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a>
                    </li>
                    <?php foreach ($phones as $phone): ?>
                    <li>
                        <i class="fas fa-phone"></i>
                        <a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $phone)) ?>"><?= htmlspecialchars($phone) ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="<?= htmlspecialchars($demoUrl) ?>" class="pub-pharma-btn-primary btn">
                        <i class="fas fa-play-circle me-1"></i> Lancer la démo
                    </a>
                    <a href="<?= htmlspecialchars(PharmaCommercial::subscribeUrl(PharmaSubscriptionPlan::ANNUAL)) ?>" class="pub-pharma-btn-outline btn">
                        <i class="fas fa-rocket me-1"></i> Souscrire maintenant
                    </a>
                </div>
                <p class="small text-muted mt-3 mb-0">
                    Démo : identifiant <code>pharmademo</code> · mot de passe <code>demo123</code>
                </p>
            </div>
            <div class="col-lg-5 text-center">
                <div class="pub-pharma-qr-card">
                    <img src="<?= htmlspecialchars($qrUrl) ?>" width="220" height="220" alt="QR code — démo PharmaPro ERP">
                    <div class="pub-pharma-qr-label">SCANNEZ MOI</div>
                    <p class="small text-muted mb-0">Accès direct à la démo gratuite</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
public_footer();
public_scripts();
