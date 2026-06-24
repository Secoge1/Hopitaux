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
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        if ($personnelModel->delete($id)) {
            header("Location: index.php?deleted=1");
            exit;
        } else {
            $error = "Erreur lors de la suppression.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

?>
<?php
app_module_page_start([
    'active'   => 'personnel',
    'title'    => 'Supprimer le Personnel',
    'subtitle' => 'Confirmation de désactivation',
    'icon'     => 'fa-user-tie',
]);
app_module_back_toolbar(app_url('personnel/voir.php?id=' . $id), 'Annuler');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Supprimer le Personnel</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Attention !</h6>
                    <p>Vous êtes sur le point de désactiver le membre du personnel suivant :</p>
                    <ul>
                        <li><strong>Nom:</strong> <?php echo htmlspecialchars($personnel['nom'] . ' ' . $personnel['prenom']); ?></li>
                        <li><strong>Numéro:</strong> <?php echo htmlspecialchars($personnel['numero_employe']); ?></li>
                        <li><strong>Poste:</strong> <?php echo htmlspecialchars($personnel['poste']); ?></li>
                    </ul>
                    <p class="mb-0"><strong>Note:</strong> Cette action désactivera le membre (soft delete). Les données seront conservées mais le statut sera changé en "inactif".</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="confirm" value="1">
                    <div class="d-flex justify-content-between">
                        <a href="voir.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir désactiver ce membre du personnel ?');">
                            <i class="fas fa-trash me-2"></i>Confirmer la suppression
                        </button>
                    </div>
                </form>
            </div>
        </div>
<?php app_module_page_end(); ?>
