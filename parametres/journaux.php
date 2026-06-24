<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';

app_parametres_require_admin();
extract(app_prepare_context());

require_once '../config/SystemLogs.php';

$logs = new SystemLogs();

// Filtres
$filters = [
    'action' => $_GET['action'] ?? '',
    'user_id' => $_GET['user_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Récupérer les journaux
$systemLogs = $logs->getLogs($filters, $limit, $offset);
$totalLogs = $logs->getLogsCount($filters);
$totalPages = ceil($totalLogs / $limit);

// Statistiques
$stats = $logs->getLogsStats();
$stats = array_merge([
    'total_logs'  => 0,
    'today_logs'  => 0,
    'week_logs'   => 0,
    'month_logs'  => 0,
    'top_actions' => [],
    'top_users'   => [],
], $stats);
$availableActions = $logs->getAvailableActions();

// Enregistrer la visite dans les journaux
$logs->addLog('view', 'Consultation de la page des journaux système');

// Traitement des actions
$message = '';
$messageType = '';

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'export_csv':
                $result = $logs->exportLogsCSV($filters);
                if ($result) {
                    header('Location: download_logs.php?file=' . urlencode($result['filename']));
                    exit;
                } else {
                    $message = 'Erreur lors de l\'export CSV';
                    $messageType = 'danger';
                }
                break;
                
            case 'clean_logs':
                if (isset($_POST['days_to_keep'])) {
                    $days = intval($_POST['days_to_keep']);
                    if ($logs->cleanOldLogs($days)) {
                        $message = "Journaux de plus de $days jours supprimés avec succès";
                        $messageType = 'success';
                    } else {
                        $message = 'Erreur lors du nettoyage des journaux';
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

app_head('Journaux', ['assets/css/app-parametres.css'], 'app-parametres-page');
app_layout_start(['active' => 'parametres', 'skip_page_header' => true]);
app_parametres_shell_start('journaux', 'Journaux système', 'Historique des actions et audit');
if ($message) app_parametres_alert($message, $messageType);
?>

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total</h5>
                                <h3><?= $stats['total_logs'] ?></h3>
                                <small>Journaux</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Aujourd'hui</h5>
                                <h3><?= $stats['today_logs'] ?></h3>
                                <small>Actions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Cette semaine</h5>
                                <h3><?= $stats['week_logs'] ?></h3>
                                <small>Actions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Ce mois</h5>
                                <h3><?= $stats['month_logs'] ?></h3>
                                <small>Actions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Actions les plus fréquentes</h6>
                                <?php if (!empty($stats['top_actions'])): ?>
                                    <div class="small">
                                        <?php foreach (array_slice($stats['top_actions'], 0, 3) as $action): ?>
                                            <div class="d-flex justify-content-between">
                                                <span><?= $logs->formatAction($action['action']) ?></span>
                                                <span class="badge bg-light text-dark"><?= $action['count'] ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <small>Aucune donnée</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="filter-section">
                    <h5 class="mb-3">
                        <i class="fas fa-filter text-secondary me-2"></i>
                        Filtres de recherche
                    </h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label for="action" class="form-label">Action</label>
                            <select name="action" id="action" class="form-select">
                                <option value="">Toutes</option>
                                <?php foreach ($availableActions as $action): ?>
                                    <option value="<?= htmlspecialchars($action) ?>" 
                                            <?= $filters['action'] === $action ? 'selected' : '' ?>>
                                        <?= $logs->formatAction($action) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">Date début</label>
                            <input type="date" name="date_from" id="date_from" 
                                   class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">Date fin</label>
                            <input type="date" name="date_to" id="date_to" 
                                   class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="search" class="form-label">Recherche</label>
                            <input type="text" name="search" id="search" 
                                   class="form-control" placeholder="Rechercher..." 
                                   value="<?= htmlspecialchars($filters['search']) ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i>Filtrer
                            </button>
                            <a href="journaux.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Actions -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="export_csv">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-file-csv me-2"></i>Exporter en CSV
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#cleanModal">
                            <i class="fas fa-broom me-2"></i>Nettoyer les journaux
                        </button>
                    </div>
                </div>

                <!-- Résultats -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list text-secondary me-2"></i>
                            Journaux système
                        </h5>
                        <small class="text-muted">
                            <?= $totalLogs ?> entrées trouvées
                            <?php if ($totalPages > 1): ?>
                                - Page <?= $page ?> sur <?= $totalPages ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <?php if (empty($systemLogs)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucun journal trouvé</p>
                                <p class="small">Essayez de modifier vos filtres de recherche.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>Détails</th>
                                            <th>Utilisateur</th>
                                            <th>Adresse IP</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($systemLogs as $log): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?= $logs->getActionColor($log['action']) ?> log-action">
                                                        <?= $logs->formatAction($log['action']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="log-details">
                                                        <?= htmlspecialchars($log['details'] ?: '-') ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($log['user_id']): ?>
                                                        <?php if ($log['user_nom'] && $log['user_prenom']): ?>
                                                            <span class="badge bg-secondary">
                                                                <?= htmlspecialchars($log['user_nom'] . ' ' . $log['user_prenom']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">
                                                                Utilisateur #<?= $log['user_id'] ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <code class="log-time"><?= htmlspecialchars($log['ip_address']) ?></code>
                                                </td>
                                                <td>
                                                    <div class="log-time">
                                                        <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Navigation des pages">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

<?php app_parametres_shell_end(); ?>

    <!-- Modal de nettoyage -->
    <div class="modal fade" id="cleanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nettoyer les journaux</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Cette action supprimera définitivement tous les journaux plus anciens que la période spécifiée.</p>
                    <form method="POST" id="cleanForm">
                        <input type="hidden" name="action" value="clean_logs">
                        <div class="mb-3">
                            <label for="days_to_keep" class="form-label">Conserver les journaux des derniers jours :</label>
                            <select name="days_to_keep" id="days_to_keep" class="form-select">
                                <option value="30">30 jours</option>
                                <option value="60">60 jours</option>
                                <option value="90" selected>90 jours</option>
                                <option value="180">180 jours</option>
                                <option value="365">1 an</option>
                            </select>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Attention :</strong> Cette action est irréversible !
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" form="cleanForm" class="btn btn-warning">Nettoyer</button>
                </div>
            </div>
        </div>
    </div>

<?php app_layout_end(['minimal_scripts' => true]); ?>
