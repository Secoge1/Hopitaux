<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_platform_layout.php';
require_once __DIR__ . '/../includes/saas/PlatformTenantFeatures.php';
require_once __DIR__ . '/../includes/saas/saas_helpers.php';
require_once __DIR__ . '/_handlers.php';

app_platform_require_admin();
$postResult = admin_platform_handle_post();
extract(app_prepare_platform_context());
extract($postResult);

$selectedKey = $_GET['feature'] ?? PlatformTenantFeatures::PAYMENT_FINANCE_SYNC;
$catalog = PlatformTenantFeatures::catalog();
if (!isset($catalog[$selectedKey])) {
    $selectedKey = PlatformTenantFeatures::PAYMENT_FINANCE_SYNC;
}

$featureMeta = $catalog[$selectedKey];
$tenantsFeatures = PlatformTenantFeatures::listTenantsStatus($selectedKey);
$enabledCount = PlatformTenantFeatures::countEnabled($selectedKey);
$totalTenants = count($tenantsFeatures);
$disabledCount = max(0, $totalTenants - $enabledCount);

$statusLabels = [
    'live' => ['label' => 'Opérationnelle', 'class' => 'platform-pill--success', 'icon' => 'fa-check-circle'],
    'beta' => ['label' => 'Bêta partielle', 'class' => 'platform-pill--warning', 'icon' => 'fa-flask'],
    'planned' => ['label' => 'Planifiée', 'class' => 'platform-pill--muted', 'icon' => 'fa-clock'],
];

$statusNotices = [
    'live' => [
        'class' => 'platform-feature-notice--live',
        'icon' => 'fa-check-circle',
        'title' => 'Fonctionnalité opérationnelle',
        'text' => "L'activation a un effet immédiat pour l'établissement concerné.",
    ],
    'beta' => [
        'class' => 'platform-feature-notice--beta',
        'icon' => 'fa-flask',
        'title' => 'Fonctionnalité en bêta',
        'text' => 'Partiellement disponible — activation progressive recommandée.',
    ],
    'planned' => [
        'class' => 'platform-feature-notice--planned',
        'icon' => 'fa-info-circle',
        'title' => 'Fonctionnalité planifiée',
        'text' => "L'activation prépare le déploiement ; l'effet métier complet arrive ultérieurement.",
    ],
];

$catalogStats = ['live' => 0, 'beta' => 0, 'planned' => 0];
foreach ($catalog as $item) {
    $st = $item['status'] ?? 'planned';
    if (isset($catalogStats[$st])) {
        $catalogStats[$st]++;
    }
}

$st = $statusLabels[$featureMeta['status']] ?? $statusLabels['planned'];
$notice = $statusNotices[$featureMeta['status']] ?? $statusNotices['planned'];

$headerActions = '<span class="platform-pill platform-pill--success">'
    . (int) $enabledCount . ' activé(s)</span>'
    . '<span class="platform-pill platform-pill--muted">' . (int) $totalTenants . ' établissement(s)</span>';

app_head('Fonctionnalités — Admin plateforme', ['assets/css/app-platform.css'], 'app-platform-page');
app_layout_start(['active' => 'platform', 'skip_page_header' => true]);
app_platform_shell_start(
    'features',
    'Déploiement des fonctionnalités',
    'Activez les évolutions métier établissement par établissement — rien ne s\'applique sans activation ici',
    $headerActions
);
echo displayFlashMessages();
app_platform_alert($message, $messageType);
?>

