<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('laboratoire'));

require_once __DIR__ . '/../models/Analyse.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Medecin.php';

$analyseModel = new Analyse();
$patientModel = new Patient();
$medecinModel = new Medecin();

$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';
$type_analyse = $_GET['type_analyse'] ?? '';
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;

$patient = null;
if ($patient_id) {
    $patient = $patientModel->getById($patient_id);
    if (!$patient) {
        header("Location: index.php");
        exit();
    }
}

$filters = [];
if ($search) $filters['search'] = $search;
if ($statut) $filters['statut'] = $statut;
if ($type_analyse) $filters['type_analyse'] = $type_analyse;
if ($patient_id) $filters['patient_id'] = $patient_id;

$analyses = $analyseModel->getAll($page, $limit, $filters);
$stats = $analyseModel->getStats();
$totalAnalyses = $analyseModel->count($filters);
$totalPages = (int) ceil($totalAnalyses / $limit);

$typesAnalyses = $analyseModel->getTypesAnalyses();
$statuts = $analyseModel->getStatuts();
$labTitle = ($patient_id && $patient)
    ? 'Analyses de ' . $patient['prenom'] . ' ' . $patient['nom']
    : 'Laboratoire';
$labToolbar = [
    ['href' => app_url('laboratoire/ajouter.php' . ($patient_id ? '?patient_id=' . $patient_id : '')), 'label' => 'Nouvelle Analyse', 'icon' => 'fa-plus'],
];
if ($patient_id) {
    $labToolbar[] = ['href' => app_url('patients/voir.php?id=' . $patient_id), 'label' => 'Retour au patient', 'icon' => 'fa-user', 'class' => 'btn-outline-secondary'];
    $labToolbar[] = ['href' => app_url('laboratoire/index.php'), 'label' => 'Toutes les analyses', 'icon' => 'fa-list', 'class' => 'btn-outline-secondary'];
} else {
    $labToolbar[] = ['href' => app_url('laboratoire/rapport.php'), 'label' => 'Rapport', 'icon' => 'fa-chart-bar', 'class' => 'btn-outline-secondary'];
}
app_module_page_start([
    'active'   => 'laboratoire',
    'title'    => $labTitle,
    'subtitle' => 'Analyses et résultats',
    'icon'     => 'fa-flask',
]);
app_module_toolbar($labToolbar);
app_module_flash();
?>

        <?php if ($patient_id && $patient): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-filter me-2"></i>
            <strong>Filtre actif :</strong> Affichage des analyses de
            <strong><?php echo htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']); ?></strong>
            (Dossier: <?php echo htmlspecialchars($patient['numero_dossier']); ?>)
            - <strong><?php echo $totalAnalyses; ?></strong> analyse(s) trouvée(s)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

<?php require __DIR__ . '/_list_view.php'; ?>

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
                if (!dateStr) return 'N/A';
                const date = new Date(dateStr);
                return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
            }

            function showSuggestions(suggestions, query) {
                if (suggestions.length === 0) {
                    suggestionsContainer.innerHTML = '<div class="autocomplete-no-results"><i class="fas fa-search me-2"></i>Aucune analyse trouvée</div>';
                    suggestionsContainer.classList.add('show');
                    return;
                }

                let html = '';
                suggestions.forEach((analyse, index) => {
                    let badgeColor = 'secondary';
                    if (analyse.statut === 'termine') badgeColor = 'success';
                    else if (analyse.statut === 'en_cours') badgeColor = 'info';
                    else if (analyse.statut === 'en_attente') badgeColor = 'warning';
                    else if (analyse.statut === 'annule') badgeColor = 'danger';

                    html += `
                        <div class="autocomplete-item" data-index="${index}" data-id="${analyse.id}">
                            <div class="autocomplete-analyse-header">
                                <div class="autocomplete-analyse-patient">
                                    <i class="fas fa-flask me-2 text-info"></i>
                                    ${highlightText(analyse.type_analyse, query)}
                                    <span class="badge bg-${badgeColor} autocomplete-analyse-badge">${escapeHtml(analyse.statut)}</span>
                                    ${analyse.urgence ? '<span class="badge bg-danger autocomplete-analyse-badge">URGENT</span>' : ''}
                                </div>
                            </div>
                            <div class="autocomplete-analyse-details">
                                <div class="mb-1">
                                    <i class="fas fa-user-injured me-1 text-primary"></i>${highlightText(analyse.patient_nom_complet, query)}
                                    <span class="ms-3"><i class="fas fa-folder me-1"></i>${escapeHtml(analyse.patient_numero_dossier)}</span>
                                </div>
                                <div class="mb-1">
                                    <i class="fas fa-hashtag me-1"></i>${highlightText(analyse.numero_analyse, query)}
                                    <span class="ms-3"><i class="fas fa-calendar me-1"></i>Prélèvement: ${formatDate(analyse.date_prelevement)}</span>
                                    ${analyse.date_resultat ? '<span class="ms-3"><i class="fas fa-check-circle me-1 text-success"></i>Résultat: ' + formatDate(analyse.date_resultat) + '</span>' : ''}
                                </div>
                                ${analyse.medecin_nom_complet && analyse.medecin_nom_complet.trim() ? '<div><i class="fas fa-user-md me-1 text-success"></i>' + highlightText(analyse.medecin_nom_complet, query) + '</div>' : ''}
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
