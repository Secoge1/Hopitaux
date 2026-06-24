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

$statusLabels = [
    'live' => ['label' => 'Opérationnelle', 'class' => 'platform-pill--success'],
    'beta' => ['label' => 'Bêta partielle', 'class' => 'platform-pill--warning'],
    'planned' => ['label' => 'Planifiée', 'class' => 'platform-pill'],
];

app_head('Fonctionnalités — Admin plateforme', ['assets/css/app-platform.css'], 'app-platform-page');
app_layout_start(['active' => 'platform', 'skip_page_header' => true]);
app_platform_shell_start(
    'features',
    'Déploiement des fonctionnalités',
    'Activez les évolutions métier établissement par établissement — rien ne s\'applique sans activation ici',
    '<span class="platform-pill platform-pill--success">' . (int) $enabledCount . ' activé(s)</span>'
);
echo displayFlashMessages();
app_platform_alert($message, $messageType);
?>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="platform-card h-100">
            <div class="platform-card-head">
                <span><i class="fas fa-list"></i>Catalogue</span>
            </div>
            <div class="platform-card-body p-0">
                <div class="list-group list-group-flush">
                <?php foreach ($catalog as $key => $item):
                    $st = $statusLabels[$item['status']] ?? $statusLabels['planned'];
                    $active = $key === $selectedKey ? ' active' : '';
                    $count = PlatformTenantFeatures::countEnabled($key);
                ?>
                    <a href="?feature=<?= urlencode($key) ?>" class="list-group-item list-group-item-action<?= $active ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <strong class="d-block small"><?= htmlspecialchars($item['label']) ?></strong>
                                <code class="platform-ref small"><?= htmlspecialchars($key) ?></code>
                            </div>
                            <span class="platform-pill <?= htmlspecialchars($st['class']) ?> small"><?= htmlspecialchars($st['label']) ?></span>
                        </div>
                        <?php if ($count > 0): ?>
                        <small class="text-success d-block mt-1"><?= (int) $count ?> établissement(s)</small>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <?php
        $st = $statusLabels[$featureMeta['status']] ?? $statusLabels['planned'];
        ?>
        <div class="platform-card mb-4">
            <div class="platform-card-head">
                <span><i class="fas fa-toggle-on"></i><?= htmlspecialchars($featureMeta['label']) ?></span>
                <span class="platform-pill <?= htmlspecialchars($st['class']) ?>"><?= htmlspecialchars($st['label']) ?></span>
            </div>
            <div class="platform-card-body">
                <p class="text-muted mb-3"><?= htmlspecialchars($featureMeta['description']) ?></p>
                <?php if ($featureMeta['status'] === 'planned'): ?>
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Fonctionnalité <strong>planifiée</strong> : l'activation prépare le déploiement mais n'a pas encore d'effet métier complet.
                </div>
                <?php elseif ($featureMeta['status'] === 'beta'): ?>
                <div class="alert alert-warning py-2 small mb-3">
                    <i class="fas fa-flask me-1"></i>
                    Fonctionnalité <strong>bêta</strong> : partiellement disponible, activation progressive recommandée.
                </div>
                <?php else: ?>
                <div class="alert alert-success py-2 small mb-3">
                    <i class="fas fa-check-circle me-1"></i>
                    Fonctionnalité <strong>opérationnelle</strong> : l'activation a un effet immédiat pour l'établissement.
                </div>
                <?php endif; ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Appliquer à TOUS les établissements ?');">
                    <input type="hidden" name="feature_key" value="<?= htmlspecialchars($selectedKey) ?>">
                    <input type="hidden" name="enabled" value="1">
                    <button type="submit" name="toggle_all_tenant_feature" class="btn btn-success btn-sm me-2">
                        <i class="fas fa-check-double me-1"></i>Activer pour tous
                    </button>
                </form>
                <form method="POST" class="d-inline" onsubmit="return confirm('Désactiver pour TOUS les établissements ?');">
                    <input type="hidden" name="feature_key" value="<?= htmlspecialchars($selectedKey) ?>">
                    <input type="hidden" name="enabled" value="0">
                    <button type="submit" name="toggle_all_tenant_feature" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-ban me-1"></i>Désactiver pour tous
                    </button>
                </form>
            </div>
        </div>

        <div class="platform-card">
            <div class="platform-card-head">
                <span><i class="fas fa-building"></i>Établissements</span>
                <span class="platform-pill"><?= count($tenantsFeatures) ?> total</span>
            </div>
            <div class="platform-card-body p-0">
                <?php if (empty($tenantsFeatures)): ?>
                <div class="platform-empty">
                    <i class="fas fa-building text-muted"></i>
                    <p>Aucun établissement enregistré.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table platform-table table-hover mb-0">
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
                        <?php $isOn = (int) ($row['feature_enabled'] ?? 0) === 1; ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['company_name']) ?></strong></td>
                            <td><code class="platform-ref"><?= htmlspecialchars($row['tenant_key']) ?></code></td>
                            <td><?= app_platform_status_badge($row['status']) ?></td>
                            <td>
                                <?php if ($isOn): ?>
                                <span class="platform-pill platform-pill--success"><i class="fas fa-check me-1"></i>Activée</span>
                                <?php else: ?>
                                <span class="platform-pill platform-pill--warning">Désactivée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['enabled_at'])): ?>
                                <small><?= date('d/m/Y H:i', strtotime($row['enabled_at'])) ?></small>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="platform-col-actions">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="tenant_id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="feature_key" value="<?= htmlspecialchars($selectedKey) ?>">
                                    <input type="hidden" name="enabled" value="<?= $isOn ? '0' : '1' ?>">
                                    <button type="submit" name="toggle_tenant_feature" class="btn btn-sm <?= $isOn ? 'btn-outline-danger' : 'btn-success' ?>">
                                        <?= $isOn ? 'Désactiver' : 'Activer' ?>
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

<?php
app_platform_shell_end();
app_layout_end(['minimal_scripts' => true]);
