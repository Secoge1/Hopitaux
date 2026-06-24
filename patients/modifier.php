<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('patients'));

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Assurance.php';
require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../includes/staff_scope.php';

$patientModel = new Patient();
$assuranceModel = new Assurance();
$medecinModel = new Medecin();
$canAssignMedecin = StaffScope::canAssignPatientMedecin();
$medecins = $canAssignMedecin ? $medecinModel->listForAssignment() : [];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$patient = $patientModel->getById($id);
if (!$patient) {
    header("Location: index.php");
    exit();
}

// Récupérer les contrats d'assurance du patient
$contratsAssurance = $assuranceModel->getContratsByPatient($id);

// Récupérer toutes les assurances pour le formulaire
$assurances = $assuranceModel->getAll(1, 1000);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'date_naissance' => $_POST['date_naissance'],
            'genre' => $_POST['genre'],
            'groupe_sanguin' => $_POST['groupe_sanguin'] ?: null,
            'telephone' => $_POST['telephone'] ?: null,
            'email' => $_POST['email'] ?: null,
            'adresse' => $_POST['adresse'] ?: null,
            'ville' => $_POST['ville'] ?: null,
            'code_postal' => $_POST['code_postal'] ?: null,
            'pays' => $_POST['pays'] ?: 'France',
            'profession' => $_POST['profession'] ?: null,
            'statut' => $_POST['statut'] ?? 'actif',
            'antecedents_medicaux' => $_POST['antecedents_medicaux'] ?: null,
            'allergies' => $_POST['allergies'] ?: null,
            'notes' => $_POST['notes'] ?: null,
        ];
        if ($canAssignMedecin) {
            $data['medecin_referent_id'] = $_POST['medecin_referent_id'] ?? null;
        }

        if ($patientModel->update($id, $data)) {
            $message = "Patient mis à jour avec succès !";
            // Recharger les données
            $patient = $patientModel->getById($id);
        } else {
            $error = "Erreur lors de la mise à jour du patient.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

app_module_page_start([
    'active'    => 'patients',
    'title'     => 'Modifier le Patient',
    'subtitle'  => htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']),
    'icon'      => 'fa-edit',
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
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier le Patient #<?php echo $patient['id']; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <!-- Informations personnelles -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Informations Personnelles</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="nom" class="form-label">Nom *</label>
                        <input type="text" class="form-control" id="nom" name="nom" 
                               value="<?php echo htmlspecialchars($patient['nom']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="prenom" class="form-label">Prénom *</label>
                        <input type="text" class="form-control" id="prenom" name="prenom" 
                               value="<?php echo htmlspecialchars($patient['prenom']); ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label for="date_naissance" class="form-label">Date de naissance *</label>
                        <input type="date" class="form-control" id="date_naissance" name="date_naissance" 
                               value="<?php echo htmlspecialchars($patient['date_naissance']); ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label for="genre" class="form-label">Genre *</label>
                        <select class="form-select" id="genre" name="genre" required>
                            <option value="">Choisir...</option>
                            <option value="M" <?php echo ($patient['sexe'] ?? '') === 'M' ? 'selected' : ''; ?>>Homme</option>
                            <option value="F" <?php echo ($patient['sexe'] ?? '') === 'F' ? 'selected' : ''; ?>>Femme</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="groupe_sanguin" class="form-label">Groupe sanguin</label>
                        <select class="form-select" id="groupe_sanguin" name="groupe_sanguin">
                            <option value="">Non renseigné</option>
                            <?php
                            $groupes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($groupes as $groupe):
                                $selected = $patient['groupe_sanguin'] === $groupe ? 'selected' : '';
                            ?>
                                <option value="<?php echo $groupe; ?>" <?php echo $selected; ?>><?php echo $groupe; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php
                    $selectedMedecinId = (int) ($patient['medecin_referent_id'] ?? 0);
                    include __DIR__ . '/_medecin_referent_field.php';
                    ?>

                    <!-- Coordonnées -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-address-book me-2"></i>Coordonnées</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="telephone" class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" 
                               value="<?php echo htmlspecialchars($patient['telephone'] ?? ''); ?>" placeholder="06 12 34 56 78">
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>" placeholder="patient@email.com">
                    </div>

                    <div class="col-12">
                        <label for="adresse" class="form-label">Adresse</label>
                        <input type="text" class="form-control" id="adresse" name="adresse" 
                               value="<?php echo htmlspecialchars($patient['adresse'] ?? ''); ?>" placeholder="123 Rue de la Santé">
                    </div>

                    <div class="col-md-4">
                        <label for="ville" class="form-label">Ville</label>
                        <input type="text" class="form-control" id="ville" name="ville" 
                               value="<?php echo htmlspecialchars($patient['ville'] ?? ''); ?>" placeholder="Paris">
                    </div>

                    <div class="col-md-4">
                        <label for="code_postal" class="form-label">Code postal</label>
                        <input type="text" class="form-control" id="code_postal" name="code_postal" 
                               value="<?php echo htmlspecialchars($patient['code_postal'] ?? ''); ?>" placeholder="75001">
                    </div>

                    <div class="col-md-4">
                        <label for="pays" class="form-label">Pays</label>
                        <select class="form-select" id="pays" name="pays">
                            <?php
                            $pays = [
                                'France' => 'France',
                                'Belgique' => 'Belgique',
                                'Suisse' => 'Suisse',
                                'Canada' => 'Canada',
                                'Luxembourg' => 'Luxembourg',
                                'Monaco' => 'Monaco',
                                'Allemagne' => 'Allemagne',
                                'Espagne' => 'Espagne',
                                'Italie' => 'Italie',
                                'Portugal' => 'Portugal',
                                'Pays-Bas' => 'Pays-Bas',
                                'Royaume-Uni' => 'Royaume-Uni',
                                'États-Unis' => 'États-Unis',
                                'Maroc' => 'Maroc',
                                'Algérie' => 'Algérie',
                                'Tunisie' => 'Tunisie',
                                'Sénégal' => 'Sénégal',
                                'Côte d\'Ivoire' => 'Côte d\'Ivoire',
                                'Mali' => 'Mali',
                                'Burkina Faso' => 'Burkina Faso',
                                'Niger' => 'Niger',
                                'Tchad' => 'Tchad',
                                'Cameroun' => 'Cameroun',
                                'Gabon' => 'Gabon',
                                'Congo' => 'Congo',
                                'République démocratique du Congo' => 'République démocratique du Congo',
                                'Madagascar' => 'Madagascar',
                                'Comores' => 'Comores',
                                'Maurice' => 'Maurice',
                                'Seychelles' => 'Seychelles',
                                'Djibouti' => 'Djibouti',
                                'Guinée' => 'Guinée',
                                'Guinée-Bissau' => 'Guinée-Bissau',
                                'Guinée équatoriale' => 'Guinée équatoriale',
                                'Sao Tomé-et-Principe' => 'Sao Tomé-et-Principe',
                                'Cap-Vert' => 'Cap-Vert',
                                'Gambie' => 'Gambie',
                                'Sierra Leone' => 'Sierra Leone',
                                'Libéria' => 'Libéria',
                                'Ghana' => 'Ghana',
                                'Togo' => 'Togo',
                                'Bénin' => 'Bénin',
                                'Nigeria' => 'Nigeria',
                                'République centrafricaine' => 'République centrafricaine',
                                'Soudan' => 'Soudan',
                                'Soudan du Sud' => 'Soudan du Sud',
                                'Éthiopie' => 'Éthiopie',
                                'Érythrée' => 'Érythrée',
                                'Somalie' => 'Somalie',
                                'Kenya' => 'Kenya',
                                'Ouganda' => 'Ouganda',
                                'Rwanda' => 'Rwanda',
                                'Burundi' => 'Burundi',
                                'Tanzanie' => 'Tanzanie',
                                'Zambie' => 'Zambie',
                                'Zimbabwe' => 'Zimbabwe',
                                'Botswana' => 'Botswana',
                                'Namibie' => 'Namibie',
                                'Afrique du Sud' => 'Afrique du Sud',
                                'Lesotho' => 'Lesotho',
                                'Eswatini' => 'Eswatini',
                                'Mozambique' => 'Mozambique',
                                'Malawi' => 'Malawi',
                                'Angola' => 'Angola',
                                'Autre' => 'Autre'
                            ];
                            
                            $paysActuel = $patient['pays'] ?? 'France';
                            foreach ($pays as $code => $nom):
                                $selected = ($paysActuel === $nom) ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlspecialchars($nom); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($nom); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Informations professionnelles -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-briefcase me-2"></i>Informations Professionnelles</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="profession" class="form-label">Profession</label>
                        <input type="text" class="form-control" id="profession" name="profession" 
                               value="<?php echo htmlspecialchars($patient['profession'] ?? ''); ?>" placeholder="Employé, Retraité, Étudiant...">
                    </div>

                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="actif" <?php echo $patient['statut'] === 'actif' ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactif" <?php echo $patient['statut'] === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                            <option value="archive" <?php echo $patient['statut'] === 'archive' ? 'selected' : ''; ?>>Archivé</option>
                        </select>
                    </div>

                    <!-- Informations médicales -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-notes-medical me-2"></i>Informations Médicales</h6>
                    </div>

                    <div class="col-12">
                        <label for="antecedents_medicaux" class="form-label">Antécédents médicaux</label>
                        <textarea class="form-control" id="antecedents_medicaux" name="antecedents_medicaux" rows="3"
                                  placeholder="Antécédents médicaux, chirurgicaux, familiaux..."><?php echo htmlspecialchars($patient['antecedents_medicaux'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="allergies" class="form-label">Allergies</label>
                        <textarea class="form-control" id="allergies" name="allergies" rows="2"
                                  placeholder="Allergies médicamenteuses, alimentaires, environnementales..."><?php echo htmlspecialchars($patient['allergies'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes additionnelles</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  placeholder="Informations complémentaires, observations..."><?php echo htmlspecialchars($patient['notes'] ?? ''); ?></textarea>
                    </div>

                    <!-- Informations d'assurance -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-shield-alt me-2"></i>Assurance</h6>
                    </div>

                    <div class="col-12 mb-3">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Contrats d'Assurance Actifs</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($contratsAssurance)): ?>
                                    <p class="text-muted mb-3">Aucun contrat d'assurance actif pour ce patient.</p>
                                <?php else: ?>
                                    <?php foreach ($contratsAssurance as $contrat): ?>
                                        <div class="border rounded p-3 mb-3">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <strong><i class="fas fa-building me-2"></i>Assurance :</strong><br>
                                                    <span class="text-primary"><?php echo htmlspecialchars($contrat['assurance_nom']); ?></span>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong><i class="fas fa-file-contract me-2"></i>Numéro de contrat :</strong><br>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($contrat['numero_contrat']); ?></span>
                                                </div>
                                                <?php if ($contrat['numero_police']): ?>
                                                <div class="col-md-6 mt-2">
                                                    <strong><i class="fas fa-id-card me-2"></i>Numéro de police :</strong><br>
                                                    <span><?php echo htmlspecialchars($contrat['numero_police']); ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <div class="col-md-6 mt-2">
                                                    <strong><i class="fas fa-calendar me-2"></i>Période :</strong><br>
                                                    <small class="text-muted">
                                                        Du <?php echo date('d/m/Y', strtotime($contrat['date_debut'])); ?>
                                                        <?php if ($contrat['date_fin']): ?>
                                                            au <?php echo date('d/m/Y', strtotime($contrat['date_fin'])); ?>
                                                        <?php else: ?>
                                                            (sans date de fin)
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-6 mt-2">
                                                    <strong><i class="fas fa-percentage me-2"></i>Taux de couverture :</strong><br>
                                                    <span class="badge bg-success"><?php echo number_format($contrat['taux_couverture'], 2); ?>%</span>
                                                </div>
                                                <?php if ($contrat['franchise'] > 0): ?>
                                                <div class="col-md-6 mt-2">
                                                    <strong><i class="fas fa-money-bill me-2"></i>Franchise :</strong><br>
                                                    <span><?php echo number_format($contrat['franchise'], 2); ?> FCFA</span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <a href="../assurances/ajouter_contrat.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-success me-2">
                                        <i class="fas fa-plus me-2"></i>Ajouter un contrat
                                    </a>
                                    <a href="../assurances/index.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-cog me-2"></i>Gérer les contrats
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Mettre à jour
                        </button>
                        <a href="voir.php?id=<?php echo $patient['id']; ?>" class="btn btn-info ms-2">
                            <i class="fas fa-eye me-2"></i>Voir
                        </a>
                        <a href="index.php" class="btn btn-secondary ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Informations sur le patient -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations sur le Patient</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>ID :</strong><br>
                        <span class="badge bg-secondary"><?php echo $patient['id']; ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Numéro de dossier :</strong><br>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($patient['numero_dossier']); ?></span>
                    </div>
                    <div class="col-md-3">
                        <strong>Date de création :</strong><br>
                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($patient['date_creation'])); ?></small>
                    </div>
                    <div class="col-md-3">
                        <strong>Dernière modification :</strong><br>
                        <small class="text-muted">
                            <?php echo $patient['date_modification'] ? date('d/m/Y H:i', strtotime($patient['date_modification'])) : 'Jamais modifiée'; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

<?php app_module_page_end();
