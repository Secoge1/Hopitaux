<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('consultations'));

require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/Patient.php';

$consultationModel = new Consultation();
$patientModel = new Patient();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$limit = 20;

if ($patient_id) {
    $patient = $patientModel->getById($patient_id);
    if (!$patient) {
        header("Location: index.php");
        exit();
    }
    $consultations = $consultationModel->getPatientHistory($patient_id, $limit, $page, $limit);
    $total_consultations = $consultationModel->getPatientConsultationCount($patient_id);
    $stats = null;
} else {
    $patient = null;
    $consultations = $consultationModel->getAll($page, $limit, $search, $statut, $date);
    $total_consultations = $consultationModel->getCount($search, $statut, $date);
    $stats = $consultationModel->getStats();
}

$total_pages = (int) ceil($total_consultations / $limit);
$consTitle = ($patient_id && $patient)
    ? 'Consultations de ' . $patient['prenom'] . ' ' . $patient['nom']
    : 'Gestion des Consultations';
$consToolbar = [
    ['href' => app_url('consultations/ajouter.php' . ($patient_id ? '?patient_id=' . $patient_id : '')), 'label' => 'Nouvelle Consultation', 'icon' => 'fa-plus'],
];
if ($patient_id) {
    $consToolbar[] = ['href' => app_url('patients/voir.php?id=' . $patient_id), 'label' => 'Retour au patient', 'icon' => 'fa-user', 'class' => 'btn-outline-secondary'];
    $consToolbar[] = ['href' => app_url('consultations/index.php'), 'label' => 'Toutes les consultations', 'icon' => 'fa-list', 'class' => 'btn-outline-secondary'];
}
app_module_page_start([
    'active'   => 'consultations',
    'title'    => $consTitle,
    'subtitle' => 'Suivi des consultations médicales',
    'icon'     => 'fa-stethoscope',
]);
app_module_toolbar($consToolbar);
app_module_flash();
?>

        <?php if ($patient_id && $patient): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-filter me-2"></i>
            <strong>Filtre actif :</strong> Affichage des consultations de
            <strong><?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']); ?></strong>
            (Dossier: <?php echo htmlspecialchars($patient['numero_dossier']); ?>)
            - <strong><?php echo $total_consultations; ?></strong> consultation(s) trouvée(s)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

<?php require __DIR__ . '/_list_view.php'; ?>

