<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('consultations'));

require_once __DIR__ . '/../models/Consultation.php';

$consultationModel = new Consultation();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$consultation = $consultationModel->getById($id);
if (!$consultation) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
        try {
            if ($consultationModel->delete($id)) {
                $message = "Consultation supprimée avec succès !";
                // Rediriger après 2 secondes
                header("refresh:2;url=index.php");
            } else {
                $error = "Erreur lors de la suppression de la consultation.";
            }
        } catch (Exception $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    } else {
        // Annulation
        header("Location: index.php");
        exit();
    }

}

app_module_page_start([
    'active'   => 'consultations',
    'title'    => 'Supprimer la Consultation',
    'subtitle' => 'Confirmation de suppression',
    'icon'     => 'fa-stethoscope',
]);
app_module_back_toolbar(app_url('consultations/index.php'), 'Retour à la liste', []);
app_module_flash();
?>
<style>
.danger-zone { border: 2px solid #dc3545; border-radius: 8px; }
        .consultation-info { background: #f8f9fa; border-left: 4px solid #17a2b8; }
</style>
<?php if ($message): ?>
            <div class="alert alert-success text-center">
                <i class="fas fa-check-circle fa-2x mb-3 d-block"></i>
                <h5><?php echo htmlspecialchars($message); ?></h5>
                <p class="mb-0">Redirection vers la liste des consultations...</p>
            </div>
        <?php else: ?>
            <!-- Avertissement de suppression -->
            <div class="alert alert-danger text-center mb-4">
                <i class="fas fa-exclamation-triangle fa-3x mb-3 d-block"></i>
                <h4>⚠️ ATTENTION : Suppression définitive</h4>
                <p class="mb-0">Cette action est irréversible et supprimera définitivement la consultation.</p>
            </div>

            <!-- Informations sur la consultation à supprimer -->
            <div class="card mb-4 consultation-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Consultation à supprimer</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>ID :</strong><br>
                            <span class="badge bg-secondary"><?php echo $consultation['id']; ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Date :</strong><br>
                            <span class="text-primary"><?php echo date('d/m/Y H:i', strtotime($consultation['date_consultation'])); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Patient :</strong><br>
                            <span class="text-primary"><?php echo htmlspecialchars($consultation['patient_prenom'] . ' ' . $consultation['patient_nom']); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong><?= htmlspecialchars(medecin_profil_attribution_label_from_row($consultation)) ?> :</strong><br>
                            <span class="text-success"><?php echo htmlspecialchars(medecin_profil_format_joined($consultation)); ?></span>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <strong>Statut :</strong><br>
                            <span class="badge bg-<?php echo $consultation['statut'] === 'termine' ? 'success' : ($consultation['statut'] === 'en_cours' ? 'warning' : 'info'); ?>">
                                <?php echo ucfirst($consultation['statut']); ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>Spécialité :</strong><br>
                            <span class="text-muted"><?php echo htmlspecialchars($consultation['medecin_specialite']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zone de danger -->
            <div class="card danger-zone">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Zone de Danger</h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="fas fa-trash-alt fa-4x text-danger mb-3"></i>
                        <h4 class="text-danger">Êtes-vous absolument sûr ?</h4>
                        <p class="text-muted">Cette action ne peut pas être annulée et supprimera définitivement :</p>
                        <ul class="text-start text-muted">
                            <li>Les informations de la consultation</li>
                            <li>Le diagnostic et le traitement</li>
                            <li>L'ordonnance prescrite</li>
                            <li>Toutes les données associées</li>
                        </ul>
                    </div>

                    <form method="POST" class="d-inline">
                        <button type="submit" name="confirm" value="yes" class="btn btn-danger btn-lg me-3" onclick="return confirm('Dernière chance : êtes-vous vraiment sûr de vouloir supprimer cette consultation ?')">
                            <i class="fas fa-trash me-2"></i>Oui, supprimer définitivement
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                    </form>
                </div>
            </div>

            <!-- Actions alternatives -->
            <div class="card mt-4">
                <div class="card-header bg-warning text-white">
                    <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Alternatives à la suppression</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-edit text-warning me-2"></i>Modifier au lieu de supprimer</h6>
                            <p class="text-muted">Vous pouvez modifier les informations de la consultation plutôt que de la supprimer complètement.</p>
                            <a href="modifier.php?id=<?php echo $consultation['id']; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit me-2"></i>Modifier
                            </a>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-eye text-info me-2"></i>Voir les détails</h6>
                            <p class="text-muted">Examinez d'abord tous les détails avant de prendre une décision finale.</p>
                            <a href="voir.php?id=<?php echo $consultation['id']; ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-eye me-2"></i>Voir
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
<?php app_module_page_end(); ?>
