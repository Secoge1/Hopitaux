<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
module_require_write('finances');
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
$solde = (float) ($compte['solde_actuel'] ?? $compte['solde_initial'] ?? 0);
$nbEcritures = $financesModel->countEcrituresForCompte($id);

$typeLabels = [
    'actif' => 'Actif',
    'passif' => 'Passif',
    'produit' => 'Produit',
    'charge' => 'Charge',
    'capitaux' => 'Capitaux',
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        $result = $financesModel->deleteCompte($id);
        if ($result['ok']) {
            header('Location: comptes.php?deleted=' . urlencode($result['message']));
            exit;
        }
        $error = $result['message'] ?: 'Erreur lors de la suppression du compte.';
    } catch (Exception $e) {
        $error = 'Erreur : ' . $e->getMessage();
    }
}

app_module_page_start([
    'active'   => 'finances',
    'title'    => 'Supprimer Compte',
    'subtitle' => 'Confirmation de suppression',
    'icon'     => 'fa-calculator',
    'extra_css' => ['assets/css/app-finances.css'],
]);
app_module_back_toolbar(app_url('finances/voir_compte.php?id=' . $id), 'Annuler');
app_module_flash();
?>
<div class="card">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Confirmation de suppression</h5>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="alert alert-warning">
            <h6><i class="fas fa-exclamation-triangle me-2"></i>Attention</h6>
            <p>Vous êtes sur le point de supprimer le compte suivant :</p>
            <ul>
                <li><strong>N° compte :</strong> <?= htmlspecialchars($numero) ?></li>
                <li><strong>Libellé :</strong> <?= htmlspecialchars($libelle) ?></li>
                <li><strong>Type :</strong> <?= htmlspecialchars($typeLabels[$type] ?? ucfirst($type)) ?></li>
                <li><strong>Solde actuel :</strong> <?= number_format($solde, 0, ',', ' ') ?> FCFA</li>
                <li><strong>Écritures liées :</strong> <?= $nbEcritures ?></li>
            </ul>

            <?php if ($nbEcritures > 0): ?>
            <div class="alert alert-info mt-3 mb-0">
                <i class="fas fa-info-circle me-2"></i>
                Ce compte possède des écritures comptables. Il sera <strong>désactivé</strong> (statut inactif) au lieu d'être supprimé définitivement.
            </div>
            <?php else: ?>
            <p class="mb-0 mt-3"><strong>Note :</strong> Ce compte n'a aucune écriture liée et sera supprimé définitivement.</p>
            <?php endif; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="confirm" value="1">
            <div class="d-flex justify-content-between">
                <a href="voir_compte.php?id=<?= $id ?>" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>Annuler
                </a>
                <button type="submit" class="btn btn-danger" onclick="return confirm('Confirmer la suppression de ce compte ?');">
                    <i class="fas fa-trash me-2"></i><?= $nbEcritures > 0 ? 'Désactiver le compte' : 'Confirmer la suppression' ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php app_module_page_end(); ?>
