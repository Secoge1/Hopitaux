<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('finances'));

require_once __DIR__ . '/../models/Finances.php';

$financesModel = new Finances();

$date_debut = isset($_GET['date_debut']) ? trim($_GET['date_debut']) : date('Y-01-01');
$date_fin = isset($_GET['date_fin']) ? trim($_GET['date_fin']) : date('Y-m-d');

if ($date_debut === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut)) {
    $date_debut = date('Y-01-01');
}
if ($date_fin === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fin)) {
    $date_fin = date('Y-m-d');
}
if (strtotime($date_debut) > strtotime($date_fin)) {
    [$date_debut, $date_fin] = [$date_fin, $date_debut];
}

try {
    $bilan = $financesModel->getBilan($date_debut, $date_fin);
    $comptesActifs = $financesModel->getComptes(1, 200, '', 'actif', 'actif');
    $comptesPassifs = $financesModel->getComptes(1, 200, '', 'passif', 'actif');
} catch (Exception $e) {
    die('Erreur : ' . htmlspecialchars($e->getMessage()));
}

$fmtFcfa = static function ($amount): string {
    return number_format((float) $amount, 0, ',', ' ');
};

$ecartBilan = (float) $bilan['actifs'] - (float) $bilan['passifs'];
$bilanEquilibre = abs($ecartBilan) < 0.01;

app_module_page_start([
    'active'    => 'finances',
    'title'     => 'Bilan comptable',
    'subtitle'  => 'Situation patrimoniale et compte de résultat',
    'icon'      => 'fa-balance-scale',
    'extra_css' => ['assets/css/app-finances.css'],
]);
$printBilanUrl = app_url('finances/imprimer_bilan.php?date_debut=' . urlencode($date_debut) . '&date_fin=' . urlencode($date_fin) . '&print=1');
app_module_toolbar([
    ['href' => app_url('finances/index.php'), 'label' => 'Écritures', 'icon' => 'fa-list', 'class' => 'btn-outline-secondary'],
    ['href' => app_url('finances/comptes.php'), 'label' => 'Comptes', 'icon' => 'fa-book', 'class' => 'btn-outline-secondary'],
    ['href' => app_url('finances/budgets.php'), 'label' => 'Budgets', 'icon' => 'fa-chart-pie', 'class' => 'btn-outline-secondary'],
    ['href' => $printBilanUrl, 'label' => 'Imprimer', 'icon' => 'fa-print', 'class' => 'btn-outline-primary', 'target' => '_blank'],
]);
app_module_flash();
?>

<div class="fin-bilan-filters app-mod-filter mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4 col-lg-3">
            <label class="form-label small text-muted mb-1" for="bilanDateDebut">Date début (résultat)</label>
            <input type="date" class="form-control form-control-sm" id="bilanDateDebut" name="date_debut"
                   value="<?= htmlspecialchars($date_debut) ?>">
        </div>
        <div class="col-md-4 col-lg-3">
            <label class="form-label small text-muted mb-1" for="bilanDateFin">Date fin (résultat)</label>
            <input type="date" class="form-control form-control-sm" id="bilanDateFin" name="date_fin"
                   value="<?= htmlspecialchars($date_fin) ?>">
        </div>
        <div class="col-lg-3">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-sync-alt me-1"></i>Actualiser
            </button>
        </div>
    </form>
    <p class="small text-muted mb-0 mt-2">
        Période du résultat : <strong><?= date('d/m/Y', strtotime($date_debut)) ?></strong>
        au <strong><?= date('d/m/Y', strtotime($date_fin)) ?></strong>.
        Les soldes actif/passif reflètent l'état actuel des comptes.
    </p>
</div>

<?php
app_mod_stats([
    ['value' => $fmtFcfa($bilan['actifs']), 'label' => 'Total actif (FCFA)', 'icon' => 'fa-landmark', 'mod' => 'teal'],
    ['value' => $fmtFcfa($bilan['passifs']), 'label' => 'Total passif (FCFA)', 'icon' => 'fa-balance-scale', 'mod' => 'amber'],
    ['value' => $fmtFcfa($bilan['produits']), 'label' => 'Produits période', 'icon' => 'fa-arrow-up', 'mod' => 'teal'],
    ['value' => $fmtFcfa($bilan['charges']), 'label' => 'Charges période', 'icon' => 'fa-arrow-down', 'mod' => 'amber'],
    ['value' => $fmtFcfa($bilan['resultat']), 'label' => 'Résultat net', 'icon' => 'fa-chart-line', 'mod' => ($bilan['resultat'] >= 0 ? 'teal' : 'amber')],
], 'fin-comptes-totaux mb-4');
?>

