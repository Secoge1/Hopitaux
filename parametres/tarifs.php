<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';

app_parametres_require_admin();
extract(app_prepare_context());

require_once __DIR__ . '/../models/TarifConsultation.php';

$tarifModel = new TarifConsultation();
$message = '';
$error = '';

/**
 * Résout type / spécialité lorsque l’option « Autre » (valeur __autre__) est choisie.
 * @return array{0: string, 1: string|null}
 */
function tarifs_resolve_type_specialite_from_post() {
    $type = (string) ($_POST['type_consultation'] ?? '');
    if ($type === '__autre__') {
        $type = trim((string) ($_POST['type_consultation_autre'] ?? ''));
        if ($type === '') {
            throw new Exception('Veuillez préciser le type de consultation lorsque « Autre (préciser) » est sélectionné.');
        }
    }
    $spec = $_POST['specialite'] ?? '';
    if ($spec === '' || $spec === null) {
        $spec = null;
    } elseif ((string) $spec === '__autre__') {
        $spec = trim((string) ($_POST['specialite_autre'] ?? ''));
        if ($spec === '') {
            throw new Exception('Veuillez préciser la spécialité ou choisissez « Toutes les spécialités ».');
        }
    } else {
        $spec = (string) $spec;
    }
    return [$type, $spec];
}

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    list($typeResolu, $specResolu) = tarifs_resolve_type_specialite_from_post();
                    $data = [
                        'type_consultation' => $typeResolu,
                        'specialite' => $specResolu,
                        'prix' => $_POST['prix'],
                        'description' => $_POST['description'] ?? null,
                        'statut' => $_POST['statut'] ?? 'actif'
                    ];
                    
                    if ($tarifModel->create($data)) {
                        $message = "Tarif ajouté avec succès !";
                    } else {
                        $error = "Erreur lors de l'ajout du tarif.";
                    }
                    break;
                    
                case 'update':
                    $id = $_POST['id'];
                    list($typeResolu, $specResolu) = tarifs_resolve_type_specialite_from_post();
                    $data = [
                        'type_consultation' => $typeResolu,
                        'specialite' => $specResolu,
                        'prix' => $_POST['prix'],
                        'description' => $_POST['description'] ?? null,
                        'statut' => $_POST['statut']
                    ];
                    
                    if ($tarifModel->update($id, $data)) {
                        $message = "Tarif modifié avec succès !";
                    } else {
                        $error = "Erreur lors de la modification du tarif.";
                    }
                    break;
                    
                case 'delete':
                    $id = $_POST['id'];
                    if ($tarifModel->delete($id)) {
                        $message = "Tarif supprimé avec succès !";
                    } else {
                        $error = "Erreur lors de la suppression du tarif.";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer tous les tarifs
$tarifs = $tarifModel->getAll();
$types_consultation = ['normale', 'urgence', 'domicile', 'suivi', 'controle', 'specialiste'];
$specialites = ['generaliste', 'cardiologie', 'pediatrie', 'gynecologie', 'dermatologie', 'neurologie', 'ophtalmologie', 'oto-rhino-laryngologie', 'chirurgie'];

$actionsHtml = '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTarifModal"><i class="fas fa-plus me-1"></i>Ajouter un tarif</button>';

app_head('Tarifs', ['assets/css/app-parametres.css'], 'app-parametres-page');
app_layout_start(['active' => 'parametres', 'skip_page_header' => true]);
app_parametres_shell_start('tarifs', 'Tarifs de consultation', 'Grille tarifaire par type et spécialité', $actionsHtml);
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
                            <h4 class="mb-0"><?php echo count($tarifs); ?></h4>
                            <small>Tarifs configurés</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-success text-center">
                            <h4 class="mb-0"><?php echo count(array_filter($tarifs, function($t) { return $t['statut'] === 'actif'; })); ?></h4>
                            <small>Tarifs actifs</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-warning text-center">
                            <h4 class="mb-0"><?php echo count(array_filter($tarifs, function($t) { return $t['statut'] === 'inactif'; })); ?></h4>
                            <small>Tarifs inactifs</small>
                        </div>
                    </div>
                </div>
                
                <!-- Table des tarifs -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-info">
                            <tr>
                                <th>Type de consultation</th>
                                <th>Spécialité</th>
                                <th>Prix (FCFA)</th>
                                <th>Description</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tarifs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        Aucun tarif configuré. Cliquez sur "Ajouter un tarif" pour commencer.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tarifs as $tarif): ?>
                                    <tr class="tarif-row">
                                        <td>
                                            <span class="badge bg-primary type-badge">
                                                <i class="fas fa-stethoscope me-1"></i>
                                                <?php echo ucfirst($tarif['type_consultation']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($tarif['specialite']): ?>
                                                <span class="badge bg-secondary type-badge">
                                                    <?php echo ucfirst($tarif['specialite']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Toutes</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo number_format($tarif['prix'], 0, ',', ' '); ?></strong> FCFA</td>
                                        <td>
                                            <?php if (!empty($tarif['description'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($tarif['description']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">-</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($tarif['statut'] === 'actif'): ?>
                                                <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editTarif(<?php echo htmlspecialchars(json_encode($tarif)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteTarif(<?php echo $tarif['id']; ?>, '<?php echo htmlspecialchars($tarif['type_consultation']); ?>')">
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
    <div class="modal fade" id="addTarifModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Ajouter un nouveau tarif</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Type de consultation *</label>
                                    <select class="form-select" name="type_consultation" id="add_type_consultation" required>
                                        <option value="">Sélectionner...</option>
                                        <?php foreach ($types_consultation as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>">
                                                <?php echo ucfirst($type); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="__autre__">Autre (préciser)</option>
                                    </select>
                                    <div class="mt-2" id="add_wrap_type_autre" style="display: none;">
                                        <label class="form-label" for="add_type_consultation_autre">Précisez le type *</label>
                                        <input type="text" class="form-control" name="type_consultation_autre" id="add_type_consultation_autre"
                                               placeholder="Ex. Téléconsultation" maxlength="120" autocomplete="off">
                                    </div>
                                    <small class="text-muted d-block">Le type de consultation (normale, urgence, domicile...)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Spécialité (optionnel)</label>
                                    <select class="form-select" name="specialite" id="add_specialite">
                                        <option value="">Toutes les spécialités</option>
                                        <?php foreach ($specialites as $specialite): ?>
                                            <option value="<?php echo htmlspecialchars($specialite); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $specialite)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="__autre__">Autre (préciser)</option>
                                    </select>
                                    <div class="mt-2" id="add_wrap_specialite_autre" style="display: none;">
                                        <label class="form-label" for="add_specialite_autre">Précisez la spécialité *</label>
                                        <input type="text" class="form-control" name="specialite_autre" id="add_specialite_autre"
                                               placeholder="Ex. Infectiologie" maxlength="120" autocomplete="off">
                                    </div>
                                    <small class="text-muted">Laissez « Toutes les spécialités » pour appliquer partout</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Prix (FCFA) *</label>
                                    <input type="number" class="form-control" name="prix" required min="0" step="500" 
                                           placeholder="Ex: 10000">
                                </div>
                            </div>
                            <div class="col-md-6">
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
                                      placeholder="Description du tarif, conditions particulières..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Exemple :</strong> Pour une consultation normale chez un cardiologue à 15 000 FCFA, 
                            choisissez Type: "Normale", Spécialité: "Cardiologie", Prix: 15000
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-save me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Modification -->
    <div class="modal fade" id="editTarifModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier un tarif</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Type de consultation *</label>
                                    <select class="form-select" name="type_consultation" id="edit_type" required>
                                        <?php foreach ($types_consultation as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>">
                                                <?php echo ucfirst($type); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="__autre__">Autre (préciser)</option>
                                    </select>
                                    <div class="mt-2" id="edit_wrap_type_autre" style="display: none;">
                                        <label class="form-label" for="edit_type_consultation_autre">Précisez le type *</label>
                                        <input type="text" class="form-control" name="type_consultation_autre" id="edit_type_consultation_autre"
                                               maxlength="120" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Spécialité (optionnel)</label>
                                    <select class="form-select" name="specialite" id="edit_specialite">
                                        <option value="">Toutes les spécialités</option>
                                        <?php foreach ($specialites as $specialite): ?>
                                            <option value="<?php echo htmlspecialchars($specialite); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $specialite)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="__autre__">Autre (préciser)</option>
                                    </select>
                                    <div class="mt-2" id="edit_wrap_specialite_autre" style="display: none;">
                                        <label class="form-label" for="edit_specialite_autre">Précisez la spécialité *</label>
                                        <input type="text" class="form-control" name="specialite_autre" id="edit_specialite_autre"
                                               maxlength="120" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Prix (FCFA) *</label>
                                    <input type="number" class="form-control" name="prix" id="edit_prix" required min="0" step="500">
                                </div>
                            </div>
                            <div class="col-md-6">
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
        var TARIF_TYPES_PRESET = <?php echo json_encode($types_consultation, JSON_UNESCAPED_UNICODE); ?>;
        var TARIF_SPECS_PRESET = <?php echo json_encode($specialites, JSON_UNESCAPED_UNICODE); ?>;

        function syncAddTarifsChamps() {
            var t = document.getElementById('add_type_consultation');
            var wT = document.getElementById('add_wrap_type_autre');
            var iT = document.getElementById('add_type_consultation_autre');
            if (t && wT && iT) {
                var autreT = t.value === '__autre__';
                wT.style.display = autreT ? 'block' : 'none';
                iT.required = autreT;
                if (!autreT) iT.value = '';
            }
            var s = document.getElementById('add_specialite');
            var wS = document.getElementById('add_wrap_specialite_autre');
            var iS = document.getElementById('add_specialite_autre');
            if (s && wS && iS) {
                var autreS = s.value === '__autre__';
                wS.style.display = autreS ? 'block' : 'none';
                iS.required = autreS;
                if (!autreS) iS.value = '';
            }
        }

        function syncEditTarifsChamps() {
            var t = document.getElementById('edit_type');
            var wT = document.getElementById('edit_wrap_type_autre');
            var iT = document.getElementById('edit_type_consultation_autre');
            if (t && wT && iT) {
                var autreT = t.value === '__autre__';
                wT.style.display = autreT ? 'block' : 'none';
                iT.required = autreT;
            }
            var s = document.getElementById('edit_specialite');
            var wS = document.getElementById('edit_wrap_specialite_autre');
            var iS = document.getElementById('edit_specialite_autre');
            if (s && wS && iS) {
                var autreS = s.value === '__autre__';
                wS.style.display = autreS ? 'block' : 'none';
                iS.required = autreS;
            }
        }

        function editTarif(tarif) {
            document.getElementById('edit_id').value = tarif.id;
            var tc = tarif.type_consultation || '';
            if (TARIF_TYPES_PRESET.indexOf(tc) === -1) {
                document.getElementById('edit_type').value = '__autre__';
                document.getElementById('edit_type_consultation_autre').value = tc;
            } else {
                document.getElementById('edit_type').value = tc;
                document.getElementById('edit_type_consultation_autre').value = '';
            }
            var sp = tarif.specialite || '';
            if (sp && TARIF_SPECS_PRESET.indexOf(sp) === -1) {
                document.getElementById('edit_specialite').value = '__autre__';
                document.getElementById('edit_specialite_autre').value = sp;
            } else {
                document.getElementById('edit_specialite').value = sp;
                document.getElementById('edit_specialite_autre').value = '';
            }
            document.getElementById('edit_prix').value = tarif.prix;
            document.getElementById('edit_statut').value = tarif.statut;
            document.getElementById('edit_description').value = tarif.description || '';
            syncEditTarifsChamps();
            new bootstrap.Modal(document.getElementById('editTarifModal')).show();
        }
        
        function deleteTarif(id, type) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer ce tarif "${type}" ?\n\nCette action est irréversible.`)) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        (function () {
            var aT = document.getElementById('add_type_consultation');
            var aS = document.getElementById('add_specialite');
            if (aT) aT.addEventListener('change', syncAddTarifsChamps);
            if (aS) aS.addEventListener('change', syncAddTarifsChamps);
            var eT = document.getElementById('edit_type');
            var eS = document.getElementById('edit_specialite');
            if (eT) eT.addEventListener('change', syncEditTarifsChamps);
            if (eS) eS.addEventListener('change', syncEditTarifsChamps);
            var addModal = document.getElementById('addTarifModal');
            if (addModal) {
                addModal.addEventListener('hidden.bs.modal', function () {
                    var form = this.querySelector('form');
                    if (form) form.reset();
                    syncAddTarifsChamps();
                });
            }
        })();
    </script>
<?php app_layout_end(['minimal_scripts' => true]); ?>
