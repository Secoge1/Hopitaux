<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('laboratoire'));

require_once __DIR__ . '/../models/Analyse.php';
require_once __DIR__ . '/../models/Paiement.php';
require_once __DIR__ . '/../includes/payment_sync_badge.php';

$analyseModel = new Analyse();
$paiementModel = new Paiement();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$analyse = $analyseModel->getById($id);
if (!$analyse) {
    header("Location: index.php");
    exit();
}

$paiementAnalyse = $paiementModel->getByAnalyseId($id);
$prixAnalyse = (float) ($analyse['prix_analyse'] ?? 0);
$canAccessPaiements = $auth->aAccesModule('paiements');
$canWritePaiements = $canAccessPaiements && $auth->peutEcrirePaiements();
$paymentSyncEnabled = function_exists('payment_finance_sync_enabled') && payment_finance_sync_enabled();

$typesAnalyses = $analyseModel->getTypesAnalyses();
$priorites = $analyseModel->getPriorites();
$statuts = $analyseModel->getStatuts();


app_module_page_start([
    'active'   => 'laboratoire',
    'title'    => 'Détails de l\'Analyse',
    'subtitle' => 'Résultats et suivi',
    'icon'     => 'fa-flask',
]);
app_module_back_toolbar(app_url('laboratoire/index.php'), 'Retour à la liste', [['href' => app_url('laboratoire/modifier.php?id=' . $analyse['id']), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-warning']]);
app_module_flash();
?>
<?php if (!empty($_GET['paiement_error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars(urldecode((string) $_GET['paiement_error'])); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<style>
.status-badge { padding: 8px 16px; border-radius: 25px; font-size: 0.9rem; font-weight: 500; }
        .status-en_attente { background: #fff3cd; color: #856404; }
        .status-en_cours { background: #d1ecf1; color: #0c5460; }
        .status-termine { background: #d4edda; color: #155724; }
        .status-annule { background: #f8d7da; color: #721c24; }
        .priorite-normale { background: #6c757d; color: white; }
        .priorite-urgente { background: #ffc107; color: black; }
        .priorite-critique { background: #dc3545; color: white; }
        .analyse-header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
        
        /* Styles améliorés pour les boutons d'actions */
        .action-buttons {
            display: flex;
            flex-direction: column;
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
            text-align: left;
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
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .action-buttons .btn:active {
            transform: translateX(0);
        }
        
        .action-buttons .btn-info {
            background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%);
            color: #fff;
        }
        
        .action-buttons .btn-info:hover {
            background: linear-gradient(135deg, #0aa2c0 0%, #0891b2 100%);
            color: #fff;
        }
        
        .action-buttons .btn-success {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            color: #fff;
        }
        
        .action-buttons .btn-success:hover {
            background: linear-gradient(135deg, #157347 0%, #146c43 100%);
            color: #fff;
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
        
        .action-buttons .btn i {
            margin-right: 0.5rem;
            font-size: 0.9rem;
            width: 20px;
            text-align: center;
        }
        
        /* Boutons d'action en bas de page */
        .bottom-action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
        }
        
        .bottom-action-buttons .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .bottom-action-buttons .btn::before {
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
        
        .bottom-action-buttons .btn:hover::before {
            width: 400px;
            height: 400px;
        }
        
        .bottom-action-buttons .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }
        
        .bottom-action-buttons .btn:active {
            transform: translateY(-1px);
        }
        
        .bottom-action-buttons .btn-success {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            color: #fff;
        }
        
        .bottom-action-buttons .btn-success:hover {
            background: linear-gradient(135deg, #157347 0%, #146c43 100%);
        }
        
        .bottom-action-buttons .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
            color: #000;
        }
        
        .bottom-action-buttons .btn-warning:hover {
            background: linear-gradient(135deg, #ffb300 0%, #ffa000 100%);
        }
        
        .bottom-action-buttons .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            background: transparent;
        }
        
        .bottom-action-buttons .btn-outline-secondary:hover {
            background: #6c757d;
            color: #fff;
            border-color: #6c757d;
        }
        
        .bottom-action-buttons .btn-outline-primary {
            border: 2px solid #0d6efd;
            color: #0d6efd;
            background: transparent;
        }
        
        .bottom-action-buttons .btn-outline-primary:hover {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }
        
        @media (max-width: 768px) {
            .bottom-action-buttons {
                flex-direction: column;
            }
            
            .bottom-action-buttons .btn {
                width: 100%;
            }
        }
</style>

        <!-- En-tête de l'analyse -->
        <div class="card analyse-header text-white mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-2">
                            <?php echo htmlspecialchars($typesAnalyses[$analyse['type_analyse']] ?? ($analyse['type_analyse'] ?? '')); ?>
                        </h4>
                        <p class="mb-0">
                            <strong>Patient :</strong> <?php echo htmlspecialchars($analyse['patient_nom'] . ' ' . $analyse['patient_prenom']); ?> 
                            <?php if (!empty($analyse['numero_dossier'])): ?>(<?php echo htmlspecialchars($analyse['numero_dossier']); ?>)<?php endif; ?> |
                            <strong><?= htmlspecialchars(medecin_profil_attribution_label_from_row($analyse)) ?> :</strong> <?php echo htmlspecialchars(medecin_profil_format_joined($analyse)); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex gap-2 justify-content-end">
                            <span class="status-badge status-<?php echo $analyse['statut']; ?>">
                                <?php echo htmlspecialchars($statuts[$analyse['statut']] ?? ucfirst($analyse['statut'] ?? '')); ?>
                            </span>
                            <span class="badge priorite-<?php echo $analyse['priorite']; ?>">
                                <?php echo htmlspecialchars($priorites[$analyse['priorite']] ?? ucfirst($analyse['priorite'] ?? '')); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Détails de l'analyse -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Détails de l'Analyse</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Type d'analyse :</strong><br>
                                <span class="badge bg-info"><?php echo htmlspecialchars($typesAnalyses[$analyse['type_analyse']] ?? ($analyse['type_analyse'] ?? '')); ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Priorité :</strong><br>
                                <span class="badge priorite-<?php echo $analyse['priorite']; ?>">
                                    <?php echo htmlspecialchars($priorites[$analyse['priorite']] ?? ucfirst($analyse['priorite'] ?? '')); ?>
                                </span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Prix :</strong><br>
                                <span class="h5 text-success"><?php echo number_format($analyse['prix_analyse'] ?? 0, 0, ',', ' '); ?> FCFA</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Numéro de ticket :</strong><br>
                                <code><?php echo htmlspecialchars($analyse['numero_ticket'] ?? 'Non généré'); ?></code>
                            </div>
                            <div class="col-12 mb-3">
                                <strong>Description :</strong><br>
                                <span class="text-muted">
                                    <?php echo $analyse['description'] ? nl2br(htmlspecialchars($analyse['description'])) : 'Aucune description'; ?>
                                </span>
                            </div>
                            <div class="col-12 mb-3">
                                <strong>Instructions :</strong><br>
                                <span class="text-muted">
                                    <?php echo $analyse['instructions'] ? nl2br(htmlspecialchars($analyse['instructions'])) : 'Aucune instruction'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Résultats (si disponibles) -->
                <?php if ($analyse['resultats']): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Résultats de l'Analyse</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Date des résultats :</strong><br>
                            <span class="text-muted">
                                <?php echo $analyse['date_resultats'] ? date('d/m/Y H:i', strtotime($analyse['date_resultats'])) : 'Non spécifiée'; ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <strong>Résultats :</strong><br>
                            <span class="text-muted">
                                <?php echo nl2br(htmlspecialchars($analyse['resultats'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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
                        <p class="mb-2"><strong>Prix analyse :</strong>
                            <span class="text-success fw-bold"><?php echo number_format($prixAnalyse, 0, ',', ' '); ?> FCFA</span>
                        </p>
                        <?php if ($paiementAnalyse): ?>
                            <?php
                            $payStatut = $paiementAnalyse['statut'] ?? 'en_attente';
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
                                <small class="text-muted ms-1"><?php echo htmlspecialchars($paiementAnalyse['numero_facture'] ?? ''); ?></small>
                            </p>
                            <?php if (!empty($paiementAnalyse['ecriture_comptable_id'])): ?>
                            <p class="small text-muted mb-2"><i class="fas fa-link me-1"></i>Synchronisé avec la comptabilité</p>
                            <?php endif; ?>
                            <div class="d-grid gap-2">
                                <a href="<?php echo app_url('paiements/voir.php?id=' . (int) $paiementAnalyse['id']); ?>" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-eye me-1"></i>Voir le paiement
                                </a>
                                <?php if ($canWritePaiements && $payStatut !== 'paye'): ?>
                                <a href="<?php echo app_url('paiements/modifier.php?id=' . (int) $paiementAnalyse['id']); ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-cash-register me-1"></i>Encaisser
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-3">Aucun paiement n'est encore lié à cette analyse.</p>
                            <?php if ($canWritePaiements && $prixAnalyse > 0 && ($analyse['statut'] ?? '') !== 'annule'): ?>
                            <div class="d-grid gap-2">
                                <a href="<?php echo app_url('paiements/creer_depuis_analyse.php?analyse_id=' . (int) $analyse['id']); ?>" class="btn btn-success btn-sm"
                                   onclick="return confirm('Créer un paiement en attente pour cette analyse ?');">
                                    <i class="fas fa-file-invoice-dollar me-1"></i>Générer le paiement
                                </a>
                                <a href="<?php echo app_url('paiements/ajouter.php?patient_id=' . (int) $analyse['patient_id'] . '&analyse_id=' . (int) $analyse['id']); ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-plus me-1"></i>Saisie manuelle
                                </a>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                </div>
                <?php endif; ?>

                <!-- Informations temporelles -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Informations Temporelles</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Date de création :</strong><br>
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($analyse['date_creation'])); ?></small>
                        </div>
                        <div class="mb-3">
                            <strong>Dernière modification :</strong><br>
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($analyse['date_modification'])); ?></small>
                        </div>
                        <?php if ($analyse['date_analyse']): ?>
                        <div class="mb-3">
                            <strong>Date de l'analyse :</strong><br>
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($analyse['date_analyse'])); ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions Rapides</h6>
                    </div>
                    <div class="card-body">
                        <div class="action-buttons">
                            <?php if ($analyse['statut'] === 'en_attente'): ?>
                                <a href="modifier.php?id=<?php echo $analyse['id']; ?>&action=commencer" class="btn btn-info btn-sm">
                                    <i class="fas fa-play"></i>Commencer l'analyse
                                </a>
                            <?php elseif ($analyse['statut'] === 'en_cours'): ?>
                                <a href="modifier.php?id=<?php echo $analyse['id']; ?>&action=terminer" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i>Terminer l'analyse
                                </a>
                            <?php endif; ?>
                            
                            <a href="ticket_analyse.php?id=<?php echo $analyse['id']; ?>&print=1" class="btn btn-success btn-sm" onclick="printTicket(this.href); return false;">
                                <i class="fas fa-print"></i>Imprimer
                            </a>
                            
                            <a href="modifier.php?id=<?php echo $analyse['id']; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit"></i>Modifier
                            </a>
                            
                            <a href="supprimer.php?id=<?php echo $analyse['id']; ?>" class="btn btn-danger btn-sm"
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette analyse ?')">
                                <i class="fas fa-trash"></i>Supprimer
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statut actuel -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Statut Actuel</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                            <span class="status-badge status-<?php echo $analyse['statut']; ?> fs-5">
                                <?php echo htmlspecialchars($statuts[$analyse['statut']] ?? ucfirst($analyse['statut'] ?? '')); ?>
                            </span>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <?php
                                $statutDescriptions = [
                                    'en_attente' => 'L\'analyse est en attente de traitement',
                                    'en_cours' => 'L\'analyse est en cours de réalisation',
                                    'termine' => 'L\'analyse est terminée avec résultats',
                                    'annule' => 'L\'analyse a été annulée'
                                ];
                                echo $statutDescriptions[$analyse['statut']] ?? 'Statut non défini';
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="bottom-action-buttons">
                    <a href="ticket_analyse.php?id=<?php echo $analyse['id']; ?>&print=1" class="btn btn-success btn-lg" onclick="printTicket(this.href); return false;">
                        <i class="fas fa-print me-2"></i>Imprimer le ticket
                    </a>
                    <a href="modifier.php?id=<?php echo $analyse['id']; ?>" class="btn btn-warning btn-lg">
                        <i class="fas fa-edit me-2"></i>Modifier cette analyse
                    </a>
                    <a href="index.php?patient_id=<?php echo $analyse['patient_id']; ?>" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>Analyses du patient
                    </a>
                    <a href="../patients/voir.php?id=<?php echo $analyse['patient_id']; ?>" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-user me-2"></i>Voir la fiche patient
                    </a>
                </div>
            </div>
        </div>
<?php ob_start(); ?>
<script>
        function printTicket(url) {
            try {
                const u = new URL(url, window.location.href);
                if (!u.searchParams.has('print')) {
                    u.searchParams.set('print', '1');
                }
                window.open(u.toString(), '_blank');
            } catch (e) {
                window.open(url + (url.indexOf('?') >= 0 ? '&' : '?') + 'print=1', '_blank');
            }
        }
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
