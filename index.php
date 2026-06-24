<?php
require_once 'includes/init.php';
require_once 'includes/app_layout.php';
require_once 'includes/app_home_modules.php';

$auth = Auth::getInstance();
if (!$auth->estConnecte()) {
    header('Location: ' . app_url('home.php'));
    exit();
}

$ctx = app_prepare_context();
foreach ($ctx as $key => $value) {
    $GLOBALS[$key] = $value;
}
extract($ctx);

$homeModules = app_home_modules($auth);
$quickActions = app_home_quick_actions($auth);
$dateFr = app_home_date_fr();

app_head('Accueil', ['assets/css/app-home.css'], 'app-home-page');
app_layout_start([
    'active'             => 'home',
    'skip_page_header'   => true,
]);
?>

<section class="home-hero">
    <div class="home-hero-inner">
        <div class="home-hero-greeting">
            <div class="home-hero-eyebrow">
                <i class="fas fa-sun"></i>
                <?= htmlspecialchars($dateFr) ?>
            </div>
            <h1>Bonjour, <?= htmlspecialchars($utilisateur['nom_utilisateur']) ?> 👋</h1>
            <p>
                Bienvenue sur l'espace de gestion de <strong><?= htmlspecialchars(getNomEtablissement()) ?></strong>.
                Accédez à vos modules et suivez l'activité du jour.
            </p>
            <div class="home-hero-meta">
                <span class="home-pill"><i class="fas fa-user-md"></i><?= ucfirst(htmlspecialchars($utilisateur['role'])) ?></span>
                <span class="home-pill"><i class="fas fa-circle text-success" style="font-size:0.45rem"></i><?= ucfirst(htmlspecialchars($utilisateur['statut'] ?? 'actif')) ?></span>
                <?php if ($messagesNonLus > 0): ?>
                <span class="home-pill"><i class="fas fa-envelope"></i><?= $messagesNonLus ?> message<?= $messagesNonLus > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="home-hero-actions">
            <button type="button" class="home-hero-btn home-hero-btn--ghost" onclick="refreshCounters()" title="Actualiser">
                <i class="fas fa-sync-alt"></i>
                <span class="d-none d-sm-inline">Actualiser</span>
            </button>
            <button type="button" class="home-hero-btn home-hero-btn--ghost" data-bs-toggle="collapse" data-bs-target="#notificationsPanel">
                <i class="fas fa-bell"></i>
                <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger rounded-pill"><?= $unreadCount ?></span>
                <?php endif; ?>
            </button>
            <a href="<?= app_url('dashboard.php') ?>" class="home-hero-btn home-hero-btn--solid">
                <i class="fas fa-chart-line"></i>
                Dashboard
            </a>
        </div>
    </div>
</section>

<?php if (!empty($stats)): ?>
<?php app_home_render_kpis($stats); ?>
<?php endif; ?>

<?php if ($messagesNonLus > 0): ?>
<div class="home-alert">
    <i class="fas fa-comments"></i>
    <span>Vous avez <strong><?= $messagesNonLus ?></strong> message<?= $messagesNonLus > 1 ? 's' : '' ?> non lu<?= $messagesNonLus > 1 ? 's' : '' ?>.</span>
    <a href="<?= app_url('communication/') ?>">Consulter</a>
</div>
<?php endif; ?>

<div class="home-layout">
    <div class="home-main">
        <?php app_home_render_medecin_workspace($auth); ?>
        <?php app_home_render_modules_grid($homeModules); ?>
    </div>

    <aside class="home-aside">
        <?php if (!empty($quickActions)): ?>
        <div class="home-panel">
            <div class="home-panel-head"><i class="fas fa-bolt"></i>Actions rapides</div>
            <div class="home-panel-body">
                <div class="home-quick-grid">
                    <?php foreach ($quickActions as $qa): ?>
                    <a href="<?= app_url($qa['href']) ?>" class="home-quick home-quick--<?= htmlspecialchars($qa['tone']) ?>">
                        <i class="fas <?= htmlspecialchars($qa['icon']) ?>"></i>
                        <?= htmlspecialchars($qa['label']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="home-panel">
            <div class="home-panel-head"><i class="fas fa-user-circle"></i>Mon compte</div>
            <div class="home-panel-body">
                <div class="home-user-row">
                    <div class="home-user-avatar"><i class="fas fa-user"></i></div>
                    <div>
                        <strong><?= htmlspecialchars($utilisateur['nom_utilisateur']) ?></strong>
                        <small><?= ucfirst(htmlspecialchars($utilisateur['role'])) ?></small>
                    </div>
                </div>
                <ul class="home-info-list">
                    <li><span>Email</span><span><?= htmlspecialchars($utilisateur['email']) ?></span></li>
                    <li><span>Statut</span><span><?= ucfirst(htmlspecialchars($utilisateur['statut'] ?? 'actif')) ?></span></li>
                    <?php if (!empty($stats['last_updated'])): ?>
                    <li><span>Données</span><span><?= htmlspecialchars($stats['last_updated']) ?></span></li>
                    <?php endif; ?>
                </ul>
                <a href="<?= app_url('parametres/') ?>" class="btn btn-outline-primary btn-sm w-100">
                    <i class="fas fa-cog me-1"></i>Paramètres
                </a>
            </div>
        </div>
    </aside>
</div>

<script>
(function () {
    var input = document.getElementById('homeModuleSearch');
    var grid = document.getElementById('homeModulesGrid');
    var empty = document.getElementById('homeNoResults');
    if (!input || !grid) return;

    function filterModules() {
        var q = input.value.trim().toLowerCase();
        var visible = 0;
        grid.querySelectorAll('.home-mod').forEach(function (el) {
            var match = !q || (el.getAttribute('data-search') || '').indexOf(q) !== -1;
            el.classList.toggle('hidden-by-search', !match);
            if (match) visible++;
        });
        if (!empty) return;
        var showEmpty = visible === 0 && q.length > 0;
        empty.classList.toggle('visible', showEmpty);
        empty.hidden = !showEmpty;
    }

    input.addEventListener('input', filterModules);
    input.addEventListener('search', filterModules);
    window.addEventListener('pageshow', filterModules);
    filterModules();
})();
</script>

<?php
app_layout_end(['stats_mode' => 'live']);
