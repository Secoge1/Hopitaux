<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('assurances'));

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Assurance.php';

$patientModel = new Patient();
$assuranceModel = new Assurance();

// Récupérer l'ID du patient ou de l'assurance depuis l'URL
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$assurance_id_param = isset($_GET['assurance_id']) ? (int)$_GET['assurance_id'] : 0;
$mode_assurance = ($assurance_id_param > 0 && $patient_id == 0);

// Si on vient depuis une assurance, on doit sélectionner un patient
if ($mode_assurance) {
    $assurance = $assuranceModel->getById($assurance_id_param);
    if (!$assurance) {
        header("Location: index.php");
        exit();
    }
    // Récupérer la liste des patients pour la sélection
    $patients = $patientModel->getAll(1, 1000);
} else {
    // Mode patient (comportement original)
    if (!$patient_id) {
        header("Location: ../patients/index.php");
        exit();
    }
    
    // Récupérer les informations du patient
    $patient = $patientModel->getById($patient_id);
    if (!$patient) {
        header("Location: ../patients/index.php");
        exit();
    }
}

// Récupérer la liste des assurances actives
$assurances = $assuranceModel->getAll(1, 1000, '', 'actif');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDB();
        $pdo->beginTransaction();
        
        $assurance_id = null;
        
        // Si une nouvelle assurance doit être créée
        if (!empty($_POST['nouvelle_assurance_nom']) && $_POST['assurance_action'] === 'nouvelle') {
            $assuranceData = [
                'nom' => $_POST['nouvelle_assurance_nom'],
                'type' => $_POST['nouvelle_assurance_type'] ?? 'assurance',
                'numero_agrement' => $_POST['nouvelle_assurance_numero_agrement'] ?: null,
                'telephone' => $_POST['nouvelle_assurance_telephone'] ?: null,
                'email' => $_POST['nouvelle_assurance_email'] ?: null,
                'adresse' => $_POST['nouvelle_assurance_adresse'] ?: null,
                'taux_remboursement' => $_POST['nouvelle_assurance_taux'] ?? 0.00,
                'statut' => 'actif',
                'notes' => 'Créée depuis le formulaire contrat patient'
            ];
            
            $assurance_id = $assuranceModel->create($assuranceData);
            if (!$assurance_id) {
                throw new Exception("Erreur lors de la création de l'assurance.");
            }
        }
        // Si une assurance existante est sélectionnée
        elseif (!empty($_POST['assurance_id']) && $_POST['assurance_action'] === 'existant') {
            $assurance_id = (int)$_POST['assurance_id'];
        }
        
        // Déterminer le patient_id et l'assurance_id selon le mode
        $final_patient_id = $mode_assurance ? (int)$_POST['patient_id'] : $patient_id;
        $final_assurance_id = $mode_assurance ? $assurance_id_param : $assurance_id;
        
        // Créer un contrat d'assurance si une assurance est sélectionnée
        if ($final_assurance_id && $final_patient_id) {
            $contratData = [
                'patient_id' => $final_patient_id,
                'assurance_id' => $final_assurance_id,
                'numero_police' => $_POST['numero_police'] ?: null,
                'date_debut' => $_POST['date_debut_contrat'] ?: date('Y-m-d'),
                'date_fin' => !empty($_POST['date_fin_contrat']) ? $_POST['date_fin_contrat'] : null,
                'taux_couverture' => $_POST['taux_couverture'] ?? 100.00,
                'franchise' => $_POST['franchise'] ?? 0.00,
                'plafond_annuel' => !empty($_POST['plafond_annuel']) ? $_POST['plafond_annuel'] : null,
                'statut' => 'actif',
                'notes' => $_POST['notes_assurance'] ?: null
            ];
            
            $contrat_id = $assuranceModel->createContrat($contratData);
            if (!$contrat_id) {
                throw new Exception("Erreur lors de la création du contrat d'assurance.");
            }
            
            $pdo->commit();
            
            // Rediriger selon le mode
            if ($mode_assurance) {
                header("Location: contrats.php?assurance_id=" . $assurance_id_param . "&success=contrat_ajoute");
            } else {
                header("Location: ../patients/voir.php?id=" . $patient_id . "&success=contrat_ajoute");
            }
            exit;
        } else {
            $error = $mode_assurance ? "Veuillez sélectionner un patient." : "Veuillez sélectionner ou créer une assurance.";
        }
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        $error = "Erreur : " . $e->getMessage();
    }
}
?>
<?php
app_module_page_start([
    'active'   => 'assurances',
    'title'    => 'Ajouter un Contrat d\'Assurance',
    'subtitle' => 'Nouveau contrat patient',
    'icon'     => 'fa-shield-alt',
]);
app_module_back_toolbar($mode_assurance ? app_url('assurances/contrats.php?assurance_id=' . $assurance_id_param) : app_url('../patients/voir.php?id=' . $patient_id), $mode_assurance ? 'Retour aux contrats' : 'Retour au patient');
app_module_flash();
?>
<style>
        .patient-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .patient-info-card h5 {
            margin-bottom: 1rem;
        }
        .patient-info-card .info-item {
            margin-bottom: 0.5rem;
        }
    </style>

