<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('personnel'));

require_once __DIR__ . '/../models/Personnel.php';

$personnelModel = new Personnel();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $personnel = $personnelModel->getById($id);
    if (!$personnel) {
        header("Location: index.php");
        exit;
    }
    $conges = $personnelModel->getConges($id);
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

?>
<?php
app_module_page_start([
    'active'   => 'personnel',
    'title'    => 'Congés',
    'subtitle' => $personnel['nom'] . ' ' . $personnel['prenom'],
    'icon'     => 'fa-user-tie',
]);
app_module_back_toolbar(app_url('personnel/voir.php?id=' . $id), 'Retour à la fiche', [
    ['href' => app_url('personnel/conge_ajouter.php?id=' . $id), 'label' => 'Ajouter un congé', 'icon' => 'fa-plus', 'class' => 'btn-primary'],
    ['href' => app_url('personnel/index.php'), 'label' => 'Liste du personnel', 'icon' => 'fa-list', 'class' => 'btn-outline-secondary']
]);
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Historique des congés</h5>
            </div>
            <div class="card-body">
                <?php if (empty($conges)): ?>
                    <p class="text-muted mb-0">Aucun congé enregistré.</p>
                    <a href="conge_ajouter.php?id=<?php echo $id; ?>" class="btn btn-primary btn-sm mt-2">
                        <i class="fas fa-plus me-1"></i>Ajouter un congé
                    </a>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Date début</th>
                                    <th>Date fin</th>
                                    <th>Jours</th>
                                    <th>Statut</th>
                                    <th>Motif</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conges as $conge): ?>
                                <tr>
                                    <td><strong><?php echo ucfirst(str_replace('_', ' ', $conge['type_conge'])); ?></strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($conge['date_debut'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($conge['date_fin'])); ?></td>
                                    <td><?php echo (int)$conge['nombre_jours']; ?> jours</td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $conge['statut'] === 'approuve' ? 'success' :
                                                ($conge['statut'] === 'refuse' ? 'danger' : 'warning');
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $conge['statut'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($conge['motif'] ?? '-'); ?></td>
                                </tr>
                                <?php if (!empty($conge['notes'])): ?>
                                <tr class="table-light">
                                    <td colspan="6" class="small text-muted py-1">
                                        <i class="fas fa-sticky-note me-1"></i><?php echo nl2br(htmlspecialchars($conge['notes'])); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php app_module_page_end(); ?>