<div class="platform-kpi-grid platform-features-kpis">
    <div class="platform-kpi">
        <div class="platform-kpi-icon platform-kpi-icon--blue"><i class="fas fa-layer-group"></i></div>
        <div class="platform-kpi-val"><?= count($catalog) ?></div>
        <div class="platform-kpi-label">Fonctionnalités catalogue</div>
        <div class="platform-kpi-sub"><?= (int) $catalogStats['live'] ?> live · <?= (int) $catalogStats['beta'] ?> bêta</div>
    </div>
    <div class="platform-kpi platform-kpi--alert">
        <div class="platform-kpi-icon platform-kpi-icon--green"><i class="fas fa-toggle-on"></i></div>
        <div class="platform-kpi-val"><?= (int) $enabledCount ?></div>
        <div class="platform-kpi-label">Activées (sélection)</div>
        <div class="platform-kpi-sub"><?= htmlspecialchars($featureMeta['label']) ?></div>
    </div>
    <div class="platform-kpi">
        <div class="platform-kpi-icon platform-kpi-icon--amber"><i class="fas fa-building"></i></div>
        <div class="platform-kpi-val"><?= (int) $totalTenants ?></div>
        <div class="platform-kpi-label">Établissements</div>
        <div class="platform-kpi-sub"><?= (int) $disabledCount ?> sans activation</div>
    </div>
    <div class="platform-kpi">
        <div class="platform-kpi-icon platform-kpi-icon--purple"><i class="fas <?= htmlspecialchars($st['icon']) ?>"></i></div>
        <div class="platform-kpi-val" style="font-size:1rem;"><?= htmlspecialchars($st['label']) ?></div>
        <div class="platform-kpi-label">Statut technique</div>
        <div class="platform-kpi-sub"><code class="platform-ref"><?= htmlspecialchars($selectedKey) ?></code></div>
    </div>
</div>