<!-- Informations du patient ou de l'assurance -->
        <?php if ($mode_assurance): ?>
            <div class="patient-info-card">
                <h5><i class="fas fa-shield-alt me-2"></i>Assurance : <?php echo htmlspecialchars($assurance['nom']); ?></h5>
                <div class="row">
                    <div class="col-md-4 info-item">
                        <strong>Type :</strong> <?php echo ucfirst($assurance['type']); ?>
                    </div>
                    <div class="col-md-4 info-item">
                        <strong>Taux de remboursement :</strong> <?php echo number_format($assurance['taux_remboursement'], 2); ?>%
                    </div>
                    <div class="col-md-4 info-item">
                        <strong>Statut :</strong> 
                        <span class="badge bg-light text-dark"><?php echo ucfirst($assurance['statut']); ?></span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="patient-info-card">
                <h5><i class="fas fa-user me-2"></i>Informations du Patient</h5>
                <div class="row">
                    <div class="col-md-3 info-item">
                        <strong>Nom complet :</strong><br>
                        <?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']); ?>
                    </div>
                    <div class="col-md-3 info-item">
                        <strong>Numéro de dossier :</strong><br>
                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($patient['numero_dossier']); ?></span>
                    </div>
                    <div class="col-md-3 info-item">
                        <strong>Date de naissance :</strong><br>
                        <?php echo date('d/m/Y', strtotime($patient['date_naissance'])); ?>
                    </div>
                    <div class="col-md-3 info-item">
                        <strong>Genre :</strong><br>
                        <?php echo ($patient['sexe'] ?? '') === 'M' ? 'Homme' : 'Femme'; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-file-contract me-2"></i>Nouveau Contrat d'Assurance</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <!-- Choix de l'assurance -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3"><i class="fas fa-shield-alt me-2"></i>Assurance</h6>
                        <div class="mb-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="assurance_action" id="assurance_existant" value="existant" checked onchange="toggleAssuranceSection()">
                                <label class="form-check-label" for="assurance_existant">
                                    Assurance existante
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="assurance_action" id="assurance_nouvelle" value="nouvelle" onchange="toggleAssuranceSection()">
                                <label class="form-check-label" for="assurance_nouvelle">
                                    Créer une nouvelle assurance
                                </label>
                            </div>
                        </div>

                        <?php if ($mode_assurance): ?>
                            <!-- Mode assurance : sélectionner un patient -->
                            <div class="mb-3">
                                <label for="patient_id" class="form-label">Sélectionner un patient *</label>
                                <select class="form-select" id="patient_id" name="patient_id" required>
                                    <option value="">Choisir un patient...</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?php echo $p['id']; ?>">
                                            <?php echo htmlspecialchars(($p['prenom'] ?? '') . ' ' . ($p['nom'] ?? '')); ?> 
                                            (<?php echo htmlspecialchars($p['numero_dossier'] ?? 'N/A'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="hidden" name="assurance_action" value="existant">
                            <input type="hidden" name="assurance_id" value="<?php echo $assurance_id_param; ?>">
                        <?php else: ?>
                            <!-- Assurance existante -->
                            <div id="assurance_existant_section">
                                <div class="mb-3">
                                    <label for="assurance_id" class="form-label">Sélectionner une assurance *</label>
                                    <select class="form-select" id="assurance_id" name="assurance_id" required onchange="showContratDetails()">
                                        <option value="">Choisir une assurance...</option>
                                        <?php foreach ($assurances as $assurance): ?>
                                            <option value="<?php echo $assurance['id']; ?>" data-taux="<?php echo $assurance['taux_remboursement']; ?>">
                                                <?php echo htmlspecialchars($assurance['nom']); ?> 
                                                (<?php echo ucfirst($assurance['type']); ?> - 
                                                <?php echo number_format($assurance['taux_remboursement'], 2); ?>%)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        L'assurance sélectionnée sera utilisée pour créer le contrat
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Nouvelle assurance -->
                        <div id="assurance_nouvelle_section" style="display: none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="nouvelle_assurance_nom" class="form-label">Nom de l'assurance *</label>
                                    <input type="text" class="form-control" id="nouvelle_assurance_nom" name="nouvelle_assurance_nom" placeholder="Ex: AMAS, CNSS, etc.">
                                </div>
                                <div class="col-md-6">
                                    <label for="nouvelle_assurance_type" class="form-label">Type *</label>
                                    <select class="form-select" id="nouvelle_assurance_type" name="nouvelle_assurance_type">
                                        <option value="assurance">Assurance</option>
                                        <option value="mutuelle">Mutuelle</option>
                                        <option value="autre">Autre</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="nouvelle_assurance_numero_agrement" class="form-label">Numéro d'agrément</label>
                                    <input type="text" class="form-control" id="nouvelle_assurance_numero_agrement" name="nouvelle_assurance_numero_agrement">
                                </div>
                                <div class="col-md-6">
                                    <label for="nouvelle_assurance_taux" class="form-label">Taux de remboursement (%)</label>
                                    <input type="number" class="form-control" id="nouvelle_assurance_taux" name="nouvelle_assurance_taux" value="0" step="0.01" min="0" max="100">
                                </div>
                                <div class="col-md-6">
                                    <label for="nouvelle_assurance_telephone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="nouvelle_assurance_telephone" name="nouvelle_assurance_telephone">
                                </div>
                                <div class="col-md-6">
                                    <label for="nouvelle_assurance_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="nouvelle_assurance_email" name="nouvelle_assurance_email">
                                </div>
                                <div class="col-12">
                                    <label for="nouvelle_assurance_adresse" class="form-label">Adresse</label>
                                    <textarea class="form-control" id="nouvelle_assurance_adresse" name="nouvelle_assurance_adresse" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3 mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note :</strong> Cette assurance sera automatiquement ajoutée au module Assurances.
                            </div>
                        </div>
                    </div>

                    <!-- Détails du contrat -->
                    <div class="col-12">
                        <hr class="my-4">
                        <h6 class="text-secondary mb-3"><i class="fas fa-file-contract me-2"></i>Détails du Contrat</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="numero_police" class="form-label">Numéro de police</label>
                        <input type="text" class="form-control" id="numero_police" name="numero_police" placeholder="Numéro de police d'assurance">
                    </div>

                    <div class="col-md-6">
                        <label for="date_debut_contrat" class="form-label">Date de début *</label>
                        <input type="date" class="form-control" id="date_debut_contrat" name="date_debut_contrat" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="date_fin_contrat" class="form-label">Date de fin (optionnel)</label>
                        <input type="date" class="form-control" id="date_fin_contrat" name="date_fin_contrat">
                        <small class="text-muted">Laissez vide si le contrat n'a pas de date de fin</small>
                    </div>

                    <div class="col-md-6">
                        <label for="taux_couverture" class="form-label">Taux de couverture (%) *</label>
                        <input type="number" class="form-control" id="taux_couverture" name="taux_couverture" value="100" step="0.01" min="0" max="100" required>
                    </div>

                    <div class="col-md-6">
                        <label for="franchise" class="form-label">Franchise (FCFA)</label>
                        <input type="number" class="form-control" id="franchise" name="franchise" value="0" step="0.01" min="0">
                    </div>

                    <div class="col-md-6">
                        <label for="plafond_annuel" class="form-label">Plafond annuel (FCFA)</label>
                        <input type="number" class="form-control" id="plafond_annuel" name="plafond_annuel" step="0.01" min="0">
                    </div>

                    <div class="col-12">
                        <label for="notes_assurance" class="form-label">Notes sur le contrat</label>
                        <textarea class="form-control" id="notes_assurance" name="notes_assurance" rows="3" placeholder="Informations complémentaires sur le contrat..."></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Créer le contrat d'assurance
                        </button>
                        <?php if ($mode_assurance): ?>
                            <a href="contrats.php?assurance_id=<?php echo $assurance_id_param; ?>" class="btn btn-secondary ms-2">Annuler</a>
                        <?php else: ?>
                            <a href="../patients/voir.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary ms-2">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
ob_start();
?>
<script>
        function toggleAssuranceSection() {
            const action = document.querySelector('input[name="assurance_action"]:checked').value;
            const existantSection = document.getElementById('assurance_existant_section');
            const nouvelleSection = document.getElementById('assurance_nouvelle_section');
            
            // Masquer toutes les sections
            existantSection.style.display = 'none';
            nouvelleSection.style.display = 'none';
            
            // Afficher la section appropriée
            if (action === 'existant') {
                existantSection.style.display = 'block';
            } else if (action === 'nouvelle') {
                nouvelleSection.style.display = 'block';
            }
        }
        
        function showContratDetails() {
            const assuranceSelect = document.getElementById('assurance_id');
            if (assuranceSelect && assuranceSelect.value) {
                const selectedOption = assuranceSelect.options[assuranceSelect.selectedIndex];
                const tauxRemboursement = selectedOption.getAttribute('data-taux');
                if (tauxRemboursement) {
                    document.getElementById('taux_couverture').value = parseFloat(tauxRemboursement);
                }
            }
        }
        
        // Initialiser l'affichage au chargement
        document.addEventListener('DOMContentLoaded', function() {
            toggleAssuranceSection();
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
