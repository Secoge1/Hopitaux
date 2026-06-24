<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';

app_parametres_require_admin();
extract(app_prepare_context());

require_once __DIR__ . '/../models/TarifAnalyseLaboratoire.php';

$tarifModel = new TarifAnalyseLaboratoire();
$message = '';
$error = '';
$currencyLabel = function_exists('app_currency_label') ? app_currency_label() : 'FCFA';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create':
                $data = [
                    'code' => $_POST['code'] ?? '',
                    'libelle' => $_POST['libelle'] ?? '',
                    'prix' => $_POST['prix'] ?? 0,
                    'description' => $_POST['description'] ?? null,
                    'ordre' => $_POST['ordre'] ?? 0,
                    'statut' => $_POST['statut'] ?? 'actif',
                ];
                if ($tarifModel->create($data)) {
                    $message = 'Type d\'analyse ajouté avec succès.';
                } else {
                    $error = 'Impossible d\'ajouter ce type (code invalide ou déjà utilisé).';
                }
                break;

            case 'update':
                $id = (int) ($_POST['id'] ?? 0);
                $data = [
                    'libelle' => $_POST['libelle'] ?? '',
                    'prix' => $_POST['prix'] ?? 0,
                    'description' => $_POST['description'] ?? null,
                    'ordre' => $_POST['ordre'] ?? 0,
                    'statut' => $_POST['statut'] ?? 'actif',
                ];
                if ($tarifModel->update($id, $data)) {
                    $message = 'Tarif modifié avec succès.';
                } else {
                    $error = 'Erreur lors de la modification du tarif.';
                }
                break;

            case 'delete':
                $id = (int) ($_POST['id'] ?? 0);
                $row = $tarifModel->getById($id);
                if (!$row) {
                    $error = 'Tarif introuvable.';
                } elseif ($tarifModel->countUsages($row['code']) > 0) {
                    $error = 'Impossible de supprimer : ce type est utilisé par des analyses existantes. Passez-le en « inactif ».';
                } elseif ($tarifModel->delete($id)) {
                    $message = 'Type d\'analyse supprimé.';
                } else {
                    $error = 'Erreur lors de la suppression.';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Erreur : ' . $e->getMessage();
    }
}

$tarifs = $tarifModel->getAll();
$actifs = count(array_filter($tarifs, fn($t) => $t['statut'] === 'actif'));

$actionsHtml = '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTarifModal">'
    . '<i class="fas fa-plus me-1"></i>Ajouter un type</button>';

app_head('Tarifs laboratoire', ['assets/css/app-parametres.css'], 'app-parametres-page');
app_layout_start(['active' => 'parametres', 'skip_page_header' => true]);
app_parametres_shell_start(
    'tarifs_labo',
    'Tarifs laboratoire',
    'Types d\'analyses et prix par défaut pour le module Laboratoire',
    $actionsHtml
);
?>

<div class="param-card">
    <div class="param-card-body">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="alert alert-info text-center mb-0">
                    <h4 class="mb-0"><?= count($tarifs) ?></h4>
                    <small>Types au catalogue</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-success text-center mb-0">
                    <h4 class="mb-0"><?= $actifs ?></h4>
                    <small>Types actifs</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-secondary text-center mb-0">
                    <h4 class="mb-0"><?= count($tarifs) - $actifs ?></h4>
                    <small>Types inactifs</small>
                </div>
            </div>
        </div>

        <p class="text-muted small mb-3">
            Le code technique (ex. <code>sang</code>) est utilisé dans les analyses. Il ne peut pas être modifié après création.
            Les prix suggérés s'appliquent automatiquement lors d'une nouvelle demande d'analyse.
        </p>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>Libellé</th>
                        <th>Code</th>
                        <th>Prix (<?= htmlspecialchars($currencyLabel) ?>)</th>
                        <th>Ordre</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tarifs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="fas fa-flask fa-3x mb-3 d-block"></i>
                                Aucun type configuré. Les valeurs par défaut seront créées au premier accès.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tarifs as $tarif): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($tarif['libelle']) ?></strong>
                                    <?php if (!empty($tarif['description'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($tarif['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= htmlspecialchars($tarif['code']) ?></code></td>
                                <td><strong><?= number_format((float) $tarif['prix'], 0, ',', ' ') ?></strong></td>
                                <td><?= (int) $tarif['ordre'] ?></td>
                                <td>
                                    <?php if ($tarif['statut'] === 'actif'): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick='editTarif(<?= json_encode($tarif, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="deleteTarif(<?= (int) $tarif['id'] ?>, '<?= htmlspecialchars($tarif['libelle'], ENT_QUOTES) ?>')">
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

<div class="modal fade" id="addTarifModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Ajouter un type d'analyse</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Code technique *</label>
                                <input type="text" class="form-control" name="code" required
                                       pattern="[a-z0-9_]+" title="Lettres minuscules, chiffres et underscore"
                                       placeholder="ex: serologie">
                                <small class="text-muted">Non modifiable ensuite</small>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Libellé affiché *</label>
                                <input type="text" class="form-control" name="libelle" required
                                       placeholder="Ex: Sérologie">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Prix (<?= htmlspecialchars($currencyLabel) ?>) *</label>
                                <input type="number" class="form-control" name="prix" required min="0" step="100" value="5000">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Ordre d'affichage</label>
                                <input type="number" class="form-control" name="ordre" value="100" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
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
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editTarifModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier le tarif</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Code technique</label>
                        <input type="text" class="form-control" id="edit_code" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Libellé affiché *</label>
                        <input type="text" class="form-control" name="libelle" id="edit_libelle" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Prix (<?= htmlspecialchars($currencyLabel) ?>) *</label>
                                <input type="number" class="form-control" name="prix" id="edit_prix" required min="0" step="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Ordre</label>
                                <input type="number" class="form-control" name="ordre" id="edit_ordre" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
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
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function editTarif(tarif) {
    document.getElementById('edit_id').value = tarif.id;
    document.getElementById('edit_code').value = tarif.code;
    document.getElementById('edit_libelle').value = tarif.libelle;
    document.getElementById('edit_prix').value = tarif.prix;
    document.getElementById('edit_ordre').value = tarif.ordre;
    document.getElementById('edit_statut').value = tarif.statut;
    document.getElementById('edit_description').value = tarif.description || '';
    new bootstrap.Modal(document.getElementById('editTarifModal')).show();
}

function deleteTarif(id, libelle) {
    if (confirm('Supprimer le type « ' + libelle + ' » ?\n\nImpossible si des analyses l\'utilisent déjà.')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
<?php app_layout_end(['minimal_scripts' => true]); ?>
