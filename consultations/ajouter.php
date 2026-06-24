<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('consultations'));

require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../models/TarifConsultation.php';
require_once __DIR__ . '/../models/CategorieHospitalisation.php';
require_once __DIR__ . '/../models/SoinsConsultation.php';

$consultationModel = new Consultation();
$patientModel = new Patient();
$medecinModel = new Medecin();
$tarifModel = new TarifConsultation();
$categorieModel = new CategorieHospitalisation();
$soinsModel = new SoinsConsultation();

$message = '';
$error = '';

// Récupérer l'ID du patient depuis l'URL si disponible
$selectedPatientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : '';

// Récupérer la liste des patients et médecins
$patients = $patientModel->getAll(1, 1000); // Tous les patients
$medecins = $medecinModel->getAll(1, 1000); // Tous les médecins
$tarifs = $tarifModel->getAll(); // Tous les tarifs
$categories = $categorieModel->getAll(); // Toutes les catégories d'hospitalisation
$soins = $soinsModel->getAll('actif'); // Tous les soins actifs

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $date_consultation = $_POST['date_consultation'];

        $typePost = (string) ($_POST['type_consultation'] ?? 'consultation_simple');
        if ($typePost === '__autre__') {
            $typeAutre = trim((string) ($_POST['type_consultation_autre'] ?? ''));
            if ($typeAutre === '') {
                throw new Exception('Veuillez préciser le type de consultation lorsque « Autre (préciser) » est sélectionné.');
            }
            $typeConsultation = preg_replace('/\s+/', '_', mb_strtolower($typeAutre, 'UTF-8'));
        } elseif (in_array($typePost, ['consultation_simple', 'consultation_specialisee', 'urgence', 'controle'], true)) {
            $typeConsultation = $typePost;
        } else {
            $typeConsultation = 'consultation_simple';
        }
        
        $data = [
            'patient_id' => $_POST['patient_id'],
            'medecin_id' => $_POST['medecin_id'],
            'date_consultation' => $date_consultation,
            'symptomes' => $_POST['symptomes'] ?: null,
            'diagnostic' => $_POST['diagnostic'] ?: null,
            'traitement' => $_POST['traitement'] ?: null,
            'ordonnance' => $_POST['ordonnance'] ?: null,
            'statut' => $_POST['statut'],
            'prix_consultation' => $_POST['prix_consultation'] ?: 0.00,
            'type_consultation' => $typeConsultation,
            'hospitalisation_requise' => isset($_POST['hospitalisation_requise']) ? 1 : 0
        ];

        // Ajouter les soins si fournis
        if (!empty($_POST['soins_data'])) {
            $data['soins_data'] = $_POST['soins_data'];
        }
        
        // Variable pour stocker la catégorie d'hospitalisation
        $categorieHospitalisation = null;
        $dureeHospitalisation = null;
        
        // Préparer les données d'hospitalisation si nécessaire (pour consultation_hospitalisation)
        if (isset($_POST['hospitalisation_requise']) && !empty($_POST['categorie_hospitalisation'])) {
            // Récupérer le prix de la catégorie
            $categorieHospitalisation = $categorieModel->getById($_POST['categorie_hospitalisation']);
            
            if ($categorieHospitalisation) {
                $dureeHospitalisation = $_POST['duree_hospitalisation'] ?: 1;
                $prix_jour = $categorieHospitalisation['prix_jour'];
                $prix_total = $prix_jour * $dureeHospitalisation;
                
                $data['hospitalisation_data'] = [
                    'categorie_id' => $_POST['categorie_hospitalisation'],
                    'duree' => $dureeHospitalisation,
                    'prix_jour' => $prix_jour,
                    'prix_total' => $prix_total,
                    'notes' => $_POST['notes_hospitalisation'] ?: null,
                    'date_admission' => $date_consultation
                ];
            }
        }

        try {
            $consultation_id = $consultationModel->create($data);
        } catch (Exception $createException) {
            // Afficher l'erreur détaillée pour le debug
            $error = "Erreur lors de la création de la consultation : " . $createException->getMessage();
            $consultation_id = false;
        }
        
        if ($consultation_id && is_numeric($consultation_id)) {
            // Si hospitalisation requise, créer un séjour d'hospitalisation (pour sejours_hospitalisation)
            // Cette table est utilisée par le système de gestion des séjours
            if ($categorieHospitalisation && isset($_POST['categorie_hospitalisation'])) {
                try {
                    require_once __DIR__ . '/../models/SejourHospitalisation.php';
                    
                    $sejourModel = new SejourHospitalisation();
                    
                    // Vérifier que la consultation existe bien dans la BD
                    $consultationVerif = $consultationModel->getById($consultation_id);
                    
                    if ($consultationVerif) {
                        // Le modèle SejourHospitalisation calcule lui-même duree_jours et prix_total
                        // Il ne nécessite que: consultation_id, patient_id, categorie_id, date_admission, statut, notes
                        $sejourData = [
                            'consultation_id' => (int)$consultation_id,
                            'patient_id' => (int)$_POST['patient_id'],
                            'categorie_id' => (int)$_POST['categorie_hospitalisation'],
                            'date_admission' => $date_consultation,
                            'date_sortie' => null,  // Pas de sortie pour l'instant
                            'statut' => 'en_cours',
                            'notes' => $_POST['notes_hospitalisation'] ?: null
                        ];
                        
                        $sejourModel->create($sejourData);
                    } else {
                        error_log("La consultation ID {$consultation_id} n'a pas été trouvée après création");
                    }
                } catch (Exception $sejourException) {
                    // Log l'erreur mais ne pas bloquer la création de la consultation
                    error_log("Erreur création séjour: " . $sejourException->getMessage());
                    // Optionnel: afficher l'erreur à l'utilisateur
                    // $error .= " Note: L'hospitalisation n'a pas pu être enregistrée dans le système de séjours.";
                }
            }
            
            $message = "Consultation créée avec succès !";
        } else {
            $error = "Erreur lors de la création de la consultation.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }

}

