<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('rdv'));

require_once __DIR__ . '/../models/RendezVous.php';
$rdvModel = new RendezVous();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$rdv = $rdvModel->getById($id);
if (!$rdv) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        if ($rdvModel->delete($id)) {
            $message = "Rendez-vous supprimé avec succès !";
            // Rediriger après 2 secondes
            header("refresh:2;url=index.php");
        } else {
            $error = "Erreur lors de la suppression du rendez-vous.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

app_module_page_start([
    'active'   => 'rdv',
    'title'    => 'Supprimer un Rendez-vous',
    'subtitle' => 'Confirmation de suppression définitive',
    'icon'     => 'fa-calendar-times',
]);
app_module_back_toolbar(app_url('rendez-vous/index.php'));
app_module_flash();
?>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                <br><small>Redirection automatique dans 2 secondes...</small>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!$message && !$error): ?>
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Confirmation de suppression</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <strong>Attention !</strong> Cette action est irréversible.
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-8">
                            <h5>Rendez-vous à supprimer :</h5>
                            <ul class="list-unstyled">
                                <li><strong>Patient :</strong> <?php echo htmlspecialchars($rdv['patient_prenom'] . ' ' . $rdv['patient_nom']); ?></li>
                                <li><strong><?= htmlspecialchars(medecin_profil_attribution_label_from_row($rdv)) ?> :</strong> <?php echo htmlspecialchars(medecin_profil_format_joined($rdv)); ?></li>
                                <li><strong>Date :</strong> <?php echo date('d/m/Y', strtotime($rdv['date_rdv'])); ?></li>
                                <li><strong>Heure :</strong> <?php echo $rdv['heure_rdv']; ?></li>
                                <li><strong>Statut :</strong> <?php echo ucfirst($rdv['statut']); ?></li>
                                <?php if ($rdv['motif']): ?>
                                    <li><strong>Motif :</strong> <?php echo htmlspecialchars($rdv['motif']); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-calendar-times text-danger" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>

                    <form method="POST" class="text-center">
                        <input type="hidden" name="confirm_delete" value="1">
                        <button type="submit" class="btn btn-danger btn-lg me-3" onclick="return confirm('Êtes-vous absolument sûr de vouloir supprimer ce rendez-vous ?')">
                            <i class="fas fa-trash me-2"></i>Confirmer la suppression
                        </button>
                        <a href="voir.php?id=<?php echo $rdv['id']; ?>" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                    </form>
                </div>
            </div>
        <?php endif; ?>

<?php app_module_page_end();





