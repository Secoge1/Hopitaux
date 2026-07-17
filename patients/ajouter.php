<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('patients'));

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Assurance.php';
require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../includes/staff_scope.php';
require_once __DIR__ . '/../includes/patient_birthdate.php';

$patientModel = new Patient();
$assuranceModel = new Assurance();
$medecinModel = new Medecin();
$canAssignMedecin = StaffScope::canAssignPatientMedecin();
$medecins = $canAssignMedecin ? $medecinModel->listForAssignment() : [];

// Récupérer la liste des assurances actives
$assurances = $assuranceModel->getAll(1, 1000, '', 'actif');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDB();
        $pdo->beginTransaction();
        
        $data = [
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'date_naissance' => patient_resolve_date_naissance_from_post($_POST),
            'genre' => $_POST['genre'],
            'groupe_sanguin' => $_POST['groupe_sanguin'] ?: null,
            'telephone' => $_POST['telephone'] ?: null,
            'email' => $_POST['email'] ?: null,
            'adresse' => $_POST['adresse'] ?: null,
            'ville' => $_POST['ville'] ?: null,
            'code_postal' => $_POST['code_postal'] ?: null,
            'pays' => $_POST['pays'] ?: 'Mali',
            'profession' => $_POST['profession'] ?: null,
            'statut' => $_POST['statut'] ?? 'actif',
            'antecedents_medicaux' => $_POST['antecedents_medicaux'] ?: null,
            'allergies' => $_POST['allergies'] ?: null,
            'notes' => $_POST['notes'] ?: null,
        ];
        if ($canAssignMedecin) {
            $data['medecin_referent_id'] = $_POST['medecin_referent_id'] ?? null;
        }

        $patient_id = $patientModel->create($data);
        
        if ($patient_id) {
            $createdPatient = $patientModel->getById((int) $patient_id);
            // Gestion de l'assurance
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
                    'notes' => 'Créée depuis le formulaire patient'
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
            
            // Créer un contrat d'assurance si une assurance est sélectionnée
            if ($assurance_id) {
                $contratData = [
                    'patient_id' => $patient_id,
                    'assurance_id' => $assurance_id,
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
            }
            
            $pdo->commit();
            // Invalider le cache du dashboard pour que le compteur patients soit à jour
            try {
                require_once __DIR__ . '/../includes/CacheSystem.php';
                CacheSystem::getInstance()->invalidateDashboardCache();
            } catch (Exception $e) { /* ignorer */ }
            $message = "Patient créé avec succès ! Numéro de dossier : " . ($createdPatient['numero_dossier'] ?? '');
            if ($assurance_id) {
                $message .= " | Assurance associée avec succès.";
            }
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . app_url('patients/index.php'));
            exit;
        } else {
            $pdo->rollBack();
            $error = "Erreur lors de la création du patient.";
        }
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        $error = "Erreur : " . $e->getMessage();
    }
}

