<?php
/**
 * Dossier médical complet du patient
 * Affiche toutes les informations médicales, documents et historique
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('patients'));

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/RendezVous.php';
require_once __DIR__ . '/../models/Paiement.php';

$patientModel = new Patient();
$consultationModel = new Consultation();
$rendezVousModel = new RendezVous();
$paiementModel = new Paiement();

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

// Calculer l'âge
$age = $patientModel->calculateAge($patient['date_naissance']);

// Récupérer toutes les données du patient
$consultations = $consultationModel->getPatientHistory($id, 50); // Plus d'historique
$rendezVous = $rendezVousModel->getPatientRendezVous($id);
$paiements = $paiementModel->getPatientPaiements($id);

// Statistiques du patient
$stats = [
    'total_consultations' => count($consultations),
    'total_rdv' => count($rendezVous),
    'total_paiements' => count($paiements),
    'derniere_consultation' => !empty($consultations) ? $consultations[0]['date_consultation'] : null,
    'prochain_rdv' => null
];

// Trouver le prochain rendez-vous
foreach ($rendezVous as $rdv) {
    if ($rdv['statut'] === 'planifie' && strtotime($rdv['date_rdv']) > time()) {
        $stats['prochain_rdv'] = $rdv['date_rdv'];
        break;
    }
}

app_module_page_start([
    'active'    => 'patients',
    'title'     => 'Dossier Médical',
    'subtitle'  => htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) . ' — Dossier ' . htmlspecialchars($patient['numero_dossier']),
    'icon'      => 'fa-notes-medical',
    'extra_css' => ['assets/css/app-patients.css'],
]);
app_module_back_toolbar(app_url('patients/index.php'), 'Retour à la liste', [
    ['href' => app_url('patients/voir.php?id=' . $patient['id']), 'label' => 'Vue rapide', 'icon' => 'fa-eye', 'class' => 'btn-info'],
    ['href' => app_url('patients/modifier.php?id=' . $patient['id']), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-warning'],
]);
app_module_flash();
?>
<style>
        .dossier-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white;
            border-radius: 15px;
        }
        .section-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .section-header {
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
        }
        .timeline-item {
            border-left: 3px solid #667eea;
            padding-left: 20px;
            margin-bottom: 20px;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 0;
            width: 13px;
            height: 13px;
            border-radius: 50%;
            background: #667eea;
        }
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        .document-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #17a2b8;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .document-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            background: #ffffff;
        }
        .document-item .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .document-item .btn:last-child {
            margin-right: 0;
        }
        .document-item i {
            transition: transform 0.3s ease;
        }
        .document-item:hover i {
            transform: scale(1.1);
        }
        .document-stats {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .document-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .category-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 12px;
        }
        .alert-custom {
            border-radius: 10px;
            border: none;
        }
</style>

        <!-- En-tête du dossier médical -->
        <div class="dossier-header p-4 mb-4">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <h1 class="mb-2">
                        <i class="fas fa-notes-medical me-3"></i>
                        Dossier Médical
                    </h1>
                    <h3 class="mb-2">
                        <?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']); ?>
                    </h3>
                    <p class="mb-0">
                        <strong>Dossier N° :</strong> <?php echo htmlspecialchars($patient['numero_dossier']); ?> |
                        <strong>Âge :</strong> <?php echo $age ? $age . ' ans' : 'N/A'; ?> |
                        <strong>Genre :</strong> <?php echo ($patient['sexe'] ?? '') === 'M' ? 'Homme' : 'Femme'; ?> |
                        <strong>Statut :</strong> 
                        <span class="badge bg-<?php echo $patient['statut'] === 'actif' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($patient['statut']); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Statistiques du patient -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stat-card">
                    <i class="fas fa-stethoscope fa-2x text-primary mb-2"></i>
                    <h4><?php echo $stats['total_consultations']; ?></h4>
                    <small class="text-muted">Consultations</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                    <h4><?php echo $stats['total_rdv']; ?></h4>
                    <small class="text-muted">Rendez-vous</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <i class="fas fa-credit-card fa-2x text-info mb-2"></i>
                    <h4><?php echo $stats['total_paiements']; ?></h4>
                    <small class="text-muted">Paiements</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                    <h4><?php echo $stats['derniere_consultation'] ? date('d/m/Y', strtotime($stats['derniere_consultation'])) : 'N/A'; ?></h4>
                    <small class="text-muted">Dernière consultation</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <i class="fas fa-calendar-plus fa-2x text-danger mb-2"></i>
                    <h4><?php echo $stats['prochain_rdv'] ? date('d/m/Y', strtotime($stats['prochain_rdv'])) : 'N/A'; ?></h4>
                    <small class="text-muted">Prochain RDV</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card">
                    <i class="fas fa-user-plus fa-2x text-secondary mb-2"></i>
                    <h4><?php echo date('d/m/Y', strtotime($patient['date_creation'])); ?></h4>
                    <small class="text-muted">Date d'inscription</small>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Colonne principale -->
            <div class="col-md-8">
                <!-- Informations personnelles -->
                <div class="card section-card">
                    <div class="card-header bg-primary text-white section-header">
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
                                <span class="badge bg-danger"><?php echo $patient['groupe_sanguin'] ?: 'Non renseigné'; ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Profession :</strong><br>
                                <span class="text-muted"><?php echo $patient['profession'] ?: 'Non renseignée'; ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Ville :</strong><br>
                                <span class="text-info"><?php echo $patient['ville'] ?: 'Non renseignée'; ?></span>
                            </div>
                        </div>
                        
                        <!-- Coordonnées -->
                        <hr>
                        <h6><i class="fas fa-address-book me-2"></i>Coordonnées</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Téléphone :</strong><br>
                                <span class="text-primary"><?php echo $patient['telephone'] ?: 'Non renseigné'; ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Email :</strong><br>
                                <span class="text-primary"><?php echo $patient['email'] ?: 'Non renseigné'; ?></span>
                            </div>
                            <div class="col-12 mb-3">
                                <strong>Adresse complète :</strong><br>
                                <span class="text-muted">
                                    <?php if ($patient['adresse']): ?>
                                        <?php echo htmlspecialchars($patient['adresse']); ?><br>
                                        <?php echo htmlspecialchars($patient['code_postal'] . ' ' . $patient['ville']); ?><br>
                                        <?php echo htmlspecialchars($patient['pays']); ?>
                                    <?php else: ?>
                                        Non renseignée
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations médicales -->
                <div class="card section-card">
                    <div class="card-header bg-warning text-white section-header">
                        <h5 class="mb-0"><i class="fas fa-notes-medical me-2"></i>Informations Médicales</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <strong>Antécédents médicaux :</strong><br>
                                <div class="alert alert-custom alert-info">
                                    <?php echo $patient['antecedents_medicaux'] ? nl2br(htmlspecialchars($patient['antecedents_medicaux'])) : 'Aucun antécédent renseigné'; ?>
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <strong>Allergies :</strong><br>
                                <div class="alert alert-custom alert-danger">
                                    <?php echo $patient['allergies'] ? nl2br(htmlspecialchars($patient['allergies'])) : 'Aucune allergie connue'; ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <strong>Notes additionnelles :</strong><br>
                                <div class="alert alert-custom alert-secondary">
                                    <?php echo $patient['notes'] ? nl2br(htmlspecialchars($patient['notes'])) : 'Aucune note'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historique des consultations -->
                <div class="card section-card">
                    <div class="card-header bg-success text-white section-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Historique des Consultations</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($consultations)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucune consultation trouvée</p>
                                <a href="../consultations/ajouter.php?patient_id=<?php echo $patient['id']; ?>&patient_name=<?php echo urlencode($patient['prenom'] . ' ' . $patient['nom']); ?>&patient_dossier=<?php echo urlencode($patient['numero_dossier']); ?>" class="btn btn-success">
                                    <i class="fas fa-plus me-2"></i>Première consultation
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($consultations as $consultation): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="fas fa-stethoscope me-2 text-success"></i>
                                                    Consultation du <?php echo date('d/m/Y H:i', strtotime($consultation['date_consultation'])); ?>
                                                </h6>
                                                <p class="mb-1">
                                                    <strong><?= htmlspecialchars(medecin_profil_attribution_label_from_row($consultation)) ?> :</strong> 
                                                    <?php echo htmlspecialchars(medecin_profil_format_joined($consultation)); ?>
                                                </p>
                                                <?php if ($consultation['diagnostic']): ?>
                                                    <p class="mb-1">
                                                        <strong>Diagnostic :</strong> 
                                                        <?php echo htmlspecialchars($consultation['diagnostic']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if ($consultation['traitement']): ?>
                                                    <p class="mb-1">
                                                        <strong>Traitement :</strong> 
                                                        <?php echo htmlspecialchars($consultation['traitement']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge bg-<?php echo $consultation['statut'] === 'termine' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($consultation['statut']); ?>
                                            </span>
                                        </div>
                                        <div class="mt-2">
                                            <a href="../consultations/voir.php?id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i>Voir détails
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="../consultations/?patient_id=<?php echo $patient['id']; ?>" class="btn btn-outline-success">
                                    <i class="fas fa-list me-2"></i>Voir tout l'historique
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rendez-vous -->
                <div class="card section-card">
                    <div class="card-header bg-info text-white section-header">
                        <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Rendez-vous</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($rendezVous)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucun rendez-vous trouvé</p>
                                <a href="../rendez-vous/ajouter.php?patient_id=<?php echo $patient['id']; ?>&patient_name=<?php echo urlencode($patient['prenom'] . ' ' . $patient['nom']); ?>&patient_dossier=<?php echo urlencode($patient['numero_dossier']); ?>" class="btn btn-info">
                                    <i class="fas fa-plus me-2"></i>Prendre rendez-vous
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($rendezVous as $rdv): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="fas fa-calendar me-2 text-info"></i>
                                                    RDV du <?php echo date('d/m/Y H:i', strtotime($rdv['date_rdv'])); ?>
                                                </h6>
                                                <p class="mb-1">
                                                    <strong><?= htmlspecialchars(medecin_profil_attribution_label_from_row($consultation)) ?> :</strong> 
                                                    <?php echo htmlspecialchars(medecin_profil_format_joined($rdv)); ?>
                                                </p>
                                                <?php if ($rdv['motif']): ?>
                                                    <p class="mb-1">
                                                        <strong>Motif :</strong> 
                                                        <?php echo htmlspecialchars($rdv['motif']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge bg-<?php 
                                                echo $rdv['statut'] === 'termine' ? 'success' : 
                                                    ($rdv['statut'] === 'annule' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($rdv['statut']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Colonne latérale -->
            <div class="col-md-4">
                <!-- Actions rapides -->
                <div class="card section-card">
                    <div class="card-header bg-success text-white section-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions Rapides</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="../consultations/ajouter.php?patient_id=<?php echo $patient['id']; ?>&patient_name=<?php echo urlencode($patient['prenom'] . ' ' . $patient['nom']); ?>&patient_dossier=<?php echo urlencode($patient['numero_dossier']); ?>" class="btn btn-success" target="_blank">
                                <i class="fas fa-stethoscope me-2"></i>Nouvelle Consultation
                                <small class="d-block text-white-50"><?php echo htmlspecialchars($patient['numero_dossier']); ?></small>
                            </a>
                            <a href="../rendez-vous/ajouter.php?patient_id=<?php echo $patient['id']; ?>&patient_name=<?php echo urlencode($patient['prenom'] . ' ' . $patient['nom']); ?>&patient_dossier=<?php echo urlencode($patient['numero_dossier']); ?>" class="btn btn-info" target="_blank">
                                <i class="fas fa-calendar-plus me-2"></i>Nouveau Rendez-vous
                                <small class="d-block text-white-50"><?php echo htmlspecialchars($patient['numero_dossier']); ?></small>
                            </a>
                            <a href="../laboratoire/ajouter.php?patient_id=<?php echo $patient['id']; ?>&patient_name=<?php echo urlencode($patient['prenom'] . ' ' . $patient['nom']); ?>&patient_dossier=<?php echo urlencode($patient['numero_dossier']); ?>" class="btn btn-warning" target="_blank">
                                <i class="fas fa-flask me-2"></i>Nouvelle Analyse
                                <small class="d-block text-white-50"><?php echo htmlspecialchars($patient['numero_dossier']); ?></small>
                            </a>
                            <a href="../paiements/ajouter.php?patient_id=<?php echo $patient['id']; ?>&patient_name=<?php echo urlencode($patient['prenom'] . ' ' . $patient['nom']); ?>&patient_dossier=<?php echo urlencode($patient['numero_dossier']); ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-credit-card me-2"></i>Nouveau Paiement
                                <small class="d-block text-white-50"><?php echo htmlspecialchars($patient['numero_dossier']); ?></small>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Documents et fichiers -->
                <div class="card section-card">
                    <div class="card-header bg-secondary text-white section-header">
                        <h6 class="mb-0"><i class="fas fa-file-medical me-2"></i>Documents</h6>
                    </div>
                    <div class="card-body">
                        <div class="document-item">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-pdf fa-2x text-danger me-3"></i>
                                <div>
                                    <strong>Dossier médical</strong><br>
                                    <small class="text-muted">PDF complet</small>
                                </div>
                            </div>
                            <div class="mt-2">
                                <a href="generer_dossier_pdf.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-download me-1"></i>Télécharger
                                </a>
                            </div>
                        </div>
                        
                        <?php
                        // Récupérer le comptage des documents par catégorie depuis le système de fichiers
                        $uploadDir = __DIR__ . '/../uploads/patients/' . $patient['id'];
                        $categories = [
                            'photos_medicales' => ['icon' => 'fas fa-camera', 'color' => 'success', 'label' => 'Photos médicales'],
                            'rapports' => ['icon' => 'fas fa-file-medical', 'color' => 'info', 'label' => 'Rapports'],
                            'analyses' => ['icon' => 'fas fa-flask', 'color' => 'warning', 'label' => 'Analyses'],
                            'ordonnances' => ['icon' => 'fas fa-prescription', 'color' => 'primary', 'label' => 'Ordonnances'],
                            'certificats' => ['icon' => 'fas fa-certificate', 'color' => 'dark', 'label' => 'Certificats'],
                            'autres' => ['icon' => 'fas fa-file', 'color' => 'secondary', 'label' => 'Autres']
                        ];
                        
                        $stats = [];
                        
                        if (is_dir($uploadDir)) {
                            foreach ($categories as $key => $cat) {
                                $count = 0;
                                $files = scandir($uploadDir);
                                foreach ($files as $file) {
                                    if ($file !== '.' && $file !== '..' && !str_ends_with($file, '.info')) {
                                        $infoFile = $uploadDir . '/' . $file . '.info';
                                        if (file_exists($infoFile)) {
                                            $metadata = json_decode(file_get_contents($infoFile), true);
                                            if ($metadata && isset($metadata['categorie']) && $metadata['categorie'] === $key) {
                                                $count++;
                                            }
                                        }
                                    }
                                }
                                $stats[$key] = $count;
                            }
                        } else {
                            // Si le dossier n'existe pas, initialiser les compteurs à 0
                            foreach ($categories as $key => $cat) {
                                $stats[$key] = 0;
                            }
                        }
                        ?>
                        
                        <!-- Photos médicales -->
                        <div class="document-item">
                            <div class="d-flex align-items-center">
                                <i class="<?php echo $categories['photos_medicales']['icon']; ?> fa-2x text-<?php echo $categories['photos_medicales']['color']; ?> me-3"></i>
                                <div>
                                    <strong><?php echo $categories['photos_medicales']['label']; ?></strong><br>
                                    <small class="text-muted"><?php echo $stats['photos_medicales']; ?> fichier(s)</small>
                                </div>
                            </div>
                            <div class="mt-2 document-actions">
                                <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=photos_medicales" class="btn btn-sm btn-outline-<?php echo $categories['photos_medicales']['color']; ?>">
                                    <i class="fas fa-upload me-1"></i>Ajouter
                                </a>
                                <?php if ($stats['photos_medicales'] > 0): ?>
                                    <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=photos_medicales" class="btn btn-sm btn-<?php echo $categories['photos_medicales']['color']; ?>">
                                        <i class="fas fa-eye me-1"></i>Voir
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Rapports -->
                        <div class="document-item">
                            <div class="d-flex align-items-center">
                                <i class="<?php echo $categories['rapports']['icon']; ?> fa-2x text-<?php echo $categories['rapports']['color']; ?> me-3"></i>
                                <div>
                                    <strong><?php echo $categories['rapports']['label']; ?></strong><br>
                                    <small class="text-muted"><?php echo $stats['rapports']; ?> fichier(s)</small>
                                </div>
                            </div>
                            <div class="mt-2 document-actions">
                                <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=rapports" class="btn btn-sm btn-outline-<?php echo $categories['rapports']['color']; ?>">
                                    <i class="fas fa-upload me-1"></i>Ajouter
                                </a>
                                <?php if ($stats['rapports'] > 0): ?>
                                    <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=rapports" class="btn btn-sm btn-<?php echo $categories['rapports']['color']; ?>">
                                        <i class="fas fa-eye me-1"></i>Voir
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Analyses -->
                        <div class="document-item">
                            <div class="d-flex align-items-center">
                                <i class="<?php echo $categories['analyses']['icon']; ?> fa-2x text-<?php echo $categories['analyses']['color']; ?> me-3"></i>
                                <div>
                                    <strong><?php echo $categories['analyses']['label']; ?></strong><br>
                                    <small class="text-muted"><?php echo $stats['analyses']; ?> fichier(s)</small>
                                </div>
                            </div>
                            <div class="mt-2 document-actions">
                                <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=analyses" class="btn btn-sm btn-outline-<?php echo $categories['analyses']['color']; ?>">
                                    <i class="fas fa-upload me-1"></i>Ajouter
                                </a>
                                <?php if ($stats['analyses'] > 0): ?>
                                    <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=analyses" class="btn btn-sm btn-<?php echo $categories['analyses']['color']; ?>">
                                        <i class="fas fa-eye me-1"></i>Voir
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Ordonnances -->
                        <div class="document-item">
                            <div class="d-flex align-items-center">
                                <i class="<?php echo $categories['ordonnances']['icon']; ?> fa-2x text-<?php echo $categories['ordonnances']['color']; ?> me-3"></i>
                                <div>
                                    <strong><?php echo $categories['ordonnances']['label']; ?></strong><br>
                                    <small class="text-muted"><?php echo $stats['ordonnances']; ?> fichier(s)</small>
                                </div>
                            </div>
                            <div class="mt-2 document-actions">
                                <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=ordonnances" class="btn btn-sm btn-outline-<?php echo $categories['ordonnances']['color']; ?>">
                                    <i class="fas fa-upload me-1"></i>Ajouter
                                </a>
                                <?php if ($stats['ordonnances'] > 0): ?>
                                    <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=ordonnances" class="btn btn-sm btn-<?php echo $categories['ordonnances']['color']; ?>">
                                        <i class="fas fa-eye me-1"></i>Voir
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Certificats -->
                        <div class="document-item">
                            <div class="d-flex align-items-center">
                                <i class="<?php echo $categories['certificats']['icon']; ?> fa-2x text-<?php echo $categories['certificats']['color']; ?> me-3"></i>
                                <div>
                                    <strong><?php echo $categories['certificats']['label']; ?></strong><br>
                                    <small class="text-muted"><?php echo $stats['certificats']; ?> fichier(s)</small>
                                </div>
                            </div>
                            <div class="mt-2 document-actions">
                                <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=certificats" class="btn btn-sm btn-outline-<?php echo $categories['certificats']['color']; ?>">
                                    <i class="fas fa-upload me-1"></i>Ajouter
                                </a>
                                <?php if ($stats['certificats'] > 0): ?>
                                    <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=certificats" class="btn btn-sm btn-<?php echo $categories['certificats']['color']; ?>">
                                        <i class="fas fa-eye me-1"></i>Voir
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Autres documents -->
                        <div class="document-item">
                            <div class="d-flex align-items-center">
                                <i class="<?php echo $categories['autres']['icon']; ?> fa-2x text-<?php echo $categories['autres']['color']; ?> me-3"></i>
                                <div>
                                    <strong><?php echo $categories['autres']['label']; ?></strong><br>
                                    <small class="text-muted"><?php echo $stats['autres']; ?> fichier(s)</small>
                                </div>
                            </div>
                            <div class="mt-2 document-actions">
                                <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=autres" class="btn btn-sm btn-outline-<?php echo $categories['autres']['color']; ?>">
                                    <i class="fas fa-upload me-1"></i>Ajouter
                                </a>
                                <?php if ($stats['autres'] > 0): ?>
                                    <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>&categorie=autres" class="btn btn-sm btn-<?php echo $categories['autres']['color']; ?>">
                                        <i class="fas fa-eye me-1"></i>Voir
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="gerer_documents.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-folder-open me-1"></i>Gérer tous les documents
                            </a>
                            <a href="voir.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-eye me-1"></i>Vue rapide du patient
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Paiements -->
                <div class="card section-card">
                    <div class="card-header bg-primary text-white section-header">
                        <h6 class="mb-0"><i class="fas fa-credit-card me-2"></i>Paiements</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($paiements)): ?>
                            <p class="text-muted text-center">Aucun paiement</p>
                        <?php else: ?>
                            <?php foreach (array_slice($paiements, 0, 3) as $paiement): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo htmlspecialchars($paiement['numero_facture']); ?></strong>
                                        <span class="badge bg-<?php echo $paiement['statut'] === 'paye' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($paiement['statut']); ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo number_format($paiement['montant'], 2); ?> FCFA - 
                                        <?php echo date('d/m/Y', strtotime($paiement['date_paiement'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center">
                                <a href="../paiements/?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    Voir tous les paiements
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Boutons d'action en bas -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="../consultations/ajouter.php?patient_id=<?php echo $patient['id']; ?>&patient_name=<?php echo urlencode($patient['prenom'] . ' ' . $patient['nom']); ?>&patient_dossier=<?php echo urlencode($patient['numero_dossier']); ?>" class="btn btn-success btn-lg me-3">
                    <i class="fas fa-stethoscope me-2"></i>Nouvelle Consultation
                </a>
                <a href="modifier.php?id=<?php echo $patient['id']; ?>" class="btn btn-warning btn-lg me-3">
                    <i class="fas fa-edit me-2"></i>Modifier le patient
                </a>
                <a href="voir.php?id=<?php echo $patient['id']; ?>" class="btn btn-info btn-lg me-3">
                    <i class="fas fa-eye me-2"></i>Vue rapide
                </a>
                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Retour à la liste
                </a>
            </div>
        </div>

<?php app_module_page_end();
