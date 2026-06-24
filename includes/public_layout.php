<?php
/**
 * Layout partagé — pages publiques SeSanté (home, tarifs, subscribe, etc.)
 */

if (!function_exists('public_init')) {
    function public_init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        require_once __DIR__ . '/header_logo.php';
        if (file_exists(__DIR__ . '/../config/config.php')) {
            require_once __DIR__ . '/../config/config.php';
        }
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', efficasante_web_base_path());
        }
        require_once __DIR__ . '/platform_brand.php';
    }

    function public_redirect_if_logged_in(string $to = 'dashboard.php'): void
    {
        if (isset($_SESSION['user_id'], $_SESSION['user_connected']) && $_SESSION['user_connected'] === true) {
            header('Location: ' . $to);
            exit();
        }
    }

    function public_url(string $path = ''): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        return $base . '/' . ltrim($path, '/');
    }

    function public_head(string $title, string $bodyClass = '', array $extraCss = []): void
    {
        $cssFiles = array_merge(['assets/css/public.css', 'assets/css/subscription.css'], $extraCss);
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(platform_logo_url()) ?>" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php foreach ($cssFiles as $css): ?>
    <link href="<?= htmlspecialchars(public_url($css)) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<body class="public-site <?= htmlspecialchars($bodyClass) ?>">
        <?php
    }

    function public_nav(string $active = ''): void
    {
        $items = [
            'home'      => ['label' => 'Accueil',        'href' => public_url('home.php')],
            'features'  => ['label' => 'Fonctionnalités', 'href' => public_url('home.php#services')],
            'tarifs'    => ['label' => 'Tarifs',         'href' => public_url('tarifs.php')],
            'documentation' => ['label' => 'Documentation', 'href' => public_url('documentation.php')],
            'apropos'   => ['label' => 'À propos',       'href' => public_url('home.php#apropos')],
            'contact'   => ['label' => 'Contact',        'href' => public_url('home.php#contact')],
        ];
        ?>
<nav class="pub-navbar navbar navbar-expand-lg" id="pubNavbar">
    <div class="container">
        <a class="pub-brand navbar-brand" href="<?= public_url('home.php') ?>">
            <?= platform_brand_html('nav') ?>
        </a>
        <button class="navbar-toggler pub-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pubNavMenu" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="pubNavMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <?php foreach ($items as $key => $item): ?>
                <li class="nav-item">
                    <a class="pub-nav-link nav-link<?= $active === $key ? ' active' : '' ?>"
                       href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a>
                </li>
                <?php endforeach; ?>
                <li class="nav-item ms-lg-2">
                    <a href="<?= public_url('login.php?demo_try=1') ?>" class="pub-btn pub-btn-trial btn btn-sm">Essai gratuit</a>
                </li>
                <li class="nav-item ms-lg-1">
                    <a href="<?= public_url('login.php') ?>" class="pub-btn pub-btn-outline btn btn-sm">
                        <i class="fas fa-sign-in-alt me-1"></i> Connexion
                    </a>
                </li>
                <li class="nav-item ms-lg-1">
                    <a href="<?= public_url('subscribe.php') ?>" class="pub-btn pub-btn-primary btn btn-sm">
                        Souscrire
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
        <?php
    }

    function public_hero(string $title, string $subtitle = '', bool $compact = false): void
    {
        $cls = $compact ? 'pub-page-hero pub-page-hero-compact' : 'pub-page-hero';
        ?>
<section class="<?= $cls ?>">
    <div class="container text-center">
        <h1><?= htmlspecialchars($title) ?></h1>
        <?php if ($subtitle !== ''): ?>
        <p class="lead mb-0"><?= htmlspecialchars($subtitle) ?></p>
        <?php endif; ?>
    </div>
</section>
        <?php
    }

    function public_footer(): void
    {
        $year = date('Y');
        ?>
<footer id="contact" class="pub-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="pub-footer-brand">
                    <?= platform_brand_html('footer') ?>
                </div>
                <p class="pub-footer-desc"><?= htmlspecialchars(platform_tagline()) ?> — abonnements annuels ou licence à vie pour les établissements de santé.</p>
            </div>
            <div class="col-md-4">
                <h6>Navigation</h6>
                <ul class="pub-footer-links">
                    <li><a href="<?= public_url('home.php') ?>">Accueil</a></li>
                    <li><a href="<?= public_url('documentation.php') ?>">Documentation</a></li>
                    <li><a href="<?= public_url('tarifs.php') ?>">Tarifs &amp; licences</a></li>
                    <li><a href="<?= public_url('subscribe.php') ?>">Souscrire</a></li>
                    <li><a href="<?= public_url('login.php') ?>">Connexion</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6>Contact</h6>
                <ul class="pub-footer-links">
                    <li><i class="fas fa-phone me-2"></i>(+223) 94 03 54 56</li>
                    <li><a href="mailto:contact@secogesarl.com"><i class="fas fa-envelope me-2"></i>contact@secogesarl.com</a></li>
                    <li><i class="fas fa-map-marker-alt me-2"></i>Bamako, Mali</li>
                </ul>
            </div>
        </div>
        <div class="pub-footer-bottom">
            <p>&copy; <?= $year ?> <?= htmlspecialchars(platform_name()) ?> — <?= htmlspecialchars(platform_company()) ?>. Tous droits réservés.</p>
        </div>
    </div>
</footer>
        <?php
    }

    function public_modules_showcase(bool $showDocLink = true): void
    {
        require_once __DIR__ . '/documentation_sections.php';
        $moduleCount = doc_module_count();
        ?>
<section id="services" class="pub-section">
    <div class="container">
        <h2 class="pub-section-title">Tous les modules</h2>
        <p class="pub-section-sub">
            <?= $moduleCount ?> modules métier intégrés — une solution complète pour votre établissement de santé
        </p>
        <?php foreach (doc_module_groups() as $group): ?>
        <div class="pub-modules-group">
            <h3 class="pub-modules-group-title">
                <i class="fas <?= htmlspecialchars($group['icon']) ?> me-2"></i><?= htmlspecialchars($group['title']) ?>
            </h3>
            <div class="row g-4">
                <?php foreach ($group['items'] as $item): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="pub-service-card pub-module-card">
                        <div class="pub-service-icon"><i class="fas <?= htmlspecialchars($item['icon']) ?>"></i></div>
                        <h4>
                            <?= htmlspecialchars($item['name']) ?>
                            <?php if (!empty($item['badge'])): ?>
                            <span class="pub-feature-badge"><?= htmlspecialchars($item['badge']) ?></span>
                            <?php endif; ?>
                        </h4>
                        <p><?= htmlspecialchars($item['desc']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if ($showDocLink): ?>
        <p class="text-center mt-4 mb-0">
            <a href="<?= public_url('documentation.php#modules-detail') ?>" class="text-decoration-none fw-semibold" style="color: var(--pub-primary);">
                Documentation complète <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </p>
        <?php endif; ?>
    </div>
</section>
        <?php
    }

    function public_how_it_works(): void
    {
        require_once __DIR__ . '/saas/SubscriptionPlan.php';
        require_once __DIR__ . '/saas/saas_helpers.php';

        $starter = SubscriptionPlan::formatPrice((int) SubscriptionPlan::get(SubscriptionPlan::STARTER)['price_xof']);
        $annual = SubscriptionPlan::formatPrice((int) SubscriptionPlan::get(SubscriptionPlan::ANNUAL)['price_xof']);
        $lifetime = SubscriptionPlan::formatPrice((int) SubscriptionPlan::get(SubscriptionPlan::LIFETIME)['price_xof']);
        $payment = saas_get_payment_number();

        $steps = [
            [
                'icon' => 'fa-tags',
                'title' => 'Choisissez votre formule',
                'text' => "Essentiel {$starter}/an (5 utilisateurs), Pro {$annual}/an (15 utilisateurs) ou licence à vie {$lifetime} — sans frais cachés.",
            ],
            [
                'icon' => 'fa-hospital-user',
                'title' => 'Inscrivez votre établissement',
                'text' => 'Nom de la clinique, email, téléphone et compte administrateur en quelques minutes.',
            ],
            [
                'icon' => 'fa-mobile-alt',
                'title' => 'Payez par Mobile Money',
                'text' => "Orange Money ou Wave au {$payment}. Indiquez la référence de commande en motif.",
            ],
            [
                'icon' => 'fa-rocket',
                'title' => 'Activation sous 24 h',
                'text' => 'Votre espace ' . platform_name() . ' est configuré dès confirmation du paiement.',
            ],
        ];
        ?>
<section class="pub-how-section">
    <div class="pub-how-header text-center">
        <span class="pub-how-eyebrow"><i class="fas fa-route me-1"></i> Processus simple</span>
        <h2 class="pub-how-title">Comment ça marche ?</h2>
        <p class="pub-how-sub">De la souscription à l'utilisation de votre clinique en 4 étapes</p>
    </div>

    <div class="pub-how-track">
        <?php foreach ($steps as $i => $step): ?>
        <article class="pub-how-step">
            <div class="pub-how-step-marker">
                <span class="pub-how-step-num"><?= $i + 1 ?></span>
                <?php if ($i < count($steps) - 1): ?>
                <span class="pub-how-step-line" aria-hidden="true"></span>
                <?php endif; ?>
            </div>
            <div class="pub-how-step-card">
                <div class="pub-how-step-icon"><i class="fas <?= $step['icon'] ?>"></i></div>
                <h3 class="pub-how-step-title"><?= htmlspecialchars($step['title']) ?></h3>
                <p class="pub-how-step-text"><?= htmlspecialchars($step['text']) ?></p>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <div class="pub-how-footer text-center">
        <a href="<?= public_url('subscribe.php') ?>" class="pub-btn pub-btn-primary btn btn-lg">
            <i class="fas fa-arrow-right me-2"></i>Commencer maintenant
        </a>
        <p class="pub-how-note mt-3 mb-0">
            <i class="fas fa-shield-alt me-1"></i>
            Essai gratuit 15 jours — <a href="<?= public_url('login.php?demo_try=1') ?>">tester sans engagement</a>
        </p>
    </div>
</section>
        <?php
    }

    /** Bandeau marketing — sync Paiements · Finances · Analyses (page d'accueil). */
    function public_payment_sync_spotlight(): void
    {
        require_once __DIR__ . '/documentation_sections.php';
        $sync = doc_payment_sync_public();
        ?>
<section class="pub-sync-spotlight">
    <div class="container">
        <div class="pub-sync-spotlight__inner">
            <div class="pub-sync-spotlight__icon" aria-hidden="true"><i class="fas fa-star"></i></div>
            <div class="pub-sync-spotlight__body">
                <span class="pub-feature-badge pub-feature-badge--lg">Nouveau</span>
                <h2 class="pub-sync-spotlight__title"><?= htmlspecialchars($sync['title']) ?></h2>
                <p class="pub-sync-spotlight__summary"><?= htmlspecialchars($sync['summary']) ?></p>
                <ul class="pub-sync-spotlight__list">
                    <?php foreach ($sync['steps'] as $step): ?>
                    <li><i class="fas fa-check-circle"></i><?= htmlspecialchars($step) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="pub-sync-spotlight__note mb-0">
                    <i class="fas fa-info-circle me-1"></i><?= htmlspecialchars($sync['activation']) ?>
                </p>
            </div>
            <div class="pub-sync-spotlight__cta">
                <a href="<?= public_url('documentation.php#sync-paiements') ?>" class="pub-btn pub-btn-outline btn">
                    En savoir plus
                </a>
            </div>
        </div>
    </div>
</section>
        <?php
    }

    function public_plan_cards(): void
    {
        require_once __DIR__ . '/saas/SubscriptionPlan.php';
        $plans = SubscriptionPlan::getCommercialPlans();
        ?>
<div class="pub-pricing-grid">
    <?php foreach ($plans as $slug => $plan): ?>
    <div class="card saas-plan-card h-100 <?php echo !empty($plan['popular']) ? 'saas-plan-popular' : ''; ?>">
        <?php if (!empty($plan['popular_badge'])): ?>
        <div class="saas-badge"><?php echo htmlspecialchars($plan['popular_badge']); ?></div>
        <?php endif; ?>
        <div class="card-body p-4 d-flex flex-column">
            <h2 class="h4 fw-bold"><?php echo htmlspecialchars($plan['name']); ?></h2>
            <p class="text-muted small"><?php echo htmlspecialchars($plan['tagline']); ?></p>
            <div class="saas-price my-3<?php echo SubscriptionPlan::isAnnual($slug) ? ' saas-price-annual' : ' saas-price-lifetime'; ?>">
                <span class="saas-price-amount"><?php echo SubscriptionPlan::formatPrice((int) $plan['price_xof']); ?></span><?php if (SubscriptionPlan::isAnnual($slug)): ?><span class="saas-price-period"> / an</span><?php else: ?><span class="saas-price-period saas-price-period-below">— paiement unique</span><?php endif; ?>
            </div>
            <ul class="list-unstyled saas-features flex-grow-1">
                <?php foreach (SubscriptionPlan::getPlanMarketingFeatures($slug) as $feat): ?>
                <li class="<?php echo $feat['ok'] ? 'ok' : 'no'; ?>">
                    <i class="fas fa-<?php echo $feat['ok'] ? 'check-circle text-success' : 'times-circle text-muted'; ?> me-2"></i>
                    <?php echo htmlspecialchars($feat['text']); ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <a href="<?= public_url('subscribe.php?plan=' . urlencode($slug)) ?>"
               class="btn btn-lg <?php echo !empty($plan['popular']) ? 'pub-btn-primary' : 'pub-btn-outline'; ?> w-100 mt-3">
                <?php echo htmlspecialchars($plan['cta']); ?>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<p class="pub-plan-footnote text-center text-muted mt-4 mb-0">
    <i class="fas fa-sync-alt me-1"></i>
    Toutes les formules incluent la synchronisation Paiements · Finances · Analyses
    (activation par établissement via l'administrateur plateforme).
</p>
        <?php
    }

    function public_scripts(): void
    {
        ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const nav = document.getElementById('pubNavbar');
    if (nav) {
        window.addEventListener('scroll', function () {
            nav.classList.toggle('scrolled', window.scrollY > 40);
        });
    }
    document.querySelectorAll('a[href*="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (!href || href.indexOf('#') === -1) return;
            const hash = href.substring(href.indexOf('#'));
            if (hash.length <= 1) return;
            const target = document.querySelector(hash);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
})();
</script>
</body>
</html>
        <?php
    }
}
