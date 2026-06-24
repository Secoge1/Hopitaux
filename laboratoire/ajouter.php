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

$message = '';
$error = '';

// Récupérer les listes pour les formulaires
$patients = $patientModel->getAll(1, 1000); // Tous les patients
$medecins = $medecinModel->getAll(1, 1000); // Tous les médecins
$typesAnalyses = $analyseModel->getTypesAnalyses();
$prixParType = $analyseModel->getPrixParType();
$priorites = $analyseModel->getPriorites();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'patient_id' => $_POST['patient_id'],
            'medecin_id' => $_POST['medecin_id'],
            'type_analyse' => $_POST['type_analyse'],
            'priorite' => $_POST['priorite'] ?? 'normale',
            'description' => $_POST['description'] ?? null,
            'instructions' => $_POST['instructions'] ?? null,
            'prix_analyse' => $_POST['prix_analyse'] ?? null,
            'statut' => 'en_attente',
        ];
        $tid = StaffScope::technicienIdForAnalyseForm(
            isset($_POST['technicien_id']) && $_POST['technicien_id'] !== '' ? (int) $_POST['technicien_id'] : null
        );
        if ($tid) {
            $data['technicien_id'] = $tid;
        }

        if ($analyseModel->create($data)) {
            $message = "Analyse créée avec succès !";
            // Réinitialiser les valeurs
            $patient_id = $medecin_id = $type_analyse = $description = $instructions = $prix_analyse = $technicien_id = '';
            $priorite = 'normale';
        } else {
            $error = "Erreur lors de la création de l'analyse.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer les valeurs POST pour la persistance ou depuis l'URL
$patient_id = $_POST['patient_id'] ?? (isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : '');
$medecin_id = $_POST['medecin_id'] ?? '';
$type_analyse = $_POST['type_analyse'] ?? '';
$priorite = $_POST['priorite'] ?? 'normale';
$description = $_POST['description'] ?? '';
$instructions = $_POST['instructions'] ?? '';
$prix_analyse = $_POST['prix_analyse'] ?? '';
$technicien_id = $_POST['technicien_id'] ?? '';
if (!$canPickTechnicien && !empty($staffCtx['personnel_id'])) {
    $technicien_id = (string) $staffCtx['personnel_id'];
}

app_module_page_start([
    'active'   => 'laboratoire',
    'title'    => 'Nouvelle Analyse',
    'subtitle' => 'Création d\'une analyse',
    'icon'     => 'fa-flask',
]);
app_module_back_toolbar(app_url('laboratoire/index.php'), 'Retour à la liste', []);
app_module_flash();
?>
<style>
.form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .form-section h6 {
            color: #495057;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
        }
        
        /* Styles pour le système de suggestions intelligentes */
        .ai-suggestions {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 2px solid #2196f3;
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
            display: none;
            animation: fadeIn 0.5s ease-in;
        }
        
        .ai-suggestions.show {
            display: block;
        }
        
        .ai-suggestions h6 {
            color: #1976d2;
            border-bottom: 2px solid #2196f3;
            padding-bottom: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .ai-suggestions h6 i {
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        
        .suggestion-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .suggestion-item:hover {
            background: #f0f8ff;
            border-color: #2196f3;
            transform: translateX(5px);
        }
        
        .suggestion-item.selected {
            background: #e3f2fd;
            border-color: #1976d2;
            box-shadow: 0 2px 8px rgba(25, 118, 210, 0.2);
        }
        
        .suggestion-item::before {
            content: "✨";
            position: absolute;
            left: -10px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .suggestion-item:hover::before {
            opacity: 1;
        }

        .mistral-badge { background: #6f42c1; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75em; margin-left: 8px; }
        .mistral-note { background: #f3e8ff; border: 1px solid #c4b5fd; border-radius: 8px; padding: 10px 14px; margin-bottom: 15px; font-size: 0.9em; color: #5b21b6; }
        .text-suggestions-section { background: #fff; border: 1px solid #c4b5fd; border-radius: 10px; padding: 15px; margin-bottom: 18px; }
        .text-suggestion-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; background: #fafafa; }
        .text-suggestion-card:hover { border-color: #6f42c1; }
        .text-suggestion-body { white-space: pre-wrap; font-size: 0.92em; line-height: 1.5; }
        .btn-insert-text { font-size: 0.78em; padding: 3px 10px; border-radius: 14px; border: 1px solid #2196f3; background: #fff; color: #1976d2; }
        .btn-insert-text:hover { background: #1976d2; color: #fff; }
        .suggestion-item-text { flex: 1; white-space: pre-wrap; line-height: 1.45; }
        
        .suggestion-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid #28a745;
        }
        
        .suggestion-details h7 {
            color: #28a745;
            font-weight: bold;
            display: block;
            margin-bottom: 10px;
        }
        
        .suggestion-details ul {
            margin-bottom: 10px;
            padding-left: 20px;
        }
        
        .suggestion-details li {
            margin-bottom: 5px;
            color: #495057;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2196f3;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        .auto-fill-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-top: 10px;
            transition: all 0.3s ease;
        }
        
        .auto-fill-btn:hover {
            background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .patient-info-context {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }
        
        .patient-info-context.show {
            display: block;
        }
        
        .patient-info-context h6 {
            color: #856404;
            border-bottom: 2px solid #ffc107;
            margin-bottom: 10px;
        }
</style>
        <div class="card shadow">
            <div class="card-header text-white">
                <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Demande d'Analyse</h5>
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
                        <div class="form-section">
                            <h6><i class="fas fa-user me-2"></i>Informations du Patient</h6>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="patient_id" class="form-label">Patient *</label>
                                    <select class="form-select" id="patient_id" name="patient_id" required>
                                        <option value="">Sélectionner un patient...</option>
                                        <?php foreach ($patients as $patient): ?>
                                            <option value="<?php echo $patient['id']; ?>" <?php echo ($patient_id ?? '') == $patient['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($patient['nom'] . ' ' . $patient['prenom'] . ' (' . $patient['numero_dossier'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="medecin_id" class="form-label">Médecin prescripteur *</label>
                                    <select class="form-select" id="medecin_id" name="medecin_id" required>
                                        <option value="">Sélectionner un médecin...</option>
                                        <?php foreach ($medecins as $medecin): ?>
                                            <option value="<?php echo $medecin['id']; ?>" <?php echo ($medecin_id ?? '') == $medecin['id'] ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars(medecin_profil_format_name($medecin)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
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
                                                ?>
                                                <option value="<?= (int) $tech['id'] ?>" <?= (string) ($technicien_id ?? '') === (string) $tech['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($techLabel) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Optionnel. Le laborantin verra l'analyse si son nom est sélectionné.</small>
                                    <?php else: ?>
                                        <input type="text" class="form-control" readonly
                                               value="<?= htmlspecialchars($staffLinkSelf['label'] ?? 'Compte non rattaché') ?>">
                                        <input type="hidden" name="technicien_id" id="technicien_id"
                                               value="<?= htmlspecialchars((string) ($technicien_id ?? '')) ?>">
                                        <?php if (empty($staffCtx['linked'])): ?>
                                            <small class="text-warning">Rattachez ce compte à une fiche personnel (Paramètres → Utilisateurs).</small>
                                        <?php else: ?>
                                            <small class="text-muted">Assigné automatiquement à votre fiche.</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Type d'analyse -->
                    <div class="col-12">
                        <div class="form-section">
                            <h6><i class="fas fa-flask me-2"></i>Type d'Analyse</h6>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="type_analyse" class="form-label">Type d'analyse *</label>
                                    <select class="form-select" id="type_analyse" name="type_analyse" required onchange="loadAISuggestions()">
                                        <option value="">Choisir le type...</option>
                                        <?php foreach ($typesAnalyses as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" <?php echo ($type_analyse ?? '') == $key ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="priorite" class="form-label">Priorité</label>
                                    <select class="form-select" id="priorite" name="priorite">
                                        <?php foreach ($priorites as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" <?php echo ($priorite ?? 'normale') == $key ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="prix_analyse" class="form-label">Prix (<?= htmlspecialchars(function_exists('app_currency_label') ? app_currency_label() : 'FCFA') ?>)</label>
                                    <input type="number" class="form-control" id="prix_analyse" name="prix_analyse" 
                                           value="<?php echo htmlspecialchars($prix_analyse ?? ''); ?>" 
                                           min="0" step="0.01" placeholder="Prix de l'analyse">
                                    <small class="text-muted">Laissez vide pour le prix par défaut</small>
                                </div>
                            </div>
                            
                            <!-- Section des suggestions intelligentes -->
                            <div id="ai-suggestions-container" class="ai-suggestions">
                                <h6><i class="fas fa-robot"></i>Suggestions Intelligentes <small class="text-muted ms-2" id="ia-source-label"></small></h6>
                                <div id="mistral-note-container" class="mistral-note" style="display: none;"></div>
                                
                                <div id="loading-suggestions" class="text-center" style="display: none;">
                                    <div class="loading-spinner"></div>
                                    <span>Chargement des suggestions personnalisées...</span>
                                </div>
                                
                                <div id="suggestions-content" style="display: none;">
                                    <div id="text-suggestions-section" class="text-suggestions-section" style="display: none;">
                                        <strong><i class="fas fa-align-left me-2"></i>Textes rédigés suggérés</strong>
                                        <p class="text-muted small mb-2">Paragraphes ou phrases complètes à insérer dans la description ou les instructions.</p>
                                        <div id="text-suggestions-list"></div>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Analyses suggérées pour ce type :</strong>
                                        <div id="suggestions-list"></div>
                                    </div>
                                    
                                    <div class="suggestion-details">
                                        <h7>Informations détaillées :</h7>
                                        <div id="suggestion-info"></div>
                                    </div>
                                    
                                    <button type="button" class="auto-fill-btn" onclick="autoFillDescription()">
                                        <i class="fas fa-magic me-2"></i>Remplir automatiquement la description
                                    </button>
                                    <button type="button" class="auto-fill-btn" onclick="autoFillInstructions()">
                                        <i class="fas fa-clipboard-list me-2"></i>Remplir automatiquement les instructions
                                    </button>
                                </div>
                                
                                <div id="suggestions-error" class="alert alert-warning" style="display: none;">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <span id="error-message"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Détails de l'analyse -->
                    <div class="col-12">
                        <div class="form-section">
                            <h6><i class="fas fa-clipboard-list me-2"></i>Détails de l'Analyse</h6>
                            
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="description" class="form-label">Description de l'analyse</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" 
                                              placeholder="Détails de l'analyse demandée..."><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                                </div>

                                <div class="col-12">
                                    <label for="instructions" class="form-label">Instructions spéciales</label>
                                    <textarea class="form-control" id="instructions" name="instructions" rows="2"
                                              placeholder="Préparation, contraintes, notes..."><?php echo htmlspecialchars($instructions ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Boutons -->
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-danger btn-lg px-4">
                            <i class="fas fa-save me-2"></i>Demander l'analyse
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg px-4 ms-3">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Informations sur le module -->
        <div class="card mt-4 shadow">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>À propos du module Laboratoire</h6>
            </div>
            <div class="card-body">
                <p>Le module Laboratoire permet de :</p>
                <ul>
                    <li><i class="fas fa-check text-success me-2"></i>Gérer les demandes d'analyses médicales</li>
                    <li><i class="fas fa-check text-success me-2"></i>Suivre le statut des prélèvements</li>
                    <li><i class="fas fa-check text-success me-2"></i>Enregistrer et valider les résultats</li>
                    <li><i class="fas fa-check text-success me-2"></i>Générer des rapports de laboratoire</li>
                    <li><i class="fas fa-check text-success me-2"></i>Intégrer avec les modules Patients et Consultations</li>
                </ul>
                <p class="mb-0"><strong>Statut :</strong> <span class="badge bg-success">Fonctionnel</span></p>
            </div>
        </div>
<?php ob_start(); ?>
<script>
        // Variables globales pour stocker les données des suggestions
        let currentSuggestions = null;
        let mistralSuggestionSet = new Set();
        let selectedSuggestions = [];

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        const LAB_FIELD_LABELS = { description: 'Description', instructions: 'Instructions' };

        function insertTextIntoField(fieldId, text, append = true) {
            const el = document.getElementById(fieldId);
            if (!el) return;
            const trimmed = String(text || '').trim();
            if (!trimmed) return;
            if (append && el.value.trim()) {
                el.value = el.value.trim() + '\n\n' + trimmed;
            } else {
                el.value = trimmed;
            }
            el.style.backgroundColor = '#e8f5e8';
            el.focus();
            setTimeout(() => { el.style.backgroundColor = ''; }, 1200);
        }

        function flashInsertedButton(btn) {
            if (!btn) return;
            const orig = btn.textContent;
            btn.textContent = '✓ Inséré';
            btn.disabled = true;
            setTimeout(() => { btn.textContent = orig; btn.disabled = false; }, 1500);
        }

        function buildLabWrittenSuggestions(data) {
            const blocks = [];
            const seen = new Set();
            function add(cible, label, texte, source) {
                const key = cible + '|' + String(texte).trim().toLowerCase();
                if (!texte || !String(texte).trim() || seen.has(key)) return;
                seen.add(key);
                blocks.push({ cible, label, texte: String(texte).trim(), source: source || 'local' });
            }

            if (data.title) {
                let desc = `Demande d'analyse : ${data.title}\n\n`;
                if (data.suggestions && data.suggestions.length) {
                    desc += 'Analyses envisagées :\n';
                    data.suggestions.forEach(s => { desc += `• ${s}\n`; });
                }
                if (data.indications && data.indications.length) {
                    desc += '\nIndications :\n';
                    data.indications.forEach(i => { desc += `• ${i}\n`; });
                }
                add('description', 'Description d\'analyse', desc.trim(), 'local');
            }

            if (data.preparation || data.delai) {
                let instr = 'INSTRUCTIONS POUR LE PATIENT :\n\n';
                if (data.preparation) instr += `Préparation : ${data.preparation}\n`;
                if (data.delai) instr += `Délai de rendu : ${data.delai}\n`;
                if (data.contraindications && data.contraindications.length) {
                    instr += '\nPrécautions :\n';
                    data.contraindications.forEach(c => { instr += `• ${c}\n`; });
                }
                add('instructions', 'Instructions patient', instr.trim(), 'local');
            }

            Object.entries(data.mistral_textes || {}).forEach(([cible, texte]) => {
                const labels = { description: 'Description suggérée', instructions: 'Instructions suggérées' };
                add(cible, labels[cible] || 'Suggestion IA', texte, 'mistral');
            });
            (data.mistral_phrases || []).forEach((p, i) => {
                add(p.cible, 'Phrase suggérée ' + (i + 1), p.texte, 'mistral');
            });

            return blocks;
        }

        function displayLabWrittenSuggestions(data) {
            const section = document.getElementById('text-suggestions-section');
            const container = document.getElementById('text-suggestions-list');
            if (!section || !container) return;
            const blocks = buildLabWrittenSuggestions(data);
            container.innerHTML = '';
            if (!blocks.length) {
                section.style.display = 'none';
                return;
            }
            section.style.display = 'block';
            blocks.forEach(block => {
                const card = document.createElement('div');
                card.className = 'text-suggestion-card';
                const fieldLabel = LAB_FIELD_LABELS[block.cible] || block.cible;
                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <span>
                            <strong>${escapeHtml(block.label)}</strong>
                            <span class="badge bg-light text-dark border ms-1">${escapeHtml(fieldLabel)}</span>
                            ${block.source === 'mistral' ? '<span class="mistral-badge">IA</span>' : ''}
                        </span>
                        <button type="button" class="btn-insert-text"><i class="fas fa-arrow-down me-1"></i>Insérer</button>
                    </div>
                    <div class="text-suggestion-body">${escapeHtml(block.texte)}</div>
                `;
                const btn = card.querySelector('.btn-insert-text');
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    insertTextIntoField(block.cible, block.texte, true);
                    flashInsertedButton(btn);
                });
                card.addEventListener('dblclick', () => insertTextIntoField(block.cible, block.texte, false));
                container.appendChild(card);
            });
        }
        
        // Fonction utilitaire pour vérifier si un élément existe
        function getElementSafely(id) {
            const element = document.getElementById(id);
            if (!element) {
                console.warn(`Élément avec l'ID "${id}" non trouvé`);
            }
            return element;
        }
        
        // Fonction pour charger les suggestions intelligentes
        async function loadAISuggestions() {
            const typeAnalyse = document.getElementById('type_analyse').value;
            const patientId = document.getElementById('patient_id').value;
            
            if (!typeAnalyse) {
                hideSuggestions();
                return;
            }
            
            showLoading();
            
            try {
                const descriptionEl = document.getElementById('description');
                const description = descriptionEl ? descriptionEl.value.trim() : '';
                let apiUrl = `api_suggestions.php?type_analyse=${encodeURIComponent(typeAnalyse)}&patient_id=${patientId}`;
                if (description) {
                    apiUrl += `&description=${encodeURIComponent(description)}`;
                }
                let response = await fetch(apiUrl);
                
                // Si ça ne marche pas, essayer avec un chemin absolu
                if (!response.ok) {
                    apiUrl = `/efficasante/laboratoire/api_suggestions.php?type_analyse=${encodeURIComponent(typeAnalyse)}&patient_id=${patientId}`;
                    if (description) {
                        apiUrl += `&description=${encodeURIComponent(description)}`;
                    }
                    response = await fetch(apiUrl);
                }
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    currentSuggestions = data.data;
                    mistralSuggestionSet = new Set((data.data.mistral_suggestions || []).map(s => String(s).toLowerCase().trim()));
                    displaySuggestions(data.data, data.mistral, data.ia_config);
                } else {
                    showError(data.error || 'Erreur lors du chargement des suggestions');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showError(`Erreur de connexion au serveur: ${error.message}`);
            }
        }
        
        // Afficher l'état de chargement
        function showLoading() {
            const container = document.getElementById('ai-suggestions-container');
            const loading = document.getElementById('loading-suggestions');
            const content = document.getElementById('suggestions-content');
            const error = document.getElementById('suggestions-error');
            
            if (container) container.classList.add('show');
            if (loading) loading.style.display = 'block';
            if (content) content.style.display = 'none';
            if (error) error.style.display = 'none';
        }
        
        // Afficher les suggestions
        function displaySuggestions(data, mistralMeta, iaConfig) {
            const loading = document.getElementById('loading-suggestions');
            const content = document.getElementById('suggestions-content');
            if (loading) loading.style.display = 'none';
            if (content) content.style.display = 'block';

            const iaLabel = document.getElementById('ia-source-label');
            const mistralNote = document.getElementById('mistral-note-container');
            if (iaLabel) {
                if (mistralMeta && mistralMeta.enriched) {
                    iaLabel.innerHTML = '<span class="mistral-badge"><i class="fas fa-robot me-1"></i>+ IA</span>';
                } else if (iaConfig && iaConfig.laboratoire) {
                    iaLabel.textContent = '(IA activée)';
                } else {
                    iaLabel.textContent = '(base locale)';
                }
            }
            if (mistralNote) {
                const note = (mistralMeta && mistralMeta.note) ? mistralMeta.note : '';
                if (note) {
                    mistralNote.style.display = 'block';
                    mistralNote.innerHTML = '<i class="fas fa-robot me-2"></i>' + escapeHtml(note);
                } else {
                    mistralNote.style.display = 'none';
                    mistralNote.innerHTML = '';
                }
            }
            
            // Afficher la liste des suggestions
            const suggestionsList = document.getElementById('suggestions-list');
            suggestionsList.innerHTML = '';
            
            data.suggestions.forEach((suggestion, index) => {
                const isMistral = mistralSuggestionSet.has(String(suggestion).toLowerCase().trim());
                const suggestionDiv = document.createElement('div');
                suggestionDiv.className = 'suggestion-item';
                suggestionDiv.innerHTML = `
                    <div class="d-flex align-items-start gap-2">
                        <div class="form-check flex-grow-1">
                            <input class="form-check-input" type="checkbox" value="${index}" id="suggestion-${index}">
                            <label class="form-check-label suggestion-item-text" for="suggestion-${index}">
                                ${escapeHtml(suggestion)}
                                ${isMistral ? ' <span class="mistral-badge">IA</span>' : ''}
                            </label>
                        </div>
                        <button type="button" class="btn-insert-text btn-insert-suggestion" title="Insérer dans la description">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                `;
                
                const insertBtn = suggestionDiv.querySelector('.btn-insert-suggestion');
                insertBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    insertTextIntoField('description', suggestion, true);
                    flashInsertedButton(insertBtn);
                });
                
                suggestionDiv.addEventListener('click', function(e) {
                    if (e.target.type !== 'checkbox' && !e.target.closest('.btn-insert-suggestion')) {
                        const checkbox = suggestionDiv.querySelector('input[type="checkbox"]');
                        checkbox.checked = !checkbox.checked;
                    }
                    updateSelectedSuggestions();
                });
                
                suggestionsList.appendChild(suggestionDiv);
            });

            displayLabWrittenSuggestions(data);
            
            // Afficher les informations détaillées
            const suggestionInfo = document.getElementById('suggestion-info');
            suggestionInfo.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Préparation :</strong><br>
                        <span class="text-muted">${data.preparation}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Délai de rendu :</strong><br>
                        <span class="text-muted">${data.delai}</span>
                    </div>
                </div>
                
                ${data.indications ? `
                    <div class="mt-3">
                        <strong>Indications courantes :</strong>
                        <ul>
                            ${data.indications.map(indication => `<li>${indication}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}
                
                ${data.contraindications ? `
                    <div class="mt-3">
                        <strong>Contre-indications/Précautions :</strong>
                        <ul>
                            ${data.contraindications.map(contraindication => `<li class="text-warning">⚠️ ${contraindication}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}
                
                ${data.patient_info && data.patient_info.personalized ? `
                    <div class="mt-3">
                        <div class="alert alert-info">
                            <i class="fas fa-user-md me-2"></i>
                            <strong>Suggestions personnalisées</strong> basées sur l'âge et le sexe du patient
                        </div>
                    </div>
                ` : ''}
            `;
        }
        
        // Afficher une erreur
        function showError(message) {
            const loading = document.getElementById('loading-suggestions');
            const content = document.getElementById('suggestions-content');
            const error = document.getElementById('suggestions-error');
            const errorMessage = document.getElementById('error-message');
            
            if (loading) loading.style.display = 'none';
            if (content) content.style.display = 'none';
            if (error) error.style.display = 'block';
            if (errorMessage) errorMessage.textContent = message;
        }
        
        // Masquer les suggestions
        function hideSuggestions() {
            const container = document.getElementById('ai-suggestions-container');
            if (container) container.classList.remove('show');
        }
        
        // Mettre à jour les suggestions sélectionnées
        function updateSelectedSuggestions() {
            selectedSuggestions = [];
            document.querySelectorAll('#suggestions-list input[type="checkbox"]:checked').forEach(checkbox => {
                const index = parseInt(checkbox.value);
                selectedSuggestions.push(currentSuggestions.suggestions[index]);
            });
        }
        
        // Remplir automatiquement la description
        function autoFillDescription() {
            if (!currentSuggestions) return;
            
            let description = `Analyse de type : ${currentSuggestions.title}\n\n`;
            
            if (selectedSuggestions.length > 0) {
                description += `Analyses sélectionnées :\n`;
                selectedSuggestions.forEach(suggestion => {
                    description += `• ${suggestion}\n`;
                });
                description += `\n`;
            }
            
            description += `Analyses suggérées pour ce type :\n`;
            currentSuggestions.suggestions.forEach(suggestion => {
                description += `• ${suggestion}\n`;
            });
            
            description += `\nPréparation requise : ${currentSuggestions.preparation}\n`;
            description += `Délai de rendu : ${currentSuggestions.delai}`;
            
            if (currentSuggestions.indications && currentSuggestions.indications.length > 0) {
                description += `\n\nIndications courantes :\n`;
                currentSuggestions.indications.forEach(indication => {
                    description += `• ${indication}\n`;
                });
            }
            
            document.getElementById('description').value = description;
            
            // Effet visuel
            const textarea = document.getElementById('description');
            textarea.style.backgroundColor = '#e8f5e8';
            setTimeout(() => {
                textarea.style.backgroundColor = '';
            }, 1000);
        }
        
        // Remplir automatiquement les instructions
        function autoFillInstructions() {
            if (!currentSuggestions) return;
            
            let instructions = `INSTRUCTIONS POUR LE PATIENT :\n\n`;
            instructions += `Préparation : ${currentSuggestions.preparation}\n\n`;
            instructions += `Délai de rendu : ${currentSuggestions.delai}\n\n`;
            
            if (currentSuggestions.contraindications && currentSuggestions.contraindications.length > 0) {
                instructions += `ATTENTION - Précautions à prendre :\n`;
                currentSuggestions.contraindications.forEach(contraindication => {
                    instructions += `⚠️ ${contraindication}\n`;
                });
                instructions += `\n`;
            }
            
            instructions += `IMPORTANT : Informer le laboratoire de tout traitement en cours ou d'antécédents médicaux pertinents.`;
            
            document.getElementById('instructions').value = instructions;
            
            // Effet visuel
            const textarea = document.getElementById('instructions');
            textarea.style.backgroundColor = '#e8f5e8';
            setTimeout(() => {
                textarea.style.backgroundColor = '';
            }, 1000);
        }
        
        // Charger les suggestions au chargement de la page si un type est déjà sélectionné
        document.addEventListener('DOMContentLoaded', function() {
            const typeAnalyse = document.getElementById('type_analyse').value;
            if (typeAnalyse) {
                loadAISuggestions();
                updatePrice();
            }
        });
        
        // Recharger les suggestions si le patient change
        document.getElementById('patient_id').addEventListener('change', function() {
            const typeAnalyse = document.getElementById('type_analyse').value;
            if (typeAnalyse) {
                loadAISuggestions();
            }
        });
        
        // Mettre à jour le prix selon le type d'analyse
        function updatePrice() {
            const typeAnalyse = document.getElementById('type_analyse').value;
            const prixInput = document.getElementById('prix_analyse');
            
            const prices = <?= json_encode($prixParType, JSON_UNESCAPED_UNICODE) ?>;
            
            if (typeAnalyse && prices[typeAnalyse]) {
                prixInput.value = prices[typeAnalyse];
                prixInput.style.backgroundColor = '#e8f5e8';
                setTimeout(() => {
                    prixInput.style.backgroundColor = '';
                }, 1000);
            }
        }
        
        // Écouter les changements de type d'analyse
        document.getElementById('type_analyse').addEventListener('change', function() {
            loadAISuggestions();
            updatePrice();
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