<?php ob_start(); ?>
<script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const suggestionsContainer = document.getElementById('autocompleteSuggestions');
            const searchForm = document.getElementById('searchForm');

            if (!searchInput || !suggestionsContainer) return;

            let currentFocus = -1;
            let searchTimeout = null;

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text || '';
                return div.innerHTML;
            }

            function highlightText(text, query) {
                if (!text || !query) return escapeHtml(text);
                const regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                return escapeHtml(text).replace(regex, '<span class="highlight">$1</span>');
            }

            function formatDate(dateStr) {
                const date = new Date(dateStr);
                const options = { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' };
                return date.toLocaleDateString('fr-FR', options);
            }

            function showSuggestions(suggestions, query) {
                if (suggestions.length === 0) {
                    suggestionsContainer.innerHTML = '<div class="autocomplete-no-results"><i class="fas fa-search me-2"></i>Aucune consultation trouvée</div>';
                    suggestionsContainer.classList.add('show');
                    return;
                }

                let html = '';
                suggestions.forEach((consultation, index) => {
                    let badgeColor = 'secondary';
                    if (consultation.statut === 'terminee') badgeColor = 'success';
                    else if (consultation.statut === 'en_cours') badgeColor = 'warning';
                    else if (consultation.statut === 'planifiee') badgeColor = 'info';
                    else if (consultation.statut === 'annulee') badgeColor = 'danger';

                    const consultDate = new Date(consultation.date_consultation);
                    const today = new Date();
                    const isToday = consultDate.toDateString() === today.toDateString();
                    const isFuture = consultDate > today;

                    let dateIcon = isToday ? '<i class="fas fa-calendar-day text-warning"></i>' :
                                   (isFuture ? '<i class="fas fa-calendar-plus text-success"></i>' :
                                   '<i class="fas fa-calendar-check text-secondary"></i>');

                    html += `
                        <div class="autocomplete-item" data-index="${index}" data-id="${consultation.id}">
                            <div class="autocomplete-consultation-header">
                                <div class="autocomplete-consultation-patient">
                                    <i class="fas fa-user-injured me-2 text-primary"></i>
                                    ${highlightText(consultation.patient_nom_complet, query)}
                                    <span class="badge bg-${badgeColor} autocomplete-consultation-badge">${escapeHtml(consultation.statut)}</span>
                                </div>
                            </div>
                            <div class="autocomplete-consultation-details">
                                <div class="mb-1">
                                    ${dateIcon} ${formatDate(consultation.date_consultation)}
                                    <span class="ms-3"><i class="fas fa-folder me-1"></i>${escapeHtml(consultation.patient_numero_dossier)}</span>
                                </div>
                                <div>
                                    <i class="fas fa-user-md me-1 text-success"></i>${highlightText(consultation.medecin_nom_complet, query)}
                                    <small class="text-muted ms-2">${escapeHtml(consultation.medecin_specialite || '')}</small>
                                    ${consultation.hospitalisation ? '<span class="badge bg-warning ms-2">Hospitalisation</span>' : ''}
                                </div>
                                ${consultation.symptomes ? '<div class="mt-1 text-muted"><i class="fas fa-notes-medical me-1"></i><small>' + escapeHtml(consultation.symptomes) + '</small></div>' : ''}
                            </div>
                        </div>
                    `;
                });

                suggestionsContainer.innerHTML = html;
                suggestionsContainer.classList.add('show');
                currentFocus = -1;

                document.querySelectorAll('.autocomplete-item').forEach(item => {
                    item.addEventListener('click', function() {
                        window.location.href = 'voir.php?id=' + this.getAttribute('data-id');
                    });
                    item.addEventListener('mouseenter', function() {
                        removeActiveClass();
                        this.classList.add('active');
                        currentFocus = parseInt(this.getAttribute('data-index'));
                    });
                });
            }

            function hideSuggestions() {
                suggestionsContainer.classList.remove('show');
                suggestionsContainer.innerHTML = '';
                currentFocus = -1;
            }

            function removeActiveClass() {
                document.querySelectorAll('.autocomplete-item').forEach(item => item.classList.remove('active'));
            }

            function addActiveClass() {
                const items = document.querySelectorAll('.autocomplete-item');
                if (!items.length) return;
                removeActiveClass();
                if (currentFocus >= items.length) currentFocus = 0;
                if (currentFocus < 0) currentFocus = items.length - 1;
                items[currentFocus].classList.add('active');
                items[currentFocus].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }

            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                if (searchTimeout) clearTimeout(searchTimeout);
                if (query.length < 2) {
                    hideSuggestions();
                    return;
                }
                suggestionsContainer.innerHTML = '<div class="autocomplete-loading"><i class="fas fa-spinner fa-spin me-2"></i>Recherche en cours...</div>';
                suggestionsContainer.classList.add('show');
                searchTimeout = setTimeout(() => {
                    fetch('api_suggestions.php?q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => showSuggestions(data, query))
                        .catch(() => {
                            suggestionsContainer.innerHTML = '<div class="autocomplete-no-results text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Erreur lors de la recherche</div>';
                        });
                }, 300);
            });

            searchInput.addEventListener('keydown', function(e) {
                const items = document.querySelectorAll('.autocomplete-item');
                if (e.key === 'ArrowDown') { e.preventDefault(); currentFocus++; addActiveClass(); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); currentFocus--; addActiveClass(); }
                else if (e.key === 'Enter' && currentFocus > -1 && items.length > 0) {
                    e.preventDefault();
                    items[currentFocus].click();
                } else if (e.key === 'Escape') hideSuggestions();
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.autocomplete-container')) hideSuggestions();
            });

            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    if (currentFocus > -1 && suggestionsContainer.classList.contains('show')) {
                        e.preventDefault();
                        const items = document.querySelectorAll('.autocomplete-item');
                        if (items[currentFocus]) items[currentFocus].click();
                    }
                });
            }
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
