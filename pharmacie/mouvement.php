<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('pharmacie'));

require_once __DIR__ . '/../models/Medicament.php';

$medicamentModel = new Medicament();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: index.php');
    exit;
}

$medicament = null;
$mouvements = [];
$message = '';
$error = '';

try {
    $medicament = $medicamentModel->getById($id);
    if (!$medicament) {
        header('Location: index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_mouvement') {
        $type = $_POST['type_mouvement'] ?? '';
        $quantite = isset($_POST['quantite']) ? (int)$_POST['quantite'] : 0;
        $motif = $_POST['motif'] ?? null;
        $reference = $_POST['reference'] ?? null;

        if (empty($type) || !in_array($type, ['entree', 'sortie', 'ajustement', 'perte', 'retour'], true)) {
            $error = 'Type de mouvement invalide.';
        } elseif ($quantite <= 0) {
            $error = 'La quantité doit être supérieure à 0.';
        } elseif (in_array($type, ['sortie', 'perte'], true) && $quantite > $medicament['stock_actuel']) {
            $error = 'Stock insuffisant. Stock actuel : ' . $medicament['stock_actuel'];
        } else {
            $utilisateur = $auth->getUtilisateur();
            $user_id = $utilisateur ? $utilisateur['id'] : null;

            if ($medicamentModel->addMouvement($id, $type, $quantite, $motif, $reference, $user_id)) {
                $medicament = $medicamentModel->getById($id);
                $message = 'Mouvement enregistré avec succès.';
            } else {
                $error = 'Erreur lors de l\'enregistrement du mouvement.';
            }
        }
    }

    $mouvements = $medicamentModel->getMouvementsStock($id, 100);
    if (!is_array($mouvements)) {
        $mouvements = [];
    }
} catch (Exception $e) {
    $error = 'Erreur : ' . $e->getMessage();
    if ($medicament === null) {
        header('Location: index.php');
        exit;
    }
    $mouvements = [];
}

?>
<?php
app_module_page_start([
    'active'   => 'pharmacie',
    'title'    => 'Mouvement de Stock',
    'subtitle' => $medicament['nom_commercial'],
    'icon'     => 'fa-pills',
]);
app_module_back_toolbar(app_url('pharmacie/voir.php?id=' . $id), 'Retour à la fiche');
app_module_flash();
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Stock actuel</span>
                    <strong class="fs-4 text-primary"><?= (int) ($medicament['stock_actuel'] ?? 0) ?></strong>
                </div>
            </div>
        </div>
        <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Nouveau Mouvement</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add_mouvement">
                            
                            <div class="mb-3">
                                <label for="type_mouvement" class="form-label">Type de Mouvement *</label>
                                <select class="form-select" id="type_mouvement" name="type_mouvement" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="entree">Entrée</option>
                                    <option value="sortie">Sortie</option>
                                    <option value="ajustement">Ajustement</option>
                                    <option value="perte">Perte</option>
                                    <option value="retour">Retour</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="quantite" class="form-label">Quantité *</label>
                                <input type="number" class="form-control" id="quantite" name="quantite" 
                                       min="1" required placeholder="Ex: 10">
                            </div>

                            <div class="mb-3">
                                <label for="motif" class="form-label">Motif</label>
                                <textarea class="form-control" id="motif" name="motif" rows="2" 
                                          placeholder="Raison du mouvement (optionnel)"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="reference" class="form-label">Référence</label>
                                <input type="text" class="form-control" id="reference" name="reference" 
                                       placeholder="N° commande, facture, etc. (optionnel)">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Enregistrer le Mouvement
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Historique des mouvements -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Historique des Mouvements</h5>
                        <span class="badge bg-light text-dark"><?= count($mouvements) ?> mouvement(s)</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($mouvements)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>Aucun mouvement enregistré pour ce médicament.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Quantité</th>
                                            <th>Stock après</th>
                                            <th>Motif</th>
                                            <th>Référence</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Calculer le stock après chaque mouvement
                                        // Les mouvements sont triés du plus récent au plus ancien
                                        $stock_actuel = $medicament['stock_actuel'];
                                        foreach ($mouvements as $mouvement): 
                                            // Déterminer si le mouvement augmente ou diminue le stock
                                            $signe = ($mouvement['type_mouvement'] === 'entree' || $mouvement['type_mouvement'] === 'ajustement' || $mouvement['type_mouvement'] === 'retour') ? 1 : -1;
                                            
                                            // Le stock après ce mouvement est le stock actuel (pour le premier mouvement)
                                            // Pour les suivants, on remonte dans le temps
                                            $stock_apres_mouvement = $stock_actuel;
                                            
                                            // Calculer le stock avant ce mouvement pour le prochain itération
                                            $stock_actuel = $stock_actuel - ($signe * $mouvement['quantite']);
                                            
                                            $type_classes = [
                                                'entree' => 'success',
                                                'sortie' => 'danger',
                                                'ajustement' => 'warning',
                                                'perte' => 'secondary',
                                                'retour' => 'info'
                                            ];
                                            $type_class = $type_classes[$mouvement['type_mouvement']] ?? 'secondary';
                                            $type_labels = [
                                                'entree' => 'Entrée',
                                                'sortie' => 'Sortie',
                                                'ajustement' => 'Ajustement',
                                                'perte' => 'Perte',
                                                'retour' => 'Retour'
                                            ];
                                            $type_label = $type_labels[$mouvement['type_mouvement']] ?? $mouvement['type_mouvement'];
                                        ?>
                                        <tr class="mouvement-card mouvement-<?php echo $mouvement['type_mouvement']; ?>">
                                            <td>
                                                <i class="fas fa-calendar-alt me-1 text-muted"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($mouvement['date_mouvement'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $type_class; ?> badge-mouvement">
                                                    <?php echo $type_label; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong class="<?php echo $signe > 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $signe > 0 ? '+' : '-'; ?><?php echo $mouvement['quantite']; ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <strong><?php echo $stock_apres_mouvement; ?></strong>
                                            </td>
                                            <td>
                                                <?php echo $mouvement['motif'] ? htmlspecialchars($mouvement['motif']) : '<span class="text-muted">-</span>'; ?>
                                            </td>
                                            <td>
                                                <?php echo $mouvement['reference'] ? htmlspecialchars($mouvement['reference']) : '<span class="text-muted">-</span>'; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
<?php
ob_start();
?>
<script>
        // Validation côté client pour les sorties
        document.getElementById('type_mouvement').addEventListener('change', function() {
            const type = this.value;
            const quantiteInput = document.getElementById('quantite');
            const stockActuel = <?php echo $medicament['stock_actuel']; ?>;
            
            if (type === 'sortie' || type === 'perte') {
                quantiteInput.setAttribute('max', stockActuel);
                quantiteInput.setAttribute('title', 'Stock disponible: ' + stockActuel);
            } else {
                quantiteInput.removeAttribute('max');
                quantiteInput.removeAttribute('title');
            }
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