<?php if (!$bilanEquilibre): ?>
<div class="alert alert-warning fin-bilan-alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    Écart actif/passif : <strong><?= $fmtFcfa(abs($ecartBilan)) ?> FCFA</strong>
    (<?= $ecartBilan > 0 ? 'actif &gt; passif' : 'passif &gt; actif' ?>).
    Vérifiez les écritures et les soldes des comptes.
</div>
<?php else: ?>
<div class="alert alert-success fin-bilan-alert">
    <i class="fas fa-check-circle me-2"></i>Bilan équilibré : total actif = total passif.
</div>
<?php endif; ?>

<div class="row g-4 fin-bilan-grid">
    <div class="col-lg-6">
        <div class="fin-panel h-100">
            <div class="fin-panel-head">
                <h2><i class="fas fa-landmark me-2"></i>Bilan — Actif</h2>
                <span class="fin-count-badge">
                    <strong><?= $fmtFcfa($bilan['actifs']) ?></strong> FCFA
                </span>
            </div>
            <div class="fin-panel-body">
                <?php if (empty($comptesActifs)): ?>
                <p class="text-muted mb-0">Aucun compte actif enregistré.</p>
                <?php else: ?>
                <?php foreach ($comptesActifs as $compte): ?>
                <div class="fin-compte-row">
                    <div class="min-w-0 flex-grow-1">
                        <span class="mod-code"><?= htmlspecialchars((string) ($compte['numero_compte'] ?? '')) ?></span>
                        <div class="fin-compte-lib"><?= htmlspecialchars((string) ($compte['libelle'] ?? $compte['nom_compte'] ?? '')) ?></div>
                    </div>
                    <div class="text-end fin-montant"><?= $fmtFcfa($compte['solde_actuel'] ?? 0) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="fin-panel h-100">
            <div class="fin-panel-head" style="background: linear-gradient(135deg, #b45309 0%, #d97706 55%, #f59e0b 100%);">
                <h2><i class="fas fa-balance-scale me-2"></i>Bilan — Passif</h2>
                <span class="fin-count-badge">
                    <strong><?= $fmtFcfa($bilan['passifs']) ?></strong> FCFA
                </span>
            </div>
            <div class="fin-panel-body">
                <?php if (empty($comptesPassifs)): ?>
                <p class="text-muted mb-0">Aucun compte passif enregistré.</p>
                <?php else: ?>
                <?php foreach ($comptesPassifs as $compte): ?>
                <div class="fin-compte-row">
                    <div class="min-w-0 flex-grow-1">
                        <span class="mod-code"><?= htmlspecialchars((string) ($compte['numero_compte'] ?? '')) ?></span>
                        <div class="fin-compte-lib"><?= htmlspecialchars((string) ($compte['libelle'] ?? $compte['nom_compte'] ?? '')) ?></div>
                    </div>
                    <div class="text-end fin-montant"><?= $fmtFcfa($compte['solde_actuel'] ?? 0) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="fin-panel">
            <div class="fin-panel-head" style="background: linear-gradient(135deg, #4338ca 0%, #6366f1 55%, #818cf8 100%);">
                <h2><i class="fas fa-chart-line me-2"></i>Compte de résultat</h2>
                <span class="fin-count-badge">
                    Résultat : <strong class="<?= $bilan['resultat'] >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $fmtFcfa($bilan['resultat']) ?> FCFA
                    </strong>
                </span>
            </div>
            <div class="fin-panel-body">
                <div class="row g-3 fin-bilan-resultat">
                    <div class="col-md-4">
                        <div class="fin-bilan-line fin-bilan-line--produit">
                            <span class="fin-bilan-line-label">Total produits</span>
                            <span class="fin-bilan-line-val"><?= $fmtFcfa($bilan['produits']) ?> FCFA</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="fin-bilan-line fin-bilan-line--charge">
                            <span class="fin-bilan-line-label">Total charges</span>
                            <span class="fin-bilan-line-val"><?= $fmtFcfa($bilan['charges']) ?> FCFA</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="fin-bilan-line fin-bilan-line--resultat">
                            <span class="fin-bilan-line-label">Résultat net</span>
                            <span class="fin-bilan-line-val <?= $bilan['resultat'] >= 0 ? 'fin-solde-pos' : 'fin-solde-neg' ?>">
                                <?= $fmtFcfa($bilan['resultat']) ?> FCFA
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php app_module_page_end(); ?>
