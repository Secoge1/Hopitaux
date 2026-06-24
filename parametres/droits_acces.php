<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';
require_once __DIR__ . '/../includes/roles.php';
require_once __DIR__ . '/../includes/tenant_permissions.php';

app_parametres_require_admin();
extract(app_prepare_context());

$tenantId = (int) $auth->getTenantId();
TenantPermissions::ensureTables();

$message = '';
$message_type = 'success';
$selectedRole = $_GET['role'] ?? 'secretaire';
if (!app_role_is_valid($selectedRole)) {
    $selectedRole = 'secretaire';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'save_role':
            $role = (string) ($_POST['role'] ?? '');
            $modules = isset($_POST['modules']) && is_array($_POST['modules']) ? $_POST['modules'] : [];
            if (!app_role_is_valid($role)) {
                $message = 'Rôle invalide.';
                $message_type = 'danger';
            } elseif (TenantPermissions::saveRoleModules($tenantId, $role, $modules)) {
                $message = 'Droits enregistrés pour le profil « ' . app_role_label($role) . ' ».';
                $selectedRole = $role;
                require_once __DIR__ . '/../config/SystemLogs.php';
                (new SystemLogs())->addLog('permissions_update', 'Mise à jour droits module — rôle ' . $role, (int) $auth->getUserId());
            } else {
                $message = 'Erreur lors de l\'enregistrement des droits.';
                $message_type = 'danger';
            }
            break;

        case 'reset_role':
            $role = (string) ($_POST['role'] ?? '');
            if (!app_role_is_valid($role)) {
                $message = 'Rôle invalide.';
                $message_type = 'danger';
            } elseif (TenantPermissions::resetRoleToDefaults($tenantId, $role)) {
                $message = 'Profil « ' . app_role_label($role) . ' » réinitialisé aux valeurs par défaut.';
                $selectedRole = $role;
            } else {
                $message = 'Erreur lors de la réinitialisation.';
                $message_type = 'danger';
            }
            break;

        case 'reset_all':
            if (TenantPermissions::resetTenantToDefaults($tenantId)) {
                $message = 'Tous les profils ont été réinitialisés aux valeurs par défaut de l\'application.';
            } else {
                $message = 'Erreur lors de la réinitialisation globale.';
                $message_type = 'danger';
            }
            break;
    }
}

$matrix = TenantPermissions::getMatrixForTenant($tenantId);
$moduleLabels = app_module_labels();
$moduleGroups = TenantPermissions::moduleGroups();
$hasCustom = TenantPermissions::tenantHasCustomizations($tenantId);
$roleModules = $matrix[$selectedRole] ?? [];

$actionsHtml = '<a href="' . htmlspecialchars(app_url('parametres/utilisateurs.php')) . '" class="btn btn-outline-secondary btn-sm">'
    . '<i class="fas fa-users me-1"></i>Utilisateurs</a>';

