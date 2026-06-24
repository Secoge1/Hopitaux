<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('finances'));
$canWriteFinance = module_can_write('finances');

require_once __DIR__ . '/../models/Finances.php';

$financesModel = new Finances();

$annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y');
$message = '';
$error = '';

$categories = [
    'medecine'    => 'Médecine',
    'pharmacie'   => 'Pharmacie',
    'equipement'  => 'Équipement',
    'personnel'   => 'Personnel',
    'maintenance' => 'Maintenance',
    'autre'       => 'Autre',
];

$statuts = [
    'planifie'  => 'Planifié',
    'approuve'  => 'Approuvé',
    'en_cours'  => 'En cours',
    'cloture'   => 'Clôturé',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$canWriteFinance) {
        header('Location: ' . app_url('finances/budgets.php?error=access_denied'));
        exit;
    }

    try {
        switch ($_POST['action']) {
            case 'create':
                $data = [
                    'annee'          => (int) $_POST['annee'],
                    'departement'    => trim((string) ($_POST['departement'] ?? '')) ?: null,
                    'categorie'      => (string) $_POST['categorie'],
                    'montant_alloue' => (float) $_POST['montant_alloue'],
                    'statut'         => (string) ($_POST['statut'] ?? 'planifie'),
                    'notes'          => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'cree_par'       => $auth->getUtilisateur()['id'] ?? null,
                ];
                if ($financesModel->createBudget($data)) {
                    $message = 'Budget créé avec succès.';
                } else {
                    $error = 'Erreur lors de la création du budget.';
                }
                break;

            case 'update':
                $budgetId = (int) ($_POST['budget_id'] ?? 0);
                if ($budgetId < 1) {
                    $error = 'Budget introuvable.';
                    break;
                }
                $data = [
                    'annee'          => (int) $_POST['annee'],
                    'departement'    => trim((string) ($_POST['departement'] ?? '')) ?: null,
                    'categorie'      => (string) $_POST['categorie'],
                    'montant_alloue' => (float) $_POST['montant_alloue'],
                    'statut'         => (string) $_POST['statut'],
                    'notes'          => trim((string) ($_POST['notes'] ?? '')) ?: null,
                ];
                if ($financesModel->updateBudget($budgetId, $data)) {
                    $message = 'Budget mis à jour.';
                } else {
                    $error = 'Erreur lors de la mise à jour.';
                }
                break;

            case 'delete':
                $budgetId = (int) ($_POST['budget_id'] ?? 0);
                if ($budgetId < 1 || !$financesModel->deleteBudget($budgetId)) {
                    $error = 'Impossible de supprimer ce budget.';
                } else {
                    $message = 'Budget supprimé.';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Erreur : ' . $e->getMessage();
    }
}

try {
    $budgets = $financesModel->getBudgets($annee);
    $stats = $financesModel->getStats();
} catch (Exception $e) {
    die('Erreur : ' . htmlspecialchars($e->getMessage()));
}

$totalAlloue = 0.0;
$totalUtilise = 0.0;
foreach ($budgets as $b) {
    $totalAlloue += (float) ($b['montant_alloue'] ?? 0);
    $totalUtilise += (float) ($b['montant_utilise'] ?? 0);
}

app_module_page_start([
    'active'    => 'finances',
    'title'     => 'Budgets',
    'subtitle'  => 'Suivi budgétaire par catégorie et département',
    'icon'      => 'fa-chart-pie',
    'extra_css' => ['assets/css/app-finances.css'],
]);
app_module_toolbar([
    ['href' => app_url('finances/bilan.php'), 'label' => 'Bilan', 'icon' => 'fa-balance-scale', 'class' => 'btn-outline-secondary'],
    ['href' => app_url('finances/index.php'), 'label' => 'Écritures', 'icon' => 'fa-list', 'class' => 'btn-outline-secondary'],
    ['href' => app_url('finances/comptes.php'), 'label' => 'Comptes', 'icon' => 'fa-book', 'class' => 'btn-outline-secondary'],
]);
app_module_flash();
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
<div class="alert alert-warning alert-dismissible fade show">
    <i class="fas fa-lock me-2"></i>Vous n'avez pas les droits d'écriture sur les budgets.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php app_mod_stats([
    ['value' => count($budgets), 'label' => 'Lignes budgétaires', 'icon' => 'fa-chart-pie'],
    ['value' => number_format($totalAlloue, 0, ',', ' '), 'label' => 'Total alloué (FCFA)', 'icon' => 'fa-coins', 'mod' => 'teal'],
    ['value' => number_format($totalUtilise, 0, ',', ' '), 'label' => 'Total consommé (FCFA)', 'icon' => 'fa-money-bill-wave', 'mod' => 'amber'],
    ['value' => number_format($stats['budget_annuel'] ?? 0, 0, ',', ' '), 'label' => 'Budget approuvé année', 'icon' => 'fa-check-circle'],
], 'mb-4'); ?>

<div class="fin-panel mb-4">
    <div class="fin-panel-head fin-panel-head--violet">
        <h2><i class="fas fa-filter me-2"></i>Année <?= (int) $annee ?></h2>
        <?php if ($canWriteFinance): ?>
        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalBudgetCreate">
            <i class="fas fa-plus me-1"></i>Nouveau budget
        </button>
        <?php endif; ?>
    </div>
    <div class="fin-panel-body">
        <div class="app-mod-filter mb-0">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1" for="annee">Année</label>
                    <input type="number" class="form-control form-control-sm" id="annee" name="annee"
                           value="<?= (int) $annee ?>" min="2020" max="2100" style="width: 7rem;">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search me-1"></i>Afficher
                    </button>
                </div>
            </form>
            <p class="small text-muted mb-0 mt-3">
                Consommation estimée depuis les <strong>écritures de charges validées</strong>,
                rapprochées par catégorie et département.
            </p>
        </div>
    </div>
</div>

<?php if (empty($budgets)): ?>
<div class="app-mod-empty">
    <i class="fas fa-chart-pie d-block"></i>
    <h5 class="mb-2">Aucun budget</h5>
    <p class="mb-3 text-muted">Aucun budget défini pour l'année <?= (int) $annee ?>.</p>
    <?php if ($canWriteFinance): ?>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBudgetCreate">
        <i class="fas fa-plus me-1"></i>Créer un budget
    </button>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="row g-4 fin-budgets-grid">
    <?php foreach ($budgets as $budget):
        $bid = (int) $budget['id'];
        $categorieClass = $budget['categorie'] ?? 'autre';
        $montantDepense = (float) ($budget['montant_utilise'] ?? 0);
        $montantAlloue = (float) $budget['montant_alloue'];
        $pourcentage = $montantAlloue > 0 ? ($montantDepense / $montantAlloue) * 100 : 0;
        $progressClass = $pourcentage >= 90 ? 'danger' : ($pourcentage >= 70 ? 'warning' : 'success');
        $statutKey = $budget['statut'] ?? 'planifie';
        $catLabel = $categories[$categorieClass] ?? ucfirst($categorieClass);
        $deptLabel = trim((string) ($budget['departement'] ?? ''));
        $cardTitle = $deptLabel !== '' ? $deptLabel : $catLabel;
        $cardSubtitle = $deptLabel !== '' ? $catLabel : '';
    ?>
    <div class="col-md-6 col-xl-6 d-flex">
        <article class="card fin-budget-card fin-budget-card--<?= htmlspecialchars($categorieClass) ?> w-100 shadow-sm">
            <div class="card-header border-bottom">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <h3 class="h6 fw-bold mb-1 text-truncate"><?= htmlspecialchars($cardTitle) ?></h3>
                        <?php if ($cardSubtitle !== ''): ?>
                        <p class="small text-muted mb-0"><?= htmlspecialchars($cardSubtitle) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <span class="badge rounded-pill text-bg-secondary mb-1"><?= (int) $budget['annee'] ?></span><br>
                        <?= app_mod_badge($statutKey, $statuts[$statutKey] ?? ucfirst($statutKey)) ?>
                    </div>
                </div>
            </div>
            <div class="card-body d-flex flex-column">
                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="fin-budget-kpi rounded-3 p-2 h-100">
                            <div class="fin-budget-kpi-label">Alloué</div>
                            <div class="fin-budget-kpi-val"><?= number_format($montantAlloue, 0, ',', ' ') ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="fin-budget-kpi rounded-3 p-2 h-100">
                            <div class="fin-budget-kpi-label">Consommé</div>
                            <div class="fin-budget-kpi-val text-<?= $progressClass ?>"><?= number_format($montantDepense, 0, ',', ' ') ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="fin-budget-kpi rounded-3 p-2 h-100">
                            <div class="fin-budget-kpi-label">Reste</div>
                            <div class="fin-budget-kpi-val"><?= number_format(max(0, $montantAlloue - $montantDepense), 0, ',', ' ') ?></div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Progression</span>
                        <span class="fw-semibold"><?= number_format($pourcentage, 1) ?> %</span>
                    </div>
                    <div class="progress fin-budget-progress" style="height: 12px;">
                        <div class="progress-bar bg-<?= $progressClass ?>" style="width: <?= min($pourcentage, 100) ?>%"></div>
                    </div>
                </div>
                <?php if (!empty($budget['notes'])): ?>
                <p class="small text-muted mb-3"><?= nl2br(htmlspecialchars($budget['notes'])) ?></p>
                <?php endif; ?>
                <?php if ($canWriteFinance): ?>
                <div class="mt-auto pt-3 border-top d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary js-edit-budget"
                            data-budget="<?= htmlspecialchars(json_encode([
                                'id' => $bid,
                                'annee' => (int) $budget['annee'],
                                'departement' => $budget['departement'] ?? '',
                                'categorie' => $categorieClass,
                                'montant_alloue' => $montantAlloue,
                                'statut' => $statutKey,
                                'notes' => $budget['notes'] ?? '',
                            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fas fa-edit me-1"></i>Modifier
                    </button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce budget ?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="budget_id" value="<?= $bid ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Supprimer</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </article>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($canWriteFinance): ?>
<div class="modal fade" id="modalBudgetCreate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header fin-modal-header">
                <h5 class="modal-title text-white"><i class="fas fa-plus me-2"></i>Nouveau budget</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <?php require __DIR__ . '/_budget_form_fields.php'; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalBudgetEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header fin-modal-header">
                <h5 class="modal-title text-white"><i class="fas fa-edit me-2"></i>Modifier le budget</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formBudgetEdit">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="budget_id" id="editBudgetId" value="">
                <div class="modal-body">
                    <?php
                    $formPrefix = 'edit';
                    require __DIR__ . '/_budget_form_fields.php';
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php ob_start(); ?>
<script>
document.querySelectorAll('.js-edit-budget').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var data = JSON.parse(this.getAttribute('data-budget') || '{}');
        document.getElementById('editBudgetId').value = data.id || '';
        document.getElementById('edit_annee').value = data.annee || '';
        document.getElementById('edit_departement').value = data.departement || '';
        document.getElementById('edit_categorie').value = data.categorie || '';
        document.getElementById('edit_montant_alloue').value = data.montant_alloue || '';
        document.getElementById('edit_statut').value = data.statut || 'planifie';
        document.getElementById('edit_notes').value = data.notes || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalBudgetEdit')).show();
    });
});
</script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
