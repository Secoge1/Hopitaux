<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';
require_once __DIR__ . '/../includes/tenant_data_import.php';
require_once __DIR__ . '/../includes/saas/TenantSqlImporter.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/SystemLogs.php';

app_parametres_require_admin();
extract(app_prepare_context());

$auth = Auth::getInstance();
$tenantId = (int) $auth->getTenantId();
if ($tenantId < 1) {
    header('Location: ' . app_url('index.php?error=no_tenant'));
    exit;
}

$message = '';
$messageType = 'info';
$importLog = [];
$importStats = [];
$pending = TenantDataImportWeb::getPending($tenantId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!validateCSRFToken($token)) {
        $message = 'Session expirée — rechargez la page et réessayez.';
        $messageType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'cancel') {
            TenantDataImportWeb::clearPending($tenantId);
            $pending = null;
            $message = 'Import annulé.';
            $messageType = 'warning';
        } elseif ($action === 'analyze') {
            $stored = TenantDataImportWeb::storeUpload($tenantId, $_FILES['sql_file'] ?? []);
            if (!$stored['success']) {
                $message = $stored['message'];
                $messageType = 'danger';
            } else {
                try {
                    TenantDataImportWeb::clearPending($tenantId);
                    $importer = new TenantSqlImporter(getDB(), $tenantId, (string) $stored['path']);
                    $details = $importer->analyzeDetails();
                    if ($details === []) {
                        @unlink((string) $stored['path']);
                        $message = 'Aucune donnée importable détectée dans ce fichier SQL.';
                        $messageType = 'danger';
                    } else {
                        $counts = [];
                        foreach ($details as $table => $d) {
                            $counts[$table] = $d['inserts'];
                        }
                        TenantDataImportWeb::savePending(
                            $tenantId,
                            (string) $stored['path'],
                            (string) ($stored['original'] ?? 'import.sql'),
                            $counts,
                            $details
                        );
                        $pending = TenantDataImportWeb::getPending($tenantId);
                        $message = 'Fichier analysé — vérifiez le contenu puis confirmez l\'import.';
                        $messageType = 'info';
                    }
                } catch (Throwable $e) {
                    if (!empty($stored['path']) && is_file($stored['path'])) {
                        @unlink($stored['path']);
                    }
                    $message = 'Analyse impossible : ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'import') {
            if (empty($_POST['confirm_import'])) {
                $message = 'Cochez la case de confirmation pour lancer l\'import.';
                $messageType = 'warning';
            } elseif ($pending === null) {
                $message = 'Aucun fichier en attente — uploadez d\'abord un dump SQL.';
                $messageType = 'danger';
            } else {
                $result = TenantDataImportWeb::runImport($tenantId, $pending['path']);
                $importLog = $result['log'] ?? [];
                $importStats = $result['stats'] ?? [];
                TenantDataImportWeb::clearPending($tenantId);
                $pending = null;

                if (!empty($result['success'])) {
                    $total = array_sum($importStats);
                    $message = $result['message'] . ' (' . $total . ' enregistrement(s) importé(s) pour votre établissement.)';
                    $messageType = 'success';
                    try {
                        $logs = new SystemLogs();
                        $logs->addLog('import', 'Import SQL tenant #' . $tenantId . ' — ' . $total . ' lignes');
                    } catch (Throwable $e) {
                        // ignore
                    }
                } else {
                    $message = $result['message'];
                    $messageType = 'danger';
                }
            }
        }
    }
}

$csrf = generateCSRFToken();
$tenantRow = null;
try {
    require_once __DIR__ . '/../includes/saas/TenantContext.php';
    $tenantRow = TenantContext::getTenantRow();
} catch (Throwable $e) {
    // ignore
}

app_head('Import de données', ['assets/css/app-parametres.css'], 'app-parametres-page');
app_layout_start(['active' => 'parametres', 'skip_page_header' => true]);
app_parametres_shell_start(
    'import_donnees',
    'Import de données',
    'Migrer un dump SQL Efficasante vers votre établissement uniquement'
);
app_parametres_alert($message, $messageType);
?>

