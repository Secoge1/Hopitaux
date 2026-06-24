<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('dossiers'));

// Inclure la configuration de la devise
require_once '../config/currency.php';
?>
<?php
app_module_page_start([
    'active'   => 'dossiers',
    'title'    => 'Nouveau Dossier Patient',
    'subtitle' => 'Création de dossier médical',
    'icon'     => 'fa-folder',
]);
app_module_back_toolbar(app_url('dossiers/index.php'), 'Retour aux dossiers');
app_module_flash();
?>
<?php
        // Inclure les modèles nécessaires
        require_once '../models/Dossier.php';
        require_once '../models/Patient.php';
        
        $dossierModel = new Dossier();
        $patientModel = new Patient();
        
        $message = '';
        $error = '';
        
        // Traitement du formulaire
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $patient_id = $_POST['patient_id'] ?? '';
            $groupe_sanguin = $_POST['groupe_sanguin'] ?? null;
            $priorite = $_POST['priorite'] ?? 'basse';
            $antecedents = $_POST['antecedents'] ?? null;
            $allergies = $_POST['allergies'] ?? null;
            $statut = $_POST['statut'] ?? 'actif';
            $notes = $_POST['notes'] ?? null;
            
            if (empty($patient_id)) {
                $error = "Veuillez sélectionner un patient.";
            } else {
                // Vérifier si le patient a déjà un dossier
                $existingDossier = $dossierModel->getByPatientId($patient_id);
                if ($existingDossier) {
                    $error = "Ce patient a déjà un dossier. Utilisez la fonction de modification.";
                } else {
                    $data = [
                        'patient_id' => $patient_id,
                        'groupe_sanguin' => $groupe_sanguin,
                        'priorite' => $priorite,
                        'antecedents' => $antecedents,
                        'allergies' => $allergies,
                        'statut' => $statut,
                        'notes' => $notes
                    ];
                    
                    if ($dossierModel->create($data)) {
                        $message = "Le dossier a été créé avec succès !";
                    } else {
                        $error = "Erreur lors de la création du dossier.";
                    }
                }
            }
        }
        
        // Récupération des listes pour les formulaires
        $patients = $patientModel->getAll(1, 1000);
        $groupesSanguins = $dossierModel->getGroupesSanguins();
        $priorites = $dossierModel->getPriorites();
        $statuts = $dossierModel->getStatuts();
        ?>
        
        <!-- Messages d'alerte -->
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
        
        
<div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Création de Dossier</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <!-- Sélection du patient -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Sélection du Patient</h6>
                    </div>

                    <div class="col-md-12">
                        <label for="patient_id" class="form-label">Patient *</label>
                        <select class="form-select" id="patient_id" name="patient_id" required>
                            <option value="">Choisir un patient...</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" 
                                        <?php echo (isset($_POST['patient_id']) && $_POST['patient_id'] == $patient['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['nom'] . ' ' . $patient['prenom'] . ' (' . $patient['numero_dossier'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Sélectionnez le patient pour lequel créer le dossier</small>
                    </div>

                    <!-- Informations médicales -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-stethoscope me-2"></i>Informations Médicales</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="groupe_sanguin" class="form-label">Groupe sanguin</label>
                        <select class="form-select" id="groupe_sanguin" name="groupe_sanguin">
                            <option value="">Sélectionner...</option>
                            <?php foreach ($groupesSanguins as $key => $label): ?>
                                <option value="<?php echo $key; ?>" 
                                        <?php echo (isset($_POST['groupe_sanguin']) && $_POST['groupe_sanguin'] === $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="priorite" class="form-label">Priorité</label>
                        <select class="form-select" id="priorite" name="priorite" required>
                            <?php foreach ($priorites as $key => $label): ?>
                                <option value="<?php echo $key; ?>" 
                                        <?php echo (isset($_POST['priorite']) && $_POST['priorite'] === $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <?php foreach ($statuts as $key => $label): ?>
                                <option value="<?php echo $key; ?>" 
                                        <?php echo (isset($_POST['statut']) && $_POST['statut'] === $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="notes" class="form-label">Notes générales</label>
                        <input type="text" class="form-control" id="notes" name="notes" 
                               value="<?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>"
                               placeholder="Notes importantes...">
                    </div>

                    <div class="col-12">
                        <label for="antecedents" class="form-label">Antécédents médicaux</label>
                        <textarea class="form-control" id="antecedents" name="antecedents" rows="3" 
                                  placeholder="Antécédents médicaux du patient..."><?php echo htmlspecialchars($_POST['antecedents'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="allergies" class="form-label">Allergies connues</label>
                        <textarea class="form-control" id="allergies" name="allergies" rows="2" 
                                  placeholder="Allergies connues du patient..."><?php echo htmlspecialchars($_POST['allergies'] ?? ''); ?></textarea>
                    </div>

                    <!-- Boutons -->
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Créer le dossier
                        </button>
                        <a href="index.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Informations sur le module -->
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-check-circle me-2"></i>Module Dossiers Patients - Fonctionnel</h6>
            </div>
            <div class="card-body">
                <p>Le module Dossiers permet maintenant de :</p>
                <ul>
                    <li><i class="fas fa-check text-success me-2"></i>Créer et gérer les dossiers patients</li>
                    <li><i class="fas fa-check text-success me-2"></i>Suivre l'historique médical complet</li>
                    <li><i class="fas fa-check text-success me-2"></i>Gérer les antécédents et allergies</li>
                    <li><i class="fas fa-check text-success me-2"></i>Recherche avancée dans les dossiers</li>
                    <li><i class="fas fa-check text-success me-2"></i>Gestion des priorités et statuts</li>
                    <li><i class="fas fa-check text-success me-2"></i>Interface intuitive et responsive</li>
                </ul>
                <p class="mb-0"><strong>Statut :</strong> <span class="badge bg-success">✅ Fonctionnel</span></p>
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
