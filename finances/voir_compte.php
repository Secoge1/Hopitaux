<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('finances'));

require_once __DIR__ . '/../models/Finances.php';

$financesModel = new Finances();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$id) {
    header('Location: comptes.php');
    exit;
}

try {
    $compte = $financesModel->getCompteById($id);
    if (!$compte) {
        header('Location: comptes.php?error=compte_not_found');
        exit;
    }
} catch (Exception $e) {
    die('Erreur: ' . $e->getMessage());
}

$libelle = $compte['libelle'] ?? $compte['nom_compte'] ?? '';
$numero = $compte['numero_compte'] ?? '';
$type = strtolower((string) ($compte['type_compte'] ?? ''));
$statut = strtolower((string) ($compte['statut'] ?? ($compte['actif'] ?? 1 ? 'actif' : 'inactif')));
$solde = (float) ($compte['solde_actuel'] ?? $compte['solde_initial'] ?? 0);
$nbEcritures = $financesModel->countEcrituresForCompte($id);

$typeLabels = [
    'actif' => 'Actif',
    'passif' => 'Passif',
    'produit' => 'Produit',
    'charge' => 'Charge',
    'capitaux' => 'Capitaux',
];

app_module_page_start([
    'active'   => 'finances',
    'title'    => 'Détail Compte',
    'subtitle' => $numero . ' — ' . $libelle,
    'icon'     => 'fa-calculator',
    'extra_css' => ['assets/css/app-finances.css'],
]);

$toolbar = [];
if ($auth->peutEcrireFinances()) {
    $toolbar[] = ['href' => app_url('finances/modifier_compte.php?id=' . $id), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-outline-primary'];
    $toolbar[] = ['href' => app_url('finances/supprimer_compte.php?id=' . $id), 'label' => 'Supprimer', 'icon' => 'fa-trash', 'class' => 'btn-outline-danger'];
}
if ($toolbar !== []) {
    app_module_toolbar($toolbar);
}
app_module_back_toolbar(app_url('finances/comptes.php'), 'Retour aux comptes');
app_module_flash();
?>
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-book me-2"></i>Informations du compte</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small">N° compte</div>
                <div class="fw-semibold"><?= htmlspecialchars($numero) ?></div>
            </div>
            <div class="col-md-8">
                <div class="text-muted small">Libellé</div>
                <div class="fw-semibold"><?= htmlspecialchars($libelle) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Type</div>
                <div><?= app_mod_badge($type, $typeLabels[$type] ?? ucfirst($type)) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Statut</div>
                <div><?= app_mod_badge($statut) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Solde actuel</div>
                <div class="fw-bold text-success"><?= number_format($solde, 0, ',', ' ') ?> FCFA</div>
            </div>
            <?php if (!empty($compte['classe'])): ?>
            <div class="col-md-4">
                <div class="text-muted small">Classe</div>
                <div><?= htmlspecialchars((string) $compte['classe']) ?></div>
            </div>
            <?php endif; ?>
            <div class="col-md-4">
                <div class="text-muted small">Écritures liées</div>
                <div><?= $nbEcritures ?> écriture(s)</div>
            </div>
        </div>

        <?php if ($auth->peutEcrireFinances()): ?>
        <hr>
        <div class="d-flex flex-wrap gap-2">
            <a href="nouvelle_ecriture.php?compte_debit_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-minus-circle me-1"></i>Écriture au débit
            </a>
            <a href="nouvelle_ecriture.php?compte_credit_id=<?= $id ?>" class="btn btn-sm btn-outline-success">
                <i class="fas fa-plus-circle me-1"></i>Écriture au crédit
            </a>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-list me-1"></i>Journal des écritures
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php app_module_page_end(); ?>
