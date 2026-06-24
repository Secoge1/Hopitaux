<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('laboratoire'));

require_once __DIR__ . '/../models/Analyse.php';

$analyseModel = new Analyse();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$analyse = $analyseModel->getById($id);
if (!$analyse) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';
$deleted = false;

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!validateCSRFToken($csrfToken)) {
        $error = 'Session expirée ou requête invalide. Veuillez réessayer.';
    } elseif (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        $analyseCheck = $analyseModel->getById($id);
        if (!$analyseCheck) {
            header('Location: index.php');
            exit();
        }
        if ($analyseModel->delete($id)) {
            $deleted = true;
            $message = "L'analyse a été supprimée avec succès.";
        } else {
            $error = "Erreur lors de la suppression de l'analyse.";
        }
    } else {
        $error = "Veuillez confirmer la suppression.";
    }
}

$csrfToken = generateCSRFToken();

app_module_page_start([
    'active'   => 'laboratoire',
    'title'    => 'Supprimer l\'Analyse',
    'subtitle' => 'Confirmation de suppression',
    'icon'     => 'fa-flask',
]);
app_module_back_toolbar(app_url('laboratoire/index.php'), 'Retour à la liste', []);
app_module_flash();
?>
<style>
.status-badge { padding: 8px 16px; border-radius: 25px; font-size: 0.9rem; font-weight: 500; }
        .status-en_attente { background: #fff3cd; color: #856404; }
        .status-en_cours { background: #d1ecf1; color: #0c5460; }
        .status-termine { background: #d4edda; color: #155724; }
        .status-annule { background: #f8d7da; color: #721c24; }
        .priorite-normale { background: #6c757d; color: white; }
        .priorite-urgente { background: #ffc107; color: black; }
        .priorite-critique { background: #dc3545; color: white; }
        .danger-zone { border: 2px solid #dc3545; border-radius: 10px; }
</style>

        <!-- Messages d'alerte -->
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

        <?php if ($deleted): ?>
            <!-- Message de succès après suppression -->
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Analyse Supprimée</h5>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 text-success">Analyse supprimée avec succès !</h4>
                    <p class="text-muted">L'analyse #<?php echo $analyse['id']; ?> a été définitivement supprimée de la base de données.</p>
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-list me-2"></i>Retour à la liste
                        </a>
                        <a href="ajouter.php" class="btn btn-success btn-lg">
                            <i class="fas fa-plus me-2"></i>Nouvelle analyse
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Informations de l'analyse à supprimer -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Analyse à Supprimer</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6>Détails de l'analyse :</h6>
                            <ul class="list-unstyled">
                                <li><strong>Type :</strong> <?php echo htmlspecialchars($analyse['type_analyse']); ?></li>
                                <li><strong>Patient :</strong> <?php echo htmlspecialchars($analyse['patient_nom'] . ' ' . $analyse['patient_prenom']); ?></li>
                                <li><strong><?= htmlspecialchars(medecin_profil_attribution_label_from_row($analyse)) ?> :</strong> <?php echo htmlspecialchars(medecin_profil_format_joined($analyse)); ?></li>
                                <li><strong>Date de création :</strong> <?php echo date('d/m/Y H:i', strtotime($analyse['date_creation'])); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="status-badge status-<?php echo $analyse['statut']; ?>">
                                <?php echo ucfirst($analyse['statut']); ?>
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
                            Vous êtes sur le point de supprimer définitivement cette analyse. 
                            Cette action est <strong>irréversible</strong> et supprimera :
                        </p>
                        <ul class="mb-0 mt-2">
                            <li>Toutes les informations de l'analyse</li>
                            <li>Les résultats associés</li>
                            <li>L'historique de l'analyse</li>
                        </ul>
                    </div>

                    <form method="POST" action="" id="deleteForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirm_delete" name="confirm_delete" value="yes" required>
                                <label class="form-check-label" for="confirm_delete">
                                    <strong>Je confirme que je veux supprimer définitivement cette analyse</strong>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="delete_reason" class="form-label">Raison de la suppression (optionnel) :</label>
                            <textarea class="form-control" id="delete_reason" name="delete_reason" rows="3" 
                                      placeholder="Expliquez pourquoi cette analyse doit être supprimée..."></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="voir.php?id=<?php echo $analyse['id']; ?>" class="btn btn-outline-secondary">
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
                            <h6><i class="fas fa-edit me-2 text-warning"></i>Modifier l'analyse</h6>
                            <p class="text-muted">Au lieu de supprimer, vous pouvez modifier les informations de l'analyse.</p>
                            <a href="modifier.php?id=<?php echo $analyse['id']; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit me-2"></i>Modifier
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6><i class="fas fa-archive me-2 text-secondary"></i>Archiver l'analyse</h6>
                            <p class="text-muted">Vous pouvez changer le statut à "annulé" au lieu de supprimer.</p>
                            <a href="modifier.php?id=<?php echo $analyse['id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-archive me-2"></i>Archiver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
<?php if (!$deleted): ob_start(); ?>
<script>
        document.getElementById('confirm_delete').addEventListener('change', function() {
            document.getElementById('deleteBtn').disabled = !this.checked;
        });
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            if (!confirm('Êtes-vous ABSOLUMENT sûr de vouloir supprimer cette analyse ? Cette action est irréversible !')) {
                e.preventDefault();
            }
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
endif;
app_module_page_end();
