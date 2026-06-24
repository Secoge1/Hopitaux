<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('paiements'));

require_once '../config/config.php';
require_once '../models/Paiement.php';
require_once __DIR__ . '/../includes/payment_sync_badge.php';

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$paiementId = (int)$_GET['id'];
$paiementModel = new Paiement();

// Récupérer les détails du paiement
$paiement = $paiementModel->getById($paiementId);
$ecritureLiee = $paiement ? $paiementModel->getLinkedEcriture($paiementId) : null;
$canAccessFinances = $auth->aAccesModule('finances');
$paymentSyncEnabled = function_exists('payment_finance_sync_enabled') && payment_finance_sync_enabled();

if (!$paiement) {
    header('Location: index.php');
    exit;
}

// Fonctions utilitaires
$paiementVerrouille = $paiementModel->isEncaisseVerrouille($paiement);
$paiementClos = $paiementModel->isHistoriqueClos($paiement);

function getTypePaiementLabel($type) {
    $types = [
        'carte' => 'Carte bancaire',
        'virement' => 'Virement bancaire',
        'especes' => 'Espèces',
        'cheque' => 'Chèque',
        'securite_sociale' => 'Sécurité sociale',
        'mutuelle' => 'Mutuelle',
        'mobile_money' => 'Mobile Money',
        'autre' => 'Autre'
    ];
    
    return $types[$type] ?? ucfirst($type);
}

function getStatutLabel($statut) {
    $statuts = [
        'en_attente' => 'En attente',
        'partiel' => 'Paiement partiel',
        'paye' => 'Payé',
        'annule' => 'Annulé',
        'rembourse' => 'Remboursé'
    ];
    
    return $statuts[$statut] ?? ucfirst($statut);
}

function getStatutClass($statut) {
    $classes = [
        'en_attente' => 'bg-warning',
        'partiel' => 'bg-info',
        'paye' => 'bg-success',
        'annule' => 'bg-danger',
        'rembourse' => 'bg-secondary'
    ];
    
    return $classes[$statut] ?? 'bg-secondary';
}

