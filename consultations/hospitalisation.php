<?php
/**
 * Gestion de l'hospitalisation pour une consultation
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('consultations'));

require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/SejourHospitalisation.php';
require_once __DIR__ . '/../models/CategorieHospitalisation.php';

$consultationModel = new Consultation();
$sejourModel = new SejourHospitalisation();
$categorieModel = new CategorieHospitalisation();

$message = '';
$error = '';

// Récupérer l'ID de la consultation depuis l'URL
$consultation_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['consultation_id']) ? (int)$_GET['consultation_id'] : 0);
if (!$consultation_id) {
    header("Location: index.php");
    exit();
}

$consultation = $consultationModel->getById($consultation_id);
if (!$consultation) {
    echo "<!DOCTYPE html><html><head><title>Erreur</title></head><body>";
    echo "<h1>Erreur</h1><p>Consultation non trouvée.</p>";
    echo "<a href='index.php'>Retour à la liste des consultations</a>";
    echo "</body></html>";
    exit();
}

$sejours = $sejourModel->getByConsultation($consultation_id);
$categories = $categorieModel->getAll();

// Traitement du formulaire d'ajout de séjour
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_sejour') {
    try {
        $data = [
            'consultation_id' => $consultation_id,
            'patient_id' => $consultation['patient_id'],
            'categorie_id' => $_POST['categorie_id'],
            'date_admission' => $_POST['date_admission'],
            'date_sortie' => $_POST['date_sortie'] ?: null,
            'statut' => $_POST['statut'],
            'notes' => $_POST['notes'] ?: null
        ];
        
        if ($sejourModel->create($data)) {
            $message = "Séjour d'hospitalisation ajouté avec succès !";
            $sejours = $sejourModel->getByConsultation($consultation_id); // Recharger les séjours
        } else {
            $error = "Erreur lors de l'ajout du séjour.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

app_module_page_start([
    'active'   => 'consultations',
    'title'    => 'Gestion de l\'Hospitalisation',
    'subtitle' => 'Séjour hospitalier',
    'icon'     => 'fa-stethoscope',
]);
app_module_back_toolbar(app_url('consultations/voir.php?id=' . $consultation_id), 'Retour à la consultation');
app_module_flash();
?>
            <!-- Messages -->
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

        <!-- Informations de la consultation -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-stethoscope me-2"></i>Consultation</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Patient :</strong> <?php echo htmlspecialchars($consultation['patient_prenom'] . ' ' . $consultation['patient_nom']); ?><br>
                        <strong><?= htmlspecialchars(medecin_profil_attribution_label_from_row($consultation)) ?> :</strong> <?php echo htmlspecialchars(medecin_profil_format_joined($consultation)); ?><br>
                        <strong>Spécialité :</strong> <?php echo htmlspecialchars($consultation['medecin_specialite']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Date :</strong> <?php echo date('d/m/Y H:i', strtotime($consultation['date_consultation'])); ?><br>
                        <strong>Type :</strong> <?php echo ucfirst(str_replace('_', ' ', $consultation['type_consultation'])); ?><br>
                        <strong>Prix consultation :</strong> <?php echo number_format($consultation['prix_consultation'], 0, ',', ' '); ?> FCFA
                    </div>
                </div>
            </div>
        </div>

        <!-- Résumé d'hospitalisation du patient -->
        <?php
        // Trouver le séjour en cours s'il existe
        $sejour_en_cours = null;
        foreach ($sejours as $sj) {
            if (isset($sj['statut']) && $sj['statut'] === 'en_cours') {
                $sejour_en_cours = $sj;
                break;
            }
        }
        ?>
        <?php if ($sejour_en_cours): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-hospital me-2"></i>Résumé de l'hospitalisation en cours</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Catégorie :</strong><br>
                        <span class="text-dark"><?php echo htmlspecialchars($sejour_en_cours['categorie_nom'] ?? ''); ?></span>
                        <?php if (isset($sejour_en_cours['categorie_prix'])): ?>
                            <br><small class="text-muted"><?php echo number_format($sejour_en_cours['categorie_prix'], 0, ',', ' '); ?> FCFA/jour</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Admission :</strong><br>
                        <span class="text-dark"><?php echo isset($sejour_en_cours['date_admission']) ? date('d/m/Y H:i', strtotime($sejour_en_cours['date_admission'])) : '-'; ?></span>
                        <br><small class="text-muted">Durée: <?php echo (int)($sejour_en_cours['duree_jours'] ?? 1); ?> jour(s)</small>
                    </div>
                    <div class="col-md-4">
                        <strong>Prix estimé à ce jour :</strong><br>
                        <span class="h5 text-success"><?php echo number_format((int)($sejour_en_cours['prix_total'] ?? 0), 0, ',', ' '); ?> FCFA</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Séjours d'hospitalisation existants -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Séjours d'Hospitalisation</h5>
            </div>
            <div class="card-body">
                <?php if (empty($sejours)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-bed fa-3x mb-3 d-block"></i>
                        <p>Aucun séjour d'hospitalisation enregistré</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Catégorie</th>
                                    <th>Admission</th>
                                    <th>Sortie</th>
                                    <th>Durée</th>
                                    <th>Prix total</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sejours as $sejour): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($sejour['categorie_nom']); ?></strong><br>
                                            <small class="text-muted"><?php echo number_format($sejour['categorie_prix'], 0, ',', ' '); ?> FCFA/jour</small>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($sejour['date_admission'])); ?></td>
                                        <td>
                                            <?php if ($sejour['date_sortie']): ?>
                                                <?php echo date('d/m/Y H:i', strtotime($sejour['date_sortie'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">En cours</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $sejour['duree_jours']; ?> jour(s)</td>
                                        <td><strong><?php echo number_format($sejour['prix_total'], 0, ',', ' '); ?> FCFA</strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $sejour['statut'] === 'termine' ? 'success' : ($sejour['statut'] === 'en_cours' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($sejour['statut']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulaire d'ajout de séjour -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Ajouter un Séjour</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_sejour">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="categorie_id" class="form-label">Catégorie *</label>
                                <select class="form-select" id="categorie_id" name="categorie_id" required>
                                    <option value="">Sélectionner une catégorie</option>
                                    <?php foreach ($categories as $categorie): ?>
                                        <option value="<?php echo $categorie['id']; ?>" 
                                                data-prix="<?php echo $categorie['prix_jour']; ?>">
                                            <?php echo htmlspecialchars($categorie['nom']); ?> - 
                                            <?php echo number_format($categorie['prix_jour'], 0, ',', ' '); ?> FCFA/jour
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="statut" class="form-label">Statut *</label>
                                <select class="form-select" id="statut" name="statut" required>
                                    <option value="en_cours">En cours</option>
                                    <option value="termine">Terminé</option>
                                    <option value="annule">Annulé</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_admission" class="form-label">Date d'admission *</label>
                                <input type="datetime-local" class="form-control" id="date_admission" name="date_admission" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_sortie" class="form-label">Date de sortie</label>
                                <input type="datetime-local" class="form-control" id="date_sortie" name="date_sortie">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <strong>Prix estimé :</strong> <span id="prix-estime">0 FCFA</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Ajouter le séjour
                        </button>
                    </div>
                </form>
            </div>
        </div>
<?php ob_start(); ?>
<script>
        // Calculer le prix estimé
        function calculerPrixEstime() {
            const categorieSelect = document.getElementById('categorie_id');
            const dateAdmissionInput = document.getElementById('date_admission');
            const dateSortieInput = document.getElementById('date_sortie');
            const prixEstimeSpan = document.getElementById('prix-estime');
            
            const categorie = categorieSelect.options[categorieSelect.selectedIndex];
            const prixJour = parseFloat(categorie.dataset.prix) || 0;
            const dateAdmission = dateAdmissionInput.value ? new Date(dateAdmissionInput.value) : new Date();
            const dateSortie = dateSortieInput.value ? new Date(dateSortieInput.value) : new Date();
            
            if (dateAdmission && dateSortie) {
                const dureeJours = Math.ceil((dateSortie - dateAdmission) / (1000 * 60 * 60 * 24)) + 1;
                const prixTotal = prixJour * dureeJours;
                prixEstimeSpan.textContent = prixTotal.toLocaleString('fr-FR') + ' FCFA';
            } else {
                prixEstimeSpan.textContent = '0 FCFA';
            }
        }
        
        document.getElementById('categorie_id').addEventListener('change', calculerPrixEstime);
        document.getElementById('date_admission').addEventListener('change', calculerPrixEstime);
        document.getElementById('date_sortie').addEventListener('change', calculerPrixEstime);
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
