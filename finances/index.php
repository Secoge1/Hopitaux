<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('finances'));

require_once __DIR__ . '/../models/Finances.php';

$financesModel = new Finances();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$date_debut = isset($_GET['date_debut']) ? trim($_GET['date_debut']) : '';
$date_fin = isset($_GET['date_fin']) ? trim($_GET['date_fin']) : '';
$valide = isset($_GET['valide']) ? trim($_GET['valide']) : '';

try {
    $stats = $financesModel->getStats();
    $ecritures = $financesModel->getEcritures($page, $limit, $date_debut, $date_fin, $valide);
    $total_ecritures = $financesModel->getCountEcritures($date_debut, $date_fin, $valide);
    $total_pages = ceil($total_ecritures / $limit);
    $comptes = $financesModel->getComptes(1, 10);
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

app_module_page_start([
    'active'    => 'finances',
    'title'     => 'Finances & Comptabilité',
    'subtitle'  => 'Écritures, comptes et budgets',
    'icon'      => 'fa-calculator',
    'extra_css' => ['assets/css/app-finances.css'],
]);
$finToolbar = [
    ['href' => app_url('finances/bilan.php'), 'label' => 'Bilan', 'icon' => 'fa-balance-scale', 'class' => 'btn-outline-secondary'],
    ['href' => app_url('finances/comptes.php'), 'label' => 'Comptes', 'icon' => 'fa-list', 'class' => 'btn-outline-secondary'],
    ['href' => app_url('finances/budgets.php'), 'label' => 'Budgets', 'icon' => 'fa-chart-pie', 'class' => 'btn-outline-secondary'],
];
if ($auth->peutEcrireFinances()) {
    array_unshift(
        $finToolbar,
        ['href' => app_url('finances/nouvelle_ecriture.php'), 'label' => 'Nouvelle Écriture', 'icon' => 'fa-plus'],
        ['href' => app_url('finances/nouveau_compte.php'), 'label' => 'Nouveau Compte', 'icon' => 'fa-book', 'class' => 'btn-outline-secondary']
    );
}
app_module_toolbar($finToolbar);
app_module_flash();
?>

        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>L'écriture a été supprimée avec succès.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>Opération effectuée avec succès.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <?php app_mod_stats([
            ['value' => $stats['comptes_actifs'] ?? 0, 'label' => 'Comptes actifs', 'icon' => 'fa-book'],
            ['value' => $stats['ecritures_en_attente'] ?? 0, 'label' => 'Écritures en attente', 'icon' => 'fa-clock', 'mod' => 'amber'],
            ['value' => number_format($stats['montant_aujourd_hui'] ?? 0, 0, ',', ' '), 'label' => "Montant aujourd'hui (FCFA)", 'icon' => 'fa-money-bill-wave', 'mod' => 'teal'],
            ['value' => number_format($stats['budget_annuel'] ?? 0, 0, ',', ' '), 'label' => 'Budget annuel (FCFA)', 'icon' => 'fa-chart-pie'],
        ]); ?>

        <div class="row g-4">
            <div class="col-lg-8 mb-4 mb-lg-0">
                <div class="fin-panel">
                    <div class="fin-panel-head">
                        <h2><i class="fas fa-list me-2"></i>Écritures comptables</h2>
                        <span class="fin-count-badge">
                            <i class="fas fa-file-invoice"></i>
                            <span><strong><?php echo (int) $total_ecritures; ?></strong> écriture<?php echo (int) $total_ecritures > 1 ? 's' : ''; ?></span>
                        </span>
                    </div>
                    <div class="fin-panel-body">
                        <div class="app-mod-filter">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-md-4 col-lg-3">
                                    <label class="form-label small text-muted mb-1" for="finDateDebut">Date début</label>
                                    <input type="date" class="form-control form-control-sm" id="finDateDebut" name="date_debut" value="<?php echo htmlspecialchars($date_debut); ?>">
                                </div>
                                <div class="col-md-4 col-lg-3">
                                    <label class="form-label small text-muted mb-1" for="finDateFin">Date fin</label>
                                    <input type="date" class="form-control form-control-sm" id="finDateFin" name="date_fin" value="<?php echo htmlspecialchars($date_fin); ?>">
                                </div>
                                <div class="col-md-4 col-lg-3">
                                    <label class="form-label small text-muted mb-1" for="finStatut">Statut</label>
                                    <select class="form-select form-select-sm" id="finStatut" name="valide">
                                        <option value="">Tous</option>
                                        <option value="1" <?php echo $valide === '1' ? 'selected' : ''; ?>>Validées</option>
                                        <option value="0" <?php echo $valide === '0' ? 'selected' : ''; ?>>En attente</option>
                                    </select>
                                </div>
                                <div class="col-lg-3">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                            <i class="fas fa-filter me-1"></i>Filtrer
                                        </button>
                                        <a href="index.php" class="btn btn-outline-secondary btn-sm" title="Effacer les filtres"><i class="fas fa-times"></i></a>
                                    </div>
                                </div>
                            </form>
                            <?php if ($date_debut || $date_fin || $valide !== ''): ?>
                            <?php app_mod_filter_active((int) $total_ecritures, implode(' · ', array_filter([
                                $date_debut ? 'Début : ' . date('d/m/Y', strtotime($date_debut)) : '',
                                $date_fin ? 'Fin : ' . date('d/m/Y', strtotime($date_fin)) : '',
                                $valide !== '' ? 'Statut : ' . ($valide === '1' ? 'Validées' : 'En attente') : '',
                            ]))); ?>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($ecritures)): ?>
                        <div class="app-mod-empty">
                            <i class="fas fa-file-invoice d-block"></i>
                            <h5 class="mb-2">Aucune écriture</h5>
                            <p class="mb-0 text-muted">Aucune écriture ne correspond à vos critères.</p>
                            <?php if ($date_debut || $date_fin || $valide !== ''): ?>
                            <a href="index.php" class="btn btn-outline-secondary btn-sm mt-3">Effacer les filtres</a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="app-mod-table-wrap">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 mod-list-table">
                                    <thead>
                                        <tr>
                                            <th>N° écriture</th>
                                            <th>Date</th>
                                            <th>Compte débit</th>
                                            <th>Compte crédit</th>
                                            <th class="text-end">Montant</th>
                                            <th>Statut</th>
                                            <th class="text-end mod-actions-cell">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ecritures as $ecriture): ?>
                                        <tr class="<?php echo !$ecriture['valide'] ? 'table-warning' : ''; ?>">
                                            <td class="text-nowrap">
                                                <strong class="text-primary"><?php echo htmlspecialchars($ecriture['numero_ecriture']); ?></strong>
                                            </td>
                                            <td class="text-nowrap text-muted small">
                                                <?php echo date('d/m/Y', strtotime($ecriture['date_ecriture'])); ?>
                                            </td>
                                            <td class="fin-compte-cell">
                                                <span class="fin-compte-tag fin-compte-tag--debit">D</span>
                                                <span class="mod-code"><?php echo htmlspecialchars($ecriture['compte_debit_num']); ?></span>
                                                <span class="fin-compte-lib"><?php echo htmlspecialchars($ecriture['compte_debit_lib']); ?></span>
                                            </td>
                                            <td class="fin-compte-cell">
                                                <span class="fin-compte-tag fin-compte-tag--credit">C</span>
                                                <span class="mod-code"><?php echo htmlspecialchars($ecriture['compte_credit_num']); ?></span>
                                                <span class="fin-compte-lib"><?php echo htmlspecialchars($ecriture['compte_credit_lib']); ?></span>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <span class="fin-montant"><?php echo number_format((float) $ecriture['montant'], 0, ',', ' '); ?></span>
                                                <small class="text-muted d-block">FCFA</small>
                                            </td>
                                            <td>
                                                <?php if ($ecriture['valide']): ?>
                                                <span class="mod-badge mod-badge--terminee">Validée</span>
                                                <?php else: ?>
                                                <span class="mod-badge mod-badge--en_attente">En attente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end mod-actions-cell">
                                                <?php
                                                $eid = (int) $ecriture['id'];
                                                $eActions = [
                                                    ['href' => 'voir_ecriture.php?id=' . $eid, 'label' => 'Voir', 'icon' => 'fa-eye', 'tone' => 'primary'],
                                                    ['href' => 'imprimer_ecriture.php?id=' . $eid, 'label' => 'Imprimer', 'icon' => 'fa-print', 'tone' => 'neutral', 'target' => '_blank'],
                                                ];
                                                if ($auth->peutEcrireFinances()) {
                                                    $eActions[] = ['href' => 'modifier_ecriture.php?id=' . $eid, 'label' => 'Modifier', 'icon' => 'fa-edit', 'tone' => 'warning'];
                                                    if (!$ecriture['valide']) {
                                                        $eActions[] = ['href' => 'valider_ecriture.php?id=' . $eid, 'label' => 'Valider', 'icon' => 'fa-check', 'tone' => 'success', 'onclick' => "return confirm('Valider cette écriture ?')"];
                                                    }
                                                    $eActions[] = ['divider' => true];
                                                    $eActions[] = ['href' => 'supprimer_ecriture.php?id=' . $eid, 'label' => 'Supprimer', 'icon' => 'fa-trash', 'tone' => 'danger', 'onclick' => "return confirm('Supprimer cette écriture ? Irréversible.')"];
                                                }
                                                app_mod_actions_dropdown($eActions);
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <?php if ($total_pages > 1): ?>
                        <?php app_mod_pagination($page, $total_pages, [
                            'date_debut' => $date_debut,
                            'date_fin' => $date_fin,
                            'valide' => $valide,
                        ], 'Pagination écritures'); ?>
                        <?php endif; ?>

                        <?php app_mod_list_count(count($ecritures), (int) $total_ecritures, 'écriture(s)'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4 mb-lg-0">
                <div class="fin-sidebar-panel">
                    <div class="fin-sidebar-head">
                        <h2><i class="fas fa-book me-2"></i>Comptes principaux</h2>
                    </div>
                    <div class="fin-sidebar-body">
                        <?php if (empty($comptes)): ?>
                            <p class="text-muted text-center mb-0 py-3">Aucun compte</p>
                        <?php else: ?>
                            <?php foreach ($comptes as $compte): ?>
                            <?php
                                $libelleCompte = $compte['libelle'] ?? $compte['nom_compte'] ?? '';
                                $soldeAct = isset($compte['solde_actuel']) ? (float) $compte['solde_actuel'] : 0.0;
                            ?>
                            <div class="fin-compte-row">
                                <div class="min-w-0 flex-grow-1">
                                    <span class="mod-code"><?php echo htmlspecialchars((string) ($compte['numero_compte'] ?? '')); ?></span>
                                    <div class="fin-compte-lib"><?php echo htmlspecialchars((string) $libelleCompte); ?></div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <?php echo app_mod_badge($compte['type_compte'] ?? 'actif', ucfirst($compte['type_compte'] ?? 'actif')); ?>
                                    <div class="small fw-semibold mt-1 fin-montant"><?php echo number_format($soldeAct, 0, ',', ' '); ?> <span class="text-muted fw-normal">FCFA</span></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3 pt-2">
                                <a href="comptes.php" class="btn btn-sm btn-outline-primary">Voir tous les comptes</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php app_module_page_end(); ?>
