<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('laboratoire'));

require_once __DIR__ . '/../includes/staff_scope.php';
require_once __DIR__ . '/../includes/staff_link.php';
require_once __DIR__ . '/../models/Analyse.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../models/Personnel.php';

$analyseModel = new Analyse();
$patientModel = new Patient();
$medecinModel = new Medecin();
$personnelModel = new Personnel();
$staffCtx = StaffScope::context();
$canPickTechnicien = StaffScope::canPickTechnicienOnAnalyse();
$techniciensLab = $canPickTechnicien ? $personnelModel->listTechniciensLaboratoire() : [];
$staffLinkSelf = (!$canPickTechnicien && !empty($staffCtx['user_id']))
    ? StaffLink::getLinkForUser((int) $staffCtx['user_id'])
    : ['label' => null];

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

$message = '';
$error = '';

// Traitement des actions rapides
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'commencer' && $analyse['statut'] === 'en_attente') {
        $updateData = [
            'statut' => 'en_cours',
            'date_analyse' => date('Y-m-d H:i:s'),
        ];
        $claimId = StaffScope::technicienIdForAnalyseClaim((int) ($analyse['technicien_id'] ?? 0));
        if ($claimId) {
            $updateData['technicien_id'] = $claimId;
        }
        if ($analyseModel->update($id, $updateData)) {
            $message = "L'analyse a été mise en cours avec succès.";
            $analyse = $analyseModel->getById($id); // Rafraîchir les données
        } else {
            $error = "Erreur lors de la mise à jour du statut.";
        }
    } elseif ($action === 'terminer' && $analyse['statut'] === 'en_cours') {
        $updateData = [
            'statut' => 'termine',
            'date_resultats' => date('Y-m-d H:i:s')
        ];
        if ($analyseModel->update($id, $updateData)) {
            $message = "L'analyse a été marquée comme terminée.";
            $analyse = $analyseModel->getById($id); // Rafraîchir les données
        } else {
            $error = "Erreur lors de la mise à jour du statut.";
        }
    }
}

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateData = [
        'patient_id' => (int)$_POST['patient_id'],
        'medecin_id' => (int)$_POST['medecin_id'],
        'type_analyse' => $_POST['type_analyse'],
        'priorite' => $_POST['priorite'],
        'description' => $_POST['description'],
        'instructions' => $_POST['instructions'],
        'statut' => $_POST['statut']
    ];

    // Prix de l'analyse si fourni
    if (isset($_POST['prix_analyse']) && $_POST['prix_analyse'] !== '') {
        $updateData['prix_analyse'] = (float)$_POST['prix_analyse'];
    }

    $tid = StaffScope::technicienIdForAnalyseForm(
        isset($_POST['technicien_id']) && $_POST['technicien_id'] !== '' ? (int) $_POST['technicien_id'] : null
    );
    if ($tid) {
        $updateData['technicien_id'] = $tid;
    } elseif ($canPickTechnicien && array_key_exists('technicien_id', $_POST) && $_POST['technicien_id'] === '') {
        $updateData['technicien_id'] = null;
    }

    // Ajouter les dates selon le statut
    if ($_POST['statut'] === 'en_cours' && $analyse['statut'] !== 'en_cours') {
        $updateData['date_analyse'] = date('Y-m-d H:i:s');
    }
    if ($_POST['statut'] === 'termine' && $analyse['statut'] !== 'termine') {
        $updateData['date_resultats'] = date('Y-m-d H:i:s');
    }

    // Ajouter les résultats si fournis
    if (!empty($_POST['resultats'])) {
        $updateData['resultats'] = $_POST['resultats'];
    }

    if ($analyseModel->update($id, $updateData)) {
        $message = "L'analyse a été modifiée avec succès.";
        $analyse = $analyseModel->getById($id); // Rafraîchir les données
    } else {
        $error = "Erreur lors de la modification de l'analyse.";
    }
}

// Récupération des listes pour les formulaires
$patients = $patientModel->getAll(1, 1000);
$medecins = $medecinModel->getAll(1, 1000);
$typesAnalyses = $analyseModel->getTypesAnalyses();
$priorites = $analyseModel->getPriorites();
$statuts = $analyseModel->getStatuts();


