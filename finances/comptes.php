<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('finances'));

require_once __DIR__ . '/../models/Finances.php';

$financesModel = new Finances();

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_compte = isset($_GET['type_compte']) ? trim($_GET['type_compte']) : '';
$statut_filtre = isset($_GET['statut']) ? trim($_GET['statut']) : '';
$limit = 20;

try {
    $comptes = $financesModel->getComptes($page, $limit, $search, $type_compte, $statut_filtre);
    $total = $financesModel->getCountComptes($search, $type_compte, $statut_filtre);
    $total_pages = max(1, (int) ceil($total / $limit));

    $stats = [
        'total' => $financesModel->getCountComptes('', ''),
        'actifs' => $financesModel->getCountComptes('', 'actif'),
        'passifs' => $financesModel->getCountComptes('', 'passif'),
        'produits' => $financesModel->getCountComptes('', 'produit'),
        'charges' => $financesModel->getCountComptes('', 'charge'),
    ];

    require_once __DIR__ . '/../includes/saas/TenantScope.php';
    $pdo = getDB();
    $where = ["statut = 'actif'"];
    $params = [];
    $totaux = TenantScope::aggregate(
        $pdo,
        'comptes_comptables',
        "SUM(CASE WHEN type_compte = 'actif' THEN solde_actuel ELSE 0 END) as total_actifs,
         SUM(CASE WHEN type_compte = 'passif' THEN solde_actuel ELSE 0 END) as total_passifs,
         SUM(CASE WHEN type_compte = 'produit' THEN solde_actuel ELSE 0 END) as total_produits,
         SUM(CASE WHEN type_compte = 'charge' THEN solde_actuel ELSE 0 END) as total_charges",
        $where,
        $params
    );
    $totaux = $totaux ?: [
        'total_actifs' => 0,
        'total_passifs' => 0,
        'total_produits' => 0,
        'total_charges' => 0,
    ];
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}

$fmtFcfa = static function ($amount): string {
    return number_format((float) $amount, 0, ',', ' ');
};

app_module_page_start([
    'active'    => 'finances',
    'title'     => 'Comptes Comptables',
    'subtitle'  => 'Plan comptable et soldes par compte',
    'icon'      => 'fa-book',
    'extra_css' => ['assets/css/app-finances.css'],
]);
$comptesToolbar = [
    ['href' => app_url('finances/bilan.php'), 'label' => 'Bilan', 'icon' => 'fa-balance-scale', 'class' => 'btn-outline-secondary'],
    ['href' => app_url('finances/index.php'), 'label' => 'Écritures', 'icon' => 'fa-list', 'class' => 'btn-outline-secondary'],
    ['href' => app_url('finances/budgets.php'), 'label' => 'Budgets', 'icon' => 'fa-chart-pie', 'class' => 'btn-outline-secondary'],
];
if ($auth->peutEcrireFinances()) {
    array_unshift(
        $comptesToolbar,
        ['href' => app_url('finances/nouveau_compte.php'), 'label' => 'Nouveau compte', 'icon' => 'fa-plus'],
        ['href' => app_url('finances/nouvelle_ecriture.php'), 'label' => 'Nouvelle écriture', 'icon' => 'fa-file-invoice', 'class' => 'btn-outline-secondary']
    );
}
app_module_toolbar($comptesToolbar);
app_module_flash();

if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?= ($_GET['success'] === 'updated') ? 'Compte modifié avec succès.' : 'Compte créé avec succès.' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars(urldecode((string) $_GET['deleted'])) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
</div>
<?php endif; ?>

<?php
app_mod_stats([
    ['value' => $stats['total'], 'label' => 'Total comptes', 'icon' => 'fa-book'],
    ['value' => $stats['actifs'], 'label' => 'Type actif', 'icon' => 'fa-landmark', 'mod' => 'teal'],
    ['value' => $stats['passifs'], 'label' => 'Type passif', 'icon' => 'fa-balance-scale', 'mod' => 'amber'],
    ['value' => $stats['produits'], 'label' => 'Produits', 'icon' => 'fa-chart-line', 'mod' => 'teal'],
    ['value' => $stats['charges'], 'label' => 'Charges', 'icon' => 'fa-chart-line', 'mod' => 'amber'],
], 'fin-comptes-kpis mb-3');

