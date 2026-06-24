<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';

app_parametres_require_admin();
extract(app_prepare_context());

require_once __DIR__ . '/../models/SoinsConsultation.php';

$soinsModel = new SoinsConsultation();
$message = '';
$error = '';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    $data = [
                        'nom' => $_POST['nom'],
                        'description' => $_POST['description'] ?? null,
                        'prix' => $_POST['prix'],
                        'type_soin' => $_POST['type_soin'],
                        'duree_minutes' => $_POST['duree_minutes'] ?? 30,
                        'statut' => $_POST['statut'] ?? 'actif'
                    ];
                    
                    if ($soinsModel->create($data)) {
                        $message = "Soin ajouté avec succès !";
                    } else {
                        $error = "Erreur lors de l'ajout du soin.";
                    }
                    break;
                    
                case 'update':
                    $id = $_POST['id'];
                    $data = [
                        'nom' => $_POST['nom'],
                        'description' => $_POST['description'] ?? null,
                        'prix' => $_POST['prix'],
                        'type_soin' => $_POST['type_soin'],
                        'duree_minutes' => $_POST['duree_minutes'] ?? 30,
                        'statut' => $_POST['statut']
                    ];
                    
                    if ($soinsModel->update($id, $data)) {
                        $message = "Soin modifié avec succès !";
                    } else {
                        $error = "Erreur lors de la modification du soin.";
                    }
                    break;
                    
                case 'delete':
                    $id = $_POST['id'];
                    if ($soinsModel->delete($id)) {
                        $message = "Soin supprimé avec succès !";
                    } else {
                        $error = "Erreur lors de la suppression du soin.";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer tous les soins
$soins = $soinsModel->getAll();
$types_disponibles = ['pansement', 'injection', 'perfusion', 'suture', 'examen', 'soins_intensifs', 'petit_chirurgie', 'autre'];

$actionsHtml = '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSoinModal"><i class="fas fa-plus me-1"></i>Ajouter un soin</button>';

app_head('Soins', ['assets/css/app-parametres.css'], 'app-parametres-page');
app_layout_start(['active' => 'parametres', 'skip_page_header' => true]);
app_parametres_shell_start('soins', 'Catalogue des soins', 'Soins facturables et tarifs associés', $actionsHtml);
?>

<div class="param-card">
    <div class="param-card-body">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistiques rapides -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="alert alert-info text-center">
                            <h4 class="mb-0"><?php echo count($soins); ?></h4>
                            <small>Soins au catalogue</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-success text-center">
                            <h4 class="mb-0"><?php echo count(array_filter($soins, function($s) { return $s['statut'] === 'actif'; })); ?></h4>
                            <small>Soins actifs</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-warning text-center">
                            <h4 class="mb-0"><?php echo count(array_filter($soins, function($s) { return $s['statut'] === 'inactif'; })); ?></h4>
                            <small>Soins inactifs</small>
                        </div>
                    </div>
                </div>
                
                <!-- Table des soins -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-success">
                            <tr>
                                <th>Nom du soin</th>
                                <th>Type</th>
                                <th>Prix (FCFA)</th>
                                <th>Durée</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($soins)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        Aucun soin enregistré. Cliquez sur "Ajouter un soin" pour commencer.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($soins as $soin): ?>
                                    <tr class="soin-row">
                                        <td>
                                            <strong><?php echo htmlspecialchars($soin['nom']); ?></strong>
                                            <?php if (!empty($soin['description'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($soin['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary type-badge">
                                                <?php echo ucfirst(str_replace('_', ' ', $soin['type_soin'])); ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo number_format($soin['prix'], 0, ',', ' '); ?></strong> FCFA</td>
                                        <td><?php echo $soin['duree_minutes']; ?> min</td>
                                        <td>
                                            <?php if ($soin['statut'] === 'actif'): ?>
                                                <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editSoin(<?php echo htmlspecialchars(json_encode($soin)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteSoin(<?php echo $soin['id']; ?>, '<?php echo htmlspecialchars($soin['nom']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
    </div>
</div>

<?php app_parametres_shell_end(); ?>

    <!-- Modal Ajout -->
    <div class="modal fade" id="addSoinModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Ajouter un nouveau soin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Nom du soin *</label>
                                    <input type="text" class="form-control" name="nom" required 
                                           placeholder="Ex: Pansement simple, Injection intramusculaire">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Prix (FCFA) *</label>
                                    <input type="number" class="form-control" name="prix" required min="0" step="100">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Type de soin *</label>
                                    <select class="form-select" name="type_soin" required>
                                        <option value="">Sélectionner...</option>
                                        <?php foreach ($types_disponibles as $type): ?>
                                            <option value="<?php echo $type; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Durée (min)</label>
                                    <input type="number" class="form-control" name="duree_minutes" value="30" min="5" max="240">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Statut</label>
                                    <select class="form-select" name="statut">
                                        <option value="actif">Actif</option>
                                        <option value="inactif">Inactif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description (optionnel)</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Description détaillée du soin..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Modification -->
    <div class="modal fade" id="editSoinModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier un soin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Nom du soin *</label>
                                    <input type="text" class="form-control" name="nom" id="edit_nom" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Prix (FCFA) *</label>
                                    <input type="number" class="form-control" name="prix" id="edit_prix" required min="0" step="100">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Type de soin *</label>
                                    <select class="form-select" name="type_soin" id="edit_type_soin" required>
                                        <?php foreach ($types_disponibles as $type): ?>
                                            <option value="<?php echo $type; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Durée (min)</label>
                                    <input type="number" class="form-control" name="duree_minutes" id="edit_duree" min="5" max="240">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Statut</label>
                                    <select class="form-select" name="statut" id="edit_statut">
                                        <option value="actif">Actif</option>
                                        <option value="inactif">Inactif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description (optionnel)</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Mettre à jour
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Form de suppression (caché) -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>
    
    <script>
        function editSoin(soin) {
            document.getElementById('edit_id').value = soin.id;
            document.getElementById('edit_nom').value = soin.nom;
            document.getElementById('edit_prix').value = soin.prix;
            document.getElementById('edit_type_soin').value = soin.type_soin;
            document.getElementById('edit_duree').value = soin.duree_minutes;
            document.getElementById('edit_statut').value = soin.statut;
            document.getElementById('edit_description').value = soin.description || '';
            
            new bootstrap.Modal(document.getElementById('editSoinModal')).show();
        }
        
        function deleteSoin(id, nom) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer le soin "${nom}" ?\n\nCette action est irréversible.`)) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
<?php app_layout_end(['minimal_scripts' => true]); ?>
