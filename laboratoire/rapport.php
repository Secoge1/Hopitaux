<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/pdf_branding.php';
require_once __DIR__ . '/../models/Analyse.php';
require_once __DIR__ . '/../models/Medecin.php';

$auth = Auth::getInstance();
$auth->requireAuth();
module_require_roles('laboratoire');

require_once __DIR__ . '/../includes/app_module_layout.php';

$analyseModel = new Analyse();
$medecinModel = new Medecin();

$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$statut = $_GET['statut'] ?? '';
$type_analyse = $_GET['type_analyse'] ?? '';
$medecin_id = $_GET['medecin_id'] ?? '';
$print_mode = isset($_GET['print']) && $_GET['print'] === '1';

$filters = array_filter([
    'date_debut' => $date_debut,
    'date_fin' => $date_fin,
    'statut' => $statut,
    'type_analyse' => $type_analyse,
    'medecin_id' => $medecin_id,
], static fn($v) => $v !== '' && $v !== null);

$analyses = $analyseModel->getAll(1, 1000, $filters);
$typesAnalyses = $analyseModel->getTypesAnalyses();
$statuts = $analyseModel->getStatuts();
$medecins = $medecinModel->getAll(1, 1000);

$totalAnalyses = count($analyses);
$analysesParStatut = [];
$analysesParType = [];

foreach ($analyses as $analyse) {
    $sk = $analyse['statut'] ?? 'inconnu';
    $tk = $analyse['type_analyse'] ?? 'inconnu';
    $analysesParStatut[$sk] = ($analysesParStatut[$sk] ?? 0) + 1;
    $analysesParType[$tk] = ($analysesParType[$tk] ?? 0) + 1;
}

function lr_filter_query(array $extra = []): string
{
    global $date_debut, $date_fin, $statut, $type_analyse, $medecin_id;
    $params = array_filter([
        'date_debut' => $date_debut,
        'date_fin' => $date_fin,
        'statut' => $statut,
        'type_analyse' => $type_analyse,
        'medecin_id' => $medecin_id,
    ], static fn($v) => $v !== '' && $v !== null);
    return http_build_query(array_merge($params, $extra));
}

function lr_filter_summary(array $statuts, array $typesAnalyses, array $medecins): string
{
    global $date_debut, $date_fin, $statut, $type_analyse, $medecin_id;

    $parts = [
        'Période : ' . date('d/m/Y', strtotime($date_debut)) . ' → ' . date('d/m/Y', strtotime($date_fin)),
    ];
    if ($statut !== '') {
        $parts[] = 'Statut : ' . ($statuts[$statut] ?? $statut);
    }
    if ($type_analyse !== '') {
        $parts[] = 'Type : ' . ($typesAnalyses[$type_analyse] ?? $type_analyse);
    }
    if ($medecin_id !== '') {
        foreach ($medecins as $m) {
            if ((string) $m['id'] === (string) $medecin_id) {
                $parts[] = medecin_profil_attribution_label_from_row($m, 'medecin') . ' : ' . medecin_profil_format_name($m);
                break;
            }
        }
    }
    return implode(' · ', $parts);
}

function lr_badge_class(string $kind, string $value): string
{
    $safe = preg_replace('/[^a-z0-9_]/', '', strtolower($value));
    return 'lab-doc-badge lab-doc-badge--' . ($safe ?: 'normale');
}

