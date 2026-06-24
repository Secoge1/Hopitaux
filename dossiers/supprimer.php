<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('dossiers'));

require_once '../models/Dossier.php';

$dossierModel = new Dossier();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$dossier = $dossierModel->getById($id);
if (!$dossier) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';
$deleted = false;

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        if ($dossierModel->delete($id)) {
            $deleted = true;
            $message = "Le dossier a été supprimé avec succès.";
        } else {
            $error = "Erreur lors de la suppression du dossier.";
        }
    } else {
        $error = "Veuillez confirmer la suppression.";
    }
}
?>
<?php
app_module_page_start([
    'active'   => 'dossiers',
    'title'    => 'Supprimer Dossier',
    'subtitle' => 'Confirmation de suppression',
    'icon'     => 'fa-folder',
]);
app_module_back_toolbar(app_url('dossiers/voir.php?id=' . $id), 'Annuler');
app_module_flash();
?>
<?php if ($deleted): ?>
    <div class="card border-success">
        <div class="card-body text-center py-5">
            <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
            <h4>Dossier supprimé</h4>
            <p class="text-muted"><?php echo htmlspecialchars($message); ?></p>
            <a href="<?php echo htmlspecialchars(app_url('dossiers/index.php')); ?>" class="btn btn-primary">
                <i class="fas fa-list me-2"></i>Retour aux dossiers
            </a>
        </div>
    </div>
<?php else: ?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>Dossier à supprimer</h5>
    </div>
    <div class="card-body">
        <div class="row">
                        <div class="col-md-8">
                            <h6>Détails du dossier :</h6>
                            <ul class="list-unstyled">
                                <li><strong>Patient :</strong> <?php echo htmlspecialchars($dossier['nom'] . ' ' . $dossier['prenom']); ?></li>
                                <li><strong>Numéro de dossier :</strong> <?php echo htmlspecialchars($dossier['numero_dossier']); ?></li>
                                <li><strong>Date de création :</strong> <?php echo date('d/m/Y H:i', strtotime($dossier['date_creation'])); ?></li>
                                <li><strong>Groupe sanguin :</strong> <?php echo htmlspecialchars($dossier['groupe_sanguin'] ?? 'Non spécifié'); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="status-badge status-<?php echo $dossier['statut']; ?>">
                                <?php echo ucfirst($dossier['statut']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zone de danger -->
            <div class="card danger-zone">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Zone de Danger</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Attention !</h6>
                        <p class="mb-0">
                            Vous êtes sur le point de supprimer définitivement ce dossier patient. 
                            Cette action est <strong>irréversible</strong> et supprimera :
                        </p>
                        <ul class="mb-0 mt-2">
                            <li>Toutes les informations médicales du dossier</li>
                            <li>Les antécédents et allergies</li>
                            <li>L'historique des modifications</li>
                            <li>Les notes et observations</li>
                        </ul>
                    </div>

                    <form method="POST" action="" id="deleteForm">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirm_delete" name="confirm_delete" value="yes" required>
                                <label class="form-check-label" for="confirm_delete">
                                    <strong>Je confirme que je veux supprimer définitivement ce dossier</strong>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="delete_reason" class="form-label">Raison de la suppression (optionnel) :</label>
                            <textarea class="form-control" id="delete_reason" name="delete_reason" rows="3" 
                                      placeholder="Expliquez pourquoi ce dossier doit être supprimé..."></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="voir.php?id=<?php echo $dossier['id']; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-danger" id="deleteBtn" disabled>
                                <i class="fas fa-trash me-2"></i>Supprimer Définitivement
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Actions alternatives -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Alternatives à la Suppression</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6><i class="fas fa-edit me-2 text-warning"></i>Modifier le dossier</h6>
                            <p class="text-muted">Au lieu de supprimer, vous pouvez modifier les informations du dossier.</p>
                            <a href="modifier.php?id=<?php echo $dossier['id']; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit me-2"></i>Modifier
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6><i class="fas fa-archive me-2 text-secondary"></i>Archiver le dossier</h6>
                            <p class="text-muted">Vous pouvez changer le statut à "archivé" au lieu de supprimer.</p>
                            <a href="modifier.php?id=<?php echo $dossier['id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-archive me-2"></i>Archiver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
<?php endif; ?>
<?php
ob_start();
?>
<script src="assets/js/auto-responsive.js"></script>
<script>
        // Activer/désactiver le bouton de suppression selon la confirmation
        document.getElementById('confirm_delete').addEventListener('change', function() {
            const deleteBtn = document.getElementById('deleteBtn');
            deleteBtn.disabled = !this.checked;
        });

        // Confirmation supplémentaire avant soumission
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            if (!confirm('Êtes-vous ABSOLUMENT sûr de vouloir supprimer ce dossier ? Cette action est irréversible !')) {
                e.preventDefault();
            }
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
