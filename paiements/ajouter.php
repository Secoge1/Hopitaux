<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('paiements'));

// Inclure la configuration principale et les modèles
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Paiement.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/Analyse.php';

$paiementModel = new Paiement();
$patientModel = new Patient();
$consultationModel = new Consultation();
$analyseModel = new Analyse();

$message = '';
$error = '';
$form_success = false;

// Récupérer les listes pour les formulaires
$patients = $patientModel->getAll(1, 1000);
$typesPaiement = $paiementModel->getTypesPaiement();
$statuts = $paiementModel->getStatuts();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $patient_id_post = (int) ($_POST['patient_id'] ?? 0);
        if ($patient_id_post < 1 || !$patientModel->getById($patient_id_post)) {
            throw new Exception('Patient invalide ou inaccessible pour cet établissement.');
        }

        $consultation_id_post = !empty($_POST['consultation_id']) ? (int) $_POST['consultation_id'] : null;
        if ($consultation_id_post) {
            if (!$consultationModel->getByIdForPatient($consultation_id_post, $patient_id_post)) {
                throw new Exception('La consultation sélectionnée n\'appartient pas à ce patient.');
            }
        }

        $analyse_id_post = !empty($_POST['analyse_id']) ? (int) $_POST['analyse_id'] : null;
        if ($analyse_id_post) {
            $analyseRow = $analyseModel->getById($analyse_id_post);
            if (!$analyseRow || (int) ($analyseRow['patient_id'] ?? 0) !== $patient_id_post) {
                throw new Exception('L\'analyse sélectionnée n\'appartient pas à ce patient.');
            }
        }

        $data = [
            'patient_id' => $patient_id_post,
            'consultation_id' => $consultation_id_post,
            'analyse_id' => $analyse_id_post,
            'numero_facture' => $_POST['numero_facture'],
            'montant' => $_POST['montant'],
            'type_paiement' => $_POST['type_paiement'],
            'statut' => $_POST['statut'],
            'description' => $_POST['description'] ?? null,
            'date_paiement' => $_POST['date_paiement'] ?? date('Y-m-d H:i:s'),
            'reference_paiement' => $_POST['reference_paiement'] ?? null,
            'notes' => $_POST['notes'] ?? null,
            'cree_par' => $auth->getUtilisateur()['id']
        ];

        if ($paiementModel->create($data)) {
            $message = "Paiement créé avec succès !";
            $form_success = true;
        } else {
            $error = "Erreur lors de la création du paiement.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Valeurs du formulaire : après succès, nouvelle saisie avec numéros régénérés
if ($form_success) {
    $patient_id = '';
    $consultation_id = '';
    $analyse_id = '';
    $numero_facture = $paiementModel->generateNumeroFacture();
    $montant = '';
    $type_paiement = 'especes';
    $statut = 'en_attente';
    $description = '';
    $date_paiement = date('Y-m-d H:i');
    $reference_paiement = $paiementModel->generateReferencePaiement();
    $notes = '';
} else {
    $patient_id = $_POST['patient_id'] ?? (isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : '');
    $consultation_id = $_POST['consultation_id'] ?? '';
    $analyse_id = $_POST['analyse_id'] ?? '';
    $numero_facture = $_POST['numero_facture'] ?? $paiementModel->generateNumeroFacture();
    $montant = $_POST['montant'] ?? '';
    $type_paiement = $_POST['type_paiement'] ?? 'especes';
    $statut = $_POST['statut'] ?? 'en_attente';
    $description = $_POST['description'] ?? '';
    $date_paiement = $_POST['date_paiement'] ?? date('Y-m-d H:i');
    $refPost = isset($_POST['reference_paiement']) ? trim((string) $_POST['reference_paiement']) : '';
    $reference_paiement = $refPost !== '' ? $refPost : $paiementModel->generateReferencePaiement();
    $notes = $_POST['notes'] ?? '';
}

// Pré-remplissage depuis une consultation ou analyse (fonctionnalité activée par l'admin plateforme)
if (
    !$form_success
    && $_SERVER['REQUEST_METHOD'] !== 'POST'
    && function_exists('payment_finance_sync_enabled')
    && payment_finance_sync_enabled()
) {
    if (!empty($_GET['consultation_id'])) {
        $prefillConsultationId = (int) $_GET['consultation_id'];
        $prefillConsultation = $consultationModel->getById($prefillConsultationId);
        if ($prefillConsultation) {
            $consultation_id = $prefillConsultationId;
            if (empty($patient_id)) {
                $patient_id = (int) $prefillConsultation['patient_id'];
            }
            $montantCalcule = (float) $consultationModel->getPrixTotalComplet($prefillConsultationId);
            if ($montantCalcule > 0) {
                $montant = $montantCalcule;
            }
            if ($description === '') {
                $ticket = $prefillConsultation['numero_ticket'] ?? ('#' . $prefillConsultationId);
                $description = 'Paiement consultation ' . $ticket;
            }
        }
    } elseif (!empty($_GET['analyse_id'])) {
        $prefillAnalyseId = (int) $_GET['analyse_id'];
        $prefillAnalyse = $analyseModel->getById($prefillAnalyseId);
        if ($prefillAnalyse) {
            $analyse_id = $prefillAnalyseId;
            if (empty($patient_id)) {
                $patient_id = (int) $prefillAnalyse['patient_id'];
            }
            $prixAnalyse = (float) ($prefillAnalyse['prix_analyse'] ?? 0);
            if ($prixAnalyse > 0) {
                $montant = $prixAnalyse;
            }
            if ($description === '') {
                $ticket = $prefillAnalyse['numero_ticket'] ?? ('#' . $prefillAnalyseId);
                $type = $prefillAnalyse['type_analyse'] ?? 'analyse';
                $description = 'Paiement analyse ' . $type . ' ' . $ticket;
            }
        }
    }
}

app_module_page_start([
    'active'   => 'paiements',
    'title'    => 'Nouveau Paiement',
    'subtitle' => 'Enregistrement d\'un paiement',
    'icon'     => 'fa-credit-card',
]);
app_module_back_toolbar(app_url('paiements/index.php'), 'Retour à la liste', []);
app_module_flash();
?>
<style>
:root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }
        
        .card {
            border-radius: 16px;
            border: none;
            box-shadow: var(--card-shadow);
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        #consultation-info .card {
            border-left: 4px solid #0dcaf0;
        }
        
        .consultation-picker-wrap {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: #fafbfc;
            padding: 0.75rem;
        }
        
        .consultation-picker {
            max-height: 320px;
            overflow-y: auto;
        }
        
        .consultation-card-btn {
            width: 100%;
            text-align: left;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            background: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        
        .consultation-card-btn:last-child {
            margin-bottom: 0;
        }
        
        .consultation-card-btn:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.12);
        }
        
        .consultation-card-btn.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.06) 100%);
            box-shadow: inset 0 0 0 1px rgba(102, 126, 234, 0.25);
        }
        
        .consultation-card-btn.is-paid {
            opacity: 0.92;
        }
        
        .consultation-card-btn.filtered-out {
            display: none !important;
        }
