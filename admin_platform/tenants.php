<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_platform_layout.php';
require_once __DIR__ . '/../includes/saas/SubscriptionCheckout.php';
require_once __DIR__ . '/../includes/saas/SubscriptionPlan.php';
require_once __DIR__ . '/../includes/saas/saas_helpers.php';
require_once __DIR__ . '/../includes/app_platform_actions.php';
require_once __DIR__ . '/_handlers.php';

app_platform_require_admin();
$postResult = admin_platform_handle_post();
extract(app_prepare_platform_context());
extract($postResult);

$checkout = new SubscriptionCheckout();
$tenants = $checkout->listAllTenants();
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$headerActions = '<a href="' . htmlspecialchars(app_url('tarifs.php')) . '" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">'
    . '<i class="fas fa-external-link-alt me-1"></i>Tarifs publics</a>';

app_head('Établissements — Admin plateforme', ['assets/css/app-platform.css'], 'app-platform-page');
app_layout_start(['active' => 'platform', 'skip_page_header' => true]);
app_platform_shell_start(
    'tenants',
    'Établissements abonnés',
    count($tenants) . ' établissement(s) enregistré(s) sur la plateforme',
    $headerActions
);
echo displayFlashMessages();
app_platform_alert($message, $messageType);
?>

<div class="platform-toolbar">
    <div class="platform-toolbar-search">
        <i class="fas fa-search"></i>
        <input type="search" id="tenantSearch" class="form-control form-control-sm" placeholder="Rechercher un établissement, une clé…" autocomplete="off">
    </div>
    <a href="<?= htmlspecialchars(app_url('admin_platform/payments.php')) ?>" class="btn btn-success btn-sm">
        <i class="fas fa-check-circle me-1"></i>Valider un paiement
    </a>
</div>

<div class="platform-card">
    <div class="platform-card-head">
        <span><i class="fas fa-building"></i>Liste des établissements</span>
        <span class="platform-pill"><?= count($tenants) ?> total</span>
    </div>
    <div class="platform-card-body p-0">
        <?php if (empty($tenants)): ?>
        <div class="platform-empty">
            <i class="fas fa-building text-muted"></i>
            <p>Aucun établissement enregistré.</p>
            <a href="<?= htmlspecialchars(app_url('tarifs.php')) ?>" class="btn btn-sm btn-primary" target="_blank" rel="noopener">Voir la page tarifs</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table platform-table table-hover mb-0" id="tenantsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Clé</th>
                        <th>Établissement</th>
                        <th>Identifiant</th>
                        <th>Mot de passe</th>
                        <th>Licence</th>
                        <th>Expiration</th>
                        <th>Utilisateurs</th>
                        <th>Statut</th>
                        <th class="platform-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tenants as $t):
                    $creds = app_platform_tenant_credentials($t);
                    $searchHay = strtolower($t['company_name'] . ' ' . $t['tenant_key'] . ' ' . $t['email'] . ' ' . $creds['username']);
                ?>
                <tr data-search="<?= htmlspecialchars($searchHay) ?>">
                    <td><?= (int) $t['id'] ?></td>
                    <td><code class="platform-ref"><?= htmlspecialchars($t['tenant_key']) ?></code></td>
                    <td><strong><?= htmlspecialchars($t['company_name']) ?></strong></td>
                    <td><?= app_platform_credential_cell($creds, 'username') ?></td>
                    <td><?= app_platform_credential_cell($creds, 'password') ?></td>
                    <td><?= htmlspecialchars(SubscriptionPlan::get($t['license_type'])['name']) ?></td>
                    <td><?= $t['expires_at'] ? date('d/m/Y', strtotime($t['expires_at'])) : '∞' ?></td>
                    <td><?= (int) ($t['users_count'] ?? 0) ?>/<?= (int) $t['max_users'] ?></td>
                    <td><?= app_platform_status_badge($t['status']) ?></td>
                    <td class="platform-col-actions">
                        <?= app_platform_tenant_actions($t) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($tenants as $t):
    $tid = (int) $t['id'];
    $creds = app_platform_tenant_credentials($t);