app_mod_stats([
    ['value' => $fmtFcfa($totaux['total_actifs']), 'label' => 'Solde actifs (FCFA)', 'icon' => 'fa-coins'],
    ['value' => $fmtFcfa($totaux['total_passifs']), 'label' => 'Solde passifs (FCFA)', 'icon' => 'fa-coins', 'mod' => 'amber'],
    ['value' => $fmtFcfa($totaux['total_produits']), 'label' => 'Solde produits (FCFA)', 'icon' => 'fa-chart-line', 'mod' => 'teal'],
    ['value' => $fmtFcfa($totaux['total_charges']), 'label' => 'Solde charges (FCFA)', 'icon' => 'fa-chart-line', 'mod' => 'amber'],
], 'fin-comptes-totaux mb-4');
?>

<div class="fin-panel">
    <div class="fin-panel-head">
        <h2><i class="fas fa-list me-2"></i>Liste des comptes</h2>
        <span class="fin-count-badge">
            <i class="fas fa-book"></i>
            <span><strong><?= (int) $total ?></strong> compte<?= (int) $total > 1 ? 's' : '' ?></span>
        </span>
    </div>
    <div class="fin-panel-body">
        <div class="app-mod-filter">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small text-muted mb-1" for="finSearchCompte">Recherche</label>
                    <input type="text" class="form-control form-control-sm" id="finSearchCompte" name="search"
                           placeholder="N° compte, libellé…" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-lg-3 col-md-4">
                    <label class="form-label small text-muted mb-1" for="finTypeCompte">Type</label>
                    <select class="form-select form-select-sm" id="finTypeCompte" name="type_compte">
                        <option value="">Tous les types</option>
                        <option value="actif" <?= $type_compte === 'actif' ? 'selected' : '' ?>>Actif</option>
                        <option value="passif" <?= $type_compte === 'passif' ? 'selected' : '' ?>>Passif</option>
                        <option value="produit" <?= $type_compte === 'produit' ? 'selected' : '' ?>>Produit</option>
                        <option value="charge" <?= $type_compte === 'charge' ? 'selected' : '' ?>>Charge</option>
                        <option value="capitaux" <?= $type_compte === 'capitaux' ? 'selected' : '' ?>>Capitaux</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-4">
                    <label class="form-label small text-muted mb-1" for="finStatutCompte">Statut</label>
                    <select class="form-select form-select-sm" id="finStatutCompte" name="statut">
                        <option value="">Tous</option>
                        <option value="actif" <?= $statut_filtre === 'actif' ? 'selected' : '' ?>>Actif</option>
                        <option value="inactif" <?= $statut_filtre === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-12">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                            <i class="fas fa-search me-1"></i>Filtrer
                        </button>
                        <a href="<?= htmlspecialchars(app_url('finances/comptes.php')) ?>" class="btn btn-outline-secondary btn-sm" title="Effacer">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
            <?php
            if ($search || $type_compte || $statut_filtre):
                $parts = array_filter([
                    $search ? '« ' . $search . ' »' : '',
                    $type_compte ? 'type <strong>' . htmlspecialchars($type_compte) . '</strong>' : '',
                    $statut_filtre ? 'statut <strong>' . htmlspecialchars($statut_filtre) . '</strong>' : '',
                ]);
                app_mod_filter_active((int) $total, implode(' · ', $parts));
            endif;
            ?>
        </div>

        <?php if (empty($comptes)): ?>
        <div class="app-mod-empty">
            <i class="fas fa-book d-block"></i>
            <h5 class="mb-2">Aucun compte</h5>
            <p class="mb-3"><?= ($search || $type_compte) ? 'Aucun résultat pour ces critères.' : 'Créez votre premier compte comptable.' ?></p>
            <a href="<?= htmlspecialchars(app_url('finances/nouveau_compte.php')) ?>" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nouveau compte
            </a>
        </div>
        <?php else: ?>
        <div class="app-mod-table-wrap">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 app-mod-table">
                    <thead>
                        <tr>
                            <th>N° compte</th>
                            <th>Libellé</th>
                            <th>Type</th>
                            <th class="d-none d-md-table-cell">Catégorie</th>
                            <th class="text-end">Solde actuel</th>
                            <th>Statut</th>
                            <th class="text-end mod-actions-cell">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comptes as $compte):
                            $cid = (int) ($compte['id'] ?? 0);
                            $numero = (string) ($compte['numero_compte'] ?? '');
                            $libelle = (string) ($compte['libelle'] ?? $compte['nom_compte'] ?? '');
                            $soldeAct = (float) ($compte['solde_actuel'] ?? 0);
                            $statut = (string) ($compte['statut'] ?? 'actif');
                            $categorie = (string) ($compte['categorie'] ?? $compte['classe'] ?? '');
                            $type = (string) ($compte['type_compte'] ?? 'actif');
                            $soldeClass = $soldeAct >= 0 ? 'fin-solde-pos' : 'fin-solde-neg';

                            $typeLabels = [
                                'actif' => 'Actif',
                                'passif' => 'Passif',
                                'produit' => 'Produit',
                                'charge' => 'Charge',
                                'capitaux' => 'Capitaux',
                            ];
                            $actions = [
                                ['href' => 'voir_compte.php?id=' . $cid, 'label' => 'Voir', 'icon' => 'fa-eye', 'tone' => 'primary'],
                            ];
                            if ($auth->peutEcrireFinances()) {
                                $actions[] = ['href' => 'modifier_compte.php?id=' . $cid, 'label' => 'Modifier', 'icon' => 'fa-edit', 'tone' => 'warning'];
                                $actions[] = ['divider' => true];
                                $actions[] = ['href' => 'nouvelle_ecriture.php?compte_debit_id=' . $cid, 'label' => 'Écriture au débit', 'icon' => 'fa-minus-circle', 'tone' => 'primary'];
                                $actions[] = ['href' => 'nouvelle_ecriture.php?compte_credit_id=' . $cid, 'label' => 'Écriture au crédit', 'icon' => 'fa-plus-circle', 'tone' => 'success'];
                                $actions[] = ['divider' => true];
                                $actions[] = [
                                    'href' => 'supprimer_compte.php?id=' . $cid,
                                    'label' => 'Supprimer',
                                    'icon' => 'fa-trash',
                                    'tone' => 'danger',
                                ];
                            }
                            $actions[] = ['divider' => true];
                            $actions[] = ['href' => 'index.php', 'label' => 'Journal des écritures', 'icon' => 'fa-list', 'tone' => 'neutral'];
                        ?>
                        <tr>
                            <td class="text-nowrap"><span class="mod-code"><?= htmlspecialchars($numero) ?></span></td>
                            <td>
                                <span class="fin-compte-lib"><?= htmlspecialchars($libelle) ?></span>
                            </td>
                            <td><?= app_mod_badge($type, $typeLabels[$type] ?? ucfirst($type)) ?></td>
                            <td class="d-none d-md-table-cell">
                                <?= $categorie !== '' ? htmlspecialchars($categorie) : '<span class="text-muted">—</span>' ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <span class="<?= $soldeClass ?>"><?= $fmtFcfa($soldeAct) ?> <small class="text-muted">FCFA</small></span>
                            </td>
                            <td><?= app_mod_badge($statut, ucfirst($statut)) ?></td>
                            <td class="text-end mod-actions-cell">
                                <?php app_mod_actions_dropdown($actions); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <?php app_mod_pagination($page, $total_pages, [
            'search' => $search,
            'type_compte' => $type_compte,
            'statut' => $statut_filtre,
        ], 'Pagination comptes'); ?>
        <?php endif; ?>

        <?php app_mod_list_count(count($comptes), (int) $total, 'compte(s)'); ?>
        <?php endif; ?>
    </div>
</div>

<?php app_module_page_end(); ?>
