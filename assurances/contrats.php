<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('assurances'));

require_once __DIR__ . '/../models/Assurance.php';

$assuranceModel = new Assurance();

$assurance_id = isset($_GET['assurance_id']) ? (int)$_GET['assurance_id'] : 0;

if (!$assurance_id) {
    header("Location: index.php");
    exit;
}

try {
    $assurance = $assuranceModel->getById($assurance_id);
    if (!$assurance) {
        header("Location: index.php");
        exit;
    }
    $contrats = $assuranceModel->getContrats($assurance_id);
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

?>
<?php
app_module_page_start([
    'active'   => 'assurances',
    'title'    => 'Contrats Assurance',
    'subtitle' => isset($assurance) ? $assurance['nom'] : 'Liste des contrats',
    'icon'     => 'fa-shield-alt',
]);
app_module_back_toolbar(app_url('assurances/index.php'), 'Retour à la liste', [
    ['href' => app_url('assurances/ajouter_contrat.php' . (isset($assurance_id) && $assurance_id ? '?assurance_id=' . $assurance_id : '')), 'label' => 'Nouveau contrat', 'icon' => 'fa-plus', 'class' => 'btn-primary']
]);
app_module_flash();
?>
<style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        
        
        
        
        
        
        .btn-light {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            backdrop-filter: blur(10px);
        }
        
        .btn-light:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        
        .card-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }
        
        .table {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table thead th {
            background: var(--primary-gradient);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }
        
        .table tbody tr {
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .btn-outline-primary i,
        .btn-outline-info i {
            font-size: 1.1rem;
        }
        
        .btn-outline-primary:hover i,
        .btn-outline-info:hover i {
            transform: scale(1.1);
            transition: transform 0.2s ease;
        }
    </style>

<div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-contract me-2"></i>Liste des Contrats</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($contrats)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-contract fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucun contrat enregistré pour cette assurance</p>
                                <a href="ajouter_contrat.php?assurance_id=<?php echo $assurance_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Créer le premier contrat
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong><?php echo count($contrats); ?> contrat(s)</strong> trouvé(s) pour cette assurance
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Numéro Contrat</th>
                                            <th>Patient</th>
                                            <th>Numéro Police</th>
                                            <th>Date Début</th>
                                            <th>Date Fin</th>
                                            <th>Taux Couverture</th>
                                            <th>Franchise</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contrats as $contrat): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($contrat['numero_contrat'] ?? 'N/A'); ?></strong>
                                            </td>
                                            <td>
                                                <?php if (!empty($contrat['patient_nom']) || !empty($contrat['patient_prenom'])): ?>
                                                    <?php echo htmlspecialchars(trim(($contrat['patient_prenom'] ?? '') . ' ' . ($contrat['patient_nom'] ?? ''))); ?>
                                                    <br>
                                                    <small class="text-muted">ID: <?php echo $contrat['patient_id']; ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Patient #<?php echo $contrat['patient_id']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($contrat['numero_police'] ?? '-'); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($contrat['date_debut'])); ?></td>
                                            <td>
                                                <?php if ($contrat['date_fin']): ?>
                                                    <?php echo date('d/m/Y', strtotime($contrat['date_fin'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Indéfini</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($contrat['taux_couverture'])): ?>
                                                    <?php echo number_format($contrat['taux_couverture'], 2, ',', ' '); ?>%
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($contrat['franchise'])): ?>
                                                    <?php echo number_format($contrat['franchise'], 0, ',', ' '); ?> FCFA
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $contrat['statut'] === 'actif' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($contrat['statut']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../patients/voir.php?id=<?php echo $contrat['patient_id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir le patient">
                                                    <i class="fas fa-user-circle"></i>
                                                </a>
                                                <?php if (!empty($contrat['notes'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info" title="Notes" data-bs-toggle="popover" data-bs-content="<?php echo htmlspecialchars($contrat['notes']); ?>">
                                                    <i class="fas fa-sticky-note"></i>
                                                </button>
                                                <?php endif; ?>
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
    </div>
<?php
ob_start();
?>
<script src="../assets/js/auto-responsive.js"></script>
<script>
        // Initialiser les popovers Bootstrap
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
