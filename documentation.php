<?php
/**
 * Documentation publique SeSanté — guide complet du système.
 */
require_once __DIR__ . '/includes/public_layout.php';
require_once __DIR__ . '/includes/documentation_sections.php';
require_once __DIR__ . '/includes/saas/SubscriptionPlan.php';
require_once __DIR__ . '/includes/saas/saas_helpers.php';

public_init();

$plans = SubscriptionPlan::getCommercialPlans();
$paymentNumber = saas_get_payment_number();
$year = date('Y');

public_head('Documentation — ' . platform_name(), 'pub-documentation');
public_nav('documentation');
public_hero(
    'Documentation ' . platform_name(),
    'Guide complet — modules, rôles, workflows, tarifs et prise en main'
);
?>

<section class="pub-main pub-doc-main">
    <div class="container">
        <nav class="pub-doc-quickbar" aria-label="Accès rapide">
            <a href="#tarifs" class="pub-doc-quicklink"><i class="fas fa-tags"></i> Tarifs</a>
            <a href="#sync-paiements" class="pub-doc-quicklink"><i class="fas fa-sync-alt"></i> Sync paiements</a>
            <a href="#modules-detail" class="pub-doc-quicklink"><i class="fas fa-th-large"></i> Modules</a>
            <a href="#essai" class="pub-doc-quicklink"><i class="fas fa-play-circle"></i> Essai 15 jours</a>
            <a href="<?= public_url('login.php') ?>" class="pub-doc-quicklink"><i class="fas fa-sign-in-alt"></i> Connexion</a>
            <a href="#support" class="pub-doc-quicklink"><i class="fas fa-life-ring"></i> Support</a>
        </nav>

        <div class="row g-4 g-lg-5">
            <aside class="col-lg-3">
                <nav class="pub-doc-toc sticky-lg-top" aria-label="Sommaire">
                    <h2 class="pub-doc-toc-title">Sommaire</h2>
                    <ul>
                        <li><a href="#presentation">Présentation</a></li>
                        <li><a href="#sync-paiements">Sync Paiements · Finances</a></li>
                        <li><a href="#features-plateforme">Fonctionnalités plateforme</a></li>
                        <li><a href="#modules-detail">Modules détaillés</a></li>
                        <li><a href="#roles">Rôles utilisateurs</a></li>
                        <li><a href="#workflows">Workflows</a></li>
                        <li><a href="#ia-mobile">IA &amp; mobile</a></li>
                        <li><a href="#technique">Fonctionnalités techniques</a></li>
                        <li><a href="#tarifs">Tarifs &amp; licences</a></li>
                        <li><a href="#essai">Essai gratuit</a></li>
                        <li><a href="#connexion">Connexion</a></li>
                        <li><a href="#souscription">Souscription</a></li>
                        <li><a href="#support">Support</a></li>
                    </ul>
                </nav>
            </aside>

            <div class="col-lg-9">

                <article id="presentation" class="pub-doc-section">
                    <span class="pub-doc-label">Vue d'ensemble</span>
                    <h2>Qu'est-ce que <?= htmlspecialchars(platform_name()) ?> ?</h2>
                    <p class="pub-doc-lead">
                        <?= htmlspecialchars(platform_name()) ?> est une solution SaaS de gestion clinique et hospitalière
                        pour les centres de santé en Afrique de l'Ouest, développée par <?= htmlspecialchars(platform_company()) ?>.
                        Elle couvre l'intégralité du cycle de soins : de l'accueil patient à la facturation, en passant
                        par le laboratoire, la pharmacie et la comptabilité.
                    </p>
                    <div class="pub-doc-highlight">
                        <div class="pub-doc-highlight-icon"><i class="fas fa-hospital"></i></div>
                        <div>
                            <p class="mb-1"><strong>Plateforme vs établissement</strong></p>
                            <p class="mb-0 text-muted" style="font-size:0.92rem;line-height:1.65;">
                                <strong><?= htmlspecialchars(platform_name()) ?></strong> est la marque de la plateforme (site public, documentation, login).
                                Une fois connecté, vous accédez à <em>votre</em> espace avec le <strong>nom et logo
                                de votre clinique</strong> configurés dans les paramètres.
                            </p>
                        </div>
                    </div>
                    <ul class="pub-doc-checklist mt-3">
                        <li><i class="fas fa-check-circle"></i> <?= doc_module_count() ?> modules métier intégrés</li>
                        <li><i class="fas fa-check-circle"></i> 8 profils utilisateurs avec droits différenciés</li>
                        <li><i class="fas fa-check-circle"></i> Intelligence artificielle (diagnostic, dermatologie, risque patient)</li>
                        <li><i class="fas fa-check-circle"></i> Application web, mobile PWA et API REST (Flutter)</li>
                        <li><i class="fas fa-check-circle"></i> Synchronisation Paiements · Finances · Analyses (feature live)</li>
                        <li><i class="fas fa-check-circle"></i> Exports PDF, sauvegardes et journaux d'audit</li>
                        <li><i class="fas fa-check-circle"></i> Devise FCFA — paiement Mobile Money</li>
                    </ul>
                </article>

                <?php $syncPublic = doc_payment_sync_public(); ?>
                <article id="sync-paiements" class="pub-doc-section">
                    <span class="pub-doc-label">Nouveau</span>
                    <h2><?= htmlspecialchars($syncPublic['title']) ?></h2>
                    <p class="pub-doc-lead"><?= htmlspecialchars($syncPublic['summary']) ?></p>
                    <div class="pub-sync-spotlight pub-sync-spotlight--doc">
                        <div class="pub-sync-spotlight__inner">
                            <div class="pub-sync-spotlight__icon" aria-hidden="true"><i class="fas fa-star"></i></div>
                            <div class="pub-sync-spotlight__body">
                                <ul class="pub-sync-spotlight__list mb-3">
                                    <?php foreach ($syncPublic['steps'] as $step): ?>
                                    <li><i class="fas fa-check-circle"></i><?= htmlspecialchars($step) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="pub-sync-spotlight__note mb-0">
                                    <i class="fas fa-info-circle me-1"></i><?= htmlspecialchars($syncPublic['activation']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </article>

                <article id="features-plateforme" class="pub-doc-section">
                    <span class="pub-doc-label">Évolution produit</span>
                    <h2>Fonctionnalités activables par établissement</h2>
                    <p class="pub-doc-lead">
                        L'administrateur plateforme peut activer certaines évolutions pour chaque clinique.
                        Seules les fonctionnalités marquées <strong>Disponible</strong> sont opérationnelles aujourd'hui.
                    </p>
                    <div class="pub-doc-detail-grid">
                        <?php foreach (doc_platform_features_catalog() as $feat): ?>
                        <div class="pub-doc-detail-card">
                            <div class="pub-doc-detail-head">
                                <span class="pub-doc-module-icon"><i class="fas fa-puzzle-piece"></i></span>
                                <strong>
                                    <?= htmlspecialchars($feat['label']) ?>
                                    <span class="pub-feature-status pub-feature-status--<?= htmlspecialchars($feat['status']) ?>">
                                        <?= htmlspecialchars($feat['status_label']) ?>
                                    </span>
                                </strong>
                            </div>
                            <p><?= htmlspecialchars($feat['description']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article id="modules-detail" class="pub-doc-section">
                    <span class="pub-doc-label">Fonctionnalités</span>
                    <h2>Modules du système</h2>
                    <p class="pub-doc-lead">
                        Toutes les formules incluent l'ensemble des modules. Voici le détail par domaine métier.
                    </p>
                    <?php foreach (doc_module_groups() as $group): ?>
                    <div class="pub-doc-group">
                        <h3 class="pub-doc-group-title">
                            <i class="fas <?= $group['icon'] ?> me-2"></i><?= htmlspecialchars($group['title']) ?>
                        </h3>
                        <div class="pub-doc-detail-grid">
                            <?php foreach ($group['items'] as $item): ?>
                            <div class="pub-doc-detail-card">
                                <div class="pub-doc-detail-head">
                                    <span class="pub-doc-module-icon"><i class="fas <?= $item['icon'] ?>"></i></span>
                                    <strong>
                                        <?= htmlspecialchars($item['name']) ?>
                                        <?php if (!empty($item['badge'])): ?>
                                        <span class="pub-feature-badge"><?= htmlspecialchars($item['badge']) ?></span>
                                        <?php endif; ?>
                                    </strong>
                                </div>
                                <p><?= htmlspecialchars($item['desc']) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </article>

                <article id="roles" class="pub-doc-section">
                    <span class="pub-doc-label">Sécurité &amp; accès</span>
                    <h2>Rôles utilisateurs</h2>
                    <p class="pub-doc-lead">
                        Chaque membre de l'équipe reçoit un compte avec un rôle adapté. Les menus et actions
                        visibles dépendent du profil assigné par l'administrateur de l'établissement.
                    </p>
                    <div class="table-responsive">
                        <table class="table pub-doc-table">
                            <thead>
                                <tr>
                                    <th>Rôle</th>
                                    <th>Accès principal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (doc_user_roles() as $r): ?>
                                <tr>
                                    <td><i class="fas <?= $r['icon'] ?> me-2 text-primary"></i><strong><?= htmlspecialchars($r['role']) ?></strong></td>
                                    <td><?= htmlspecialchars($r['access']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article id="workflows" class="pub-doc-section">
                    <span class="pub-doc-label">Processus métier</span>
                    <h2>Workflows principaux</h2>
                    <p class="pub-doc-lead">Les parcours types au sein de votre établissement.</p>
                    <div class="row g-3">
                        <?php foreach (doc_workflows() as $wf): ?>
                        <div class="col-md-4">
                            <div class="pub-doc-workflow-card">
                                <h3><i class="fas <?= $wf['icon'] ?> me-2"></i><?= htmlspecialchars($wf['title']) ?></h3>
                                <ol>
                                    <?php foreach ($wf['steps'] as $step): ?>
                                    <li><?= htmlspecialchars($step) ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article id="ia-mobile" class="pub-doc-section">
                    <span class="pub-doc-label">Innovation</span>
                    <h2>Intelligence artificielle &amp; accès mobile</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="pub-doc-detail-card h-100">
                                <div class="pub-doc-detail-head">
                                    <span class="pub-doc-module-icon"><i class="fas fa-robot"></i></span>
                                    <strong>Modules IA</strong>
                                </div>
                                <ul class="pub-doc-inline-list">
                                    <li>Diagnostic assisté par symptômes</li>
                                    <li>Analyse dermatologique (classification lésions)</li>
                                    <li>Évaluation du risque patient</li>
                                    <li>Suggestions prescriptions et rendez-vous</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="pub-doc-detail-card h-100">
                                <div class="pub-doc-detail-head">
                                    <span class="pub-doc-module-icon"><i class="fas fa-mobile-alt"></i></span>
                                    <strong>Mobile &amp; API</strong>
                                </div>
                                <ul class="pub-doc-inline-list">
                                    <li>Interface mobile tactile (<code>mobile/</code>)</li>
                                    <li>PWA installable (manifest + service worker)</li>
                                    <li>Application React (<code>efficasante_web</code>)</li>
                                    <li>Application Flutter Android/iOS</li>
                                    <li>API REST : login, patients, RDV, consultations, labo, tenant/notices</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </article>

                <article id="technique" class="pub-doc-section">
                    <span class="pub-doc-label">Infrastructure</span>
                    <h2>Fonctionnalités techniques</h2>
                    <div class="pub-doc-tech-grid">
                        <?php foreach (doc_tech_features() as $feat): ?>
                        <div class="pub-doc-tech-item">
                            <i class="fas <?= $feat['icon'] ?>"></i>
                            <div>
                                <strong><?= htmlspecialchars($feat['title']) ?></strong>
                                <p><?= htmlspecialchars($feat['text']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="pub-doc-note mt-3 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Tableau de bord avec statistiques temps réel : patients, consultations du jour,
                        rendez-vous, analyses en cours, paiements en attente et notifications.
                    </p>
                </article>

                <article id="tarifs" class="pub-doc-section">
                    <span class="pub-doc-label">Offres</span>
                    <h2>Tarifs &amp; licences</h2>
                    <p class="pub-doc-lead">Trois formules, sans frais cachés. Paiement en FCFA via Mobile Money.</p>
                    <div class="pub-doc-pricing-row">
                        <?php foreach ($plans as $slug => $plan): ?>
                        <div class="pub-doc-price-card<?= !empty($plan['popular']) ? ' is-popular' : '' ?>">
                            <?php if (!empty($plan['popular_badge'])): ?>
                            <span class="pub-doc-price-badge"><?= htmlspecialchars($plan['popular_badge']) ?></span>
                            <?php endif; ?>
                            <h3><?= htmlspecialchars($plan['name']) ?></h3>
                            <div class="pub-doc-price-amount<?= SubscriptionPlan::isAnnual($slug) ? ' pub-doc-price-annual' : ' pub-doc-price-lifetime' ?>">
                                <span class="pub-doc-price-value"><?= SubscriptionPlan::formatPrice((int) $plan['price_xof']) ?></span><?php if (SubscriptionPlan::isAnnual($slug)): ?><span> / an</span><?php else: ?><span class="pub-doc-price-below">— paiement unique</span><?php endif; ?>
                            </div>
                            <p class="pub-doc-price-tagline"><?= htmlspecialchars($plan['tagline']) ?></p>
                            <ul class="pub-doc-price-features">
                                <?php foreach (SubscriptionPlan::getPlanMarketingFeatures($slug) as $feat): ?>
                                <?php if ($feat['ok']): ?>
                                <li><i class="fas fa-check"></i> <?= htmlspecialchars($feat['text']) ?></li>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                            <a href="<?= public_url('subscribe.php?plan=' . urlencode($slug)) ?>"
                               class="pub-btn <?= !empty($plan['popular']) ? 'pub-btn-primary' : 'pub-btn-outline' ?> btn w-100">
                                <?= htmlspecialchars($plan['cta']) ?>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="pub-doc-note mt-3">
                        <i class="fas fa-mobile-alt me-1"></i>
                        Paiement au <strong><?= htmlspecialchars($paymentNumber) ?></strong>
                        — Orange Money ou Wave.
                        <a href="<?= public_url('tarifs.php') ?>">Page tarifs</a>
                    </p>
                </article>

                <article id="essai" class="pub-doc-section">
                    <span class="pub-doc-label">Démo</span>
                    <h2>Essai gratuit 15 jours</h2>
                    <div class="pub-doc-highlight">
                        <div class="pub-doc-highlight-icon"><i class="fas fa-play-circle"></i></div>
                        <div>
                            <p class="mb-2">
                                Testez tous les modules sans engagement. L'environnement de démonstration
                                est préconfiguré avec un compte administrateur (<code>demo</code>).
                            </p>
                            <a href="<?= public_url('login.php?demo_try=1') ?>" class="pub-btn pub-btn-primary btn">
                                <i class="fas fa-rocket me-2"></i>Lancer l'essai gratuit
                            </a>
                        </div>
                    </div>
                </article>

                <article id="connexion" class="pub-doc-section">
                    <span class="pub-doc-label">Accès</span>
                    <h2>Connexion sécurisée</h2>
                    <p class="pub-doc-lead">
                        <a href="<?= public_url('login.php') ?>">login.php</a> est l'entrée <strong>universelle</strong> :
                        identifiant ou email + mot de passe. Session sécurisée (8 h), redirection vers le tableau
                        de bord de votre établissement.
                    </p>
                    <div class="pub-doc-steps-inline">
                        <div class="pub-doc-step-inline"><span>1</span><p>Ouvrez la page Connexion depuis un navigateur récent</p></div>
                        <div class="pub-doc-step-inline"><span>2</span><p>Saisissez identifiant <em>ou</em> email et mot de passe</p></div>
                        <div class="pub-doc-step-inline"><span>3</span><p>Accédez au dashboard avec le branding de votre clinique</p></div>
                    </div>
                    <a href="<?= public_url('login.php') ?>" class="pub-btn pub-btn-outline btn mt-2">
                        <i class="fas fa-lock me-2"></i>Aller à la connexion
                    </a>
                </article>

                <article id="souscription" class="pub-doc-section">
                    <span class="pub-doc-label">Processus</span>
                    <h2>Comment souscrire ?</h2>
                    <?php public_how_it_works(); ?>
                </article>

                <article id="support" class="pub-doc-section pub-doc-section-last">
                    <span class="pub-doc-label">Aide</span>
                    <h2>Support &amp; contact</h2>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="pub-doc-contact-card">
                                <i class="fas fa-phone"></i>
                                <strong>Téléphone</strong>
                                <p>(+223) 94 03 54 56</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="pub-doc-contact-card">
                                <i class="fas fa-envelope"></i>
                                <strong>Email</strong>
                                <p><a href="mailto:contact@secogesarl.com">contact@secogesarl.com</a></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="pub-doc-contact-card">
                                <i class="fas fa-map-marker-alt"></i>
                                <strong>Adresse</strong>
                                <p>Bamako, Mali</p>
                            </div>
                        </div>
                    </div>
                    <p class="pub-doc-renew mt-3 mb-0">
                        Client existant :
                        <a href="<?= public_url('renew.php') ?>">Renouvellement</a> ·
                        <a href="<?= public_url('tarifs.php') ?>">Tarifs & souscription</a>
                    </p>
                </article>
            </div>
        </div>

        <div class="pub-doc-bottom-bar text-center">
            <p class="mb-0">
                <i class="fas fa-lock me-1"></i> Connexion sécurisée
                &nbsp;&mdash;&nbsp;
                <a href="mailto:contact@secogesarl.com">Support</a>
                &nbsp;&middot;&nbsp;
                <a href="<?= public_url('home.php') ?>">Accueil</a>
            </p>
            <p class="pub-doc-copyright">&copy; <?= $year ?> <?= htmlspecialchars(platform_name()) ?> — <?= htmlspecialchars(platform_company()) ?></p>
        </div>
    </div>
</section>

<?php
public_footer();
public_scripts();
