<?php
require_once __DIR__ . '/includes/public_layout.php';
require_once __DIR__ . '/includes/documentation_sections.php';
require_once __DIR__ . '/includes/saas/SubscriptionPlan.php';

public_init();

if (defined('APP_PHARMA_HOST') && APP_PHARMA_HOST) {
    header('Location: ' . public_url('tarifs_pharma.php'));
    exit();
}

public_redirect_if_logged_in();
require_once __DIR__ . '/includes/home_images.php';

$moduleCount = doc_module_count();

public_head('Accueil — ' . platform_name(), 'pub-home', [], [
    'description' => 'Logiciel de gestion clinique SaaS pour hôpitaux et centres de santé. Patients, consultations, laboratoire, paiements Mobile Money. Essai gratuit 15 jours.',
    'keywords' => 'logiciel médical Mali, gestion clinique Afrique, hôpital numérique, patients consultations, laboratoire médical, Mobile Money Orange Wave, SaaS santé',
]);
public_nav('home');
?>

<!-- Hero carousel -->
<div id="heroCarousel" class="carousel slide pub-home-hero" data-bs-ride="carousel" data-bs-interval="5500">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="3" aria-label="Slide 4"></button>
    </div>
    <div class="carousel-inner">
        <div class="carousel-item active pub-hero-slide-1">
            <img src="<?= htmlspecialchars(home_banner_image(1)) ?>"
                 alt="<?= htmlspecialchars(platform_name()) ?> — gestion clinique"
                 onerror="this.onerror=null;this.src='<?= htmlspecialchars(home_banner_fallback(1), ENT_QUOTES) ?>'">
            <div class="carousel-caption">
                <h1><?= htmlspecialchars(platform_name()) ?> — Gestion clinique SaaS</h1>
                <p>Patients, consultations, rendez-vous, laboratoire et facturation — tout en un seul outil pour votre établissement.</p>
                <div class="pub-hero-actions">
                    <a href="<?= public_url('subscribe.php') ?>" class="pub-hero-btn pub-hero-btn-light">
                        <i class="fas fa-rocket"></i> Souscrire
                    </a>
                    <a href="<?= public_url('login.php?demo_try=1') ?>" class="pub-hero-btn pub-hero-btn-outline">
                        <i class="fas fa-play-circle"></i> Essai gratuit 15 jours
                    </a>
                </div>
            </div>
        </div>
        <div class="carousel-item pub-hero-slide-2">
            <img src="<?= htmlspecialchars(home_banner_image(2)) ?>"
                 alt="Consultations et dossiers patients"
                 onerror="this.onerror=null;this.src='<?= htmlspecialchars(home_banner_fallback(2), ENT_QUOTES) ?>'">
            <div class="carousel-caption">
                <h1>Dossiers patients &amp; consultations</h1>
                <p>Suivi médical complet, hospitalisation, pharmacie, PharmaPro ERP (officine) et paiements intégrés pour une clinique efficace.</p>
                <div class="pub-hero-actions">
                    <a href="#services" class="pub-hero-btn pub-hero-btn-light">
                        <i class="fas fa-th-large"></i> Fonctionnalités
                    </a>
                </div>
            </div>
        </div>
        <div class="carousel-item pub-hero-slide-3">
            <img src="<?= htmlspecialchars(home_banner_image(3)) ?>"
                 alt="Tarifs <?= htmlspecialchars(platform_name()) ?>"
                 onerror="this.onerror=null;this.src='<?= htmlspecialchars(home_banner_fallback(3), ENT_QUOTES) ?>'">
            <div class="carousel-caption">
                <h1>Trois formules adaptées</h1>
                <p>Essentiel <?= SubscriptionPlan::formatPrice((int) SubscriptionPlan::get(SubscriptionPlan::STARTER)['price_xof']) ?>/an (5 utilisateurs), Pro <?= SubscriptionPlan::formatPrice((int) SubscriptionPlan::get(SubscriptionPlan::ANNUAL)['price_xof']) ?>/an (15 utilisateurs) ou licence à vie <?= SubscriptionPlan::formatPrice((int) SubscriptionPlan::get(SubscriptionPlan::LIFETIME)['price_xof']) ?> — paiement Mobile Money.</p>
                <div class="pub-hero-actions">
                    <a href="<?= public_url('tarifs.php') ?>" class="pub-hero-btn pub-hero-btn-light">
                        <i class="fas fa-tags"></i> Voir les tarifs
                    </a>
                </div>
            </div>
        </div>
        <div class="carousel-item pub-hero-slide-4">
            <img src="<?= htmlspecialchars(home_banner_image(4)) ?>"
                 alt="Support et activation"
                 onerror="this.onerror=null;this.src='<?= htmlspecialchars(home_banner_fallback(4), ENT_QUOTES) ?>'">
            <div class="carousel-caption">
                <h1>Activation sous 24 h</h1>
                <p>Orange Money ou Wave — votre espace est prêt après confirmation du paiement.</p>
                <div class="pub-hero-actions">
                    <a href="<?= public_url('login.php') ?>" class="pub-hero-btn pub-hero-btn-light">
                        <i class="fas fa-sign-in-alt"></i> Connexion
                    </a>
                </div>
            </div>
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Précédent</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Suivant</span>
    </button>