</style>
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Enregistrement de Paiement</h5>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" class="row g-3">
                    <!-- Informations du patient -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Informations du Patient</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="patient_id" class="form-label">Patient *</label>
                        <select class="form-select" id="patient_id" name="patient_id" required>
                            <option value="">Sélectionner un patient...</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" <?php echo $patient_id == $patient['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['nom'] . ' ' . $patient['prenom'] . ' (' . $patient['numero_dossier'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Consultation liée
                            <small class="text-muted">(optionnel)</small>
                        </label>
                        <input type="hidden" name="consultation_id" id="consultation_id" value="<?php echo htmlspecialchars((string) $consultation_id); ?>">
                        <?php if (!empty($analyse_id)): ?>
                        <input type="hidden" name="analyse_id" id="analyse_id" value="<?php echo (int) $analyse_id; ?>">
                        <?php endif; ?>
                        
                        <div id="consultations-placeholder" class="text-muted small border rounded-3 p-3 bg-light">
                            <i class="fas fa-user-md me-2"></i>
                            Choisissez d’abord un patient pour afficher ses consultations (les plus récentes en premier).
                        </div>
                        
                        <div id="consultations-loader" class="d-none alert alert-light border mb-2 py-2">
                            <span class="spinner-border spinner-border-sm text-primary me-2" role="status"></span>
                            Chargement des consultations…
                        </div>
                        
                        <div id="consultations-empty" class="d-none alert alert-warning py-2 mb-2 small mb-0">
                            <i class="fas fa-inbox me-1"></i> Aucune consultation enregistrée pour ce patient.
                        </div>
                        
                        <div id="consultations-ui" class="d-none">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                <div class="input-group input-group-sm flex-grow-1" style="min-width: 200px;">
                                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                    <input type="search" class="form-control" id="consultation-filter" placeholder="Filtrer (date, n°, médecin, diagnostic…)" autocomplete="off">
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="consultation-clear" title="Ne lier aucune consultation">
                                    <i class="fas fa-unlink me-1"></i>Aucune
                                </button>
                            </div>
                            <div class="consultation-picker-wrap">
                                <div class="consultation-picker" id="consultations-list" role="list"></div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Cliquez sur une ligne pour la lier au paiement. Les consultations déjà associées à un paiement sont indiquées.
                            </small>
                        </div>
                        
                        <div id="consultation-info" class="mt-2" style="display: none;">
                            <div class="card border-info">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-calendar-check text-info me-2"></i>
                                                <span id="consultation-date"></span>
                                            </h6>
                                            <small class="text-muted" id="consultation-ticket"></small>
                                        </div>
                                        <span id="consultation-badge" class="badge"></span>
                                    </div>
                                    <div id="consultation-details" class="mt-2"></div>
                                    <div id="consultation-montant" class="mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Détails du paiement -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-money-bill me-2"></i>Détails du Paiement</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="montant" class="form-label">Montant (FCFA) *</label>
                        <div class="input-group">
                            <span class="input-group-text">FCFA</span>
                            <input type="number" class="form-control" id="montant" name="montant" 
                                   step="1" min="0" placeholder="0" value="<?php echo htmlspecialchars($montant); ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="type_paiement" class="form-label">Type de paiement *</label>
                        <select class="form-select" id="type_paiement" name="type_paiement" required>
                            <option value="">Choisir le type...</option>
                            <?php foreach ($typesPaiement as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $type_paiement === $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="">Choisir le statut...</option>
                            <?php foreach ($statuts as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $statut === $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="numero_facture" class="form-label">Numéro de facture *</label>
                        <input type="text" class="form-control" id="numero_facture" name="numero_facture" 
                               value="<?php echo htmlspecialchars($numero_facture); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="date_paiement" class="form-label">Date de paiement *</label>
                        <input type="datetime-local" class="form-control" id="date_paiement" name="date_paiement" 
                               value="<?php echo $date_paiement; ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="reference_paiement" class="form-label">Référence de paiement</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light" title="Générée automatiquement"><i class="fas fa-barcode text-muted"></i></span>
                            <input type="text" class="form-control font-monospace" id="reference_paiement" name="reference_paiement" 
                                   value="<?php echo htmlspecialchars($reference_paiement); ?>" 
                                   placeholder="REF… (généré si vide)">
                        </div>
                        <small class="text-muted">Générée automatiquement au format <code>REF</code> + date + numéro. Vous pouvez la modifier si besoin (ex. n° de chèque, transaction).</small>
                    </div>

                    <!-- Informations complémentaires -->
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Détails du paiement..."><?php echo htmlspecialchars($description); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  placeholder="Informations complémentaires sur le paiement..."><?php echo htmlspecialchars($notes); ?></textarea>
                    </div>

                    <!-- Boutons -->
                    <div class="col-12">
                        <button type="submit" class="btn btn-dark">
                            <i class="fas fa-save me-2"></i>Enregistrer le paiement
                        </button>
                        <a href="index.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Informations sur le module -->
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>À propos du module Paiements</h6>
            </div>
            <div class="card-body">
                <p>Le module Paiements permettra de :</p>
                <ul>
                    <li>Gérer les factures et devis</li>
                    <li>Suivre les paiements et échéances</li>
                    <li>Intégrer avec les modules Patients et Consultations</li>
                    <li>Générer des rapports comptables</li>
                    <li>Gérer les remboursements et mutuelles</li>
                    <li>Exporter les données pour la comptabilité</li>
                </ul>
                <p class="mb-0"><strong>Statut :</strong> <span class="badge bg-success">Fonctionnel</span></p>
                
                <!-- Configuration de la devise -->
                <div class="mt-3 p-3 bg-light rounded">
                    <h6><i class="fas fa-coins me-2"></i>Configuration de la Devise</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <strong>Devise :</strong> <?php echo CURRENCY_NAME; ?> (<?php echo CURRENCY_CODE; ?>)<br>
                                <strong>Symbole :</strong> <?php echo CURRENCY_SYMBOL; ?><br>
                                <strong>Décimales :</strong> <?php echo CURRENCY_DECIMALS; ?>
                            </small>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">
                                <strong>Exemples :</strong><br>
                                <?php echo formatFCFA(1000); ?><br>
                                <?php echo formatFCFA(50000); ?><br>
                                <?php echo formatFCFA(100000); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Types de paiements supportés -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-credit-card me-2"></i>Types de Paiements Supportés</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="text-center p-3 border rounded">
                            <i class="fas fa-credit-card fa-2x text-primary mb-2"></i>
                            <h6>Carte bancaire</h6>
                            <small class="text-muted">Visa, Mastercard, American Express</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-center p-3 border rounded">
                            <i class="fas fa-university fa-2x text-success mb-2"></i>
                            <h6>Virement bancaire</h6>
                            <small class="text-muted">Transfert SEPA, IBAN</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-center p-3 border rounded">
                            <i class="fas fa-money-bill fa-2x text-warning mb-2"></i>
                            <h6>Espèces/Chèque</h6>
                            <small class="text-muted">Paiement en cabinet</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php ob_start(); ?>
<script>
        document.addEventListener('DOMContentLoaded', function() {
            const patientSelect = document.getElementById('patient_id');
            const consultationIdInput = document.getElementById('consultation_id');
            const consultationInfo = document.getElementById('consultation-info');
            const consultationDate = document.getElementById('consultation-date');
            const consultationDetails = document.getElementById('consultation-details');
            const consultationTicket = document.getElementById('consultation-ticket');
            const consultationBadge = document.getElementById('consultation-badge');
            const consultationMontant = document.getElementById('consultation-montant');
            const placeholderEl = document.getElementById('consultations-placeholder');
            const loaderEl = document.getElementById('consultations-loader');
            const emptyEl = document.getElementById('consultations-empty');
            const uiEl = document.getElementById('consultations-ui');
            const listEl = document.getElementById('consultations-list');
            const filterInput = document.getElementById('consultation-filter');
            const clearBtn = document.getElementById('consultation-clear');
            const placeholderDefaultHtml = placeholderEl.innerHTML;
            
            let consultationsData = [];
            
            function escapeHtml(s) {
                const d = document.createElement('div');
                d.textContent = s;
                return d.innerHTML;
            }
            
            function setConsultationSelection(id, scrollIntoView) {
                const sid = id ? String(id) : '';
                consultationIdInput.value = sid;
                listEl.querySelectorAll('.consultation-card-btn').forEach(function(btn) {
                    const match = btn.getAttribute('data-cid') === sid;
                    btn.classList.toggle('selected', match && sid !== '');
                });
                updateConsultationInfo();
                if (scrollIntoView && sid) {
                    const sel = listEl.querySelector('.consultation-card-btn.selected');
                    if (sel) sel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            }
            
            function applyFilter() {
                const q = (filterInput.value || '').toLowerCase().trim();
                listEl.querySelectorAll('.consultation-card-btn').forEach(function(btn) {
                    const hay = (btn.getAttribute('data-search') || '').toLowerCase();
                    btn.classList.toggle('filtered-out', q !== '' && hay.indexOf(q) === -1);
                });
            }
            
            function renderConsultationCards() {
                listEl.innerHTML = '';
                consultationsData.forEach(function(c) {
                    const med = (c.medecin || '').trim();
                    const spec = (c.specialite || '').trim();
                    const ticket = (c.numero_ticket || '').trim();
                    const typeC = (c.type_consultation || '').trim();
                    const diag = c.diagnostic_short || c.diagnostic || '';
                    const montant = Number(c.montant) || 0;
                    const paid = !!c.deja_payee;
                    
                    const searchParts = [
                        String(c.id),
                        c.date || '',
                        c.date_court || '',
                        ticket,
                        med,
                        spec,
                        diag,
                        typeC,
                        paid ? 'payée' : 'non'
                    ];
                    
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'consultation-card-btn' + (paid ? ' is-paid' : '');
                    btn.setAttribute('data-cid', String(c.id));
                    btn.setAttribute('data-search', searchParts.join(' '));
                    btn.setAttribute('role', 'listitem');
                    
                    const typeLabel = typeC ? typeC.replace(/_/g, ' ') : '—';
                    let heureStr = '';
                    if (c.date && c.date.indexOf('à') !== -1) {
                        const parts = c.date.split('à');
                        if (parts.length > 1) {
                            heureStr = parts[parts.length - 1].trim();
                        }
                    }
                    
                    btn.innerHTML =
                        '<div class="d-flex justify-content-between align-items-start gap-2">' +
                            '<div class="min-w-0 flex-grow-1">' +
                                '<div class="d-flex flex-wrap align-items-center gap-1 mb-1">' +
                                    '<span class="badge bg-primary">#' + c.id + '</span>' +
                                    '<strong class="text-dark">' + escapeHtml(c.date_court || '') + '</strong>' +
                                    (heureStr ? '<span class="text-muted small">· ' + escapeHtml(heureStr) + '</span>' : '') +
                                '</div>' +
                                '<div class="small text-muted mb-1">' +
                                    (ticket ? '<i class="fas fa-ticket-alt me-1"></i>' + escapeHtml(ticket) + ' · ' : '') +
                                    '<i class="fas fa-stethoscope me-1"></i>' + escapeHtml(typeLabel) +
                                '</div>' +
                                '<div class="small text-break">' + escapeHtml(diag) + '</div>' +
                                (med ? '<div class="small mt-1"><i class="fas fa-user-md text-primary me-1"></i><span class="fw-semibold">' + escapeHtml(med) + '</span>' +
                                    (spec ? ' <span class="text-muted">(' + escapeHtml(spec) + ')</span>' : '') +
                                '</div>' : '') +
                            '</div>' +
                            '<div class="text-end flex-shrink-0">' +
                                (paid ? '<span class="badge bg-warning text-dark mb-1 d-block">Déjà payée</span>' : '<span class="badge bg-success mb-1 d-block">À payer</span>') +
                                (montant > 0
                                    ? '<div class="fw-bold text-primary small text-nowrap">' + montant.toLocaleString('fr-FR') + ' FCFA</div>'
                                    : '<div class="text-muted small">Montant N/A</div>') +
                            '</div>' +
                        '</div>';
                    
                    btn.addEventListener('click', function() {
                        setConsultationSelection(c.id, false);
                    });
                    
                    listEl.appendChild(btn);
                });
            }
            
            function showConsultationUiState(state) {
                placeholderEl.classList.toggle('d-none', state !== 'placeholder');
                loaderEl.classList.toggle('d-none', state !== 'loading');
                emptyEl.classList.toggle('d-none', state !== 'empty');
                uiEl.classList.toggle('d-none', state !== 'list');
            }
            
            patientSelect.addEventListener('change', function() {
                const patientId = this.value;
                consultationInfo.style.display = 'none';
                consultationsData = [];
                consultationIdInput.value = '';
                filterInput.value = '';
                listEl.innerHTML = '';
                
                if (!patientId) {
                    placeholderEl.innerHTML = placeholderDefaultHtml;
                    showConsultationUiState('placeholder');
                    return;
                }
                
                placeholderEl.innerHTML = placeholderDefaultHtml;
                showConsultationUiState('loading');
                
                fetch('api_consultations.php?patient_id=' + encodeURIComponent(patientId))
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.error) {
                            showConsultationUiState('placeholder');
                            placeholderEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2 text-warning"></i>' + escapeHtml(data.error);
                            return;
                        }
                        
                        if (data.success && data.consultations && data.consultations.length > 0) {
                            consultationsData = data.consultations;
                            renderConsultationCards();
                            showConsultationUiState('list');
                            
                            <?php if ($consultation_id): ?>
                            setConsultationSelection('<?php echo (int) $consultation_id; ?>', true);
                            <?php endif; ?>
                        } else {
                            showConsultationUiState('empty');
                        }
                    })
                    .catch(function(err) {
                        console.error(err);
                        showConsultationUiState('placeholder');
                        placeholderEl.classList.remove('d-none');
                        placeholderEl.innerHTML = '<i class="fas fa-exclamation-triangle me-2 text-danger"></i>Impossible de charger les consultations. Réessayez.';
                    });
            });
            
            filterInput.addEventListener('input', applyFilter);
            
            clearBtn.addEventListener('click', function() {
                setConsultationSelection('', false);
                filterInput.value = '';
                applyFilter();
            });
            
            function updateConsultationInfo() {
                const consultationId = consultationIdInput.value;
                if (!consultationId) {
                    consultationInfo.style.display = 'none';
                    return;
                }
                const consultation = consultationsData.find(function(c) { return String(c.id) === String(consultationId); });
                if (!consultation) {
                    consultationInfo.style.display = 'none';
                    return;
                }
                
                consultationDate.textContent = 'Consultation du ' + consultation.date;
                if (consultation.numero_ticket) {
                    consultationTicket.textContent = 'Ticket : ' + consultation.numero_ticket;
                } else {
                    consultationTicket.textContent = 'Consultation n° ' + consultation.id;
                }
                
                if (consultation.deja_payee) {
                    consultationBadge.className = 'badge bg-warning text-dark';
                    consultationBadge.textContent = 'Déjà liée à un paiement';
                } else {
                    consultationBadge.className = 'badge bg-success';
                    consultationBadge.textContent = 'Non payée';
                }
                
                const details = [];
                if (consultation.diagnostic && consultation.diagnostic !== 'Non spécifié') {
                    details.push('<strong>Diagnostic :</strong> ' + escapeHtml(consultation.diagnostic));
                }
                const med = (consultation.medecin || '').trim();
                if (med) {
                    const medLabel = (consultation.medecin_label || 'Médecin').trim();
                    let t = '<strong>' + escapeHtml(medLabel) + ' :</strong> ' + escapeHtml(med);
                    if (consultation.specialite) {
                        t += ' <span class="text-muted">(' + escapeHtml(consultation.specialite) + ')</span>';
                    }
                    details.push(t);
                }
                consultationDetails.innerHTML = details.length ? details.join('<br>') : '<span class="text-muted">Pas de détail supplémentaire</span>';
                
                const montant = Number(consultation.montant) || 0;
                if (montant > 0) {
                    consultationMontant.innerHTML =
                        '<div class="alert alert-success mb-0 py-2">' +
                        '<i class="fas fa-money-bill-wave me-2"></i><strong>Montant de la consultation :</strong> ' +
                        '<span class="fs-5">' + montant.toLocaleString('fr-FR') + ' FCFA</span></div>';
                    const montantInput = document.getElementById('montant');
                    if (!montantInput.value || montantInput.value === '0') {
                        montantInput.value = montant;
                    }
                } else {
                    consultationMontant.innerHTML = '';
                }
                consultationInfo.style.display = 'block';
            }
            
            <?php if ($patient_id): ?>
            patientSelect.dispatchEvent(new Event('change'));
            <?php endif; ?>
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
