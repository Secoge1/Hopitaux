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
$statut = strtolower((string) ($compte['statut'] ?? ($compte['actif'] ?? 1 ? 'actif' : 'inactif')));
$classe = $compte['classe'] ?? '';
$nbEcritures = $financesModel->countEcrituresForCompte($id);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'libelle' => $_POST['libelle'] ?? '',
            'type_compte' => $_POST['type_compte'] ?? $type,
            'classe' => $_POST['classe'] ?? null,
            'statut' => $_POST['statut'] ?? 'actif',
        ];

        if ($financesModel->updateCompte($id, $data)) {
            header('Location: comptes.php?success=updated');
            exit;
        }
        $error = 'Erreur lors de la modification du compte.';
    } catch (Exception $e) {
        $error = 'Erreur : ' . $e->getMessage();
    }
}

app_module_page_start([
    'active'   => 'finances',
    'title'    => 'Modifier Compte',
    'subtitle' => $numero . ' — ' . $libelle,
    'icon'     => 'fa-calculator',
    'extra_css' => ['assets/css/app-finances.css'],
]);
app_module_back_toolbar(app_url('finances/voir_compte.php?id=' . $id), 'Retour au compte');
app_module_flash();
?>
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier le compte</h5>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($nbEcritures > 0): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Ce compte possède <?= $nbEcritures ?> écriture(s). Le numéro et le solde initial ne peuvent pas être modifiés ici.
        </div>
        <?php endif; ?>

        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <label for="numero_compte" class="form-label">Numéro de compte</label>
                <input type="text" class="form-control" id="numero_compte" value="<?= htmlspecialchars($numero) ?>" readonly disabled>
            </div>

            <div class="col-md-8">
                <label for="libelle" class="form-label">Libellé *</label>
                <input type="text" class="form-control" id="libelle" name="libelle" required value="<?= htmlspecialchars($libelle) ?>">
            </div>

            <div class="col-md-6">
                <label for="type_compte" class="form-label">Type de compte *</label>
                <select class="form-select" id="type_compte" name="type_compte" required>
                    <option value="actif" <?= $type === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="passif" <?= $type === 'passif' ? 'selected' : '' ?>>Passif</option>
                    <option value="produit" <?= $type === 'produit' ? 'selected' : '' ?>>Produit</option>
                    <option value="charge" <?= $type === 'charge' ? 'selected' : '' ?>>Charge</option>
                </select>
            </div>

            <div class="col-md-6">
                <label for="classe" class="form-label">Classe</label>
                <input type="text" class="form-control" id="classe" name="classe" value="<?= htmlspecialchars((string) $classe) ?>" placeholder="Ex: 1, 2, 3…">
            </div>

            <div class="col-md-6">
                <label for="statut" class="form-label">Statut</label>
                <select class="form-select" id="statut" name="statut">
                    <option value="actif" <?= $statut === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactif" <?= $statut === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>

            <div class="col-12">
                <hr>
                <div class="d-flex justify-content-between">
                    <a href="voir_compte.php?id=<?= $id ?>" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Annuler
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php app_module_page_end(); ?>