app_module_page_start([
    'active'    => 'patients',
    'title'     => 'Nouveau Patient',
    'subtitle'  => 'Création d\'un nouveau dossier patient',
    'icon'      => 'fa-user-plus',
    'extra_css' => ['assets/css/app-patients.css'],
]);
app_module_back_toolbar(app_url('patients/index.php'));
app_module_flash();
?>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card app-mod-form-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Informations du Patient</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <!-- Informations personnelles -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Informations Personnelles</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="nom" class="form-label">Nom *</label>
                        <input type="text" class="form-control" id="nom" name="nom" required>
                    </div>

                    <div class="col-md-6">
                        <label for="prenom" class="form-label">Prénom *</label>
                        <input type="text" class="form-control" id="prenom" name="prenom" required>
                    </div>

                    <div class="col-md-4">
                        <label for="age_ans" class="form-label">Âge (ans) *</label>
                        <input type="number" class="form-control" id="age_ans" name="age_ans" min="0" max="120" step="1" required placeholder="Ex. 35" inputmode="numeric">
                        <small class="form-text text-muted">Saisie directe si la date exacte est inconnue</small>
                        <button type="button" class="btn btn-link btn-sm p-0 mt-1" id="toggle_date_naissance" aria-expanded="false">
                            <i class="fas fa-calendar-alt me-1"></i>Date exacte connue
                        </button>
                        <div id="date_naissance_wrap" class="mt-2" hidden>
                            <label for="date_naissance" class="form-label">Date de naissance exacte</label>
                            <input type="date" class="form-control" id="date_naissance" name="date_naissance" disabled max="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label for="genre" class="form-label">Genre *</label>
                        <select class="form-select" id="genre" name="genre" required>
                            <option value="">Choisir...</option>
                            <option value="M">Homme</option>
                            <option value="F">Femme</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="groupe_sanguin" class="form-label">Groupe sanguin</label>
                        <select class="form-select" id="groupe_sanguin" name="groupe_sanguin">
                            <option value="">Non renseigné</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>

                    <?php
                    $selectedMedecinId = 0;
                    include __DIR__ . '/_medecin_referent_field.php';
                    ?>

                    <!-- Coordonnées -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-address-book me-2"></i>Coordonnées</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="telephone" class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" placeholder="00223 00 00 00 00">
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="patient@email.com">
                    </div>

                    <div class="col-12">
                        <label for="adresse" class="form-label">Adresse</label>
                        <input type="text" class="form-control" id="adresse" name="adresse" placeholder="123 Rue de la Santé">
                    </div>

                    <div class="col-md-4">
                        <label for="ville" class="form-label">Ville</label>
                        <input type="text" class="form-control" id="ville" name="ville" placeholder="Paris">
                    </div>

                    <div class="col-md-4">
                        <label for="code_postal" class="form-label">Code postal</label>
                        <input type="text" class="form-control" id="code_postal" name="code_postal" placeholder="75001">
                    </div>

                    <div class="col-md-4">
                        <label for="pays" class="form-label">Pays</label>
                        <select class="form-select" id="pays" name="pays" data-searchable="true">
                            <option value="" disabled>Tapez pour rechercher un pays...</option>
                            <option value="France">France</option>
                            <option value="Mali" selected>Mali</option>
                            <option value="Belgique">Belgique</option>
                            <option value="Suisse">Suisse</option>
                            <option value="Luxembourg">Luxembourg</option>
                            <option value="Allemagne">Allemagne</option>
                            <option value="Italie">Italie</option>
                            <option value="Espagne">Espagne</option>
                            <option value="Portugal">Portugal</option>
                            <option value="Pays-Bas">Pays-Bas</option>
                            <option value="Royaume-Uni">Royaume-Uni</option>
                            <option value="Canada">Canada</option>
                            <option value="États-Unis">États-Unis</option>
                            <option value="Maroc">Maroc</option>
                            <option value="Algérie">Algérie</option>
                            <option value="Tunisie">Tunisie</option>
                            <option value="Sénégal">Sénégal</option>
                            <option value="Côte d'Ivoire">Côte d'Ivoire</option>
                            <option value="Mali">Mali</option>
                            <option value="Burkina Faso">Burkina Faso</option>
                            <option value="Niger">Niger</option>
                            <option value="Tchad">Tchad</option>
                            <option value="Cameroun">Cameroun</option>
                            <option value="République centrafricaine">République centrafricaine</option>
                            <option value="Gabon">Gabon</option>
                            <option value="Congo">Congo</option>
                            <option value="République démocratique du Congo">République démocratique du Congo</option>
                            <option value="Angola">Angola</option>
                            <option value="Guinée équatoriale">Guinée équatoriale</option>
                            <option value="Sao Tomé-et-Prince">Sao Tomé-et-Prince</option>
                            <option value="Guinée-Bissau">Guinée-Bissau</option>
                            <option value="Guinée">Guinée</option>
                            <option value="Sierra Leone">Sierra Leone</option>
                            <option value="Libéria">Libéria</option>
                            <option value="Ghana">Ghana</option>
                            <option value="Togo">Togo</option>
                            <option value="Bénin">Bénin</option>
                            <option value="Nigeria">Nigeria</option>
                            <option value="Soudan">Soudan</option>
                            <option value="Éthiopie">Éthiopie</option>
                            <option value="Somalie">Somalie</option>
                            <option value="Kenya">Kenya</option>
                            <option value="Ouganda">Ouganda</option>
                            <option value="Rwanda">Rwanda</option>
                            <option value="Burundi">Burundi</option>
                            <option value="Tanzanie">Tanzanie</option>
                            <option value="Zambie">Zambie</option>
                            <option value="Zimbabwe">Zimbabwe</option>
                            <option value="Botswana">Botswana</option>
                            <option value="Namibie">Namibie</option>
                            <option value="Afrique du Sud">Afrique du Sud</option>
                            <option value="Lesotho">Lesotho</option>
                            <option value="Eswatini">Eswatini</option>
                            <option value="Madagascar">Madagascar</option>
                            <option value="Maurice">Maurice</option>
                            <option value="Seychelles">Seychelles</option>
                            <option value="Comores">Comores</option>
                            <option value="Mayotte">Mayotte</option>
                            <option value="Réunion">Réunion</option>
                            <option value="Autre">Autre</option>
                        </select>
                        <small class="form-text text-muted">
                            <i class="fas fa-search me-1"></i>Tapez directement dans la liste pour rechercher un pays
                        </small>
                    </div>

                    <!-- Informations professionnelles -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-briefcase me-2"></i>Informations Professionnelles</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="profession" class="form-label">Profession</label>
                        <input type="text" class="form-control" id="profession" name="profession" placeholder="Employé, Retraité, Étudiant...">
                    </div>

                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                            <option value="archive">Archivé</option>
                        </select>
                    </div>

                    <!-- Informations médicales -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-notes-medical me-2"></i>Informations Médicales</h6>
                    </div>

                    <div class="col-12">
                        <label for="antecedents_medicaux" class="form-label">Antécédents médicaux</label>
                        <textarea class="form-control" id="antecedents_medicaux" name="antecedents_medicaux" rows="3"
                                  placeholder="Antécédents médicaux, chirurgicaux, familiaux..."></textarea>
                    </div>

                    <div class="col-12">
                        <label for="allergies" class="form-label">Allergies</label>
                        <textarea class="form-control" id="allergies" name="allergies" rows="2"
                                  placeholder="Allergies médicamenteuses, alimentaires, environnementales..."></textarea>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes additionnelles</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  placeholder="Informations complémentaires, observations..."></textarea>
                    </div>

                    <!-- Section Assurance -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-shield-alt me-2"></i>Assurance</h6>
                        <div class="card border-primary">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Type d'assurance</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="assurance_action" id="assurance_aucune" value="aucune" checked onchange="toggleAssuranceSection()">
                                        <label class="form-check-label" for="assurance_aucune">
                                            Aucune assurance
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="assurance_action" id="assurance_existant" value="existant" onchange="toggleAssuranceSection()">
                                        <label class="form-check-label" for="assurance_existant">
                                            Assurance existante
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="assurance_action" id="assurance_nouvelle" value="nouvelle" onchange="toggleAssuranceSection()">
                                        <label class="form-check-label" for="assurance_nouvelle">
                                            Créer une nouvelle assurance
                                        </label>
                                    </div>
                                </div>

                                <!-- Assurance existante -->
                                <div id="assurance_existant_section" style="display: none;">
                                    <div class="mb-3">
                                        <label for="assurance_id" class="form-label">Sélectionner une assurance *</label>
                                        <select class="form-select" id="assurance_id" name="assurance_id">
                                            <option value="">Choisir une assurance...</option>
                                            <?php foreach ($assurances as $assurance): ?>
                                                <option value="<?php echo $assurance['id']; ?>">
                                                    <?php echo htmlspecialchars($assurance['nom']); ?> 
                                                    (<?php echo ucfirst($assurance['type']); ?> - 
                                                    <?php echo number_format($assurance['taux_remboursement'], 2); ?>%)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            L'assurance sélectionnée sera synchronisée avec le module Assurances
                                        </small>
                                    </div>
                                </div>

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
                                        <strong>Note :</strong> Cette assurance sera automatiquement ajoutée au module Assurances pour une meilleure transparence et synchronisation.
                                    </div>
                                </div>

                                <!-- Détails du contrat (si assurance sélectionnée) -->
                                <div id="contrat_details_section" style="display: none;">
                                    <hr class="my-4">
                                    <h6 class="text-secondary mb-3"><i class="fas fa-file-contract me-2"></i>Détails du Contrat</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="numero_police" class="form-label">Numéro de police</label>
                                            <input type="text" class="form-control" id="numero_police" name="numero_police" placeholder="Numéro de police d'assurance">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="date_debut_contrat" class="form-label">Date de début</label>
                                            <input type="date" class="form-control" id="date_debut_contrat" name="date_debut_contrat" value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="date_fin_contrat" class="form-label">Date de fin (optionnel)</label>
                                            <input type="date" class="form-control" id="date_fin_contrat" name="date_fin_contrat">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="taux_couverture" class="form-label">Taux de couverture (%)</label>
                                            <input type="number" class="form-control" id="taux_couverture" name="taux_couverture" value="100" step="0.01" min="0" max="100">
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
                                            <label for="notes_assurance" class="form-label">Notes sur l'assurance</label>
                                            <textarea class="form-control" id="notes_assurance" name="notes_assurance" rows="2" placeholder="Informations complémentaires sur le contrat..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Créer le patient
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
                    <li>Le <strong>numéro de dossier</strong> est généré automatiquement</li>
                    <li>Les champs marqués d'un <strong>*</strong> sont obligatoires</li>
                    <li>Renseignez les <strong>antécédents</strong> pour un meilleur suivi médical</li>
                    <li>Les <strong>allergies</strong> sont cruciales pour la sécurité du patient</li>
                    <li>Le <strong>statut</strong> peut être modifié ultérieurement</li>
                </ul>
            </div>
        </div>