?>
<div class="modal fade param-modal" id="edit-<?= $tid ?>" tabindex="-1" aria-labelledby="edit-title-<?= $tid ?>" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header param-modal-header">
                <h5 class="modal-title" id="edit-title-<?= $tid ?>"><i class="fas fa-edit me-2"></i><?= htmlspecialchars($t['company_name']) ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form method="post"
                  id="edit-tenant-<?= $tid ?>"
                  action="<?= htmlspecialchars(app_url('admin_platform/tenants.php')) ?>"
                  class="platform-tenant-edit-form">
                <div class="modal-body">
                    <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                    <div class="platform-credential-box mb-3">
                        <div class="platform-credential-box-title"><i class="fas fa-key me-1"></i>Accès administrateur</div>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-0">Identifiant</label>
                                <div class="form-control-plaintext fw-semibold"><?= htmlspecialchars($creds['username']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small text-muted mb-0">Email</label>
                                <div class="form-control-plaintext"><?= htmlspecialchars($creds['email'] ?: '—') ?></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-0">Mot de passe</label>
                                <?php if ($creds['has_password']): ?>
                                <div class="input-group input-group-sm">
                                    <input type="password" class="form-control platform-credential-input" readonly
                                           value="<?= htmlspecialchars($creds['password']) ?>" id="tenant-pwd-<?= $tid ?>">
                                    <button type="button" class="btn btn-outline-secondary platform-credential-input-toggle" data-target="tenant-pwd-<?= $tid ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php else: ?>
                                <p class="small text-muted mb-0">Mot de passe non conservé (inscription antérieure). Générez-en un nouveau ci-dessous.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Établissement</label>
                        <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($t['company_name']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Type de licence</label>
                        <select name="license_type" class="form-select">
                            <?php foreach (SubscriptionPlan::getAll() as $planSlug => $planOption): ?>
                            <option value="<?= htmlspecialchars($planSlug) ?>" <?= $t['license_type'] === $planSlug ? 'selected' : '' ?>>
                                <?= htmlspecialchars($planOption['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Expiration (vide = à vie)</label>
                        <input type="date" name="expires_at" class="form-control" value="<?= htmlspecialchars($t['expires_at'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Max utilisateurs</label>
                        <input type="number" name="max_users" class="form-control" value="<?= (int) $t['max_users'] ?>" min="1" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Statut</label>
                        <select name="status" class="form-select">
                            <?php foreach (['active', 'expired', 'suspended', 'cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $t['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
            <div class="modal-footer platform-modal-footer-split">
                <div class="platform-modal-footer-left me-auto">
                    <form method="post"
                          action="<?= htmlspecialchars(app_url('admin_platform/tenants.php')) ?>"
                          class="platform-tenant-reset-form"
                          onsubmit="return confirm('Générer un nouveau mot de passe administrateur pour cet établissement ?');">
                        <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                        <button type="submit" name="reset_tenant_password" value="1" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-sync-alt me-1"></i>Nouveau mot de passe
                        </button>
                    </form>
                    <?php if ($tid > 1): ?>
                    <form method="post"
                          action="<?= htmlspecialchars(app_url('admin_platform/tenants.php')) ?>"
                          class="platform-tenant-delete-form"
                          onsubmit="return confirm(<?= json_encode('Supprimer définitivement « ' . $t['company_name'] . ' » ? Cette action est irréversible.', JSON_UNESCAPED_UNICODE) ?>);">
                        <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                        <button type="submit" name="delete_tenant" value="1" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-trash me-1"></i>Supprimer
                        </button>
                    </form>
                    <?php else: ?>
                    <span class="small text-muted">Établissement principal — non supprimable</span>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="edit-tenant-<?= $tid ?>" name="update_tenant" value="1" class="btn btn-primary" data-platform-submit>
                    <i class="fas fa-save me-1"></i>Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php
ob_start();
?>
<script>
(function () {
    function platformCleanupModalState() {
        document.querySelectorAll('.modal-backdrop').forEach(function (el) {
            el.remove();
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    function platformMountModals() {
        document.querySelectorAll('.param-modal').forEach(function (modalEl) {
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
        });
    }

    function platformSetSubmitLoading(btn) {
        if (!btn || btn.disabled) {
            return;
        }
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Enregistrement…';
    }

    platformMountModals();

    var search = document.getElementById('tenantSearch');
    var table = document.getElementById('tenantsTable');
    if (search && table) {
        search.addEventListener('input', function () {
            var q = search.value.toLowerCase().trim();
            table.querySelectorAll('tbody tr').forEach(function (row) {
                var hay = row.getAttribute('data-search') || '';
                row.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    document.querySelectorAll('.param-modal').forEach(function (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', platformCleanupModalState);
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (window.location.search.indexOf('edit=') === -1) {
                return;
            }
            var url = new URL(window.location.href);
            url.searchParams.delete('edit');
            window.history.replaceState({}, '', url.pathname + url.search + url.hash);
        });
    });

    document.querySelectorAll('.platform-tenant-edit-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) {
                return;
            }
            var btn = document.querySelector('[data-platform-submit][form="' + form.id + '"]');
            platformSetSubmitLoading(btn);
        });
    });

    document.querySelectorAll('.platform-tenant-delete-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                btn.disabled = true;
            }
        });
    });

    document.querySelectorAll('.platform-credential-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.closest('.platform-credential-secret');
            if (!wrap) return;
            var code = wrap.querySelector('.platform-credential--masked');
            var secret = wrap.getAttribute('data-secret') || '';
            if (!code) return;
            var visible = code.getAttribute('data-visible') === '1';
            if (visible) {
                code.textContent = '••••••••';
                code.removeAttribute('data-visible');
                btn.querySelector('i').className = 'fas fa-eye';
            } else {
                code.textContent = secret;
                code.setAttribute('data-visible', '1');
                btn.querySelector('i').className = 'fas fa-eye-slash';
            }
        });
    });

    document.querySelectorAll('.platform-credential-input-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-target') || '');
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
    });

    var editId = <?= (int) $editId ?>;
    var openOnLoad = <?= $_SERVER['REQUEST_METHOD'] === 'GET' ? 'true' : 'false' ?>;
    if (editId && openOnLoad) {
        var modalEl = document.getElementById('edit-' + editId);
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }
})();
</script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_platform_shell_end();
app_layout_end(['minimal_scripts' => true]);