</div>

<!-- Sync Paiements · Finances -->
<?php public_payment_sync_spotlight(); ?>

<!-- Modules -->
<?php public_modules_showcase(); ?>

<!-- Tarifs -->
<section id="tarifs" class="pub-section pub-section-gray">
    <div class="container">
        <h2 class="pub-section-title">Tarifs &amp; licences</h2>
        <p class="pub-section-sub">Essentiel, Pro ou licence à vie — choisissez la formule adaptée à votre établissement</p>
        <?php public_plan_cards(); ?>
        <p class="text-center mt-4 mb-0">
            <a href="<?= public_url('tarifs.php') ?>" class="text-decoration-none fw-semibold" style="color: var(--pub-primary);">
                Comparer en détail <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </p>
    </div>
</section>

<!-- Statistiques -->
<section class="pub-stats">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-6 col-md-3">
                <div class="pub-stat-num"><?= $moduleCount ?></div>
                <div class="pub-stat-label">Modules intégrés</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pub-stat-num">50</div>
                <div class="pub-stat-label">Utilisateurs max (licence à vie)</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pub-stat-num">24 h</div>
                <div class="pub-stat-label">Activation après paiement</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="pub-stat-num">100%</div>
                <div class="pub-stat-label">Solution en ligne</div>
            </div>
        </div>
    </div>
</section>

<!-- À propos -->
<section id="apropos" class="pub-section pub-section-alt">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-md-6">
                <h2 class="pub-section-title text-start">À propos de <?= htmlspecialchars(platform_name()) ?></h2>
                <p class="text-muted mb-3" style="line-height: 1.75;">
                    <?= htmlspecialchars(platform_name()) ?> est une solution SaaS de gestion clinique et hospitalière conçue pour les centres de santé au Mali et en Afrique de l'Ouest. Chaque établissement abonné dispose de son espace sécurisé avec son propre nom et logo.
                </p>
                <p class="text-muted mb-4" style="line-height: 1.75;">
                    Développée par Secogesarl, la plateforme combine simplicité d'utilisation et fonctionnalités avancées pour moderniser votre pratique médicale.
                </p>
                <ul class="list-unstyled">
                    <?php foreach ([
                        'Équipe médicale et administrative sur un même outil',
                        'Paiement Mobile Money (Orange / Wave)',
                        'Essai gratuit 15 jours sans engagement',
                        'Données hébergées et sécurisées',
                    ] as $point): ?>
                    <li class="mb-2 text-muted">
                        <i class="fas fa-check-circle text-success me-2"></i><?= htmlspecialchars($point) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-md-6">
                <div class="rounded-4 overflow-hidden shadow">
                    <img src="<?= htmlspecialchars(home_about_image()) ?>"
                         alt="À propos de <?= htmlspecialchars(platform_name()) ?>" class="w-100" style="object-fit: cover; min-height: 320px;"
                         onerror="this.onerror=null;this.src='<?= htmlspecialchars(home_about_fallback(), ENT_QUOTES) ?>'">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Témoignages -->
<section class="pub-section pub-section-gray">
    <div class="container">
        <h2 class="pub-section-title">Ils utilisent <?= htmlspecialchars(platform_name()) ?></h2>
        <p class="pub-section-sub">Retours d'expérience de professionnels de santé</p>
        <div class="row g-4">
            <?php
            $testimonials = [
                ['init' => 'AD', 'name' => 'Dr. Amadou Diallo', 'role' => 'Médecin généraliste', 'text' => 'La gestion des patients est simplifiée. L\'interface est intuitive et tout est accessible rapidement.'],
                ['init' => 'FT', 'name' => 'Fatoumata Traoré', 'role' => 'Secrétaire médicale', 'text' => 'Les rendez-vous et consultations sont devenus un jeu d\'enfant. L\'efficacité de notre clinique a nettement augmenté.'],
                ['init' => 'MK', 'name' => 'Moussa Keita', 'role' => 'Pharmacien', 'text' => 'Le module pharmacie est parfaitement intégré. Les alertes de stock évitent les ruptures.'],
            ];
            foreach ($testimonials as $t): ?>
            <div class="col-md-4">
                <div class="pub-testimonial">
                    <div class="pub-testimonial-stars">
                        <?php for ($i = 0; $i < 5; $i++): ?><i class="fas fa-star"></i><?php endfor; ?>
                    </div>
                    <p class="pub-testimonial-text">« <?= htmlspecialchars($t['text']) ?> »</p>
                    <div class="pub-testimonial-author">
                        <div class="pub-avatar"><?= htmlspecialchars($t['init']) ?></div>
                        <div>
                            <h5><?= htmlspecialchars($t['name']) ?></h5>
                            <p><?= htmlspecialchars($t['role']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php
public_footer();
public_scripts();