<?php ob_start(); ?>
<script src="assets/patient_form.js"></script>
<script>
function toggleAssuranceSection() {
            const action = document.querySelector('input[name="assurance_action"]:checked').value;
            const existantSection = document.getElementById('assurance_existant_section');
            const nouvelleSection = document.getElementById('assurance_nouvelle_section');
            const contratSection = document.getElementById('contrat_details_section');
            
            // Masquer toutes les sections
            existantSection.style.display = 'none';
            nouvelleSection.style.display = 'none';
            contratSection.style.display = 'none';
            
            // Afficher la section appropriée
            if (action === 'existant') {
                existantSection.style.display = 'block';
                contratSection.style.display = 'block';
            } else if (action === 'nouvelle') {
                nouvelleSection.style.display = 'block';
                contratSection.style.display = 'block';
            }
        }
        
        // Écouter les changements sur le select d'assurance existante
        document.addEventListener('DOMContentLoaded', function() {
            const assuranceSelect = document.getElementById('assurance_id');
            if (assuranceSelect) {
                assuranceSelect.addEventListener('change', function() {
                    const contratSection = document.getElementById('contrat_details_section');
                    if (this.value && document.getElementById('assurance_existant').checked) {
                        contratSection.style.display = 'block';
                    }
                });
            }
});
</script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
