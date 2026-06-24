<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('medecins'));

if (!$auth->estAdmin()) {
    header('Location: ' . app_url('access_denied.php'));
    exit;
}

require_once __DIR__ . '/../models/Medecin.php';
$medecinModel = new Medecin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$medecin = $medecinModel->getById($id);
if (!$medecin) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        if ($medecinModel->delete($id)) {
            require_once __DIR__ . '/../includes/CacheSystem.php';
            CacheSystem::getInstance()->invalidateDashboardCache();
            $message = "Médecin supprimé avec succès !";
            // Rediriger après 2 secondes
            header("refresh:2;url=index.php");
        } else {
            $error = "Erreur lors de la suppression du médecin.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

app_module_page_start([
    'active'   => 'medecins',
    'title'    => 'Supprimer un Médecin',
    'subtitle' => 'Confirmation de suppression définitive',
    'icon'     => 'fa-user-times',
]);
app_module_back_toolbar(app_url('medecins/index.php'));
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
                            <h5>Médecin à supprimer :</h5>
                            <ul class="list-unstyled">
                                <li><strong>Nom :</strong> <?php echo htmlspecialchars($medecin['prenom'] . ' ' . $medecin['nom']); ?></li>
                                <li><strong>Numéro de licence :</strong> <?php echo $medecin['numero_licence'] ? htmlspecialchars($medecin['numero_licence']) : 'Non renseigné'; ?></li>
                                <li><strong>Spécialité :</strong> <?php echo $medecin['specialite'] ? htmlspecialchars($medecin['specialite']) : 'Non renseigné'; ?></li>
                                <li><strong>Statut :</strong> <?php echo ucfirst($medecin['statut']); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-user-md text-danger" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>

                    <form method="POST" class="text-center">
                        <input type="hidden" name="confirm_delete" value="1">
                        <button type="submit" class="btn btn-danger btn-lg me-3" onclick="return confirm('Êtes-vous absolument sûr de vouloir supprimer ce médecin ?')">
                            <i class="fas fa-trash me-2"></i>Confirmer la suppression
                        </button>
                        <a href="voir.php?id=<?php echo $medecin['id']; ?>" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                    </form>
                </div>
            </div>
        <?php endif; ?>

<?php app_module_page_end();