function lr_render_print_doc(
    array $analyses,
    int $totalAnalyses,
    array $analysesParStatut,
    array $analysesParType,
    array $statuts,
    array $typesAnalyses,
    array $medecins,
    SystemParameters $systemParams
): string {
    $nom = $systemParams->get('nom_etablissement') ?: 'Établissement de santé';
    $filterSummary = lr_filter_summary($statuts, $typesAnalyses, $medecins);

    ob_start();
    ?>
<div class="lab-doc">
    <header class="lab-doc-head">
        <div class="lab-doc-brand">
            <?= $systemParams->getPdfLogoBlockHtml(['align' => 'left', 'max_height' => 52, 'max_width' => 140, 'margin_bottom' => '6px']) ?>
            <h1><?= htmlspecialchars($nom) ?></h1>
            <p>Rapport d'activité — Laboratoire</p>
        </div>
        <div class="lab-doc-meta">
            <div class="lab-doc-meta-kicker">Généré le</div>
            <div class="lab-doc-meta-date"><?= date('d/m/Y H:i') ?></div>
        </div>
    </header>

    <div class="lab-doc-strip">
        <div class="lab-doc-strip-item">
            <span class="lab-doc-strip-label">Total</span>
            <span class="lab-doc-strip-value"><?= $totalAnalyses ?></span>
        </div>
        <div class="lab-doc-strip-item">
            <span class="lab-doc-strip-label">En attente</span>
            <span class="lab-doc-strip-value"><?= (int) ($analysesParStatut['en_attente'] ?? 0) ?></span>
        </div>
        <div class="lab-doc-strip-item">
            <span class="lab-doc-strip-label">En cours</span>
            <span class="lab-doc-strip-value"><?= (int) ($analysesParStatut['en_cours'] ?? 0) ?></span>
        </div>
        <div class="lab-doc-strip-item">
            <span class="lab-doc-strip-label">Terminées</span>
            <span class="lab-doc-strip-value"><?= (int) ($analysesParStatut['termine'] ?? 0) ?></span>
        </div>
    </div>

    <div class="lab-doc-body">
        <div class="lab-doc-filters"><?= htmlspecialchars($filterSummary) ?></div>

        <?php if ($totalAnalyses > 0): ?>
        <div class="lab-doc-grid-2">
            <section class="lab-doc-section">
                <h2 class="lab-doc-section-title">Par statut</h2>
                <table class="lab-doc-table">
                    <thead><tr><th>Statut</th><th>Nb</th><th>%</th></tr></thead>
                    <tbody>
                    <?php foreach ($analysesParStatut as $key => $count): ?>
                        <tr>
                            <td><?= htmlspecialchars($statuts[$key] ?? ucfirst(str_replace('_', ' ', $key))) ?></td>
                            <td><strong><?= (int) $count ?></strong></td>
                            <td><?= number_format(($count / $totalAnalyses) * 100, 1) ?> %</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            <section class="lab-doc-section">
                <h2 class="lab-doc-section-title">Par type</h2>
                <table class="lab-doc-table">
                    <thead><tr><th>Type</th><th>Nb</th><th>%</th></tr></thead>
                    <tbody>
                    <?php foreach ($analysesParType as $key => $count): ?>
                        <tr>
                            <td><?= htmlspecialchars($typesAnalyses[$key] ?? ucfirst(str_replace('_', ' ', $key))) ?></td>
                            <td><strong><?= (int) $count ?></strong></td>
                            <td><?= number_format(($count / $totalAnalyses) * 100, 1) ?> %</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <section class="lab-doc-section">
            <h2 class="lab-doc-section-title">Liste des analyses (<?= $totalAnalyses ?>)</h2>
            <table class="lab-doc-table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Patient</th>
                        <th>Médecin</th>
                        <th>Type</th>
                        <th>Priorité</th>
                        <th>Statut</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($analyses as $a): ?>
                    <tr>
                        <td>#<?= (int) $a['id'] ?></td>
                        <td>
                            <?= htmlspecialchars(trim(($a['patient_nom'] ?? '') . ' ' . ($a['patient_prenom'] ?? ''))) ?>
                            <?php if (!empty($a['numero_dossier'])): ?>
                            <br><small><?= htmlspecialchars($a['numero_dossier']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars(medecin_profil_format_joined($a)) ?></td>
                        <td><?= htmlspecialchars($typesAnalyses[$a['type_analyse'] ?? ''] ?? ($a['type_analyse'] ?? '—')) ?></td>
                        <td><span class="<?= lr_badge_class('priorite', $a['priorite'] ?? 'normale') ?>"><?= ucfirst($a['priorite'] ?? '—') ?></span></td>
                        <td><span class="<?= lr_badge_class('statut', $a['statut'] ?? '') ?>"><?= htmlspecialchars($statuts[$a['statut'] ?? ''] ?? ucfirst($a['statut'] ?? '—')) ?></span></td>
                        <td><?= !empty($a['date_creation']) ? date('d/m/Y H:i', strtotime($a['date_creation'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php else: ?>
        <p style="text-align:center;color:#64748b;font-size:8pt;padding:8mm 0;">Aucune analyse pour les critères sélectionnés.</p>
        <?php endif; ?>

        <footer class="lab-doc-foot">
            Document généré automatiquement · <?= htmlspecialchars($nom) ?>
        </footer>
    </div>
</div>
    <?php
    return (string) ob_get_clean();
}

$systemParams = pdf_tenant_system_params();
$printHtml = lr_render_print_doc($analyses, $totalAnalyses, $analysesParStatut, $analysesParType, $statuts, $typesAnalyses, $medecins, $systemParams);

if ($print_mode) {
    $cssUrl = htmlspecialchars(app_url('assets/css/lab-rapport.css'));
    $backQs = lr_filter_query();
    $backUrl = htmlspecialchars(app_url('laboratoire/rapport.php' . ($backQs ? '?' . $backQs : '')));
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport laboratoire — <?= date('d/m/Y') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= $cssUrl ?>" rel="stylesheet">
</head>
<body class="lab-print-page">
    <div class="lab-print-controls">
        <button type="button" class="btn-lr btn-lr--primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimer</button>
        <a href="<?= $backUrl ?>" class="btn-lr btn-lr--ghost"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
    <div class="lab-print-wrap"><?= $printHtml ?></div>
    <script>setTimeout(function () { window.print(); }, 350);</script>
</body>
</html>
    <?php
    exit;
}

extract(app_module_context('laboratoire'));

$printUrl = app_url('laboratoire/rapport.php?' . lr_filter_query(['print' => '1']));

app_module_page_start([
    'active'    => 'laboratoire',
    'title'     => 'Rapport laboratoire',
    'subtitle'  => 'Statistiques et synthèse des analyses',
    'icon'      => 'fa-chart-bar',
    'extra_css' => ['assets/css/lab-rapport.css'],
]);

app_module_back_toolbar(app_url('laboratoire/index.php'), 'Retour à la liste', [
    ['href' => $printUrl, 'label' => 'Imprimer', 'icon' => 'fa-print', 'class' => 'btn-primary'],
    ['href' => app_url('laboratoire/ajouter.php'), 'label' => 'Nouvelle analyse', 'icon' => 'fa-plus', 'class' => 'btn-outline-primary'],
]);
app_module_flash();
?>

<div class="app-mod-filter mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3 col-lg-2">
            <label class="form-label small text-muted mb-1" for="date_debut">Date début</label>
            <input type="date" class="form-control form-control-sm" id="date_debut" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>">
        </div>
        <div class="col-md-3 col-lg-2">
            <label class="form-label small text-muted mb-1" for="date_fin">Date fin</label>
            <input type="date" class="form-control form-control-sm" id="date_fin" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-1" for="statut">Statut</label>
            <select class="form-select form-select-sm" id="statut" name="statut">
                <option value="">Tous</option>
                <?php foreach ($statuts as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= $statut === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-1" for="type_analyse">Type</label>
            <select class="form-select form-select-sm" id="type_analyse" name="type_analyse">
                <option value="">Tous</option>
                <?php foreach ($typesAnalyses as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= $type_analyse === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted mb-1" for="medecin_id">Médecin</label>
            <select class="form-select form-select-sm" id="medecin_id" name="medecin_id">
                <option value="">Tous</option>
                <?php foreach ($medecins as $medecin): ?>
                <option value="<?= (int) $medecin['id'] ?>" <?= (string) $medecin_id === (string) $medecin['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(medecin_profil_format_name($medecin)) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-12 col-lg-2">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter me-1"></i>Filtrer</button>
                <a href="rapport.php" class="btn btn-outline-secondary btn-sm" title="Réinitialiser"><i class="fas fa-times"></i></a>
            </div>
        </div>
    </form>
    <?php if ($statut || $type_analyse || $medecin_id || $date_debut !== date('Y-m-01') || $date_fin !== date('Y-m-d')): ?>
    <?php app_mod_filter_active($totalAnalyses, lr_filter_summary($statuts, $typesAnalyses, $medecins)); ?>
    <?php endif; ?>
</div>

<?php app_mod_stats([
    ['value' => $totalAnalyses, 'label' => 'Total analyses', 'icon' => 'fa-flask'],
    ['value' => $analysesParStatut['en_attente'] ?? 0, 'label' => 'En attente', 'icon' => 'fa-clock', 'mod' => 'amber'],
    ['value' => $analysesParStatut['en_cours'] ?? 0, 'label' => 'En cours', 'icon' => 'fa-spinner', 'mod' => 'teal'],
    ['value' => $analysesParStatut['termine'] ?? 0, 'label' => 'Terminées', 'icon' => 'fa-check-circle'],
]); ?>

<?php if ($totalAnalyses > 0): ?>
<div class="lab-rapport-charts">
    <div class="lab-rapport-chart-card">
        <div class="lab-rapport-chart-head"><i class="fas fa-chart-pie me-2"></i>Répartition par statut</div>
        <div class="lab-rapport-chart-body"><canvas id="statutChart"></canvas></div>
    </div>
    <div class="lab-rapport-chart-card">
        <div class="lab-rapport-chart-head lab-rapport-chart-head--type"><i class="fas fa-chart-bar me-2"></i>Répartition par type</div>
        <div class="lab-rapport-chart-body"><canvas id="typeChart"></canvas></div>
    </div>
</div>
<?php endif; ?>

<!-- Zone cachée incluse dans l'impression navigateur depuis la page module -->
<div class="lab-doc-print-area"><?= $printHtml ?></div>

<div class="app-mod-table-wrap">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 mod-list-table">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Patient</th>
                    <th>Médecin</th>
                    <th>Type</th>
                    <th>Priorité</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th class="text-end mod-actions-cell">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($totalAnalyses === 0): ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                        Aucune analyse ne correspond aux critères.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($analyses as $analyse):
                    $aid = (int) $analyse['id'];
                    $aActions = [
                        ['href' => 'voir.php?id=' . $aid, 'label' => 'Voir', 'icon' => 'fa-eye', 'tone' => 'primary'],
                        ['href' => 'modifier.php?id=' . $aid, 'label' => 'Modifier', 'icon' => 'fa-edit', 'tone' => 'warning'],
                        ['href' => 'export_analyse_pdf.php?id=' . $aid, 'label' => 'PDF', 'icon' => 'fa-file-pdf', 'tone' => 'danger', 'target' => '_blank'],
                    ];
                ?>
                <tr>
                    <td><strong class="text-primary">#<?= $aid ?></strong></td>
                    <td>
                        <?= htmlspecialchars(trim(($analyse['patient_nom'] ?? '') . ' ' . ($analyse['patient_prenom'] ?? ''))) ?>
                        <?php if (!empty($analyse['numero_dossier'])): ?>
                        <div class="mod-meta"><?= htmlspecialchars($analyse['numero_dossier']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(medecin_profil_format_joined($analyse)) ?></td>
                    <td><?= app_mod_badge($analyse['type_analyse'] ?? '', $typesAnalyses[$analyse['type_analyse'] ?? ''] ?? ucfirst($analyse['type_analyse'] ?? '')) ?></td>
                    <td><?= app_mod_badge($analyse['priorite'] ?? 'normale', ucfirst($analyse['priorite'] ?? '')) ?></td>
                    <td><?= app_mod_badge($analyse['statut'] ?? '', $statuts[$analyse['statut'] ?? ''] ?? ucfirst($analyse['statut'] ?? '')) ?></td>
                    <td class="text-nowrap text-muted small"><?= !empty($analyse['date_creation']) ? date('d/m/Y H:i', strtotime($analyse['date_creation'])) : '—' ?></td>
                    <td class="text-end mod-actions-cell"><?php app_mod_actions_dropdown($aActions); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalAnalyses > 0): ?>
<?php app_mod_list_count($totalAnalyses, $totalAnalyses, 'analyse(s)'); ?>
<?php endif; ?>

<?php if ($totalAnalyses > 0):
    ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var statutLabels = <?= json_encode(array_map(static fn($k) => $statuts[$k] ?? ucfirst(str_replace('_', ' ', $k)), array_keys($analysesParStatut)), JSON_UNESCAPED_UNICODE) ?>;
    var statutData = <?= json_encode(array_values($analysesParStatut)) ?>;
    var typeLabels = <?= json_encode(array_map(static fn($k) => $typesAnalyses[$k] ?? ucfirst(str_replace('_', ' ', $k)), array_keys($analysesParType)), JSON_UNESCAPED_UNICODE) ?>;
    var typeData = <?= json_encode(array_values($analysesParType)) ?>;

    var statutEl = document.getElementById('statutChart');
    if (statutEl && statutData.length) {
        new Chart(statutEl, {
            type: 'doughnut',
            data: {
                labels: statutLabels,
                datasets: [{
                    data: statutData,
                    backgroundColor: ['#fbbf24', '#38bdf8', '#34d399', '#94a3b8', '#f87171'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
            }
        });
    }

    var typeEl = document.getElementById('typeChart');
    if (typeEl && typeData.length) {
        new Chart(typeEl, {
            type: 'bar',
            data: {
                labels: typeLabels,
                datasets: [{
                    label: 'Analyses',
                    data: typeData,
                    backgroundColor: 'rgba(27, 143, 173, 0.75)',
                    borderColor: '#1b8fad',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } } },
                    x: { ticks: { font: { size: 10 } } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }
})();
</script>
<?php
    $GLOBALS['app_page_scripts'] = ob_get_clean();
endif;

app_module_page_end();