app_module_page_start([
    'active'   => 'laboratoire',
    'title'    => 'Modifier l\'Analyse',
    'subtitle' => 'Mise à jour de l\'analyse',
    'icon'     => 'fa-flask',
]);
app_module_back_toolbar(app_url('laboratoire/index.php'), 'Retour à la liste', [['href' => app_url('laboratoire/voir.php?id=' . $analyse['id']), 'label' => 'Voir', 'icon' => 'fa-eye', 'class' => 'btn-info']]);
app_module_flash();
?>
<style>
.status-badge { padding: 8px 16px; border-radius: 25px; font-size: 0.9rem; font-weight: 500; }
        .status-en_attente { background: #fff3cd; color: #856404; }
        .status-en_cours { background: #d1ecf1; color: #0c5460; }
        .status-termine { background: #d4edda; color: #155724; }
        .status-annule { background: #f8d7da; color: #721c24; }
        .priorite-normale { background: #6c757d; color: white; }
        .priorite-urgente { background: #ffc107; color: black; }
        .priorite-critique { background: #dc3545; color: white; }
        
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
        
        .action-buttons .btn-outline-info {
            border: 2px solid #0dcaf0;
            color: #0dcaf0;
            background: transparent;
        }
        
        .action-buttons .btn-outline-info:hover {
            background: #0dcaf0;
            color: #fff;
            border-color: #0dcaf0;
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
        
        .action-buttons .btn.w-100 {
            width: 100%;
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

        <!-- Messages d'alerte -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- En-tête de l'analyse -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations de l'Analyse</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Type d'analyse :</strong><br>
                        <span class="badge bg-info"><?php echo htmlspecialchars($typesAnalyses[$analyse['type_analyse']] ?? $analyse['type_analyse']); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Statut actuel :</strong><br>
                        <span class="status-badge status-<?php echo $analyse['statut']; ?>">
                            <?php echo htmlspecialchars($statuts[$analyse['statut']] ?? ucfirst($analyse['statut'])); ?>
                        </span>
                    </div>
                    <div class="col-md-6 mt-3 mt-md-0">
                        <strong>Technicien assigné :</strong><br>
                        <?php if (!empty($analyse['technicien_nom']) || !empty($analyse['technicien_prenom'])): ?>
                            <?= htmlspecialchars(trim(($analyse['technicien_prenom'] ?? '') . ' ' . ($analyse['technicien_nom'] ?? ''))) ?>
                            <?php if (!empty($analyse['technicien_poste'])): ?>
                                <small class="text-muted">(<?= htmlspecialchars($analyse['technicien_poste']) ?>)</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Non assigné</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire de modification -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier l'Analyse</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="patient_id" class="form-label">Patient *</label>
                            <select class="form-select" id="patient_id" name="patient_id" required>
                                <option value="">Choisir un patient...</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>" 
                                            <?php echo $patient['id'] == $analyse['patient_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($patient['nom'] . ' ' . $patient['prenom'] . ' (' . $patient['numero_dossier'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="medecin_id" class="form-label">Médecin *</label>
                            <select class="form-select" id="medecin_id" name="medecin_id" required>
                                <option value="">Choisir un médecin...</option>
                                <?php foreach ($medecins as $medecin): ?>
                                    <option value="<?php echo $medecin['id']; ?>" 
                                            <?php echo $medecin['id'] == $analyse['medecin_id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars(medecin_profil_format_name($medecin)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="type_analyse" class="form-label">Type d'analyse *</label>
                            <select class="form-select" id="type_analyse" name="type_analyse" required>
                                <option value="">Choisir le type...</option>
                                <?php foreach ($typesAnalyses as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo $key === $analyse['type_analyse'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="priorite" class="form-label">Priorité *</label>
                            <select class="form-select" id="priorite" name="priorite" required>
                                <option value="">Choisir la priorité...</option>
                                <?php foreach ($priorites as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo $key === $analyse['priorite'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="technicien_id" class="form-label">Technicien / laborantin assigné</label>
                            <?php if ($canPickTechnicien): ?>
                                <select class="form-select" id="technicien_id" name="technicien_id">
                                    <option value="">— Non assigné —</option>
                                    <?php foreach ($techniciensLab as $tech): ?>
                                        <?php
                                        $techLabel = trim(($tech['prenom'] ?? '') . ' ' . ($tech['nom'] ?? ''));
                                        if (!empty($tech['poste'])) {
                                            $techLabel .= ' — ' . $tech['poste'];
                                        }
                                        $sel = (int) ($analyse['technicien_id'] ?? 0) === (int) $tech['id'];
                                        ?>
                                        <option value="<?= (int) $tech['id'] ?>" <?= $sel ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($techLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control" readonly
                                       value="<?= htmlspecialchars($staffLinkSelf['label'] ?? 'Compte non rattaché') ?>">
                                <input type="hidden" name="technicien_id" id="technicien_id"
                                       value="<?= htmlspecialchars((string) ($staffCtx['personnel_id'] ?? '')) ?>">
                                <small class="text-muted">Mis à jour automatiquement à l'enregistrement ou au démarrage de l'analyse.</small>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="prix_analyse" class="form-label">Prix (FCFA)</label>
                            <input type="number" class="form-control" id="prix_analyse" name="prix_analyse"
                                   value="<?php echo htmlspecialchars($analyse['prix_analyse'] ?? ''); ?>" min="0" step="0.01">
                            <small class="form-text text-muted">Laissez vide pour conserver le prix actuel</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Numéro de ticket</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($analyse['numero_ticket'] ?? 'Non généré'); ?>" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="statut" class="form-label">Statut *</label>
                            <select class="form-select" id="statut" name="statut" required>
                                <option value="">Choisir le statut...</option>
                                <?php foreach ($statuts as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" 
                                            <?php echo $key === $analyse['statut'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="date_analyse" class="form-label">Date de l'analyse</label>
                            <input type="datetime-local" class="form-control" id="date_analyse" name="date_analyse" 
                                   value="<?php echo $analyse['date_analyse'] ? date('Y-m-d\TH:i', strtotime($analyse['date_analyse'])) : ''; ?>">
                            <small class="form-text text-muted">Laissez vide pour utiliser la date actuelle lors du changement de statut</small>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Description détaillée de l'analyse..."><?php echo htmlspecialchars($analyse['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="instructions" class="form-label">Instructions</label>
                            <textarea class="form-control" id="instructions" name="instructions" rows="3" 
                                      placeholder="Instructions pour le laboratoire..."><?php echo htmlspecialchars($analyse['instructions'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="resultats" class="form-label">Résultats</label>
                            <textarea class="form-control" id="resultats" name="resultats" rows="5" 
                                      placeholder="Résultats de l'analyse..."><?php echo htmlspecialchars($analyse['resultats'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">Remplir ce champ pour marquer l'analyse comme terminée</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="voir.php?id=<?php echo $analyse['id']; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions Rapides</h5>
            </div>
            <div class="card-body">
                <div class="action-buttons">
                    <?php if ($analyse['statut'] === 'en_attente'): ?>
                        <a href="modifier.php?id=<?php echo $analyse['id']; ?>&action=commencer" 
                           class="btn btn-info btn-sm">
                            <i class="fas fa-play"></i>Commencer l'analyse
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($analyse['statut'] === 'en_cours'): ?>
                        <a href="modifier.php?id=<?php echo $analyse['id']; ?>&action=terminer" 
                           class="btn btn-success btn-sm">
                            <i class="fas fa-check"></i>Terminer l'analyse
                        </a>
                    <?php endif; ?>
                    
                    <a href="voir.php?id=<?php echo $analyse['id']; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-eye"></i>Voir l'analyse
                    </a>
                    
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i>Retour à la liste
                    </a>
                </div>
            </div>
        </div>
<?php ob_start(); ?>
<script>
        // Mise à jour automatique de la date d'analyse lors du changement de statut
        document.getElementById('statut').addEventListener('change', function() {
            const statut = this.value;
            const dateAnalyseField = document.getElementById('date_analyse');
            
            if (statut === 'en_cours' && !dateAnalyseField.value) {
                const now = new Date();
                const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
                dateAnalyseField.value = localDateTime.toISOString().slice(0, 16);
            }
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