app_head('Droits d\'accès', ['assets/css/app-parametres.css'], 'app-parametres-page');
app_layout_start(['active' => 'parametres', 'skip_page_header' => true]);
app_parametres_shell_start('droits', 'Droits d\'accès', 'Personnalisez les modules accessibles par profil pour votre établissement', $actionsHtml);
app_parametres_alert($message, $message_type);
?>

        <div class="alert alert-info param-perm-intro">
            <i class="fas fa-info-circle me-2"></i>
            Personnalisez les modules visibles pour chaque <strong>profil (rôle)</strong> de votre établissement.
            Les comptes se créent dans
            <a href="<?= htmlspecialchars(app_url('parametres/utilisateurs.php')) ?>">Utilisateurs</a>
            (activer / suspendre un compte) ; les modules se règlent ici par profil.
            <?php if ($hasCustom): ?>
            <span class="badge bg-primary ms-1">Personnalisé</span>
            <?php else: ?>
            <span class="badge bg-secondary ms-1">Profils par défaut</span>
            <?php endif; ?>
        </div>

        <div class="param-layout-perms">
            <aside class="param-perm-roles">
                <div class="param-card">
                    <div class="param-card-head"><i class="fas fa-user-tag"></i> Profils</div>
                    <div class="param-card-body p-2">
                        <nav class="param-perm-role-nav">
                            <?php foreach (app_role_keys() as $roleKey): ?>
                            <a href="?role=<?= urlencode($roleKey) ?>"
                               class="param-perm-role-link<?= $selectedRole === $roleKey ? ' is-active' : '' ?>">
                                <span><?= htmlspecialchars(app_role_label($roleKey)) ?></span>
                                <?php if (TenantPermissions::hasTenantOverrides($tenantId, $roleKey)): ?>
                                <i class="fas fa-pen-fancy text-primary" title="Personnalisé"></i>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                </div>

                <form method="post" class="mt-3" onsubmit="return confirm('Réinitialiser tous les profils aux valeurs par défaut ?');">
                    <input type="hidden" name="action" value="reset_all">
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                        <i class="fas fa-undo me-1"></i>Tout réinitialiser
                    </button>
                </form>
            </aside>

            <div class="param-perm-main">
                <div class="param-card">
                    <div class="param-card-head d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span><i class="fas fa-shield-alt"></i> Modules — <?= htmlspecialchars(app_role_label($selectedRole)) ?></span>
                        <?php if ($selectedRole === 'admin'): ?>
                        <span class="badge bg-warning text-dark">Paramètres toujours actifs pour l'administrateur</span>
                        <?php endif; ?>
                    </div>
                    <div class="param-card-body">
                        <form method="post" id="formDroitsRole">
                            <input type="hidden" name="action" value="save_role">
                            <input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole) ?>">

                            <?php foreach ($moduleGroups as $groupLabel => $groupModules): ?>
                            <div class="param-perm-group">
                                <h6 class="param-perm-group-title"><?= htmlspecialchars($groupLabel) ?></h6>
                                <div class="row g-2">
                                    <?php foreach ($groupModules as $modKey):
                                        if (!isset($moduleLabels[$modKey])) {
                                            continue;
                                        }
                                        $checked = !empty($roleModules[$modKey]);
                                        $locked = ($selectedRole === 'admin' && $modKey === 'parametres');
                                        ?>
                                    <div class="col-md-6 col-lg-4">
                                        <label class="param-perm-check<?= $locked ? ' is-locked' : '' ?>">
                                            <input type="checkbox" name="modules[]" value="<?= htmlspecialchars($modKey) ?>"
                                                <?= $checked ? 'checked' : '' ?>
                                                <?= $locked ? 'checked disabled' : '' ?>>
                                            <?php if ($locked): ?>
                                            <input type="hidden" name="modules[]" value="parametres">
                                            <?php endif; ?>
                                            <span class="param-perm-check-label"><?= htmlspecialchars($moduleLabels[$modKey]) ?></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <div class="d-flex flex-wrap gap-2 justify-content-between mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Enregistrer ce profil
                                </button>
                            </div>
                        </form>

                        <form method="post" class="mt-2" onsubmit="return confirm('Réinitialiser ce profil aux valeurs par défaut ?');">
                            <input type="hidden" name="action" value="reset_role">
                            <input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole) ?>">
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-undo me-1"></i>Réinitialiser ce profil
                            </button>
                        </form>
                    </div>
                </div>

                <div class="param-card mt-3">
                    <div class="param-card-head"><i class="fas fa-table"></i> Aperçu rapide</div>
                    <div class="param-card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 param-perm-matrix">
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <?php foreach (app_role_keys() as $rk): ?>
                                        <th class="text-center" title="<?= htmlspecialchars(app_role_label($rk)) ?>">
                                            <?= htmlspecialchars(mb_substr(app_role_label($rk), 0, 3)) ?>
                                        </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($moduleLabels as $modKey => $modLabel): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($modLabel) ?></td>
                                        <?php foreach (app_role_keys() as $rk): ?>
                                        <td class="text-center">
                                            <?php if (!empty($matrix[$rk][$modKey])): ?>
                                            <i class="fas fa-check text-success" title="Autorisé"></i>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php
app_parametres_shell_end();
app_layout_end(['minimal_scripts' => true]);
?>
