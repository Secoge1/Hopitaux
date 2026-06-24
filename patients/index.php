<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('patients'));

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../includes/patient_settings.php';

// Éviter que le navigateur garde une ancienne liste vide en cache
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$patientModel = new Patient();
$patients_suppression_actif = patient_deletion_allowed();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';
$limit = 20;
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

try {
    $patients = $patientModel->getAll($page, $limit, $search, $statut);
    $total_patients = $patientModel->getCount($search, $statut);
    $total_pages = ceil($total_patients / $limit);
    $stats = $patientModel->getStats();
} catch (Exception $e) {
    die("Erreur lors de la récupération des patients: " . $e->getMessage());
}

// Debug : vérifier ce que voit réellement la page (même base que le diagnostic)
if ($debug_mode) {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT DATABASE() AS db");
        $debug_db = $stmt->fetch(PDO::FETCH_ASSOC)['db'] ?? '—';
        require_once __DIR__ . '/../includes/saas/TenantScope.php';
        $debug_total_raw = TenantScope::count($pdo, 'patients');
    } catch (Exception $e) {
        $debug_db = 'Erreur';
        $debug_total_raw = 0;
    }
}

app_module_page_start([
    'active'    => 'patients',
    'title'     => 'Gestion des Patients',
    'subtitle'  => 'Liste et gestion des dossiers patients',
    'icon'      => 'fa-user-injured',
    'extra_css' => ['assets/css/app-patients.css'],
]);
app_module_toolbar([
    ['href' => app_url('patients/ajouter.php'), 'label' => 'Nouveau Patient', 'icon' => 'fa-plus'],
]);
app_module_flash();
?>
<?php if (!empty($_GET['message'])):
    $getMessage = trim((string) $_GET['message']);
    if ($getMessage !== ''):
        $isError = (stripos($getMessage, 'erreur') !== false || stripos($getMessage, 'introuvable') !== false);
?>
        <div class="alert alert-<?php echo $isError ? 'danger' : 'success'; ?> alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-<?php echo $isError ? 'exclamation-circle' : 'check-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($getMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
<?php endif; endif; ?>

<?php if (!empty($debug_mode)): ?>
        <div class="alert alert-info mb-3" role="alert">
            <strong>Mode debug</strong> — Base MySQL : <code><?php echo htmlspecialchars($debug_db ?? '—'); ?></code> |
            Total lignes (SELECT COUNT(*) patients) : <strong><?php echo (int)($debug_total_raw ?? 0); ?></strong> |
            Liste (getCount) : <strong><?php echo (int)$total_patients; ?></strong>
            <?php if (($debug_total_raw ?? 0) > 0 && $total_patients === 0): ?>
            <br><span class="text-danger">→ La base contient des données mais la liste affiche 0 : cache PHP (OPcache) ou anciens fichiers. Vider le cache serveur ou redéployer les fichiers.</span>
            <?php endif; ?>
            <br><small><a href="index.php">Sans debug</a></small>
        </div>
        <?php endif; ?>

<?php require __DIR__ . '/_list_view.php'; ?>

    <?php if ($patients_suppression_actif): ?>
    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true"
         data-delete-url="ajax_supprimer.php" data-delete-row-key="data-patient-id"
         data-delete-entity-label="patient(s)">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmation de suppression
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer le patient :</p>
                    <p class="fw-bold text-danger" id="patientNameToDelete"></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php if ($auth->estAdmin()): ?>
                        <strong>Attention :</strong> Cette action est irréversible et supprimera définitivement :
                        <ul class="mb-0 mt-2">
                            <li>Toutes les informations du patient</li>
                            <li>Son historique médical</li>
                            <li>Ses documents et fichiers</li>
                            <li>Ses rendez-vous et consultations</li>
                        </ul>
                        <?php else: ?>
                        <strong>Attention :</strong> Le patient sera <strong>retiré de votre liste</strong> (archivage).
                        L'administrateur peut le restaurer si nécessaire. Les données cliniques liées sont conservées.
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash me-2"></i>Oui, supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php ob_start(); ?>