<div class="param-section mb-4">
    <div class="alert alert-warning border-0">
        <h6 class="alert-heading mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Important</h6>
        <ul class="mb-0 small ps-3">
            <li>L'import ne modifie <strong>que votre établissement</strong>
                <?php if ($tenantRow): ?>
                (<strong><?= htmlspecialchars($tenantRow['company_name'] ?? '') ?></strong>, tenant #<?= $tenantId ?>).
                <?php else: ?>
                (tenant #<?= $tenantId ?>).
                <?php endif; ?>
            </li>
            <li>Format accepté : dump <strong>.sql</strong> d'une installation Efficasante / Se.Santé (export phpMyAdmin).</li>
            <li>Les comptes utilisateurs déjà existants (ex. <code>admin</code>) ne seront pas dupliqués.</li>
            <li>Taille max : <?= htmlspecialchars(TenantDataImportWeb::maxUploadLabel()) ?>.</li>
        </ul>
    </div>
</div>

<?php if ($pending === null): ?>
<div class="param-section">
    <div class="param-card">
        <div class="param-card-head param-card-head--blue">
            <i class="fas fa-file-upload"></i> Étape 1 — Envoyer le fichier SQL
        </div>
        <div class="param-card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="analyze">
                <div class="col-12">
                    <label for="sql_file" class="form-label">Fichier dump SQL</label>
                    <input type="file" class="form-control" id="sql_file" name="sql_file" accept=".sql,text/plain" required>
                    <div class="form-text">Ex. export phpMyAdmin de votre ancienne base clinique.</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Analyser le fichier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php else: ?>
<div class="param-section mb-4">
    <div class="param-card">
        <div class="param-card-head param-card-head--blue">
            <i class="fas fa-list-check"></i> Étape 2 — Vérifier et confirmer
        </div>
        <div class="param-card-body">
            <p class="mb-3">
                Fichier : <strong><?= htmlspecialchars($pending['original']) ?></strong>
                <span class="text-muted">(analysé <?= date('d/m/Y H:i', (int) $pending['at']) ?>)</span>
            </p>
            <?php
            $details = is_array($pending['details'] ?? null) ? $pending['details'] : [];
            $sumInserts = 0;
            $sumRows = 0;
            foreach ($pending['counts'] as $n) {
                $sumInserts += (int) $n;
            }
            foreach ($details as $d) {
                $sumRows += (int) ($d['rows'] ?? 0);
            }
            $patientsDetail = $details['patients'] ?? null;
            $medecinsDetail = $details['medecins'] ?? null;
            if ($patientsDetail !== null || $medecinsDetail !== null):
            ?>
            <div class="alert alert-info border-0 small mb-4">
                <strong>Aperçu dans l'application</strong> — les listes masquent les enregistrements « supprimés » :
                <ul class="mb-0 mt-2 ps-3">
                <?php if ($patientsDetail !== null):
                    $pVisibles = (int) $patientsDetail['rows'] - (int) ($patientsDetail['supprime'] ?? 0);
                ?>
                    <li><strong><?= $pVisibles ?></strong> patient(s) visibles
                        (<?= (int) $patientsDetail['rows'] ?> lignes dont <?= (int) ($patientsDetail['supprime'] ?? 0) ?> supprimé(s))</li>
                <?php endif; ?>
                <?php if ($medecinsDetail !== null):
                    $mVisibles = (int) $medecinsDetail['rows'] - (int) ($medecinsDetail['supprime'] ?? 0);
                ?>
                    <li><strong><?= $mVisibles ?></strong> médecin(s) visibles
                        (<?= (int) $medecinsDetail['rows'] ?> lignes dont <?= (int) ($medecinsDetail['supprime'] ?? 0) ?> supprimé(s))</li>
                <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th class="text-end">Requêtes INSERT</th>
                            <th class="text-end">Lignes</th>
                            <th>Détail</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pending['counts'] as $table => $n):
                        $rowDetail = $details[$table] ?? null;
                        $rows = $rowDetail !== null ? (int) ($rowDetail['rows'] ?? 0) : null;
                        $extra = '';
                        if ($rowDetail !== null && in_array($table, ['patients', 'medecins'], true)) {
                            $actif = (int) ($rowDetail['actif'] ?? 0);
                            $supprime = (int) ($rowDetail['supprime'] ?? 0);
                            if ($actif > 0 || $supprime > 0) {
                                $parts = [];
                                if ($actif > 0) {
                                    $parts[] = $actif . ' actif(s)';
                                }
                                if ($supprime > 0) {
                                    $parts[] = $supprime . ' supprimé(s)';
                                }
                                $extra = implode(', ', $parts);
                            }
                        }
                    ?>
                        <tr>
                            <td><code><?= htmlspecialchars($table) ?></code></td>
                            <td class="text-end"><?= (int) $n ?></td>
                            <td class="text-end"><?= $rows !== null ? (int) $rows : '—' ?></td>
                            <td class="text-muted small"><?= $extra !== '' ? htmlspecialchars($extra) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th class="text-end"><?= (int) $sumInserts ?></th>
                            <th class="text-end"><?= $sumRows > 0 ? (int) $sumRows : '—' ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <form method="post" class="border-top pt-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="import">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="confirm_import" value="1" id="confirm_import" required>
                    <label class="form-check-label" for="confirm_import">
                        Je confirme importer ces données dans <strong>mon établissement uniquement</strong>.
                        Je comprends que cette opération est irréversible pour les doublons éventuels.
                    </label>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-play me-2"></i>Lancer l'import
                    </button>
                </div>
            </form>
            <form method="post" class="mt-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times me-1"></i>Annuler et choisir un autre fichier
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($importStats !== []): ?>
<div class="param-section mb-4">
    <div class="param-card">
        <div class="param-card-head"><i class="fas fa-chart-bar"></i> Résultat de l'import</div>
        <div class="param-card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Table</th><th class="text-end">Lignes importées</th></tr></thead>
                    <tbody>
                    <?php foreach ($importStats as $table => $n): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($table) ?></code></td>
                            <td class="text-end"><?= (int) $n ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($importLog !== []): ?>
<div class="param-section">
    <div class="param-card">
        <div class="param-card-head"><i class="fas fa-terminal"></i> Journal</div>
        <div class="param-card-body">
            <pre class="small bg-light p-3 rounded mb-0" style="max-height:320px;overflow:auto"><?php
                echo htmlspecialchars(implode("\n", $importLog));
            ?></pre>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
app_parametres_shell_end();
app_layout_end();
?>
