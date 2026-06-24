<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('consultations'));

require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/SoinsConsultation.php';
require_once __DIR__ . '/../models/Paiement.php';
require_once __DIR__ . '/../includes/payment_sync_badge.php';

$consultationModel = new Consultation();
$soinsModel = new SoinsConsultation();
$paiementModel = new Paiement();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$consultation = $consultationModel->getById($id);
if (!$consultation) {
    header("Location: index.php");
    exit();

}

$paiementConsultation = $paiementModel->getByConsultationId($id);
$prixTotalConsultation = (float) $consultationModel->getPrixTotalComplet($id);
$canAccessPaiements = $auth->aAccesModule('paiements');
$canWritePaiements = $canAccessPaiements && $auth->peutEcrirePaiements();
$paymentSyncEnabled = function_exists('payment_finance_sync_enabled') && payment_finance_sync_enabled();

app_module_page_start([
    'active'   => 'consultations',
    'title'    => 'Détails de la Consultation',
    'subtitle' => 'Consultation médicale',
    'icon'     => 'fa-stethoscope',
]);
app_module_back_toolbar(app_url('consultations/index.php' . (isset($consultation['patient_id']) ? '?patient_id=' . $consultation['patient_id'] : '')), 'Retour à la liste', [['href' => app_url('consultations/modifier.php?id=' . $consultation['id']), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-warning']]);
app_module_flash();
?>
<?php if (!empty($_GET['paiement_error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars(urldecode((string) $_GET['paiement_error'])); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<style>
* {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .status-badge { padding: 8px 16px; border-radius: 25px; font-size: 0.9rem; font-weight: 500; }
        .status-planifie { background: #e3f2fd; color: #1565c0; }
        .status-en_cours { background: #fff3cd; color: #856404; }
        .status-termine { background: #e8f5e8; color: #2e7d32; }
        .status-annule { background: #ffebee; color: #c62828; }
        .info-card { background: #f8f9fa; border-left: 4px solid #17a2b8; }
        
        .consultation-header {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 2rem 1.5rem;
            margin: -1.5rem -1.5rem 2rem -1.5rem;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 10px 30px rgba(23, 162, 184, 0.3);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        /* Mobile responsive */
        @media (max-width: 767px) {
            /* Masquer la barre de navigation bleue "Clinique & Hôpital" */
            .mobile-top-bar {
                display: none !important;
            }
            
            /* Masquer la bottom navigation */
            .mobile-bottom-nav {
                display: none !important;
            }
            
            /* Masquer le bouton hamburger */
            #auto-mobile-menu-btn,
            button[aria-label*="menu"],
            button[class*="hamburger"],
            button[class*="navbar-toggler"],
            .navbar-toggler,
            .btn-menu,
            [class*="menu-toggle"] {
                display: none !important;
            }
            
            html, body {
                padding: 0 !important;
                margin: 0 !important;
                overflow-x: hidden;
            }
            
            body {
                padding-top: 0 !important;
                padding-bottom: 0 !important;
            }
            
            .container {
                padding: 0.5rem !important;
                padding-bottom: 200px !important;
                max-width: 100% !important;
                margin-top: 0 !important;
            }
            
            .consultation-header {
                margin: -0.5rem -0.5rem 1rem -0.5rem !important;
                padding: 1rem !important;
                border-radius: 0 0 12px 12px !important;
            }
            
            .consultation-header h3 {
                font-size: 1.1rem !important;
            }
            
            .consultation-header p {
                font-size: 0.85rem !important;
            }
            
            .consultation-header .btn-group {
                display: none !important;
            }
            
            /* Colonnes empilées sur mobile */
            .row {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            
            .row > [class*="col-"] {
                margin-bottom: 0.75rem;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            
            /* Boutons d'action en bas */
            .action-buttons-bottom {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                padding: 0.75rem;
                box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.15);
                z-index: 1000;
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                max-height: 45vh;
                overflow-y: auto;
                border-top: 2px solid #e9ecef;
            }
            
            .action-buttons-bottom .btn,
            .action-buttons-bottom button {
                width: 100%;
                margin: 0 !important;
                font-size: 0.875rem;
                padding: 0.65rem 0.5rem;
            }
            
            .action-buttons-bottom form {
                margin: 0;
            }
            
            /* Cacher les boutons d'action desktop sur mobile */
            .desktop-action-buttons {
                display: none !important;
            }
            
            /* Cards plus compactes sur mobile */
            .card {
                margin-bottom: 0.75rem !important;
            }
            
            .card-body {
                padding: 0.75rem !important;
            }
            
            .card-header {
                padding: 0.75rem !important;
            }
            
            .card-header h5 {
                font-size: 0.95rem !important;
                margin-bottom: 0 !important;
            }
            
            .card-header h6 {
                font-size: 0.9rem !important;
                margin-bottom: 0 !important;
            }
            
            /* Texte plus compact */
            .row .col-md-6 {
                margin-bottom: 0.5rem;
            }
            
            .row .col-md-6 strong,
            .row .col-12 strong {
                font-size: 0.9rem;
            }
            
            /* Alertes compactes */
            .alert {
                padding: 0.5rem !important;
                margin-bottom: 0.75rem !important;
                font-size: 0.875rem !important;
            }
            
            /* Badges plus petits */
            .badge {
                font-size: 0.75rem !important;
            }
            
            .status-badge {
                font-size: 0.8rem !important;
                padding: 6px 12px !important;
            }
        }
        
        /* Desktop - cacher les boutons mobiles */
        @media (min-width: 768px) {
            .action-buttons-bottom {
                display: none !important;
            }
        }
</style>

        <div class="consultation-header text-white mb-md-4 mb-2">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div class="mb-3 mb-md-0">
                    <h3 class="mb-2"><i class="fas fa-stethoscope me-2"></i>Détails de la Consultation</h3>
                    <p class="mb-0 opacity-90">Consultation #<?php echo $consultation['numero_ticket']; ?></p>
                </div>
            </div>
        </div>

        <!-- Messages de succès/erreur -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php
                switch ($_GET['success']) {
                    case 'started':
                        echo 'Consultation commencée avec succès !';
                        break;
                    case 'completed':
                        echo 'Consultation terminée avec succès !';
                        break;
                    case 'cancelled':
                        echo 'Consultation annulée avec succès !';
                        break;
                    default:
                        echo 'Action effectuée avec succès !';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php
                switch ($_GET['error']) {
                    case 'start_failed':
                        echo 'Erreur lors du démarrage de la consultation.';
                        break;
                    case 'completion_failed':
                        echo 'Erreur lors de la finalisation de la consultation.';
                        break;
                    case 'cancellation_failed':
                        echo 'Erreur lors de l\'annulation de la consultation.';
                        break;
                    case 'invalid_status':
                        echo 'Cette action ne peut pas être effectuée avec le statut actuel.';
                        break;
                    case 'system_error':
                        echo 'Erreur système. Veuillez réessayer.';
                        break;
                    default:
                        echo 'Une erreur est survenue.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <!-- Informations principales -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Informations de la Consultation</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Date et heure :</strong><br>
                                <span class="text-primary"><?php echo date('d/m/Y H:i', strtotime($consultation['date_consultation'])); ?></span>
                                <?php 
                                $consultation_time = new DateTime($consultation['date_consultation']);
                                $current_time = new DateTime();
                                $is_future = $consultation_time > $current_time;
                                $is_today = $consultation_time->format('Y-m-d') === $current_time->format('Y-m-d');
                                ?>
                                <?php if ($is_today): ?>
                                    <span class="badge bg-warning ms-2">Aujourd'hui</span>
                                <?php elseif ($is_future): ?>
                                    <span class="badge bg-success ms-2">À venir</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary ms-2">Passée</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Fuseau horaire :</strong><br>
                                <span class="text-muted"><?php echo date_default_timezone_get(); ?></span>
                                <br><small class="text-muted">Heure actuelle : <?php echo date('H:i'); ?></small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Statut :</strong><br>
                                <span class="status-badge status-<?php echo $consultation['statut']; ?>">
                                    <?php echo ucfirst($consultation['statut']); ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <strong>Type :</strong><br>
                                <span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $consultation['type_consultation'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Prix consultation :</strong><br>
                                <span class="text-success h5"><?php echo number_format($consultation['prix_consultation'], 0, ',', ' '); ?> FCFA</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Hospitalisation :</strong><br>
                                <?php if ($consultation['hospitalisation_requise']): ?>
                                    <span class="badge bg-success">Requis</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Non requis</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Numéro de ticket :</strong><br>
                                <code><?php echo $consultation['numero_ticket']; ?></code>
                            </div>
                            <div class="col-md-6">
                                <strong>Date de création :</strong><br>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($consultation['date_creation'])); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations du patient -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user-injured me-2"></i>Informations du Patient</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Nom complet :</strong><br>
                                <span class="text-primary"><?php echo htmlspecialchars($consultation['patient_prenom'] . ' ' . $consultation['patient_nom']); ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Numéro de dossier :</strong><br>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($consultation['patient_id']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations du médecin -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user-md me-2"></i>Informations du Médecin</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Nom complet :</strong><br>
                                <span class="text-success"><?php echo htmlspecialchars(medecin_profil_format_joined($consultation)); ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Spécialité :</strong><br>
                                <span class="badge bg-info"><?php echo htmlspecialchars($consultation['medecin_specialite']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Détails médicaux -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="fas fa-notes-medical me-2"></i>Détails Médicaux</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-12">
                                <strong>Symptômes :</strong><br>
                                <span class="text-muted"><?php echo $consultation['symptomes'] ? nl2br(htmlspecialchars($consultation['symptomes'])) : 'Non renseignés'; ?></span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <strong>Diagnostic :</strong><br>
                                <span class="text-muted"><?php echo $consultation['diagnostic'] ? nl2br(htmlspecialchars($consultation['diagnostic'])) : 'Non établi'; ?></span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <strong>Traitement :</strong><br>
                                <span class="text-muted"><?php echo $consultation['traitement'] ? nl2br(htmlspecialchars($consultation['traitement'])) : 'Non prescrit'; ?></span>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <strong>Ordonnance :</strong><br>
                                <span class="text-muted"><?php echo $consultation['ordonnance'] ? nl2br(htmlspecialchars($consultation['ordonnance'])) : 'Non prescrite'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <?php if ($canAccessPaiements && $paymentSyncEnabled): ?>
                <div class="payment-sync-feature-block mb-4">
                    <?php app_payment_sync_new_badge('Nouveau — facturation liée'); ?>
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-credit-card me-2"></i>Facturation &amp; Paiement</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Montant total :</strong>
                            <span class="text-success fw-bold"><?php echo number_format($prixTotalConsultation, 0, ',', ' '); ?> FCFA</span>
                        </p>
                        <?php if ($paiementConsultation): ?>
                            <?php
                            $payStatut = $paiementConsultation['statut'] ?? 'en_attente';
                            $payBadge = [
                                'paye' => 'bg-success',
                                'en_attente' => 'bg-warning text-dark',
                                'partiel' => 'bg-info',
                                'annule' => 'bg-danger',
                                'rembourse' => 'bg-secondary',
                            ];
                            ?>
                            <p class="mb-2">
                                <span class="badge <?php echo $payBadge[$payStatut] ?? 'bg-secondary'; ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payStatut))); ?>
                                </span>
                                <small class="text-muted ms-1"><?php echo htmlspecialchars($paiementConsultation['numero_facture'] ?? ''); ?></small>
                            </p>
                            <?php if (!empty($paiementConsultation['ecriture_comptable_id'])): ?>
                            <p class="small text-muted mb-2"><i class="fas fa-link me-1"></i>Synchronisé avec la comptabilité</p>
                            <?php endif; ?>
                            <div class="d-grid gap-2">
                                <a href="<?php echo app_url('paiements/voir.php?id=' . (int) $paiementConsultation['id']); ?>" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-eye me-1"></i>Voir le paiement
                                </a>
                                <?php if ($canWritePaiements && $payStatut !== 'paye'): ?>
                                <a href="<?php echo app_url('paiements/modifier.php?id=' . (int) $paiementConsultation['id']); ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-cash-register me-1"></i>Encaisser
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-3">Aucun paiement n'est encore lié à cette consultation.</p>
                            <?php if ($canWritePaiements && $prixTotalConsultation > 0 && ($consultation['statut'] ?? '') !== 'annulee'): ?>
                            <div class="d-grid gap-2">
                                <a href="<?php echo app_url('paiements/creer_depuis_consultation.php?consultation_id=' . (int) $consultation['id']); ?>" class="btn btn-success btn-sm"
                                   onclick="return confirm('Créer un paiement en attente pour cette consultation ?');">
                                    <i class="fas fa-file-invoice-dollar me-1"></i>Générer le paiement
                                </a>
                                <a href="<?php echo app_url('paiements/ajouter.php?patient_id=' . (int) $consultation['patient_id'] . '&consultation_id=' . (int) $consultation['id']); ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-plus me-1"></i>Saisie manuelle
                                </a>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                </div>
                <?php endif; ?>

                <!-- Actions rapides -->
                <div class="card mb-4 d-none d-md-block">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions Rapides</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="modifier.php?id=<?php echo $consultation['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Modifier
                            </a>
                            <?php if ($consultation['statut'] === 'planifie'): ?>
                                <form method="POST" action="actions.php" style="display: inline;">
                                    <input type="hidden" name="action" value="commencer">
                                    <input type="hidden" name="consultation_id" value="<?php echo $consultation['id']; ?>">
                                    <button type="submit" class="btn btn-success w-100" onclick="return confirm('Commencer cette consultation ?')">
                                        <i class="fas fa-play me-2"></i>Commencer
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($consultation['statut'] === 'en_cours'): ?>
                                <form method="POST" action="actions.php" style="display: inline;">
                                    <input type="hidden" name="action" value="terminer">
                                    <input type="hidden" name="consultation_id" value="<?php echo $consultation['id']; ?>">
                                    <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Terminer cette consultation ?')">
                                        <i class="fas fa-check me-2"></i>Terminer
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($consultation['statut'], ['planifie', 'en_cours'])): ?>
                                <form method="POST" action="actions.php" style="display: inline;">
                                    <input type="hidden" name="action" value="annuler">
                                    <input type="hidden" name="consultation_id" value="<?php echo $consultation['id']; ?>">
                                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette consultation ?')">
                                        <i class="fas fa-times me-2"></i>Annuler
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="index.php?patient_id=<?php echo $consultation['patient_id']; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-2"></i>Voir toutes les consultations du patient
                            </a>
                            <a href="../patients/voir.php?id=<?php echo $consultation['patient_id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-user me-2"></i>Voir la fiche patient
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Informations utiles - Desktop only -->
                <div class="card d-none d-md-block">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations Utiles</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-card p-3 mb-3">
                            <strong>Prochaine consultation :</strong><br>
                            <small class="text-muted">Aucune autre consultation prévue aujourd'hui</small>
                        </div>
                        <div class="info-card p-3">
                            <strong>Disponibilité :</strong><br>
                            <small class="text-success">Créneau disponible</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Boutons d'action Desktop -->
        <div class="row mt-4 desktop-action-buttons">
            <div class="col-12 text-center">
                <a href="modifier.php?id=<?php echo $consultation['id']; ?>" class="btn btn-warning btn-lg me-3">
                    <i class="fas fa-edit me-2"></i>Modifier cette consultation
                </a>
                <a href="index.php?patient_id=<?php echo $consultation['patient_id']; ?>" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Consultations du patient
                </a>
            </div>
        </div>
    </div>

    <!-- Boutons d'action Mobile (en bas fixe) -->
    <div class="action-buttons-bottom">
        <?php if ($consultation['statut'] === 'planifie'): ?>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="commencer">
                <input type="hidden" name="consultation_id" value="<?php echo $consultation['id']; ?>">
                <button type="submit" class="btn btn-success w-100" onclick="return confirm('Commencer cette consultation ?')">
                    <i class="fas fa-play me-2"></i>Commencer la consultation
                </button>
            </form>
        <?php endif; ?>
        <?php if ($consultation['statut'] === 'en_cours'): ?>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="terminer">
                <input type="hidden" name="consultation_id" value="<?php echo $consultation['id']; ?>">
                <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Terminer cette consultation ?')">
                    <i class="fas fa-check me-2"></i>Terminer la consultation
                </button>
            </form>
        <?php endif; ?>
        <a href="modifier.php?id=<?php echo $consultation['id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit me-2"></i>Modifier
        </a>
        <a href="soins.php?id=<?php echo $consultation['id']; ?>" class="btn btn-success">
            <i class="fas fa-stethoscope me-2"></i>Gérer les soins
        </a>
        <?php if ($consultation['hospitalisation_requise']): ?>
            <a href="hospitalisation.php?id=<?php echo $consultation['id']; ?>" class="btn btn-success">
                <i class="fas fa-bed me-2"></i>Hospitalisation
            </a>
        <?php endif; ?>
        <a href="../patients/voir.php?id=<?php echo $consultation['patient_id']; ?>" class="btn btn-outline-primary">
            <i class="fas fa-user me-2"></i>Fiche patient
        </a>
        <?php if (in_array($consultation['statut'], ['planifie', 'en_cours'])): ?>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="annuler">
                <input type="hidden" name="consultation_id" value="<?php echo $consultation['id']; ?>">
                <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette consultation ?')">
                    <i class="fas fa-times me-2"></i>Annuler la consultation
                </button>
            </form>
        <?php endif; ?>
        <a href="index.php?patient_id=<?php echo $consultation['patient_id']; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Retour
        </a>
<?php ob_start(); ?>
<script>
        // Protection RENFORCÉE : désactiver complètement le système de sidebar et hamburger sur cette page
        document.addEventListener('DOMContentLoaded', function() {
            function disableSidebarSystem() {
                // Supprimer le bouton hamburger
                const mobileMenuBtn = document.getElementById('auto-mobile-menu-btn');
                if (mobileMenuBtn) {
                    mobileMenuBtn.style.display = 'none';
                    mobileMenuBtn.remove();
                }
                
                // Supprimer tous les boutons hamburger qui pourraient être créés
                document.querySelectorAll('[id*="mobile-menu"], [class*="hamburger"], [class*="navbar-toggler"], .btn-menu, button[aria-label*="menu"]').forEach(function(btn) {
                    btn.style.display = 'none';
                    btn.remove();
                });
                
                // Supprimer la barre bleue et la bottom nav
                const topBar = document.querySelector('.mobile-top-bar');
                const bottomNav = document.querySelector('.mobile-bottom-nav');
                if (topBar) {
                    topBar.style.display = 'none';
                    topBar.remove();
                }
                if (bottomNav) {
                    bottomNav.style.display = 'none';
                    bottomNav.remove();
                }
                
                // Supprimer l'overlay
                const sidebarOverlay = document.getElementById('auto-sidebar-overlay');
                if (sidebarOverlay) {
                    sidebarOverlay.style.display = 'none';
                    sidebarOverlay.remove();
                }
                
                // S'assurer qu'AUCUNE colonne ne se comporte comme une sidebar
                const allColumns = document.querySelectorAll('[class*="col-"]');
                allColumns.forEach(function(col) {
                    col.classList.remove('is-sidebar', 'active');
                    col.style.position = 'static';
                    col.style.transform = 'none';
                    col.style.width = '';
                    col.style.maxWidth = '';
                    col.style.height = '';
                    col.style.zIndex = '';
                    col.style.left = '';
                    col.style.top = '';
                    col.style.bottom = '';
                    col.style.overflow = '';
                });
            }
            
            // Exécuter immédiatement et après plusieurs délais
            disableSidebarSystem();
            setTimeout(disableSidebarSystem, 50);
            setTimeout(disableSidebarSystem, 100);
            setTimeout(disableSidebarSystem, 200);
            setTimeout(disableSidebarSystem, 500);
            setTimeout(disableSidebarSystem, 1000);
            
            // Observer pour détecter si le bouton est ajouté dynamiquement
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.id === 'auto-mobile-menu-btn' || node.id === 'auto-sidebar-overlay' || 
                            (node.classList && (node.classList.contains('mobile-top-bar') || node.classList.contains('mobile-bottom-nav')))) {
                            node.style.display = 'none';
                            node.remove();
                        }
                    });
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