app_module_page_start([
    'active'   => 'paiements',
    'title'    => 'Détails du Paiement',
    'subtitle' => 'Informations de facturation',
    'icon'     => 'fa-credit-card',
]);
app_module_back_toolbar(app_url('paiements/index.php'), 'Retour à la liste', [['href' => app_url('paiements/facture_paiement.php?id=' . $paiement['id']), 'label' => 'Facture', 'icon' => 'fa-file-invoice', 'class' => 'btn-info']]);
app_module_flash();
?>
<?php if (!empty($_GET['created'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>Paiement créé depuis la consultation. Passez le statut à « Payé » pour synchroniser la comptabilité.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<style>
.status-badge { font-size: 0.9rem; }
        .amount-display { font-size: 1.5rem; font-weight: bold; }
        .info-card { border-left: 4px solid #007bff; }
        .patient-card { border-left: 4px solid #28a745; }
        .paiement-card { border-left: 4px solid #ffc107; }
        
        /* Styles améliorés pour les boutons d'actions */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        
        .action-buttons .btn {
            padding: 0.625rem 1.25rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .action-buttons .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .action-buttons .btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .action-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .action-buttons .btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .action-buttons .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
            color: #000;
        }
        
        .action-buttons .btn-warning:hover {
            background: linear-gradient(135deg, #ffb300 0%, #ffa000 100%);
            color: #000;
        }
        
        .action-buttons .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: #fff;
        }
        
        .action-buttons .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            color: #fff;
        }
        
        .action-buttons .btn-outline-danger {
            border: 2px solid #dc3545;
            color: #dc3545;
            background: transparent;
        }
        
        .action-buttons .btn-outline-danger:hover {
            background: #dc3545;
            color: #fff;
            border-color: #dc3545;
        }
        
        .action-buttons .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
        }
        
        .action-buttons .btn-outline-secondary:hover {
            background: #6c757d;
            color: #fff;
            border-color: #6c757d;
        }
        
        .action-buttons .btn i {
            margin-right: 0.5rem;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
            }
        }
</style>

        <!-- Informations générales -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card info-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations Générales</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Numéro de facture :</strong><br>
                                <span class="text-primary"><?php echo htmlspecialchars($paiement['numero_facture']); ?></span></p>
                                
                                <p><strong>Date de paiement :</strong><br>
                                <?php echo date('d/m/Y H:i', strtotime($paiement['date_paiement'])); ?></p>
                                
                                <p><strong>Date de création :</strong><br>
                                <?php echo date('d/m/Y H:i', strtotime($paiement['date_creation'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Statut :</strong><br>
                                <span class="badge <?php echo getStatutClass($paiement['statut']); ?> status-badge">
                                    <?php echo getStatutLabel($paiement['statut']); ?>
                                </span></p>
                                
                                <p><strong>Type de paiement :</strong><br>
                                <span class="badge bg-info"><?php echo getTypePaiementLabel($paiement['type_paiement']); ?></span></p>
                                
                                                <p><strong>Montant :</strong><br>
                <span class="amount-display text-success"><?php echo formatFCFA($paiement['montant']); ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card patient-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Patient</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Nom complet :</strong><br>
                        <?php echo htmlspecialchars($paiement['patient_nom'] . ' ' . $paiement['patient_prenom']); ?></p>
                        
                        <p><strong>Numéro de dossier :</strong><br>
                        <span class="text-success"><?php echo htmlspecialchars($paiement['numero_dossier']); ?></span></p>
                        
                        <?php if ($paiement['consultation_id']): ?>
                        <p><strong>Consultation :</strong><br>
                        <span class="text-info">#<?php echo $paiement['consultation_id']; ?></span><br>
                        <small class="text-muted"><?php echo date('d/m/Y', strtotime($paiement['date_consultation'])); ?></small></p>
                        <?php endif; ?>
                        <?php if (!empty($paiement['analyse_id'])): ?>
                        <p><strong>Analyse labo :</strong><br>
                        <a href="<?php echo app_url('laboratoire/voir.php?id=' . (int) $paiement['analyse_id']); ?>" class="text-info">#<?php echo (int) $paiement['analyse_id']; ?></a></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Détails du paiement -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card paiement-card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Détails du Paiement</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <?php if ($paiement['description']): ?>
                                <p><strong>Description :</strong><br>
                                <?php echo htmlspecialchars($paiement['description']); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($paiement['reference_paiement']): ?>
                                <p><strong>Référence :</strong><br>
                                <span class="text-muted"><?php echo htmlspecialchars($paiement['reference_paiement']); ?></span></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <?php if ($paiement['notes']): ?>
                                <p><strong>Notes :</strong><br>
                                <?php echo htmlspecialchars($paiement['notes']); ?></p>
                                <?php endif; ?>
                                
                                <p><strong>Dernière modification :</strong><br>
                                <small class="text-muted">
                                    <?php echo isset($paiement['date_modification']) ? date('d/m/Y H:i', strtotime($paiement['date_modification'])) : 'Non modifié'; ?>
                                </small></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Synchronisation comptable -->
        <?php if ($canAccessFinances && $paymentSyncEnabled): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="payment-sync-feature-block">
                    <?php app_payment_sync_new_badge('Nouveau — comptabilité auto'); ?>
                <div class="card border-<?php echo $ecritureLiee ? 'success' : 'secondary'; ?>">
                    <div class="card-header bg-<?php echo $ecritureLiee ? 'success' : 'secondary'; ?> text-white">
                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Comptabilité (Finances)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($ecritureLiee): ?>
                            <p class="mb-2">
                                <span class="badge bg-success">Synchronisé</span>
                                Écriture n° <strong><?php echo htmlspecialchars($ecritureLiee['numero_ecriture'] ?? ''); ?></strong>
                                — <?php echo number_format((float) ($ecritureLiee['montant'] ?? 0), 0, ',', ' '); ?> FCFA
                            </p>
                            <p class="text-muted small mb-3">
                                <?php echo htmlspecialchars($ecritureLiee['libelle'] ?? ''); ?>
                            </p>
                            <a href="<?php echo app_url('finances/voir_ecriture.php?id=' . (int) $ecritureLiee['id']); ?>" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-external-link-alt me-1"></i>Voir l'écriture comptable
                            </a>
                        <?php elseif (($paiement['statut'] ?? '') === 'paye'): ?>
                            <p class="text-warning mb-0">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Paiement encaissé mais aucune écriture comptable liée. Vérifiez la configuration des comptes dans Finances.
                            </p>
                        <?php else: ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Une écriture comptable sera créée automatiquement lorsque le statut passera à « Payé ».
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="action-buttons">
                            <?php if ($paiementClos): ?>
                            <span class="badge bg-secondary align-self-center me-2"><i class="fas fa-lock me-1"></i>Paiement clos</span>
                            <?php elseif ($paiementVerrouille): ?>
                            <span class="badge bg-warning text-dark align-self-center me-2"><i class="fas fa-lock me-1"></i>Encaissé — verrouillé</span>
                            <a href="modifier.php?id=<?php echo $paiement['id']; ?>" class="btn btn-outline-warning">
                                <i class="fas fa-ban"></i>Annuler / Rembourser
                            </a>
                            <?php else: ?>
                            <a href="modifier.php?id=<?php echo $paiement['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i>Modifier
                            </a>
                            <?php endif; ?>
                            <a href="facture_paiement.php?id=<?php echo $paiement['id']; ?>&print=1" class="btn btn-danger" target="_blank">
                                <i class="fas fa-print"></i>Imprimer
                            </a>
                            <?php if (!$paiementVerrouille && !$paiementClos): ?>
                            <a href="supprimer.php?id=<?php echo $paiement['id']; ?>" class="btn btn-outline-danger">
                                <i class="fas fa-trash"></i>Supprimer
                            </a>
                            <?php endif; ?>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i>Liste des Paiements
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php app_module_page_end(); ?>
