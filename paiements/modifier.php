<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('paiements'));

require_once '../includes/init.php';
require_once '../includes/currency_helper.php';
require_once '../models/Paiement.php';
require_once '../models/Patient.php';

$paiementModel = new Paiement();
$patientModel = new Patient();

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$paiementId = (int)$_GET['id'];

$paiement = $paiementModel->getById($paiementId);
if (!$paiement) {
    header('Location: index.php');
    exit;
}

$paiementVerrouille = $paiementModel->isEncaisseVerrouille($paiement);
$paiementClos = $paiementModel->isHistoriqueClos($paiement);
$formReadonly = $paiementVerrouille || $paiementClos;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'patient_id' => $_POST['patient_id'] ?? null,
            'consultation_id' => $_POST['consultation_id'] ?? null,
            'numero_facture' => $_POST['numero_facture'] ?? '',
            'montant' => $_POST['montant'] ?? 0,
            'type_paiement' => $_POST['type_paiement'] ?? '',
            'statut' => $_POST['statut'] ?? 'en_attente',
            'description' => $_POST['description'] ?? null,
            'date_paiement' => $_POST['date_paiement'] ?? date('Y-m-d H:i:s'),
            'reference_paiement' => $_POST['reference_paiement'] ?? null,
            'notes' => $_POST['notes'] ?? null,
            'cree_par' => $auth->getUtilisateur()['id']
        ];

        if ($paiementModel->isEncaisseVerrouille($paiement)) {
            $data['patient_id'] = $paiement['patient_id'];
            $data['consultation_id'] = $paiement['consultation_id'] ?? null;
            $data['numero_facture'] = $paiement['numero_facture'];
            $data['montant'] = $paiement['montant'];
            $data['type_paiement'] = $paiement['type_paiement'];
            $data['date_paiement'] = $paiement['date_paiement'];
            $data['reference_paiement'] = $paiement['reference_paiement'] ?? null;
            $data['description'] = $paiement['description'] ?? null;
        }

        if ($paiementModel->update($paiementId, $data)) {
            $message = "Le paiement a été modifié avec succès !";
            $messageType = "success";
            $paiement = $paiementModel->getById($paiementId) ?: $paiement;
            $paiementVerrouille = $paiementModel->isEncaisseVerrouille($paiement);
            $paiementClos = $paiementModel->isHistoriqueClos($paiement);
            $formReadonly = $paiementVerrouille || $paiementClos;
        } else {
            $error = "Erreur lors de la modification du paiement.";
            $messageType = "danger";
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        $messageType = "danger";
    }
}

// Récupérer les listes pour les formulaires
try {
    $patients = $patientModel->getAll(1, 1000);
} catch (Exception $e) {
    $patients = [];
    error_log("Erreur récupération patients: " . $e->getMessage());
}

