<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('consultations'));

require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/SoinsConsultation.php';

$consultationModel = new Consultation();
$soinsModel = new SoinsConsultation();

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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_soin_consultation':
                case 'add_soin_to_consultation':
                    $soin_id = (int) ($_POST['soin_id'] ?? 0);
                    $quantite = (int) ($_POST['quantite'] ?? 1) ?: 1;
                    $notes = $_POST['notes'] ?? null;
                    $soin_details = $soinsModel->getById($soin_id);
                    if ($soin_details) {
                        $prix_unitaire = (float) $soin_details['prix'];
                        if ($consultationModel->addConsultationSoinItem($id, $soin_id, $quantite, $prix_unitaire, $notes)) {
                            $message = "Soin ajouté à la consultation avec succès !";
                            $consultationModel->saveTicket($id);
                        } else {
                            $error = "Impossible d'ajouter le soin.";
                        }
                    } else {
                        $error = "Soin non trouvé.";
                    }
                    break;

                case 'remove_soin_consultation':
                    $soin_consultation_id = (int) ($_POST['soin_consultation_id'] ?? 0);
                    if ($consultationModel->removeConsultationSoinItem($soin_consultation_id, $id)) {
                        $message = "Soin retiré de la consultation avec succès !";
                        $consultationModel->saveTicket($id);
                    } else {
                        $error = "Impossible de retirer le soin.";
                    }
                    break;

                case 'update_soin_in_consultation':
                    $soin_consultation_id = (int) ($_POST['soin_consultation_id'] ?? 0);
                    $soin_id = (int) ($_POST['soin_id'] ?? 0);
                    $quantite = (int) ($_POST['quantite'] ?? 1) ?: 1;
                    $notes = $_POST['notes'] ?? null;
                    $soin_details = $soinsModel->getById($soin_id);
                    if ($soin_details) {
                        $prix_unitaire = (float) $soin_details['prix'];
                        if ($consultationModel->updateConsultationSoinItem($soin_consultation_id, $id, $soin_id, $quantite, $prix_unitaire, $notes)) {
                            $message = "Soin modifié avec succès !";
                            $consultationModel->saveTicket($id);
                        } else {
                            $error = "Impossible de modifier le soin.";
                        }
                    } else {
                        $error = "Soin non trouvé.";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

$soins = $soinsModel->getAll('actif');
$soins_consultation = $consultationModel->getConsultationSoins($id);

app_module_page_start([
    'active'   => 'consultations',
    'title'    => 'Soins de la Consultation',
    'subtitle' => 'Gestion des soins',
    'icon'     => 'fa-stethoscope',
]);
app_module_back_toolbar(app_url('consultations/voir.php?id=' . $consultation['id']), 'Retour à la consultation', []);
app_module_flash();
?>
<style>
.soin-card {
            transition: all 0.3s ease;
            border-left: 4px solid #17a2b8;
        }
        .soin-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .type-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
        }
        .prix-total {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }
        .auto-update-notice {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
</style>
            <a href="voir.php?id=<?php echo $consultation['id']; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Retour à la consultation
            </a>
        </div>

        <div class="auto-update-notice">
            <i class="fas fa-sync-alt me-2"></i>
            <strong>Mise à jour automatique :</strong> Toutes les modifications sont automatiquement appliquées au ticket de consultation et au dossier du patient.
        </div>

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

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Soins Administrés</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($soins_consultation)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-stethoscope fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucun soin administré pour cette consultation.</p>
                                <p class="text-muted">Utilisez le formulaire à droite pour ajouter des soins.</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            $total_soins = 0;
                            foreach ($soins_consultation as $soin_consultation): 
                                $total_soins += $soin_consultation['prix_total'];
                            ?>
                                <div class="soin-card card mb-3">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($soin_consultation['nom']); ?></h6>
                                                <div class="mb-2">
                                                    <span class="badge bg-info type-badge">
                                                        <?php echo ucfirst(str_replace('_', ' ', $soin_consultation['type_soin'])); ?>
                                                    </span>
                                                    <span class="badge bg-secondary">Qté: <?php echo $soin_consultation['quantite']; ?></span>
                                                </div>
                                                <?php if (!empty($soin_consultation['notes'])): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-comment me-1"></i><?php echo htmlspecialchars($soin_consultation['notes']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <div class="prix-total">
                                                    <?= htmlspecialchars(formatMoney($soin_consultation['prix_total'])) ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <button class="btn btn-sm btn-outline-primary me-2" 
                                                        onclick="editSoinFromData(<?php echo $soin_consultation['id']; ?>, <?php echo $soin_consultation['soin_id']; ?>, <?php echo $soin_consultation['quantite']; ?>, '<?php echo htmlspecialchars($soin_consultation['notes'] ?? ''); ?>')"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Retirer ce soin de la consultation ?')">
                                                    <input type="hidden" name="action" value="remove_soin_consultation">
                                                    <input type="hidden" name="soin_consultation_id" value="<?php echo $soin_consultation['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="mb-0">
                                        <strong>Total des soins : 
                                            <span class="text-success"><?= htmlspecialchars(formatMoney($total_soins)) ?></span>
                                        </strong>
                                    </h5>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Ajouter un Soin</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_soin_consultation">
                            
                            <div class="mb-3">
                                <label for="soin_id" class="form-label">Sélectionner un soin *</label>
                                <select class="form-select" id="soin_id" name="soin_id" required>
                                    <option value="">Choisir un soin...</option>
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
                            
                            <div class="mb-3">
                                <label for="quantite" class="form-label">Quantité</label>
                                <input type="number" class="form-control" id="quantite" name="quantite" 
                                       min="1" value="1" onchange="updatePrix()">
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="Observations, particularités..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="alert alert-info">
                                    <strong>Prix unitaire :</strong> <span id="prix-unitaire"><?= htmlspecialchars(formatMoney(0)) ?></span><br>
                                    <strong>Total :</strong> <span id="prix-total" class="text-success"><?= htmlspecialchars(formatMoney(0)) ?></span>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Ajouter le soin
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations</h6>
                    </div>
                    <div class="card-body">
                        <small class="text-muted">
                            <strong>Consultation :</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($consultation['date_consultation'])); ?><br><br>
                            
                            <strong>Patient :</strong><br>
                            <?php echo htmlspecialchars($consultation['patient_prenom'] . ' ' . $consultation['patient_nom']); ?><br>
                            Dossier: P<?php echo str_pad($consultation['patient_id'], 6, '0', STR_PAD_LEFT); ?><br><br>
                            
                            <strong><?= htmlspecialchars(medecin_profil_attribution_label_from_row($consultation)) ?> :</strong><br>
                            <?php echo htmlspecialchars(medecin_profil_format_joined($consultation)); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editSoinModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier un soin</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="update_soin_in_consultation">
                            <input type="hidden" name="soin_consultation_id" id="edit_soin_consultation_id">
                            <div class="mb-3">
                                <label for="edit_soin_id" class="form-label">Soin</label>
                                <select class="form-select" id="edit_soin_id" name="soin_id" required>
                                    <option value="">Sélectionner un soin...</option>
                                    <?php foreach ($soins as $soin): ?>
                                        <option value="<?php echo $soin['id']; ?>" 
                                                data-prix="<?php echo number_format($soin['prix'], 2, '.', ''); ?>">
                                            <?php echo htmlspecialchars($soin['nom']); ?> - 
                                            <?= htmlspecialchars(formatMoney($soin['prix'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="edit_quantite" class="form-label">Quantité</label>
                                <input type="number" class="form-control" id="edit_quantite" name="quantite" 
                                       min="1" value="1" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Mettre à jour</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="voir.php?id=<?php echo $consultation['id']; ?>" class="btn btn-success btn-lg me-3">
                    <i class="fas fa-check me-2"></i>Terminer et retourner à la consultation
                </a>
                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Retour à la liste
                </a>
            </div>
        </div>
<?php ob_start(); ?>
<script>
        function updatePrix() {
            const soinSelect = document.getElementById('soin_id');
            const quantiteInput = document.getElementById('quantite');
            const prixUnitaireSpan = document.getElementById('prix-unitaire');
            const prixTotalSpan = document.getElementById('prix-total');
            
            if (soinSelect.selectedOptions.length > 0 && soinSelect.value) {
                const selectedOption = soinSelect.selectedOptions[0];
                const prixStr = selectedOption.dataset.prix;
                const prix = parseFloat(prixStr);
                const quantite = parseInt(quantiteInput.value) || 1;
                const total = prix * quantite;
                
                if (!isNaN(prix) && !isNaN(total) && prix > 0) {
                    prixUnitaireSpan.textContent = typeof appFormatMoney === 'function' ? appFormatMoney(prix) : new Intl.NumberFormat('fr-FR').format(prix) + ' FCFA';
                    prixTotalSpan.textContent = typeof appFormatMoney === 'function' ? appFormatMoney(total) : new Intl.NumberFormat('fr-FR').format(total) + ' FCFA';
                } else {
                    prixUnitaireSpan.textContent = typeof appFormatMoney === 'function' ? appFormatMoney(0) : '0 FCFA';
                    prixTotalSpan.textContent = typeof appFormatMoney === 'function' ? appFormatMoney(0) : '0 FCFA';
                }
            } else {
                prixUnitaireSpan.textContent = typeof appFormatMoney === 'function' ? appFormatMoney(0) : '0 FCFA';
                prixTotalSpan.textContent = typeof appFormatMoney === 'function' ? appFormatMoney(0) : '0 FCFA';
            }
        }
        
        function editSoinFromData(id, soin_id, quantite, notes) {
            document.getElementById('edit_soin_consultation_id').value = id;
            document.getElementById('edit_quantite').value = quantite;
            document.getElementById('edit_notes').value = notes;
            document.getElementById('edit_soin_id').value = soin_id;
            const modal = new bootstrap.Modal(document.getElementById('editSoinModal'));
            modal.show();
        }
        
        document.getElementById('soin_id').addEventListener('change', updatePrix);
        document.getElementById('quantite').addEventListener('input', updatePrix);
        updatePrix();
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
