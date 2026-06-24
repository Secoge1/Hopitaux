<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('paiements'));

require_once '../config/config.php';
require_once '../models/Paiement.php';

$paiementModel = new Paiement();

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$paiementId = (int)$_GET['id'];

// Récupérer les détails du paiement avant suppression
$paiement = $paiementModel->getById($paiementId);

if (!$paiement) {
    header('Location: index.php');
    exit;
}

// Traitement de la suppression
$deleteBlocked = $paiementModel->isEncaisseVerrouille($paiement)
    || $paiementModel->isHistoriqueClos($paiement);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        if ($paiementModel->delete($paiementId)) {
            $message = "Le paiement a été supprimé avec succès !";
            $messageType = "success";
            $redirect = true;
        } else {
            $error = "Erreur lors de la suppression du paiement.";
            $messageType = "danger";
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        $messageType = "danger";
    }
}

// Fonctions utilitaires
function getTypePaiementLabel($type) {
    $types = [
        'carte' => 'Carte bancaire',
        'virement' => 'Virement bancaire',
        'especes' => 'Espèces',
        'cheque' => 'Chèque',
        'securite_sociale' => 'Sécurité sociale',
        'mutuelle' => 'Mutuelle',
        'mobile_money' => 'Mobile Money',
        'autre' => 'Autre'
    ];
    
    return $types[$type] ?? ucfirst($type);
}

function getStatutLabel($statut) {
    $statuts = [
        'en_attente' => 'En attente',
        'partiel' => 'Paiement partiel',
        'paye' => 'Payé',
        'annule' => 'Annulé',
        'rembourse' => 'Remboursé'
    ];
    
    return $statuts[$statut] ?? ucfirst($statut);
}

app_module_page_start([
    'active'   => 'paiements',
    'title'    => 'Supprimer le Paiement',
    'subtitle' => 'Confirmation de suppression',
    'icon'     => 'fa-credit-card',
]);
app_module_back_toolbar(app_url('paiements/index.php'), 'Retour à la liste', []);
app_module_flash();
?>
<style>
.danger-zone { border: 2px solid #dc3545; border-radius: 8px; }
        .paiement-info { background-color: #f8f9fa; border-radius: 8px; }
</style>
        <!-- En-tête -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-trash me-2 text-danger"></i>Supprimer le Paiement</h3>
            <div>
                <a href="voir.php?id=<?php echo $paiement['id']; ?>" class="btn btn-outline-info btn-sm me-2">
                    <i class="fas fa-eye me-1"></i>Voir
                </a>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Retour
                </a>
            </div>
        </div>

        <!-- Messages d'alerte -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            
            <?php if (isset($redirect) && $redirect): ?>
<?php ob_start(); ?>
<script>
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 2000);
                </script>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Informations du paiement à supprimer -->
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Paiement à Supprimer</h5>
            </div>
            <div class="card-body paiement-info">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Numéro de facture :</strong><br>
                        <span class="text-primary"><?php echo htmlspecialchars($paiement['numero_facture']); ?></span></p>
                        
                        <p><strong>Patient :</strong><br>
                        <?php echo htmlspecialchars($paiement['patient_nom'] . ' ' . $paiement['patient_prenom']); ?></p>
                        
                        <p><strong>Numéro de dossier :</strong><br>
                        <span class="text-success"><?php echo htmlspecialchars($paiement['numero_dossier']); ?></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Montant :</strong><br>
                        <span class="text-success fw-bold"><?php echo formatFCFA($paiement['montant']); ?></span></p>
                        
                        <p><strong>Type de paiement :</strong><br>
                        <span class="badge bg-info"><?php echo getTypePaiementLabel($paiement['type_paiement']); ?></span></p>
                        
                        <p><strong>Statut :</strong><br>
                        <span class="badge bg-<?php echo $paiement['statut'] === 'paye' ? 'success' : 'warning'; ?>">
                            <?php echo getStatutLabel($paiement['statut']); ?>
                        </span></p>
                    </div>
                </div>
                
                <?php if ($paiement['description']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <p><strong>Description :</strong><br>
                        <?php echo htmlspecialchars($paiement['description']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Zone de danger -->
        <div class="card danger-zone">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Zone de Danger</h5>
            </div>
            <div class="card-body">
                <?php if ($deleteBlocked): ?>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-lock me-2"></i>
                    Suppression interdite pour un paiement encaissé ou clos. Utilisez <strong>Annulation</strong> ou <strong>Remboursement</strong> depuis la fiche paiement.
                </div>
                <a href="voir.php?id=<?php echo (int) $paiementId; ?>" class="btn btn-outline-secondary mt-3">Retour</a>
                <?php else: ?>
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Attention !</h6>
                    <p class="mb-0">Cette action est <strong>irréversible</strong>. Une fois supprimé, ce paiement ne pourra plus être récupéré.</p>
                </div>
                
                <form method="POST" onsubmit="return confirm('Êtes-vous ABSOLUMENT sûr de vouloir supprimer ce paiement ? Cette action est irréversible !');">
                    <input type="hidden" name="confirm_delete" value="1">
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-trash me-2"></i>Confirmer la Suppression
                        </button>
                        <a href="voir.php?id=<?php echo $paiement['id']; ?>" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                        <a href="index.php" class="btn btn-outline-info btn-lg">
                            <i class="fas fa-list me-2"></i>Liste des Paiements
                        </a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informations supplémentaires -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations</h6>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Date de création :</strong> <?php echo date('d/m/Y H:i', strtotime($paiement['date_creation'])); ?></p>
                <p class="mb-2"><strong>Date de paiement :</strong> <?php echo date('d/m/Y H:i', strtotime($paiement['date_paiement'])); ?></p>
                <?php if (isset($paiement['date_modification'])): ?>
                <p class="mb-0"><strong>Dernière modification :</strong> <?php echo date('d/m/Y H:i', strtotime($paiement['date_modification'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
