<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('dossiers'));

require_once '../models/Dossier.php';
require_once '../models/Patient.php';

$dossierModel = new Dossier();
$patientModel = new Patient();

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

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupe_sanguin = $_POST['groupe_sanguin'] ?? null;
    $priorite = $_POST['priorite'] ?? 'basse';
    $antecedents = $_POST['antecedents'] ?? null;
    $allergies = $_POST['allergies'] ?? null;
    $statut = $_POST['statut'] ?? 'actif';
    $notes = $_POST['notes'] ?? null;
    
    $data = [
        'groupe_sanguin' => $groupe_sanguin,
        'priorite' => $priorite,
        'antecedents' => $antecedents,
        'allergies' => $allergies,
        'statut' => $statut,
        'notes' => $notes
    ];
    
    if ($dossierModel->update($id, $data)) {
        $message = "Le dossier a été modifié avec succès.";
        $dossier = $dossierModel->getById($id); // Rafraîchir les données
    } else {
        $error = "Erreur lors de la modification du dossier.";
    }
}

// Récupération des listes pour les formulaires
$groupesSanguins = $dossierModel->getGroupesSanguins();
$priorites = $dossierModel->getPriorites();
$statuts = $dossierModel->getStatuts();
?>
<?php
app_module_page_start([
    'active'   => 'dossiers',
    'title'    => 'Modifier Dossier',
    'subtitle' => 'Modification du dossier médical',
    'icon'     => 'fa-folder',
]);
app_module_back_toolbar(app_url('dossiers/voir.php?id=' . $id), 'Retour au dossier');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier le Dossier</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="groupe_sanguin" class="form-label">Groupe sanguin</label>
                            <select class="form-select" id="groupe_sanguin" name="groupe_sanguin">
                                <option value="">Sélectionner...</option>
                                <?php foreach ($groupesSanguins as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo $key === $dossier['groupe_sanguin'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="priorite" class="form-label">Priorité *</label>
                            <select class="form-select" id="priorite" name="priorite" required>
                                <?php foreach ($priorites as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo $key === $dossier['priorite'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="statut" class="form-label">Statut *</label>
                            <select class="form-select" id="statut" name="statut" required>
                                <?php foreach ($statuts as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo $key === $dossier['statut'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="notes" class="form-label">Notes générales</label>
                            <input type="text" class="form-control" id="notes" name="notes" 
                                   value="<?php echo htmlspecialchars($dossier['notes'] ?? ''); ?>"
                                   placeholder="Notes importantes...">
                        </div>

                        <div class="col-12 mb-3">
                            <label for="antecedents" class="form-label">Antécédents médicaux</label>
                            <textarea class="form-control" id="antecedents" name="antecedents" rows="3" 
                                      placeholder="Antécédents médicaux du patient..."><?php echo htmlspecialchars($dossier['antecedents'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="allergies" class="form-label">Allergies connues</label>
                            <textarea class="form-control" id="allergies" name="allergies" rows="2" 
                                      placeholder="Allergies connues du patient..."><?php echo htmlspecialchars($dossier['allergies'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="voir.php?id=<?php echo $dossier['id']; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions Rapides</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <a href="voir.php?id=<?php echo $dossier['id']; ?>" class="btn btn-outline-info btn-sm w-100">
                            <i class="fas fa-eye me-2"></i>Voir le dossier
                        </a>
                    </div>
                    
                    <div class="col-md-4 mb-2">
                        <a href="supprimer.php?id=<?php echo $dossier['id']; ?>" class="btn btn-outline-danger btn-sm w-100"
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce dossier ?')">
                            <i class="fas fa-trash me-2"></i>Supprimer
                        </a>
                    </div>
                    
                    <div class="col-md-4 mb-2">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="fas fa-arrow-left me-2"></i>Retour à la liste
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
ob_start();
?>
<script src="assets/js/auto-responsive.js"></script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