<script>
        // Nettoyer l'URL des paramètres message pour éviter que "Patient introuvable" persiste au rafraîchissement
        (function() {
            var url = new URL(window.location.href);
            if (url.searchParams.has('message')) {
                url.searchParams.delete('message');
                var cleanUrl = url.pathname + (url.search || '') + (url.hash || '');
                history.replaceState({}, document.title, cleanUrl);
            }
        })();

        // Gestion du formulaire de recherche
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.getElementById('searchForm');
            const searchInput = document.getElementById('searchInput');
            const statutSelect = document.getElementById('statutSelect');
            const suggestionsContainer = document.getElementById('autocompleteSuggestions');
            
            let currentFocus = -1;
            let searchTimeout = null;
            
            // Fonction pour échapper les caractères HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Fonction pour mettre en surbrillance le texte recherché
            function highlightText(text, query) {
                if (!query) return escapeHtml(text);
                const regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                return escapeHtml(text).replace(regex, '<span class="highlight">$1</span>');
            }
            
            // Fonction pour afficher les suggestions
            function showSuggestions(suggestions, query) {
                if (suggestions.length === 0) {
                    suggestionsContainer.innerHTML = '<div class="autocomplete-no-results"><i class="fas fa-search me-2"></i>Aucun patient trouvé</div>';
                    suggestionsContainer.classList.add('show');
                    return;
                }
                
                let html = '';
                suggestions.forEach((patient, index) => {
                    const badgeColor = patient.statut === 'actif' ? 'success' : (patient.statut === 'inactif' ? 'warning' : 'secondary');
                    html += `
                        <div class="autocomplete-item" data-index="${index}" data-id="${patient.id}">
                            <div class="autocomplete-patient-info">
                                <div class="autocomplete-patient-name">
                                    <i class="fas fa-user-circle me-2 text-primary"></i>
                                    ${highlightText(patient.nom_complet, query)}
                                </div>
                                <div class="autocomplete-patient-details">
                                    <span><i class="fas fa-folder me-1"></i>${escapeHtml(patient.numero_dossier)}</span>
                                    ${patient.telephone ? `<span class="ms-3"><i class="fas fa-phone me-1"></i>${escapeHtml(patient.telephone)}</span>` : ''}
                                    ${patient.age ? `<span class="ms-3"><i class="fas fa-birthday-cake me-1"></i>${patient.age} ans</span>` : ''}
                                </div>
                            </div>
                            <span class="badge bg-${badgeColor} autocomplete-patient-badge">${escapeHtml(patient.statut)}</span>
                        </div>
                    `;
                });
                
                suggestionsContainer.innerHTML = html;
                suggestionsContainer.classList.add('show');
                currentFocus = -1;
                
                // Ajouter les event listeners sur les items
                document.querySelectorAll('.autocomplete-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const patientId = this.getAttribute('data-id');
                        window.location.href = 'voir.php?id=' + patientId;
                    });
                    
                    item.addEventListener('mouseenter', function() {
                        removeActiveClass();
                        this.classList.add('active');
                        currentFocus = parseInt(this.getAttribute('data-index'));
                    });
                });
            }
            
            // Fonction pour cacher les suggestions
            function hideSuggestions() {
                suggestionsContainer.classList.remove('show');
                suggestionsContainer.innerHTML = '';
                currentFocus = -1;
            }
            
            // Fonction pour enlever la classe active
            function removeActiveClass() {
                document.querySelectorAll('.autocomplete-item').forEach(item => {
                    item.classList.remove('active');
                });
            }
            
            // Fonction pour naviguer avec les flèches
            function addActiveClass() {
                const items = document.querySelectorAll('.autocomplete-item');
                if (!items.length) return;
                
                removeActiveClass();
                
                if (currentFocus >= items.length) currentFocus = 0;
                if (currentFocus < 0) currentFocus = items.length - 1;
                
                items[currentFocus].classList.add('active');
                items[currentFocus].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
            
            // Recherche avec délai (debounce)
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Annuler la recherche précédente
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                // Si le texte est trop court, cacher les suggestions
                if (query.length < 2) {
                    hideSuggestions();
                    return;
                }
                
                // Afficher le loader
                suggestionsContainer.innerHTML = '<div class="autocomplete-loading"><i class="fas fa-spinner fa-spin me-2"></i>Recherche en cours...</div>';
                suggestionsContainer.classList.add('show');
                
                // Lancer la recherche après 300ms
                searchTimeout = setTimeout(() => {
                    fetch('api_suggestions.php?q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            showSuggestions(data, query);
                        })
                        .catch(error => {
                            console.error('Erreur lors de la recherche:', error);
                            suggestionsContainer.innerHTML = '<div class="autocomplete-no-results text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Erreur lors de la recherche</div>';
                        });
                }, 300);
            });
            
            // Navigation au clavier
            searchInput.addEventListener('keydown', function(e) {
                const items = document.querySelectorAll('.autocomplete-item');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    currentFocus++;
                    addActiveClass();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    currentFocus--;
                    addActiveClass();
                } else if (e.key === 'Enter') {
                    if (currentFocus > -1 && items.length > 0) {
                        e.preventDefault();
                        items[currentFocus].click();
                    }
                } else if (e.key === 'Escape') {
                    hideSuggestions();
                }
            });
            
            // Cacher les suggestions quand on clique ailleurs
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.autocomplete-container')) {
                    hideSuggestions();
                }
            });
            
            // S'assurer que le formulaire se soumet correctement
            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    // Si des suggestions sont visibles et qu'une est sélectionnée, ne pas soumettre le formulaire
                    if (currentFocus > -1 && suggestionsContainer.classList.contains('show')) {
                        e.preventDefault();
                        const items = document.querySelectorAll('.autocomplete-item');
                        if (items[currentFocus]) {
                            items[currentFocus].click();
                        }
                    }
                    console.log('Formulaire soumis avec:', {
                        search: searchInput.value,
                        statut: statutSelect.value
                    });
                });
            }
            
            // Soumettre le formulaire lors du changement du statut
            if (statutSelect) {
                statutSelect.addEventListener('change', function() {
                    hideSuggestions();
                    searchForm.submit();
                });
            }
            
            // Log pour debugging
            console.log('Page chargée. Paramètres actuels:', {
                search: '<?php echo addslashes($search); ?>',
                statut: '<?php echo addslashes($statut); ?>',
                totalPatients: <?php echo $total_patients; ?>,
                patientsAffiches: <?php echo count($patients); ?>
            });
        });
    </script>

<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
