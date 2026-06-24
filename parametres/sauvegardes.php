<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';

app_parametres_require_admin();
extract(app_prepare_context());

require_once '../config/SystemBackup.php';
require_once '../config/SystemLogs.php';

$backup = new SystemBackup();
$logs = new SystemLogs();

$message = '';
$messageType = '';

// Traitement des actions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_db_backup':
                $result = $backup->createDatabaseBackup();
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'create_files_backup':
                $result = $backup->createFilesBackup();
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'delete_backup':
                if (isset($_POST['filename'])) {
                    $result = $backup->deleteBackup($_POST['filename']);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'danger';
                }
                break;
        }
    }
}

// Récupérer les sauvegardes existantes
$backups = $backup->listBackups();
$stats = $backup->getBackupStats();

// Enregistrer la visite dans les journaux
$logs->addLog('view', 'Consultation de la page des sauvegardes');

app_head('Sauvegardes', ['assets/css/app-parametres.css'], 'app-parametres-page');
app_layout_start(['active' => 'parametres', 'skip_page_header' => true]);
app_parametres_shell_start('sauvegardes', 'Sauvegardes du système', 'Base de données et fichiers');
if ($message) app_parametres_alert($message, $messageType);
?>

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total</h5>
                                <h3><?= $stats['total_backups'] ?></h3>
                                <small>Sauvegardes</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Base</h5>
                                <h3><?= $stats['database_backups'] ?></h3>
                                <small>Base de données</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Fichiers</h5>
                                <h3><?= $stats['files_backups'] ?></h3>
                                <small>Fichiers uploadés</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Taille</h5>
                                <h3><?= $stats['total_size'] ?></h3>
                                <small>Espace utilisé</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions de sauvegarde -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-database text-primary me-2"></i>
                                    Sauvegarde de la base de données
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Crée une sauvegarde complète de toutes les données de la base.</p>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="create_db_backup">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-download me-2"></i>Créer la sauvegarde
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-folder text-info me-2"></i>
                                    Sauvegarde des fichiers
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Archive tous les fichiers uploadés (logos, documents, etc.).</p>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="create_files_backup">
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-archive me-2"></i>Créer l'archive
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Liste des sauvegardes -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list text-secondary me-2"></i>
                            Sauvegardes disponibles
                        </h5>
                        <small class="text-muted">Dernière sauvegarde : <?= $stats['last_backup'] ?></small>
                    </div>
                    <div class="card-body">
                        <?php if (empty($backups)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucune sauvegarde disponible</p>
                                <p class="small">Créez votre première sauvegarde en utilisant les boutons ci-dessus.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Nom du fichier</th>
                                            <th>Date de création</th>
                                            <th>Taille</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups as $backup): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($backup['type'] === 'database'): ?>
                                                        <span class="badge bg-primary">
                                                            <i class="fas fa-database me-1"></i>Base
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-folder me-1"></i>Fichiers
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <code class="backup-type"><?= htmlspecialchars($backup['filename']) ?></code>
                                                </td>
                                                <td><?= $backup['date'] ?></td>
                                                <td class="file-size"><?= $backup['size'] ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="download_backup.php?file=<?= urlencode($backup['filename']) ?>" 
                                                           class="btn btn-outline-success" 
                                                           title="Télécharger">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-outline-danger" 
                                                                title="Supprimer"
                                                                onclick="confirmDelete('<?= htmlspecialchars($backup['filename']) ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informations -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Informations importantes</h6>
                            <ul class="mb-0">
                                <li>Les sauvegardes sont automatiquement limitées à <strong>10 fichiers</strong> maximum</li>
                                <li>Les anciennes sauvegardes sont automatiquement supprimées</li>
                                <li>Le dossier de sauvegarde est sécurisé et non accessible depuis le web</li>
                                <li>Il est recommandé de télécharger et stocker les sauvegardes en lieu sûr</li>
                            </ul>
                        </div>
                    </div>
                </div>

<?php app_parametres_shell_end(); ?>

    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer cette sauvegarde ?</p>
                    <p class="text-danger"><strong>Cette action est irréversible !</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" value="delete_backup">
                        <input type="hidden" name="filename" id="deleteFilename">
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(filename) {
            document.getElementById('deleteFilename').value = filename;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
<?php app_layout_end(['minimal_scripts' => true]); ?>