<div class="platform-features-layout">
    <aside class="platform-features-catalog" aria-label="Catalogue des fonctionnalités">
        <div class="platform-card h-100 mb-0">
            <div class="platform-card-head">
                <span><i class="fas fa-list"></i>Catalogue</span>
                <span class="platform-pill"><?= count($catalog) ?></span>
            </div>
            <nav class="platform-feature-nav">
                <?php foreach ($catalog as $key => $item):
                    $itemSt = $statusLabels[$item['status']] ?? $statusLabels['planned'];
                    $isActive = $key === $selectedKey;
                    $count = PlatformTenantFeatures::countEnabled($key);
                ?>
                <a href="?feature=<?= urlencode($key) ?>"
                   class="platform-feature-nav-item<?= $isActive ? ' is-active' : '' ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <span class="platform-feature-nav-icon" aria-hidden="true">
                        <i class="fas <?= htmlspecialchars($itemSt['icon']) ?>"></i>
                    </span>
                    <span class="platform-feature-nav-body">
                        <span class="platform-feature-nav-title"><?= htmlspecialchars($item['label']) ?></span>
                        <code class="platform-ref"><?= htmlspecialchars($key) ?></code>
                    </span>
                    <span class="platform-feature-nav-meta">
                        <span class="platform-pill <?= htmlspecialchars($itemSt['class']) ?>"><?= htmlspecialchars($itemSt['label']) ?></span>
                        <?php if ($count > 0): ?>
                        <span class="platform-feature-nav-count"><?= (int) $count ?> actif(s)</span>
                        <?php endif; ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </aside>

    <div class="platform-features-main">
        <div class="platform-hero platform-hero--compact platform-features-hero">
            <div class="platform-hero-content">
                <p class="platform-page-eyebrow mb-1" style="color:rgba(255,255,255,.75);">Fonctionnalité sélectionnée</p>
                <h2><?= htmlspecialchars($featureMeta['label']) ?></h2>
                <p><?= htmlspecialchars($featureMeta['description']) ?></p>
            </div>
            <div class="platform-hero-actions">
                <form method="POST" class="d-inline" onsubmit="return confirm('Activer pour TOUS les établissements ?');">
                    <input type="hidden" name="feature_key" value="<?= htmlspecialchars($selectedKey) ?>">
                    <input type="hidden" name="enabled" value="1">
                    <button type="submit" name="toggle_all_tenant_feature" class="platform-hero-btn platform-hero-btn--primary">
                        <i class="fas fa-check-double"></i>
                        <span>Activer tous</span>
                    </button>
                </form>
                <form method="POST" class="d-inline" onsubmit="return confirm('Désactiver pour TOUS les établissements ?');">
                    <input type="hidden" name="feature_key" value="<?= htmlspecialchars($selectedKey) ?>">
                    <input type="hidden" name="enabled" value="0">
                    <button type="submit" name="toggle_all_tenant_feature" class="platform-hero-btn">
                        <i class="fas fa-ban"></i>
                        <span>Désactiver tous</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="platform-feature-notice <?= htmlspecialchars($notice['class']) ?>">
            <i class="fas <?= htmlspecialchars($notice['icon']) ?> platform-feature-notice__icon" aria-hidden="true"></i>
            <div>
                <strong><?= htmlspecialchars($notice['title']) ?></strong>
                <p><?= htmlspecialchars($notice['text']) ?></p>
            </div>
            <span class="platform-pill <?= htmlspecialchars($st['class']) ?>"><?= htmlspecialchars($st['label']) ?></span>
        </div>

        <div class="platform-toolbar">
            <div class="platform-toolbar-search">
                <i class="fas fa-search"></i>
                <input type="search" id="featureTenantSearch" class="form-control form-control-sm"
                       placeholder="Rechercher un établissement, une clé…" autocomplete="off">
            </div>
            <span class="platform-pill platform-pill--success"><?= (int) $enabledCount ?> activé(s)</span>
            <span class="platform-pill platform-pill--muted"><?= (int) $disabledCount ?> désactivé(s)</span>
        </div>

        <div class="platform-card mb-0">
            <div class="platform-card-head">
                <span><i class="fas fa-building"></i>Établissements</span>
                <span class="platform-pill"><?= (int) $totalTenants ?> total</span>
            </div>
            <div class="platform-card-body p-0">
                <?php if (empty($tenantsFeatures)): ?>
                <div class="platform-empty">
                    <i class="fas fa-building text-muted"></i>
                    <p>Aucun établissement enregistré.</p>
                    <a href="<?= htmlspecialchars(app_url('admin_platform/tenants.php')) ?>" class="btn btn-sm btn-primary">
                        Gérer les établissements
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table platform-table table-hover mb-0" id="featureTenantsTable">
                        <thead>
                            <tr>
                                <th>Établissement</th>
                                <th>Clé</th>
                                <th>Statut tenant</th>
                                <th>Fonctionnalité</th>
                                <th>Activée le</th>
                                <th class="platform-col-actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tenantsFeatures as $row): ?>
                        <?php
                            $isOn = (int) ($row['feature_enabled'] ?? 0) === 1;
                            $searchHay = strtolower(
                                ($row['company_name'] ?? '') . ' '
                                . ($row['tenant_key'] ?? '') . ' '
                                . ($row['status'] ?? '')
                            );
                        ?>
                        <tr data-search="<?= htmlspecialchars($searchHay) ?>">
                            <td><strong><?= htmlspecialchars($row['company_name']) ?></strong></td>
                            <td><code class="platform-ref"><?= htmlspecialchars($row['tenant_key']) ?></code></td>
                            <td><?= app_platform_status_badge($row['status']) ?></td>
                            <td>
                                <?php if ($isOn): ?>
                                <span class="platform-pill platform-pill--success"><i class="fas fa-check me-1"></i>Activée</span>
                                <?php else: ?>
                                <span class="platform-pill platform-pill--muted">Désactivée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['enabled_at'])): ?>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($row['enabled_at'])) ?></small>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="platform-col-actions">
                                <form method="POST" class="platform-act-form">
                                    <input type="hidden" name="tenant_id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="feature_key" value="<?= htmlspecialchars($selectedKey) ?>">
                                    <input type="hidden" name="enabled" value="<?= $isOn ? '0' : '1' ?>">
                                    <button type="submit" name="toggle_tenant_feature"
                                            class="platform-act platform-act--<?= $isOn ? 'danger' : 'success' ?>"
                                            title="<?= $isOn ? 'Désactiver' : 'Activer' ?>">
                                        <i class="fas <?= $isOn ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
                                        <span class="platform-act-label"><?= $isOn ? 'Désactiver' : 'Activer' ?></span>
                                    </button>
                                </form>
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
</div>

<script>
(function () {
    var search = document.getElementById('featureTenantSearch');
    var table = document.getElementById('featureTenantsTable');
    if (!search || !table) return;
    search.addEventListener('input', function () {
        var q = search.value.toLowerCase().trim();
        table.querySelectorAll('tbody tr').forEach(function (row) {
            var hay = row.getAttribute('data-search') || '';
            row.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
        });
    });
})();
</script>

<?php
app_platform_shell_end();
app_layout_end(['minimal_scripts' => true]);