app_module_page_start([
    'active'   => 'consultations',
    'title'    => 'Nouvelle Consultation',
    'subtitle' => 'Création d\'une consultation',
    'icon'     => 'fa-stethoscope',
]);
app_module_back_toolbar(app_url('consultations/index.php'), 'Retour à la liste', []);
app_module_flash();
?>
<style>
/* Styles pour le système de diagnostic intelligent */
        .ai-diagnostic {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8ff 100%);
            border: 2px solid #28a745;
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
            display: none;
            animation: fadeIn 0.5s ease-in;
        }
        .ai-diagnostic.show { display: block; }
        .ai-diagnostic h6 { color: #155724; border-bottom: 2px solid #28a745; padding-bottom: 10px; margin-bottom: 15px; display: flex; align-items: center; }
        .ai-diagnostic h6 i { margin-right: 8px; animation: pulse 2s infinite; }
        .diagnostic-section { background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .diagnostic-section h7 { color: #28a745; font-weight: bold; display: block; margin-bottom: 10px; font-size: 1.1em; }
        .diagnostic-item { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 10px; margin-bottom: 8px; cursor: pointer; transition: all 0.3s ease; position: relative; }
        .diagnostic-item:hover { background: #e9ecef; border-color: #28a745; transform: translateX(5px); }
        .diagnostic-item.selected { background: #d4edda; border-color: #28a745; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2); }
        .diagnostic-item::before { content: "🧠"; position: absolute; left: -10px; top: 50%; transform: translateY(-50%); opacity: 0; transition: opacity 0.3s ease; }
        .diagnostic-item:hover::before { opacity: 1; }
        .confidence-badge { background: #17a2b8; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; margin-left: 10px; }
        .mistral-badge { background: #6f42c1; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; margin-left: 8px; }
        .mistral-note { background: #f3e8ff; border: 1px solid #c4b5fd; border-radius: 8px; padding: 10px 14px; margin-bottom: 15px; font-size: 0.9em; color: #5b21b6; }
        .text-suggestions-section { background: #fff; border: 1px solid #c4b5fd; border-radius: 10px; padding: 15px; margin-bottom: 18px; }
        .text-suggestions-section h7 { color: #6f42c1; font-weight: bold; display: block; margin-bottom: 12px; }
        .text-suggestion-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; background: #fafafa; transition: all 0.2s ease; }
        .text-suggestion-card:hover { border-color: #6f42c1; box-shadow: 0 2px 8px rgba(111, 66, 193, 0.12); }
        .text-suggestion-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; gap: 8px; flex-wrap: wrap; }
        .text-suggestion-body { white-space: pre-wrap; font-size: 0.92em; line-height: 1.5; color: #333; }
        .btn-insert-text { font-size: 0.78em; padding: 3px 10px; border-radius: 14px; border: 1px solid #6f42c1; background: #fff; color: #6f42c1; white-space: nowrap; }
        .btn-insert-text:hover { background: #6f42c1; color: #fff; }
        .diagnostic-item-text { flex: 1; white-space: pre-wrap; line-height: 1.45; }
        .loading-diagnostic { text-align: center; padding: 20px; }
        .loading-spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #28a745; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 10px; }
        .auto-fill-diagnostic { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border: none; color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.9em; margin: 5px; transition: all 0.3s ease; }
        .auto-fill-diagnostic:hover { background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3); }
        .symptom-input { position: relative; }
        .typing-indicator { position: absolute; bottom: -25px; right: 10px; font-size: 0.8em; color: #6c757d; opacity: 0; transition: opacity 0.3s ease; }
        .typing-indicator.show { opacity: 1; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .patient-context { background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: none; }
        .patient-context.show { display: block; }
        .patient-context h6 { color: #856404; border-bottom: 2px solid #ffc107; margin-bottom: 10px; }
        .safety-alerts { background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border: 2px solid #dc3545; border-radius: 12px; padding: 20px; margin-top: 15px; display: none; animation: fadeIn 0.5s ease-in; }
        .safety-alerts.show { display: block; }
        .safety-alerts h6 { color: #721c24; border-bottom: 2px solid #dc3545; padding-bottom: 10px; margin-bottom: 15px; display: flex; align-items: center; }
        .safety-alerts h6 i { margin-right: 8px; animation: pulse 2s infinite; }
        .warning-item { background: white; border-left: 4px solid #dc3545; border-radius: 6px; padding: 12px; margin-bottom: 10px; box-shadow: 0 2px 4px rgba(220, 53, 69, 0.1); }
        .warning-item.severe { background: #f8d7da; border-left-color: #dc3545; }
        .warning-item.moderate { background: #fff3cd; border-left-color: #ffc107; }
        .patient-history { background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); border: 1px solid #17a2b8; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: none; }
        .patient-history.show { display: block; }
        .patient-history h6 { color: #0c5460; border-bottom: 2px solid #17a2b8; margin-bottom: 10px; }
        .history-item { background: white; border-radius: 6px; padding: 10px; margin-bottom: 8px; border-left: 3px solid #17a2b8; }
        .history-item strong { color: #0c5460; }
        .medication-filtered { background: #f8f9fa; border: 1px dashed #6c757d; color: #6c757d; text-decoration: line-through; opacity: 0.6; }
        .medication-warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
        .medication-contraindicated { background: #f8d7da; border: 1px solid #dc3545; color: #721c24; }
</style>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label for="patient_id" class="form-label">Patient *</label>
                        <select class="form-select" id="patient_id" name="patient_id" required>
                            <option value="">Choisir un patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" <?php echo $selectedPatientId == $patient['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['numero_dossier'] . ' - ' . $patient['prenom'] . ' ' . $patient['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="medecin_id" class="form-label">Médecin *</label>
                        <select class="form-select" id="medecin_id" name="medecin_id" required>
                            <option value="">Choisir un médecin</option>
                            <?php foreach ($medecins as $medecin): ?>
                                <option value="<?php echo $medecin['id']; ?>">
                                    <?php echo htmlspecialchars(medecin_profil_option_label($medecin)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="date_consultation" class="form-label">Date et heure de consultation *</label>
                        <input type="datetime-local" class="form-control" id="date_consultation" name="date_consultation" 
                               value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        <div class="form-text">Vous pouvez sélectionner une date passée ou future</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Fuseau horaire</label>
                        <div class="form-control-plaintext">
                            <i class="fas fa-clock me-2"></i>
                            <span id="timezone-display"><?php echo date_default_timezone_get(); ?></span>
                            <small class="text-muted ms-2"><?php echo date('H:i'); ?></small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="planifiee">Planifiée</option>
                            <option value="en_cours">En cours</option>
                            <option value="terminee">Terminée</option>
                            <option value="annulee">Annulée</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="type_consultation" class="form-label">Type de consultation *</label>
                        <select class="form-select" id="type_consultation" name="type_consultation" required>
                            <option value="consultation_simple">Consultation simple</option>
                            <option value="consultation_specialisee">Consultation spécialisée</option>
                            <option value="urgence">Urgence</option>
                            <option value="controle">Contrôle</option>
                            <option value="__autre__">Autre (préciser)</option>
                        </select>
                        <div class="mt-2" id="wrap_type_consultation_autre" style="display: none;">
                            <label for="type_consultation_autre" class="form-label">Précisez le type *</label>
                            <input type="text" class="form-control" id="type_consultation_autre" name="type_consultation_autre"
                                   placeholder="Ex. Téléconsultation, bilan pré-opératoire…" maxlength="120" autocomplete="off">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="prix_consultation" class="form-label">Prix de la consultation (<?= htmlspecialchars(function_exists('app_currency_label') ? app_currency_label() : 'FCFA') ?>) *</label>
                        <input type="number" class="form-control" id="prix_consultation" name="prix_consultation" 
                               step="0.01" min="0" value="5000.00" required>
                        <div class="form-text">Le prix sera automatiquement calculé selon le type de consultation</div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="hospitalisation_requise" name="hospitalisation_requise">
                            <label class="form-check-label" for="hospitalisation_requise">
                                Hospitalisation requise
                            </label>
                        </div>
                        <div class="form-text">Cochez si le patient nécessite une hospitalisation</div>
                    </div>

                    <!-- Section Hospitalisation (affichée conditionnellement) -->
                    <div class="col-12" id="hospitalisation-section" style="display: none;">
                        <div class="card mt-3">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fas fa-bed me-2"></i>Détails de l'Hospitalisation</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="categorie_hospitalisation" class="form-label">Catégorie d'hospitalisation</label>
                                        <select class="form-select" id="categorie_hospitalisation" name="categorie_hospitalisation">
                                            <option value="">Sélectionner une catégorie</option>
                                            <?php foreach ($categories as $categorie): ?>
                                                <option value="<?php echo $categorie['id']; ?>" 
                                                        data-prix="<?php echo $categorie['prix_jour']; ?>">
                                                    <?php echo htmlspecialchars($categorie['nom']); ?> - 
                                                    <?= htmlspecialchars(formatMoney($categorie['prix_jour'])) ?>/jour
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="duree_hospitalisation" class="form-label">Durée prévue (jours)</label>
                                        <input type="number" class="form-control" id="duree_hospitalisation" name="duree_hospitalisation" 
                                               min="1" value="1">
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label for="notes_hospitalisation" class="form-label">Notes sur l'hospitalisation</label>
                                        <textarea class="form-control" id="notes_hospitalisation" name="notes_hospitalisation" rows="2"
                                                  placeholder="Notes spécifiques sur l'hospitalisation..."></textarea>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <strong>Prix total estimé :</strong> <span id="prix-total-hospitalisation"><?= htmlspecialchars(formatMoney(0)) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section Soins -->
                    <div class="col-12">
                        <div class="card mt-3">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-stethoscope me-2"></i>Soins de Consultation</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="soin_id" class="form-label">Ajouter un soin</label>
                                        <select class="form-select" id="soin_id" name="soin_id">
                                            <option value="">Sélectionner un soin...</option>
                                            <?php foreach ($soins as $soin): ?>
                                                <option value="<?php echo $soin['id']; ?>" 
                                                        data-prix="<?php echo number_format($soin['prix'], 2, '.', ''); ?>"
                                                        data-type="<?php echo $soin['type_soin']; ?>">
                                                    <?php echo htmlspecialchars($soin['nom']); ?> - 
                                                    <?= htmlspecialchars(formatMoney($soin['prix'])) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="quantite_soin" class="form-label">Quantité</label>
                                        <input type="number" class="form-control" id="quantite_soin" name="quantite_soin" 
                                               min="1" value="1" onchange="updatePrixSoin()">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-success w-100" onclick="addSoinToList()">
                                            <i class="fas fa-plus me-2"></i>Ajouter
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Liste des soins ajoutés -->
                                <div id="soins-list" class="mt-3" style="display: none;">
                                    <h6>Soins sélectionnés :</h6>
                                    <div id="soins-items"></div>
                                    <div class="mt-2">
                                        <strong>Total des soins : <span id="total-soins" class="text-success"><?= htmlspecialchars(formatMoney(0)) ?></span></strong>
                                    </div>
                                </div>
                                
                                <!-- Champs cachés pour envoyer les données des soins -->
                                <input type="hidden" id="soins-data" name="soins_data" value="">
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="symptom-input">
                            <label for="symptomes" class="form-label">Symptômes</label>
                            <textarea class="form-control" id="symptomes" name="symptomes" rows="3"
                                      placeholder="Décrivez les symptômes du patient... (l'IA analysera automatiquement)"
                                      onkeyup="analyzeSymptoms()"></textarea>
                            <div class="typing-indicator" id="typing-indicator">
                                <i class="fas fa-robot me-1"></i>Analyse en cours...
                            </div>
                        </div>
                        
                        <!-- Section de l'historique du patient -->
                        <div id="patient-history-container" class="patient-history">
                            <h6><i class="fas fa-history me-2"></i>Antécédents du Patient</h6>
                            <div id="patient-history-content"></div>
                        </div>
                        
                        <!-- Section des suggestions de diagnostic intelligent -->
                        <div id="ai-diagnostic-container" class="ai-diagnostic">
                            <h6><i class="fas fa-brain"></i>Diagnostic Intelligent <small class="text-muted ms-2" id="ia-source-label"></small></h6>
                            <div id="mistral-note-container" class="mistral-note" style="display: none;"></div>
                            <div id="loading-diagnostic" class="loading-diagnostic" style="display: none;">
                                <div class="loading-spinner"></div>
                                <span>Analyse des symptômes en cours...</span>
                            </div>
                            <div id="diagnostic-content" style="display: none;">
                                <div id="text-suggestions-section" class="text-suggestions-section" style="display: none;">
                                    <h7><i class="fas fa-align-left me-2"></i>Textes rédigés suggérés</h7>
                                    <p class="text-muted small mb-2">Phrases ou paragraphes complets — cliquez sur « Insérer » pour remplir le champ correspondant.</p>
                                    <div id="text-suggestions-list"></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="diagnostic-section">
                                            <h7><i class="fas fa-stethoscope me-2"></i>Diagnostics Probables</h7>
                                            <div id="diagnostics-list"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="diagnostic-section">
                                            <h7><i class="fas fa-pills me-2"></i>Traitements Suggérés</h7>
                                            <div id="treatments-list"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="diagnostic-section">
                                            <h7><i class="fas fa-prescription-bottle me-2"></i>Médicaments</h7>
                                            <div id="medications-list"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="diagnostic-section">
                                            <h7><i class="fas fa-flask me-2"></i>Examens Recommandés</h7>
                                            <div id="exams-list"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center mt-3">
                                    <button type="button" class="auto-fill-diagnostic" onclick="autoFillDiagnostic()">
                                        <i class="fas fa-magic me-2"></i>Remplir le diagnostic
                                    </button>
                                    <button type="button" class="auto-fill-diagnostic" onclick="autoFillTreatment()">
                                        <i class="fas fa-pills me-2"></i>Remplir le traitement
                                    </button>
                                    <button type="button" class="auto-fill-diagnostic" onclick="autoFillPrescription()">
                                        <i class="fas fa-file-medical me-2"></i>Remplir l'ordonnance
                                    </button>
                                </div>
                            </div>
                            <div id="diagnostic-error" class="alert alert-warning" style="display: none;">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <span id="diagnostic-error-message"></span>
                            </div>
                        </div>
                        
                        <!-- Section des alertes de sécurité -->
                        <div id="safety-alerts-container" class="safety-alerts">
                            <h6><i class="fas fa-exclamation-triangle"></i>Alertes de Sécurité</h6>
                            <div id="safety-warnings-list"></div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="diagnostic" class="form-label">Diagnostic</label>
                        <textarea class="form-control" id="diagnostic" name="diagnostic" rows="3"
                                  placeholder="Diagnostic établi par le médecin..."></textarea>
                    </div>

                    <div class="col-12">
                        <label for="traitement" class="form-label">Traitement prescrit</label>
                        <textarea class="form-control" id="traitement" name="traitement" rows="3"
                                  placeholder="Traitement recommandé..."></textarea>
                    </div>

                    <div class="col-12">
                        <label for="ordonnance" class="form-label">Ordonnance</label>
                        <textarea class="form-control" id="ordonnance" name="ordonnance" rows="4"
                                  placeholder="Médicaments prescrits, posologie, durée..."></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-save me-2"></i>Créer la consultation
                        </button>
                        <a href="index.php" class="btn btn-secondary ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Aide pour la création -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Conseils pour la création</h6>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Les consultations peuvent être planifiées à l'avance ou créées en temps réel</li>
                    <li>Les créneaux sont disponibles de 8h00 à 18h00 par intervalles de 30 minutes</li>
                    <li>Remplissez les symptômes pour un meilleur suivi médical</li>
                    <li>Le diagnostic et le traitement peuvent être ajoutés après la consultation</li>
                    <li>L'ordonnance est optionnelle mais recommandée pour le suivi</li>
                </ul>
            </div>
        </div>
<?php ob_start(); ?>
<script>
        // Variables globales pour le système de diagnostic
        let currentDiagnosticData = null;
        let mistralDiagnosticItems = {};
        let typingTimer = null;
        let analysisInProgress = false;

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        const FIELD_LABELS = {
            diagnostic: 'Diagnostic',
            traitement: 'Traitement',
            ordonnance: 'Ordonnance',
            symptomes: 'Symptômes'
        };

        const LIST_FIELD_MAP = {
            'diagnostics-list': 'diagnostic',
            'treatments-list': 'traitement',
            'medications-list': 'ordonnance',
            'exams-list': 'diagnostic'
        };

        function insertTextIntoField(fieldId, text, append = true) {
            const el = document.getElementById(fieldId);
            if (!el) return;
            const trimmed = String(text || '').trim();
            if (!trimmed) return;
            if (append && el.value.trim()) {
                el.value = el.value.trim() + '\n\n' + trimmed;
            } else {
                el.value = trimmed;
            }
            el.style.backgroundColor = '#e8f5e8';
            el.focus();
            setTimeout(() => { el.style.backgroundColor = ''; }, 1200);
        }

        function flashInsertedButton(btn) {
            if (!btn) return;
            const orig = btn.textContent;
            btn.textContent = '✓ Inséré';
            btn.disabled = true;
            setTimeout(() => {
                btn.textContent = orig;
                btn.disabled = false;
            }, 1500);
        }

        function buildWrittenSuggestions(data) {
            const blocks = [];
            const seen = new Set();

            function addBlock(cible, label, texte, source) {
                const key = cible + '|' + String(texte).trim().toLowerCase();
                if (!texte || !String(texte).trim() || seen.has(key)) return;
                seen.add(key);
                blocks.push({ cible, label, texte: String(texte).trim(), source: source || 'local' });
            }

            if (data.diagnostic && data.diagnostic.diagnostic_complet) {
                addBlock('diagnostic', 'Compte-rendu diagnostic', data.diagnostic.diagnostic_complet, 'local');
            }
            if (data.traitement) {
                addBlock('traitement', 'Plan de traitement', data.traitement, 'local');
            }
            if (data.ordonnance) {
                addBlock('ordonnance', 'Ordonnance type', data.ordonnance, 'local');
            }

            const mistral = data.mistral || {};
            Object.entries(mistral.textes || {}).forEach(([cible, texte]) => {
                const labels = { diagnostic: 'Diagnostic suggéré', traitement: 'Traitement suggéré', ordonnance: 'Ordonnance suggérée' };
                addBlock(cible, labels[cible] || 'Suggestion IA', texte, 'mistral');
            });
            (mistral.phrases || []).forEach((p, i) => {
                addBlock(p.cible, 'Phrase suggérée ' + (i + 1), p.texte, 'mistral');
            });

            return blocks;
        }

        function displayWrittenSuggestions(data) {
            const section = document.getElementById('text-suggestions-section');
            const container = document.getElementById('text-suggestions-list');
            if (!section || !container) return;

            const blocks = buildWrittenSuggestions(data);
            container.innerHTML = '';

            if (blocks.length === 0) {
                section.style.display = 'none';
                return;
            }

            section.style.display = 'block';
            blocks.forEach(block => {
                const card = document.createElement('div');
                card.className = 'text-suggestion-card';
                const fieldLabel = FIELD_LABELS[block.cible] || block.cible;
                card.innerHTML = `
                    <div class="text-suggestion-head">
                        <span>
                            <strong>${escapeHtml(block.label)}</strong>
                            <span class="badge bg-light text-dark border ms-1">${escapeHtml(fieldLabel)}</span>
                            ${block.source === 'mistral' ? '<span class="mistral-badge">IA</span>' : ''}
                        </span>
                        <button type="button" class="btn-insert-text" data-field="${escapeHtml(block.cible)}" title="Insérer dans ${escapeHtml(fieldLabel)}">
                            <i class="fas fa-arrow-down me-1"></i>Insérer
                        </button>
                    </div>
                    <div class="text-suggestion-body">${escapeHtml(block.texte)}</div>
                `;
                const btn = card.querySelector('.btn-insert-text');
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    insertTextIntoField(block.cible, block.texte, true);
                    flashInsertedButton(btn);
                });
                card.addEventListener('dblclick', () => insertTextIntoField(block.cible, block.texte, false));
                container.appendChild(card);
            });
        }
        
        // Fonction pour analyser les symptômes
        async function analyzeSymptoms() {
            const symptomes = document.getElementById('symptomes').value.trim();
            
            // Afficher l'indicateur de frappe
            const typingIndicator = document.getElementById('typing-indicator');
            typingIndicator.classList.add('show');
            
            // Effacer le timer précédent
            if (typingTimer) {
                clearTimeout(typingTimer);
            }
            
            // Attendre 1 seconde après que l'utilisateur arrête de taper
            typingTimer = setTimeout(async () => {
                typingIndicator.classList.remove('show');
                
                if (symptomes.length < 3) {
                    hideDiagnostic();
                    return;
                }
                
                if (analysisInProgress) {
                    return;
                }
                
                analysisInProgress = true;
                await performDiagnosticAnalysis(symptomes);
            }, 1000);
        }
        
        // Fonction pour effectuer l'analyse de diagnostic
        async function performDiagnosticAnalysis(symptomes) {
            const patientId = document.getElementById('patient_id').value;
            
            showLoadingDiagnostic();
            
            try {
                console.log('Début de l\'analyse IA pour:', symptomes);
                
                let response = null;
                let apiUrl = '';
                
                // Essayer d'abord avec un chemin relatif
                try {
                    apiUrl = `api_diagnostic.php?symptomes=${encodeURIComponent(symptomes)}&patient_id=${patientId}`;
                    console.log('URL API (relatif):', apiUrl);
                    response = await fetch(apiUrl);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                } catch (relativeError) {
                    console.log('Chemin relatif échoué, essai avec chemin absolu');
                    // Essayer avec un chemin absolu
                    apiUrl = `/efficasante/consultations/api_diagnostic.php?symptomes=${encodeURIComponent(symptomes)}&patient_id=${patientId}`;
                    console.log('URL API (absolu):', apiUrl);
                    response = await fetch(apiUrl);
                    
                    if (!response.ok) {
                        console.error('Erreur HTTP:', response.status, response.statusText);
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                }
                
                console.log('Réponse API reçue avec succès');
                const data = await response.json();
                console.log('Données JSON:', data);
                
                if (data.success) {
                    currentDiagnosticData = data.data;
                    mistralDiagnosticItems = (data.data.mistral && data.data.mistral.items) ? data.data.mistral.items : {};
                    displayDiagnosticSuggestions({
                        ...data.data,
                        patient_info: data.patient_info,
                        safety_checks: data.data.safety_checks,
                        mistral_meta: data.mistral,
                        ia_config: data.ia_config
                    });
                } else {
                    showDiagnosticError(data.error || 'Erreur lors de l\'analyse des symptômes');
                }
            } catch (error) {
                console.error('Erreur lors de l\'analyse:', error);
                showDiagnosticError('Erreur de connexion au serveur: ' + error.message);
            } finally {
                analysisInProgress = false;
            }
        }
        
        // Afficher l'état de chargement
        function showLoadingDiagnostic() {
            document.getElementById('ai-diagnostic-container').classList.add('show');
            document.getElementById('loading-diagnostic').style.display = 'block';
            document.getElementById('diagnostic-content').style.display = 'none';
            document.getElementById('diagnostic-error').style.display = 'none';
        }
        
        // Afficher les suggestions de diagnostic
        function displayDiagnosticSuggestions(data) {
            try {
                document.getElementById('loading-diagnostic').style.display = 'none';
                document.getElementById('diagnostic-content').style.display = 'block';

                const iaLabel = document.getElementById('ia-source-label');
                const mistralNote = document.getElementById('mistral-note-container');
                if (iaLabel) {
                    if (data.mistral_meta && data.mistral_meta.enriched) {
                        iaLabel.innerHTML = '<span class="mistral-badge"><i class="fas fa-robot me-1"></i>+ IA</span>';
                    } else if (data.ia_config && data.ia_config.consultations) {
                        iaLabel.textContent = '(IA activée)';
                    } else {
                        iaLabel.textContent = '(base locale)';
                    }
                }
                if (mistralNote) {
                    const note = (data.mistral_meta && data.mistral_meta.note) || (data.mistral && data.mistral.note) || '';
                    if (note) {
                        mistralNote.style.display = 'block';
                        mistralNote.innerHTML = '<i class="fas fa-robot me-2"></i>' + escapeHtml(note);
                    } else {
                        mistralNote.style.display = 'none';
                        mistralNote.innerHTML = '';
                    }
                }
                
                // Vérifier que les données sont valides
                if (!data || !data.diagnostic || !data.diagnostic.analysis) {
                    throw new Error('Données de diagnostic invalides');
                }
                
                // Afficher les diagnostics
                if (data.diagnostic.analysis.diagnostics) {
                    displayDiagnosticList('diagnostics-list', data.diagnostic.analysis.diagnostics, mistralDiagnosticItems.diagnostics);
                }
                
                // Afficher les traitements
                if (data.diagnostic.analysis.traitements) {
                    displayDiagnosticList('treatments-list', data.diagnostic.analysis.traitements, mistralDiagnosticItems.traitements);
                }
                
                // Afficher les médicaments avec vérification des contre-indications
                if (data.diagnostic.analysis.medicaments) {
                    displayMedicationsList('medications-list', data.diagnostic.analysis.medicaments, data.safety_checks, mistralDiagnosticItems.medicaments);
                }
                
                // Afficher les examens
                if (data.diagnostic.analysis.examens) {
                    displayDiagnosticList('exams-list', data.diagnostic.analysis.examens, mistralDiagnosticItems.examens);
                }
                
                // Afficher les alertes de sécurité
                if (data.safety_checks) {
                    displaySafetyAlerts(data.safety_checks);
                }

                displayWrittenSuggestions(data);
                
                // Afficher les antécédents du patient (si le conteneur existe)
                if (document.getElementById('patient-history-container')) {
                    displayPatientHistory(data.patient_info);
                }
                
                console.log('Affichage des suggestions de diagnostic réussi');
            } catch (error) {
                console.error('Erreur lors de l\'affichage des suggestions:', error);
                showDiagnosticError('Erreur lors de l\'affichage des suggestions: ' + error.message);
            }
        }
        
        // Afficher une liste de suggestions
        function displayDiagnosticList(containerId, items, mistralItems) {
            const container = document.getElementById(containerId);
            const mistralSet = new Set((mistralItems || []).map(s => String(s).toLowerCase().trim()));
            const targetField = LIST_FIELD_MAP[containerId] || 'diagnostic';
            const targetLabel = FIELD_LABELS[targetField] || targetField;
            
            if (!container) {
                console.error('Conteneur non trouvé:', containerId);
                return;
            }
            
            if (!items || !Array.isArray(items)) {
                console.error('Items invalides pour', containerId, ':', items);
                container.innerHTML = '<div class="text-muted">Aucune suggestion disponible</div>';
                return;
            }
            
            container.innerHTML = '';
            
            items.forEach((item, index) => {
                if (item) {
                    const isMistral = mistralSet.has(String(item).toLowerCase().trim());
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'diagnostic-item';
                    itemDiv.innerHTML = `
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <span class="diagnostic-item-text">${escapeHtml(item)}</span>
                            <span class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                                ${isMistral ? '<span class="mistral-badge">IA</span>' : ''}
                                ${!isMistral && index === 0 ? '<span class="confidence-badge">Recommandé</span>' : ''}
                                <button type="button" class="btn-insert-text btn-insert-inline" title="Insérer dans ${escapeHtml(targetLabel)}">
                                    <i class="fas fa-plus me-1"></i>Insérer
                                </button>
                            </span>
                        </div>
                    `;
                    
                    const insertBtn = itemDiv.querySelector('.btn-insert-inline');
                    insertBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        insertTextIntoField(targetField, item, true);
                        flashInsertedButton(insertBtn);
                    });
                    itemDiv.addEventListener('dblclick', () => insertTextIntoField(targetField, item, true));
                    
                    container.appendChild(itemDiv);
                }
            });
        }
        
        // Afficher la liste des médicaments avec vérification des contre-indications
        function displayMedicationsList(containerId, medications, safetyChecks, mistralItems) {
            const container = document.getElementById(containerId);
            const mistralSet = new Set((mistralItems || []).map(s => String(s).toLowerCase().trim()));
            
            if (!container) {
                console.error('Conteneur non trouvé:', containerId);
                return;
            }
            
            if (!medications || !Array.isArray(medications)) {
                console.error('Médicaments invalides pour', containerId, ':', medications);
                container.innerHTML = '<div class="text-muted">Aucun médicament suggéré</div>';
                return;
            }
            
            container.innerHTML = '';
            
            medications.forEach((medication, index) => {
                if (!medication) return; // Ignorer les éléments vides
                const isMistral = mistralSet.has(String(medication).toLowerCase().trim());
                const itemDiv = document.createElement('div');
                let itemClass = 'diagnostic-item';
                let warningBadge = '';
                
                // Vérifier si le médicament est contre-indiqué
                if (safetyChecks && safetyChecks.contraindications && safetyChecks.contraindications.includes(medication)) {
                    itemClass += ' medication-contraindicated';
                    warningBadge = '<span class="badge bg-danger">CONTRE-INDIQUÉ</span>';
                } else if (safetyChecks && safetyChecks.interactions && safetyChecks.interactions.includes(medication)) {
                    itemClass += ' medication-warning';
                    warningBadge = '<span class="badge bg-warning">ATTENTION</span>';
                } else if (isMistral) {
                    warningBadge = '<span class="mistral-badge">IA</span>';
                } else if (index === 0) {
                    warningBadge = '<span class="confidence-badge">Recommandé</span>';
                }
                
                itemDiv.className = itemClass;
                itemDiv.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <span class="diagnostic-item-text">${escapeHtml(medication)}</span>
                        <span class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                            ${warningBadge}
                            <button type="button" class="btn-insert-text btn-insert-inline" title="Insérer dans Ordonnance">
                                <i class="fas fa-plus me-1"></i>Insérer
                            </button>
                        </span>
                    </div>
                `;
                
                const insertBtn = itemDiv.querySelector('.btn-insert-inline');
                if (!(safetyChecks && safetyChecks.contraindications && safetyChecks.contraindications.includes(medication))) {
                    insertBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        insertTextIntoField('ordonnance', medication, true);
                        flashInsertedButton(insertBtn);
                    });
                    itemDiv.addEventListener('dblclick', () => insertTextIntoField('ordonnance', medication, true));
                } else {
                    insertBtn.disabled = true;
                    insertBtn.title = 'Médicament contre-indiqué';
                }
                
                // Désactiver la sélection si contre-indiqué
                if (!(safetyChecks && safetyChecks.contraindications && safetyChecks.contraindications.includes(medication))) {
                    itemDiv.style.cursor = 'default';
                } else {
                    itemDiv.style.cursor = 'not-allowed';
                    itemDiv.title = 'Ce médicament est contre-indiqué pour ce patient';
                }
                
                container.appendChild(itemDiv);
            });
        }
        
        // Afficher les alertes de sécurité
        function displaySafetyAlerts(safetyChecks) {
            const container = document.getElementById('safety-alerts-container');
            const warningsList = document.getElementById('safety-warnings-list');
            
            // Vérifier que les éléments existent
            if (!container || !warningsList) {
                console.warn('Conteneurs d\'alertes de sécurité non trouvés');
                return;
            }
            
            if (!safetyChecks || !safetyChecks.has_warnings) {
                container.classList.remove('show');
                return;
            }
            
            container.classList.add('show');
            warningsList.innerHTML = '';
            
            safetyChecks.warnings.forEach(warning => {
                const warningDiv = document.createElement('div');
                warningDiv.className = 'warning-item severe';
                warningDiv.innerHTML = `
                    <div class="d-flex align-items-start">
                        <i class="fas fa-exclamation-triangle me-2 mt-1 text-danger"></i>
                        <span>${warning}</span>
                    </div>
                `;
                warningsList.appendChild(warningDiv);
            });
        }
        
        // Afficher les antécédents du patient
        function displayPatientHistory(patientInfo) {
            const container = document.getElementById('patient-history-container');
            const content = document.getElementById('patient-history-content');
            
            // Vérifier que les éléments existent
            if (!container || !content) {
                console.log('Conteneur d\'antécédents patient non trouvé');
                return;
            }
            
            if (!patientInfo || (!patientInfo.antecedents && !patientInfo.allergies)) {
                container.classList.remove('show');
                return;
            }
            
            container.classList.add('show');
            content.innerHTML = '';
            
            // Antécédents médicaux
            if (patientInfo.antecedents) {
                const antecedentsDiv = document.createElement('div');
                antecedentsDiv.className = 'history-item';
                antecedentsDiv.innerHTML = `
                    <strong>Antécédents médicaux :</strong><br>
                    ${patientInfo.antecedents}
                `;
                content.appendChild(antecedentsDiv);
            }
            
            // Allergies
            if (patientInfo.allergies) {
                const allergiesDiv = document.createElement('div');
                allergiesDiv.className = 'history-item';
                allergiesDiv.innerHTML = `
                    <strong>Allergies :</strong><br>
                    ${patientInfo.allergies}
                `;
                content.appendChild(allergiesDiv);
            }
            
            // Informations démographiques
            if (patientInfo.age || patientInfo.sexe) {
                const demogDiv = document.createElement('div');
                demogDiv.className = 'history-item';
                let demogInfo = '<strong>Informations :</strong><br>';
                if (patientInfo.age) demogInfo += `Âge : ${patientInfo.age} ans<br>`;
                if (patientInfo.sexe) demogInfo += `Sexe : ${patientInfo.sexe === 'M' ? 'Masculin' : 'Féminin'}`;
                demogDiv.innerHTML = demogInfo;
                content.appendChild(demogDiv);
            }
        }
        
        // Afficher une erreur
        function showDiagnosticError(message) {
            document.getElementById('loading-diagnostic').style.display = 'none';
            document.getElementById('diagnostic-content').style.display = 'none';
            document.getElementById('diagnostic-error').style.display = 'block';
            document.getElementById('diagnostic-error-message').textContent = message;
        }
        
        // Masquer les suggestions
        function hideDiagnostic() {
            document.getElementById('ai-diagnostic-container').classList.remove('show');
        }
        
        // Remplir automatiquement le diagnostic
        function autoFillDiagnostic() {
            if (!currentDiagnosticData) return;
            
            const diagnosticText = currentDiagnosticData.diagnostic.diagnostic_complet;
            document.getElementById('diagnostic').value = diagnosticText;
            
            // Effet visuel
            const textarea = document.getElementById('diagnostic');
            textarea.style.backgroundColor = '#e8f5e8';
            setTimeout(() => {
                textarea.style.backgroundColor = '';
            }, 1000);
        }
        
        // Remplir automatiquement le traitement
        function autoFillTreatment() {
            if (!currentDiagnosticData) return;
            
            const treatmentText = currentDiagnosticData.traitement;
            document.getElementById('traitement').value = treatmentText;
            
            // Effet visuel
            const textarea = document.getElementById('traitement');
            textarea.style.backgroundColor = '#e8f5e8';
            setTimeout(() => {
                textarea.style.backgroundColor = '';
            }, 1000);
        }
        
        // Remplir automatiquement l'ordonnance
        function autoFillPrescription() {
            if (!currentDiagnosticData) return;
            
            // Utiliser l'ordonnance sécurisée si disponible
            let prescriptionText = currentDiagnosticData.ordonnance;
            
            // Si on a des vérifications de sécurité, utiliser la version sécurisée
            if (currentDiagnosticData.safety_checks) {
                const analysis = currentDiagnosticData.diagnostic.analysis;
                prescriptionText = generateSecurePrescription(analysis, currentDiagnosticData.safety_checks);
            }
            
            document.getElementById('ordonnance').value = prescriptionText;
            
            // Effet visuel
            const textarea = document.getElementById('ordonnance');
            textarea.style.backgroundColor = '#e8f5e8';
            setTimeout(() => {
                textarea.style.backgroundColor = '';
            }, 1000);
        }
        
        // Générer une ordonnance sécurisée côté client
        function generateSecurePrescription(analysis, safetyChecks) {
            let ordonnance = "ORDONNANCE MÉDICALE\n";
            ordonnance += "Date : " + new Date().toLocaleDateString('fr-FR') + "\n\n";
            
            // Vérifier que analysis est défini
            if (!analysis) {
                return "Erreur: Données d'analyse non disponibles";
            }
            
            // Filtrer les médicaments contre-indiqués
            let medicaments = analysis.medicaments || [];
            if (safetyChecks && safetyChecks.contraindications && safetyChecks.contraindications.length > 0) {
                medicaments = medicaments.filter(med => !safetyChecks.contraindications.includes(med));
            }
            
            ordonnance += "MÉDICAMENTS PRESCRITS :\n";
            if (medicaments.length > 0) {
                medicaments.slice(0, 4).forEach((medicament, index) => {
                    ordonnance += (index + 1) + ". " + medicament + "\n";
                });
            } else {
                ordonnance += "Aucun médicament prescrit\n";
            }
            
            // Ajouter les avertissements de sécurité
            if (safetyChecks && safetyChecks.has_warnings && safetyChecks.warnings) {
                ordonnance += "\n⚠️ AVERTISSEMENTS DE SÉCURITÉ :\n";
                safetyChecks.warnings.forEach(warning => {
                    ordonnance += warning + "\n";
                });
            }
            
            // Ajouter les examens recommandés
            if (analysis.examens && analysis.examens.length > 0) {
                ordonnance += "\nEXAMENS COMPLÉMENTAIRES RECOMMANDÉS :\n";
                analysis.examens.slice(0, 3).forEach((examen, index) => {
                    ordonnance += (index + 1) + ". " + examen + "\n";
                });
            }
            
            ordonnance += "\nINSTRUCTIONS :\n";
            ordonnance += "- Respecter les posologies\n";
            ordonnance += "- Ne pas arrêter le traitement sans avis médical\n";
            ordonnance += "- Consulter en cas d'effets indésirables\n";
            
            if (safetyChecks && safetyChecks.has_warnings) {
                ordonnance += "- ⚠️ SURVEILLANCE RENFORCÉE REQUISE\n";
            }
            
            return ordonnance;
        }
        
        // Recharger l'analyse si le patient change
        document.getElementById('patient_id').addEventListener('change', function() {
            const symptomes = document.getElementById('symptomes').value.trim();
            if (symptomes.length >= 3) {
                analyzeSymptoms();
            }
        });
        
        // Fonction utilitaire pour créer des éléments avec animation
        function createAnimatedElement(tag, className, content) {
            const element = document.createElement(tag);
            element.className = className;
            element.innerHTML = content;
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                element.style.transition = 'all 0.3s ease';
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, 100);
            
            return element;
        }
        
        // Fonction pour afficher les informations du patient
        function showPatientContext() {
            const patientId = document.getElementById('patient_id').value;
            if (patientId) {
                // Ici on pourrait ajouter des informations contextuelles sur le patient
                // Par exemple, ses antécédents médicaux, allergies, etc.
            }
        }
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Vérifier si des symptômes sont déjà présents
            const symptomes = document.getElementById('symptomes').value.trim();
            if (symptomes.length >= 3) {
                analyzeSymptoms();
            }
        });
        
        // Validation en temps réel de la date/heure de consultation
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('date_consultation');
            const timezoneDisplay = document.getElementById('timezone-display');
            
            // Mettre à jour l'heure actuelle toutes les minutes
            function updateCurrentTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('fr-FR', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    timeZone: 'Africa/Dakar'
                });
                const timezoneElement = timezoneDisplay.parentElement.querySelector('.text-muted');
                if (timezoneElement) {
                    timezoneElement.textContent = `(${timeString})`;
                }
            }
            
            // Pas de validation de date - on accepte les dates passées et futures
            dateInput.addEventListener('change', function() {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            });
            
            // Mettre à jour l'heure actuelle
            updateCurrentTime();
            setInterval(updateCurrentTime, 60000); // Toutes les minutes
            
            // Pas de validation de date au submit - on accepte les dates passées et futures
            
            // Gestion de l'hospitalisation
            const hospitalisationCheckbox = document.getElementById('hospitalisation_requise');
            const hospitalisationSection = document.getElementById('hospitalisation-section');
            const categorieSelect = document.getElementById('categorie_hospitalisation');
            const dureeInput = document.getElementById('duree_hospitalisation');
            const prixTotalSpan = document.getElementById('prix-total-hospitalisation');
            
            // Afficher/masquer la section hospitalisation
            hospitalisationCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    hospitalisationSection.style.display = 'block';
                } else {
                    hospitalisationSection.style.display = 'none';
                }
            });
            
            // Calcul automatique du prix total d'hospitalisation
            function calculerPrixHospitalisation() {
                const categorie = categorieSelect.options[categorieSelect.selectedIndex];
                const prixJour = parseFloat(categorie.dataset.prix) || 0;
                const duree = parseInt(dureeInput.value) || 1;
                const prixTotal = prixJour * duree;
                prixTotalSpan.textContent = typeof appFormatMoney === 'function'
                    ? appFormatMoney(prixTotal)
                    : prixTotal.toLocaleString('fr-FR') + ' FCFA';
            }
            
            categorieSelect.addEventListener('change', calculerPrixHospitalisation);
            dureeInput.addEventListener('input', calculerPrixHospitalisation);
            
            // Calcul automatique du prix selon le type de consultation
            const typeConsultationSelect = document.getElementById('type_consultation');
            const prixConsultationInput = document.getElementById('prix_consultation');
            const wrapTypeAutre = document.getElementById('wrap_type_consultation_autre');
            const typeAutreInput = document.getElementById('type_consultation_autre');
            
            const tarifs = <?php echo json_encode($tarifs); ?>;

            function toggleTypeConsultationAutre() {
                const isAutre = typeConsultationSelect.value === '__autre__';
                if (wrapTypeAutre) {
                    wrapTypeAutre.style.display = isAutre ? 'block' : 'none';
                }
                if (typeAutreInput) {
                    typeAutreInput.required = isAutre;
                    if (!isAutre) {
                        typeAutreInput.value = '';
                    }
                }
            }
            
            typeConsultationSelect.addEventListener('change', function() {
                toggleTypeConsultationAutre();
                const type = this.value;
                if (type === '__autre__') {
                    return;
                }
                const tarif = tarifs.find(t => t.type_consultation === type);
                if (tarif) {
                    prixConsultationInput.value = parseFloat(tarif.prix).toFixed(2);
                }
            });

            toggleTypeConsultationAutre();
            
            // Gestion des soins
            let soinsSelectionnes = [];
            
            // Fonction pour mettre à jour le prix d'un soin
            window.updatePrixSoin = function() {
                const soinSelect = document.getElementById('soin_id');
                const quantiteInput = document.getElementById('quantite_soin');
                
                if (soinSelect.selectedOptions.length > 0 && soinSelect.value) {
                    const selectedOption = soinSelect.selectedOptions[0];
                    const prixStr = selectedOption.dataset.prix;
                    const prix = parseFloat(prixStr);
                    const quantite = parseInt(quantiteInput.value) || 1;
                    const total = prix * quantite;
                    
                    if (!isNaN(prix) && !isNaN(total) && prix > 0) {
                        // Afficher le prix total dans le bouton
                        const button = document.querySelector('button[onclick="addSoinToList()"]');
                        const prixLabel = typeof appFormatMoney === 'function' ? appFormatMoney(total) : total.toLocaleString('fr-FR') + ' FCFA';
                        button.innerHTML = `<i class="fas fa-plus me-2"></i>Ajouter (${prixLabel})`;
                    } else {
                        const button = document.querySelector('button[onclick="addSoinToList()"]');
                        button.innerHTML = `<i class="fas fa-plus me-2"></i>Ajouter`;
                    }
                } else {
                    const button = document.querySelector('button[onclick="addSoinToList()"]');
                    button.innerHTML = `<i class="fas fa-plus me-2"></i>Ajouter`;
                }
            };
            
            // Fonction pour ajouter un soin à la liste
            window.addSoinToList = function() {
                const soinSelect = document.getElementById('soin_id');
                const quantiteInput = document.getElementById('quantite_soin');
                
                if (!soinSelect.value) {
                    alert('Veuillez sélectionner un soin');
                    return;
                }
                
                const selectedOption = soinSelect.selectedOptions[0];
                const soinId = soinSelect.value;
                const nom = selectedOption.textContent.split(' - ')[0];
                const prixStr = selectedOption.dataset.prix;
                const prix = parseFloat(prixStr);
                const quantite = parseInt(quantiteInput.value) || 1;
                
                if (isNaN(prix) || prix <= 0) {
                    alert('Erreur : Prix du soin invalide');
                    return;
                }
                
                const total = prix * quantite;
                
                // Vérifier si le soin n'est pas déjà dans la liste
                const existingIndex = soinsSelectionnes.findIndex(s => s.id === soinId);
                if (existingIndex !== -1) {
                    soinsSelectionnes[existingIndex].quantite += quantite;
                    soinsSelectionnes[existingIndex].total = soinsSelectionnes[existingIndex].prix * soinsSelectionnes[existingIndex].quantite;
                } else {
                    soinsSelectionnes.push({
                        id: soinId,
                        nom: nom,
                        prix: prix,
                        quantite: quantite,
                        total: total
                    });
                }
                
                updateSoinsList();
                
                // Réinitialiser le formulaire
                soinSelect.value = '';
                quantiteInput.value = 1;
                updatePrixSoin();
            };
            
            // Fonction pour mettre à jour la liste des soins
            function updateSoinsList() {
                const container = document.getElementById('soins-items');
                const totalSpan = document.getElementById('total-soins');
                const listDiv = document.getElementById('soins-list');
                const soinsDataInput = document.getElementById('soins-data');
                
                if (soinsSelectionnes.length === 0) {
                    listDiv.style.display = 'none';
                    soinsDataInput.value = '';
                    return;
                }
                
                listDiv.style.display = 'block';
                container.innerHTML = '';
                
                let totalGeneral = 0;
                
                soinsSelectionnes.forEach((soin, index) => {
                    totalGeneral += soin.total;
                    
                    const soinDiv = document.createElement('div');
                    soinDiv.className = 'alert alert-success d-flex justify-content-between align-items-center mb-2';
                    soinDiv.innerHTML = `
                        <div>
                            <strong>${soin.nom}</strong><br>
                            <small class="text-muted">${typeof appFormatMoney === 'function' ? appFormatMoney(soin.prix) + ' × ' + soin.quantite + ' = ' + appFormatMoney(soin.total) : soin.prix.toLocaleString('fr-FR') + ' FCFA × ' + soin.quantite + ' = ' + soin.total.toLocaleString('fr-FR') + ' FCFA'}</small>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSoin(${index})" title="Retirer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    container.appendChild(soinDiv);
                });
                
                totalSpan.textContent = typeof appFormatMoney === 'function'
                    ? appFormatMoney(totalGeneral)
                    : totalGeneral.toLocaleString('fr-FR') + ' FCFA';
                soinsDataInput.value = JSON.stringify(soinsSelectionnes);
            }
            
            // Fonction pour retirer un soin
            window.removeSoin = function(index) {
                soinsSelectionnes.splice(index, 1);
                updateSoinsList();
            };
            
            // Initialiser le prix du soin
            document.getElementById('soin_id').addEventListener('change', updatePrixSoin);
            updatePrixSoin();
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
