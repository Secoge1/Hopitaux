<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('assurances'));

require_once __DIR__ . '/../models/Assurance.php';

$assuranceModel = new Assurance();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $assurance = $assuranceModel->getById($id);
    if (!$assurance) {
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
        if ($assuranceModel->hardDelete($id)) {
            header("Location: index.php?deleted=1");
            exit;
        } else {
            $detail = $assuranceModel->getLastDeleteError();
            $error = 'Erreur lors de la suppression' . ($detail !== '' ? ' : ' . $detail : '.');
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

?>
<?php
app_module_page_start([
    'active'   => 'assurances',
    'title'    => 'Supprimer Assurance',
    'subtitle' => 'Confirmation de suppression',
    'icon'     => 'fa-shield-alt',
]);
app_module_back_toolbar(app_url('assurances/voir.php?id=' . $id), 'Annuler');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Supprimer l'Assurance</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Attention !</h6>
                    <p>Vous êtes sur le point de supprimer l'assurance suivante :</p>
                    <ul>
                        <li><strong>Nom:</strong> <?php echo htmlspecialchars($assurance['nom']); ?></li>
                        <li><strong>Type:</strong> <?php echo ucfirst($assurance['type']); ?></li>
                    </ul>
                    <p class="mb-0"><strong>Note:</strong> Cette action est irréversible. Toutes les données associées seront supprimées.</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="confirm" value="1">
                    <div class="d-flex justify-content-between">
                        <a href="voir.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette assurance ?');">
                            <i class="fas fa-trash me-2"></i>Confirmer la suppression
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
ob_start();
?>
<script src="../assets/js/auto-responsive.js"></script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