try {
    // Vérifier si la méthode existe, sinon utiliser une requête directe
    if (method_exists($paiementModel, 'getConsultationsDisponibles')) {
        $consultations = $paiementModel->getConsultationsDisponibles(100);
    } else {
        require_once '../config/db.php';
        require_once '../includes/saas/TenantScope.php';
        $pdo = getDB();
        $where = ['1 = 1'];
        $params = [];
        TenantScope::appendWhere($pdo, 'consultations', $where, $params, 'c');
        $sql = "SELECT c.id, c.date_consultation, c.diagnostic, c.symptomes,
                       p.nom as patient_nom, p.prenom as patient_prenom, p.numero_dossier,
                       m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite
                FROM consultations c
                LEFT JOIN patients p ON c.patient_id = p.id
                LEFT JOIN medecins m ON c.medecin_id = m.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.date_consultation DESC
                LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $consultations = [];
    error_log("Erreur récupération consultations: " . $e->getMessage());
}

try {
    $typesPaiement = $paiementModel->getTypesPaiement();
} catch (Exception $e) {
    $typesPaiement = [];
    error_log("Erreur récupération types paiement: " . $e->getMessage());
}

try {
    $statuts = $paiementModel->getStatuts();
} catch (Exception $e) {
    $statuts = [];
    error_log("Erreur récupération statuts: " . $e->getMessage());
}

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

app_module_page_start([
    'active'   => 'paiements',
    'title'    => 'Modifier le Paiement',
    'subtitle' => 'Mise à jour du paiement',
    'icon'     => 'fa-credit-card',
]);
app_module_back_toolbar(app_url('paiements/index.php'), 'Retour à la liste', [['href' => app_url('paiements/voir.php?id=' . $paiement['id']), 'label' => 'Voir', 'icon' => 'fa-eye', 'class' => 'btn-info']]);
app_module_flash();
?>
        <!-- En-tête -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-edit me-2 text-warning"></i>Modifier le Paiement</h3>
            <div>
                <a href="voir.php?id=<?php echo $paiement['id']; ?>" class="btn btn-outline-info btn-sm me-2">
                    <i class="fas fa-eye me-1"></i>Voir
                </a>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Retour
                </a>
            </div>
        </div>

        <!-- Messages d'alerte -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($paiementClos): ?>
            <div class="alert alert-secondary">
                <i class="fas fa-lock me-2"></i>Ce paiement est clos. Aucune modification n'est autorisée.
            </div>
        <?php elseif ($paiementVerrouille): ?>
            <div class="alert alert-warning">
                <i class="fas fa-lock me-2"></i>Paiement encaissé verrouillé. Seul le passage à <strong>Annulé</strong> ou <strong>Remboursé</strong> est possible (contre-passation comptable automatique).
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Modification du Paiement</h5>
            </div>
            <div class="card-body">
                <?php if ($paiementClos): ?>
                <p class="text-muted mb-0"><a href="voir.php?id=<?php echo (int) $paiement['id']; ?>">Retour aux détails</a></p>
                <?php else: ?>
                <form method="POST" class="row g-3">
                    <!-- Informations du patient -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Patient</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="patient_id" class="form-label">Patient *</label>
                        <select class="form-select" id="patient_id" name="patient_id" required<?php echo $paiementVerrouille ? ' disabled' : ''; ?>>
                            <option value="">Choisir un patient...</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" 
                                        <?php echo ($paiement['patient_id'] == $patient['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['nom'] . ' ' . $patient['prenom'] . ' (' . $patient['numero_dossier'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="consultation_id" class="form-label">Consultation (optionnel)</label>
                        <select class="form-select" id="consultation_id" name="consultation_id"<?php echo $paiementVerrouille ? ' disabled' : ''; ?>>
                            <option value="">Aucune consultation...</option>
                            <?php if (!empty($consultations)): ?>
                                <?php foreach ($consultations as $consultation): ?>
                                    <?php 
                                    $consultationText = 'Consultation #' . $consultation['id'] . ' - ';
                                    if (!empty($consultation['patient_prenom']) && !empty($consultation['patient_nom'])) {
                                        $consultationText .= htmlspecialchars($consultation['patient_prenom'] . ' ' . $consultation['patient_nom']);
                                        if (!empty($consultation['numero_dossier'])) {
                                            $consultationText .= ' (' . $consultation['numero_dossier'] . ')';
                                        }
                                    } else {
                                        $consultationText .= 'Patient non spécifié';
                                    }
                                    if (!empty($consultation['diagnostic'])) {
                                        $consultationText .= ' - ' . htmlspecialchars(substr($consultation['diagnostic'], 0, 50));
                                        if (strlen($consultation['diagnostic']) > 50) $consultationText .= '...';
                                    }
                                    if (!empty($consultation['date_consultation'])) {
                                        $consultationText .= ' - ' . date('d/m/Y H:i', strtotime($consultation['date_consultation']));
                                    }
                                    ?>
                                    <option value="<?php echo $consultation['id']; ?>" 
                                            <?php echo ($paiement['consultation_id'] == $consultation['id']) ? 'selected' : ''; ?>>
                                        <?php echo $consultationText; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>Aucune consultation disponible</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Informations du paiement -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-credit-card me-2"></i>Informations du Paiement</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="numero_facture" class="form-label">Numéro de facture *</label>
                        <input type="text" class="form-control" id="numero_facture" name="numero_facture" 
                               value="<?php echo htmlspecialchars($paiement['numero_facture']); ?>" required<?php echo $paiementVerrouille ? ' readonly' : ''; ?>>
                    </div>

                    <div class="col-md-6">
                        <label for="montant" class="form-label">Montant (FCFA) *</label>
                        <input type="number" class="form-control" id="montant" name="montant" 
                               value="<?php echo $paiement['montant']; ?>" step="0.01" min="0" required<?php echo $paiementVerrouille ? ' readonly' : ''; ?>>
                    </div>

                    <div class="col-md-6">
                        <label for="type_paiement" class="form-label">Type de paiement *</label>
                        <select class="form-select" id="type_paiement" name="type_paiement" required<?php echo $paiementVerrouille ? ' disabled' : ''; ?>>
                            <option value="">Sélectionner...</option>
                            <?php foreach ($typesPaiement as $key => $label): ?>
                                <option value="<?php echo $key; ?>" 
                                        <?php echo ($paiement['type_paiement'] === $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <?php foreach ($statuts as $key => $label): ?>
                                <?php if ($paiementVerrouille && !in_array($key, ['paye', 'annule', 'rembourse'], true)) continue; ?>
                                <option value="<?php echo $key; ?>" 
                                        <?php echo ($paiement['statut'] === $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="date_paiement" class="form-label">Date de paiement *</label>
                        <input type="datetime-local" class="form-control" id="date_paiement" name="date_paiement" 
                               value="<?php echo date('Y-m-d\TH:i', strtotime($paiement['date_paiement'])); ?>" required<?php echo $paiementVerrouille ? ' readonly' : ''; ?>>
                    </div>

                    <div class="col-md-6">
                        <label for="reference_paiement" class="form-label">Référence</label>
                        <input type="text" class="form-control" id="reference_paiement" name="reference_paiement" 
                               value="<?php echo htmlspecialchars($paiement['reference_paiement'] ?? ''); ?>" placeholder="Référence du paiement">
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Description du paiement"><?php echo htmlspecialchars($paiement['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Notes additionnelles"><?php echo htmlspecialchars($paiement['notes'] ?? ''); ?></textarea>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="col-12">
                        <hr>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save me-1"></i>Enregistrer les modifications
                            </button>
                            <a href="voir.php?id=<?php echo $paiement['id']; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Annuler
                            </a>
                            <a href="index.php" class="btn btn-outline-info">
                                <i class="fas fa-list me-1"></i>Liste des Paiements
                            </a>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
<?php app_module_page_end(); ?>
