<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('patients'));

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/Assurance.php';
require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../includes/staff_scope.php';

$patientModel = new Patient();
$consultationModel = new Consultation();
$assuranceModel = new Assurance();
$medecinModel = new Medecin();
$canAssignMedecin = StaffScope::canAssignPatientMedecin();
$canRegisterConsultation = StaffScope::canRegisterConsultationFromPatients();
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

// Calculer l'�ge
$age = $patientModel->calculateAge($patient['date_naissance']);

// R�cup�rer la derni�re consultation
$consultations = $consultationModel->getPatientHistory($id, 1);

// R�cup�rer les contrats d'assurance du patient
$contratsAssurance = $assuranceModel->getContratsByPatient($id);

app_module_page_start([
    'active'    => 'patients',
    'title'     => 'D�tails du Patient',
    'subtitle'  => htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) . ' � Dossier ' . htmlspecialchars($patient['numero_dossier']),
    'icon'      => 'fa-user-injured',
    'extra_css' => ['assets/css/app-patients.css'],
]);
app_module_back_toolbar(app_url('patients/index.php'), 'Retour � la liste', [
    ['href' => app_url('patients/modifier.php?id=' . $patient['id']), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-warning'],
]);
app_module_flash();
?>

        <!-- En-t�te du patient -->
        <div class="card patient-header text-white mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <h2 class="mb-2">
                            <i class="fas fa-user-injured me-3"></i>
                            <?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']); ?>
                        </h2>
                        <p class="mb-0">
                            <strong>Num�ro de dossier :</strong> <?php echo htmlspecialchars($patient['numero_dossier']); ?> |
                            <strong>�ge :</strong> <?php echo $age ? $age . ' ans' : 'N/A'; ?> |
                            <strong>Genre :</strong> <?php echo ($patient['sexe'] ?? '') === 'M' ? 'Homme' : 'Femme'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Informations personnelles -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Informations Personnelles</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Date de naissance :</strong><br>
                                <span class="text-primary"><?php echo date('d/m/Y', strtotime($patient['date_naissance'])); ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Groupe sanguin :</strong><br>
                                <span class="badge bg-danger"><?php echo $patient['groupe_sanguin'] ?: 'Non renseign�'; ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Profession :</strong><br>
                                <span class="text-muted"><?php echo $patient['profession'] ?: 'Non renseign�e'; ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Statut :</strong><br>
                                <span class="status-badge status-<?php echo $patient['statut']; ?>">
                                    <?php echo ucfirst($patient['statut']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Coordonn�es -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-address-book me-2"></i>Coordonn�es</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>T�l�phone :</strong><br>
                                <span class="text-primary"><?php echo $patient['telephone'] ?: 'Non renseign�'; ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Email :</strong><br>
                                <span class="text-primary"><?php echo $patient['email'] ?: 'Non renseign�'; ?></span>
                            </div>
                            <div class="col-12 mb-3">
                                <strong>Adresse :</strong><br>
                                <span class="text-muted">
                                    <?php if ($patient['adresse']): ?>
                                        <?php echo htmlspecialchars($patient['adresse']); ?><br>
                                        <?php echo htmlspecialchars($patient['code_postal'] . ' ' . $patient['ville']); ?><br>
                                        <?php echo htmlspecialchars($patient['pays']); ?>
                                    <?php else: ?>
                                        Non renseign�e
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations m�dicales -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="fas fa-notes-medical me-2"></i>Informations M�dicales</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <strong>Ant�c�dents m�dicaux :</strong><br>
                                <span class="text-muted"><?php echo $patient['antecedents_medicaux'] ? nl2br(htmlspecialchars($patient['antecedents_medicaux'])) : 'Aucun ant�c�dent renseign�'; ?></span>
                            </div>
                            <div class="col-12 mb-3">
                                <strong>Allergies :</strong><br>
                                <span class="text-danger"><?php echo $patient['allergies'] ? nl2br(htmlspecialchars($patient['allergies'])) : 'Aucune allergie connue'; ?></span>
                            </div>
                            <div class="col-12">
                                <strong>Notes additionnelles :</strong><br>
                                <span class="text-muted"><?php echo $patient['notes'] ? nl2br(htmlspecialchars($patient['notes'])) : 'Aucune note'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations d'assurance -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Assurance</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($contratsAssurance)): ?>
                            <p class="text-muted mb-3">Aucun contrat d'assurance actif pour ce patient.</p>
                            <a href="../assurances/ajouter_contrat.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-success">
                                <i class="fas fa-plus me-2"></i>Ajouter un contrat d'assurance
                            </a>
                        <?php else: ?>
                            <?php foreach ($contratsAssurance as $contrat): ?>
                                <div class="border rounded p-3 mb-3 bg-light">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <strong><i class="fas fa-building me-2 text-primary"></i>Assurance :</strong><br>
                                            <span class="text-primary fw-bold"><?php echo htmlspecialchars($contrat['assurance_nom']); ?></span>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong><i class="fas fa-file-contract me-2 text-info"></i>Num�ro de contrat :</strong><br>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($contrat['numero_contrat']); ?></span>
                                        </div>
                                        <?php if ($contrat['numero_police']): ?>
                                        <div class="col-md-6 mb-2">
                                            <strong><i class="fas fa-id-card me-2 text-secondary"></i>Num�ro de police :</strong><br>
                                            <span><?php echo htmlspecialchars($contrat['numero_police']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-md-6 mb-2">
                                            <strong><i class="fas fa-calendar me-2 text-success"></i>P�riode :</strong><br>
                                            <small class="text-muted">
                                                Du <?php echo date('d/m/Y', strtotime($contrat['date_debut'])); ?>
                                                <?php if ($contrat['date_fin']): ?>
                                                    au <?php echo date('d/m/Y', strtotime($contrat['date_fin'])); ?>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Sans date de fin</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong><i class="fas fa-percentage me-2 text-warning"></i>Taux de couverture :</strong><br>
                                            <span class="badge bg-success"><?php echo number_format($contrat['taux_couverture'], 2); ?>%</span>
                                        </div>
                                        <?php if ($contrat['franchise'] > 0): ?>
                                        <div class="col-md-6 mb-2">
                                            <strong><i class="fas fa-money-bill me-2 text-danger"></i>Franchise :</strong><br>
                                            <span><?php echo number_format($contrat['franchise'], 2); ?> FCFA</span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($contrat['plafond_annuel']): ?>
                                        <div class="col-md-6 mb-2">
                                            <strong><i class="fas fa-chart-line me-2 text-info"></i>Plafond annuel :</strong><br>
                                            <span><?php echo number_format($contrat['plafond_annuel'], 2); ?> FCFA</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="mt-3">
                                <a href="../assurances/ajouter_contrat.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-success me-2">
                                    <i class="fas fa-plus me-2"></i>Ajouter un contrat
                                </a>
                                <a href="../assurances/index.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-cog me-2"></i>G�rer les contrats
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <?php if ($canAssignMedecin): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-user-md me-2"></i>M�decin r�f�rent</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($patient['medecin_referent_id'])): ?>
                        <p class="mb-2">
                            <strong><?= htmlspecialchars(medecin_profil_format_joined($patient, 'medecin_referent')) ?></strong>
                            <?php if (!empty($patient['medecin_referent_specialite'])): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($patient['medecin_referent_specialite']) ?></small>
                            <?php endif; ?>
                        </p>
                        <?php else: ?>
                        <p class="text-muted mb-2">Aucun m�decin assign�</p>
                        <?php endif; ?>
                        <form method="POST" action="assigner_medecin.php" class="row g-2">
                            <input type="hidden" name="patient_id" value="<?= (int) $patient['id'] ?>">
                            <input type="hidden" name="redirect" value="voir.php?id=<?= (int) $patient['id'] ?>">
                            <div class="col-12">
                                <select class="form-select form-select-sm" name="medecin_referent_id">
                                    <option value="">� Retirer l'assignation �</option>
                                    <?php foreach ($medecins as $m): ?>
                                    <option value="<?= (int) $m['id'] ?>" <?= (int) $m['id'] === (int) ($patient['medecin_referent_id'] ?? 0) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(medecin_profil_format_name($m)) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-sm btn-primary w-100">
                                    <i class="fas fa-user-check me-1"></i>Assigner le m�decin
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php elseif (!empty($patient['medecin_referent_id'])): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-user-md me-2"></i>M�decin r�f�rent</h6>
                    </div>
                    <div class="card-body">
                        <strong><?= htmlspecialchars(medecin_profil_format_joined($patient, 'medecin_referent')) ?></strong>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Actions rapides -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions Rapides</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="modifier.php?id=<?php echo $patient['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Modifier
                            </a>
                            <?php if ($canRegisterConsultation): ?>
                            <a href="ticket_caisse.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-receipt me-2"></i>Ticket caisse
                            </a>
                            <?php endif; ?>
                            <a href="../consultations/ajouter.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-info">
                                <i class="fas fa-stethoscope me-2"></i>Nouvelle Consultation
                            </a>
                            <a href="../rendez-vous/ajouter.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-calendar-plus me-2"></i>Nouveau Rendez-vous
                            </a>
                            <a href="generer_dossier_pdf.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-secondary" target="_blank">
                                <i class="fas fa-print me-2"></i>Imprimer Dossier M�dical
                            </a>
                            <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success">
                                <i class="fas fa-notes-medical me-2"></i>G�rer Documents
                            </a>
                            <div class="dropdown">
                                <button class="btn btn-success dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-folder-plus me-2"></i>Documents
                                </button>
                                <ul class="dropdown-menu w-100">
                                    <li><a class="dropdown-item" href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=photos_medicales">
                                        <i class="fas fa-camera me-2"></i>Photos M�dicales
                                    </a></li>
                                    <li><a class="dropdown-item" href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=rapports">
                                        <i class="fas fa-file-medical me-2"></i>Rapports
                                    </a></li>
                                    <li><a class="dropdown-item" href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=analyses">
                                        <i class="fas fa-flask me-2"></i>Analyses de Laboratoire
                                    </a></li>
                                    <li><a class="dropdown-item" href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=ordonnances">
                                        <i class="fas fa-prescription me-2"></i>Ordonnances
                                    </a></li>
                                    <li><a class="dropdown-item" href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=certificats">
                                        <i class="fas fa-certificate me-2"></i>Certificats M�dicaux
                                    </a></li>
                                    <li><a class="dropdown-item" href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=autres">
                                        <i class="fas fa-file me-2"></i>Autres Documents
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>">
                                        <i class="fas fa-folder-open me-2"></i>Tous les Documents
                                    </a></li>
                                </ul>
                            </div>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-2"></i>Voir tous les patients
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Informations utiles -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations Utiles</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-card p-3 mb-3">
                            <strong>Date de cr�ation :</strong><br>
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($patient['date_creation'])); ?></small>
                        </div>
                        <?php if ($patient['date_modification']): ?>
                        <div class="info-card p-3">
                            <strong>Derni�re modification :</strong><br>
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($patient['date_modification'])); ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Derni�re consultation -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-stethoscope me-2"></i>Derni�re Consultation</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($consultations)): ?>
                            <p class="text-muted mb-0">Aucune consultation trouv�e</p>
                            <div class="text-center mt-3">
                                <a href="../consultations/ajouter.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus me-2"></i>Cr�er une consultation
                                </a>
                            </div>
                        <?php else: ?>
                            <?php $consultation = $consultations[0]; ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="text-primary">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($consultation['date_consultation'])); ?>
                                    </strong>
                                    <span class="badge bg-<?php echo $consultation['statut'] === 'termine' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($consultation['statut']); ?>
                                    </span>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-user-md me-1"></i>
                                        <?php echo htmlspecialchars(medecin_profil_format_joined($consultation)); ?>
                                    </small>
                                </div>
                                <?php if (!empty($consultation['motif'])): ?>
                                <div class="alert alert-light mb-2">
                                    <small>
                                        <strong>Motif :</strong><br>
                                        <?php echo nl2br(htmlspecialchars($consultation['motif'])); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="ticket_thermique.php?consultation_id=<?php echo (int) $consultation['id']; ?>&print=1&amp;return=<?php echo urlencode(app_url('patients/voir.php?id=' . (int) $patient['id'])); ?>" class="btn btn-sm btn-success" target="_blank">
                                    <i class="fas fa-receipt me-2"></i>Ticket thermique (caisse)
                                </a>
                                <a href="imprimer_ticket.php?consultation_id=<?php echo (int) $consultation['id']; ?>&print=1" class="btn btn-sm btn-outline-secondary" target="_blank">
                                    <i class="fas fa-print me-2"></i>Ticket A4
                                </a>
                                <a href="../consultations/voir.php?id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-eye me-2"></i>Voir les d�tails
                                </a>
                                <a href="../consultations/?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-history me-2"></i>Voir tout l'historique
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="modifier.php?id=<?php echo $patient['id']; ?>" class="btn btn-warning btn-lg me-3">
                    <i class="fas fa-edit me-2"></i>Modifier ce patient
                </a>
                <a href="../consultations/ajouter.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-info btn-lg me-3">
                    <i class="fas fa-stethoscope me-2"></i>Nouvelle Consultation
                </a>
                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Retour � la liste
                </a>
            </div>
        </div>

<?php app_module_page_end();
