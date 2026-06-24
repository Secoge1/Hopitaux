<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('rdv'));

require_once __DIR__ . '/../models/RendezVous.php';

$rdvModel = new RendezVous();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$rdv = $rdvModel->getById($id);
if (!$rdv) {
    header("Location: index.php");
    exit();
}
if (($rdv['statut'] ?? '') === 'supprime') {
    header("Location: index.php");
    exit();
}

// Gérer les messages de succès/erreur
$message = '';
$messageType = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'confirmed':
            $message = 'Rendez-vous confirmé avec succès !';
            $messageType = 'success';
            break;
        case 'cancelled':
            $message = 'Rendez-vous annulé avec succès !';
            $messageType = 'success';
            break;
        case 'completed':
            $message = 'Rendez-vous marqué comme terminé !';
            $messageType = 'success';
            break;
    }
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'confirmation_failed':
            $message = 'Erreur lors de la confirmation du rendez-vous.';
            $messageType = 'danger';
            break;
        case 'cancellation_failed':
            $message = 'Erreur lors de l\'annulation du rendez-vous.';
            $messageType = 'danger';
            break;
        case 'invalid_status':
            $message = 'Action non autorisée pour ce statut de rendez-vous.';
            $messageType = 'warning';
            break;
        default:
            $message = 'Une erreur est survenue.';
            $messageType = 'danger';
    }
}

app_module_page_start([
    'active'   => 'rdv',
    'title'    => 'Détails du Rendez-vous',
    'subtitle' => date('d/m/Y', strtotime($rdv['date_rdv'])) . ' à ' . $rdv['heure_rdv'],
    'icon'     => 'fa-calendar-check',
]);
app_module_back_toolbar(app_url('rendez-vous/index.php'), 'Retour à la liste', [
    ['href' => app_url('rendez-vous/modifier.php?id=' . $rdv['id']), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-warning'],
]);
app_module_flash();
?>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <!-- Informations principales -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Informations du Rendez-vous</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Date :</strong><br>
                                <span class="text-primary"><?php echo date('d/m/Y', strtotime($rdv['date_rdv'])); ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Heure :</strong><br>
                                <span class="text-primary"><?php echo $rdv['heure_rdv']; ?></span>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Statut :</strong><br>
                                <span class="status-badge status-<?php echo $rdv['statut']; ?>">
                                    <?php echo ucfirst($rdv['statut']); ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <strong>Date de création :</strong><br>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($rdv['date_creation'])); ?></small>
                            </div>
                        </div>

                        <?php if ($rdv['motif']): ?>
                        <div class="mb-3">
                            <strong>Motif de consultation :</strong><br>
                            <span class="text-info"><?php echo htmlspecialchars($rdv['motif']); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($rdv['notes']): ?>
                        <div class="mb-3">
                            <strong>Notes :</strong><br>
                            <span class="text-muted"><?php echo nl2br(htmlspecialchars($rdv['notes'])); ?></span>
                        </div>
                        <?php endif; ?>
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
                                <span class="text-primary"><?php echo htmlspecialchars($rdv['patient_prenom'] . ' ' . $rdv['patient_nom']); ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Numéro de dossier :</strong><br>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($rdv['patient_id']); ?></span>
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
                                <span class="text-success"><?php echo htmlspecialchars(medecin_profil_format_joined($rdv)); ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Spécialité :</strong><br>
                                <span class="badge bg-info"><?php echo htmlspecialchars($rdv['medecin_specialite']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Actions rapides -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions Rapides</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="modifier.php?id=<?php echo $rdv['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Modifier
                            </a>
                            <?php if ($rdv['statut'] === 'planifie'): ?>
                                <form method="POST" action="actions.php" style="display: inline;">
                                    <input type="hidden" name="action" value="confirmer">
                                    <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                    <button type="submit" class="btn btn-success w-100" onclick="return confirm('Confirmer ce rendez-vous ?')">
                                        <i class="fas fa-check me-2"></i>Confirmer
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($rdv['statut'], ['planifie', 'confirme'])): ?>
                                <form method="POST" action="actions.php" style="display: inline;">
                                    <input type="hidden" name="action" value="annuler">
                                    <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')">
                                        <i class="fas fa-times me-2"></i>Annuler
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($rdv['statut'] === 'confirme'): ?>
                                <form method="POST" action="actions.php" style="display: inline;">
                                    <input type="hidden" name="action" value="terminer">
                                    <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                    <button type="submit" class="btn btn-info w-100" onclick="return confirm('Marquer ce rendez-vous comme terminé ?')">
                                        <i class="fas fa-flag-checkered me-2"></i>Terminer
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-2"></i>Voir tous les RDV
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Informations utiles -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations Utiles</h6>
                    </div>
                    <div class="card-body">
                        <div class="info-card p-3 mb-3">
                            <strong>Prochain RDV :</strong><br>
                            <small class="text-muted">Aucun autre RDV prévu aujourd'hui</small>
                        </div>
                        <div class="info-card p-3">
                            <strong>Disponibilité :</strong><br>
                            <small class="text-success">Créneau disponible</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="modifier.php?id=<?php echo $rdv['id']; ?>" class="btn btn-warning btn-lg me-3">
                    <i class="fas fa-edit me-2"></i>Modifier ce rendez-vous
                </a>
                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Retour à la liste
                </a>
            </div>
        </div>

<?php app_module_page_end();


