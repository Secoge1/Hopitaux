<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
module_require_write('finances');
extract(app_module_context('finances'));

require_once __DIR__ . '/../models/Finances.php';

$financesModel = new Finances();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $ecriture = $financesModel->getEcritureById($id);
    if (!$ecriture) {
        header("Location: index.php?error=ecriture_not_found");
        exit;
    }
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        if ($financesModel->deleteEcriture($id)) {
            header("Location: index.php?deleted=1");
            exit;
        } else {
            $error = "Erreur lors de la suppression de l'écriture.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

?>
<?php
app_module_page_start([
    'active'   => 'finances',
    'title'    => 'Supprimer Écriture',
    'subtitle' => 'Confirmation de suppression',
    'icon'     => 'fa-calculator',
]);
app_module_back_toolbar(app_url('finances/voir_ecriture.php?id=' . $id), 'Annuler');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Confirmation de Suppression</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Attention !</h6>
                    <p>Vous êtes sur le point de supprimer l'écriture comptable suivante :</p>
                    <ul>
                        <li><strong>Numéro:</strong> <?php echo htmlspecialchars($ecriture['numero_ecriture']); ?></li>
                        <li><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($ecriture['date_ecriture'])); ?></li>
                        <li><strong>Montant:</strong> <?php echo number_format($ecriture['montant'], 0, ',', ' '); ?> FCFA</li>
                        <li><strong>Compte Débit:</strong> <?php echo htmlspecialchars($ecriture['compte_debit_numero'] ?? ''); ?> - <?php echo htmlspecialchars($ecriture['compte_debit_libelle'] ?? ''); ?></li>
                        <li><strong>Compte Crédit:</strong> <?php echo htmlspecialchars($ecriture['compte_credit_numero'] ?? ''); ?> - <?php echo htmlspecialchars($ecriture['compte_credit_libelle'] ?? ''); ?></li>
                        <li><strong>Statut:</strong> 
                            <span class="badge bg-<?php echo $ecriture['valide'] ? 'success' : 'warning'; ?>">
                                <?php echo $ecriture['valide'] ? 'Validée' : 'En attente'; ?>
                            </span>
                        </li>
                        <?php if ($ecriture['libelle']): ?>
                        <li><strong>Libellé:</strong> <?php echo htmlspecialchars(substr($ecriture['libelle'], 0, 100)); ?><?php echo strlen($ecriture['libelle']) > 100 ? '...' : ''; ?></li>
                        <?php endif; ?>
                    </ul>
                    
                    <?php if ($ecriture['valide']): ?>
                    <div class="alert alert-danger mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Important :</strong> Cette écriture est validée. 
                        Sa suppression annulera automatiquement son impact sur les soldes des comptes concernés.
                    </div>
                    <?php endif; ?>
                    
                    <p class="mb-0 mt-3"><strong>Note:</strong> Cette action est irréversible. Toutes les données associées à cette écriture seront définitivement supprimées.</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="confirm" value="1">
                    <div class="d-flex justify-content-between">
                        <a href="voir_ecriture.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous absolument sûr de vouloir supprimer cette écriture ? Cette action est irréversible et affectera les soldes des comptes si l\'écriture est validée.');">
                            <i class="fas fa-trash me-2"></i>Confirmer la Suppression
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php app_module_page_end(); ?>
