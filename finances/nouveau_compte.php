<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
module_require_write('finances');
extract(app_module_context('finances'));

require_once __DIR__ . '/../models/Finances.php';

$financesModel = new Finances();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'numero_compte' => $_POST['numero_compte'] ?: null,
            'libelle' => $_POST['libelle'],
            'type_compte' => $_POST['type_compte'],
            'classe' => $_POST['classe'] ?: null,
            'solde_initial' => $_POST['solde_initial'] ?? 0.00,
            'statut' => $_POST['statut'] ?? 'actif'
        ];

        if ($financesModel->createCompte($data)) {
            header("Location: comptes.php?success=1");
            exit;
        } else {
            $error = "Erreur lors de la création du compte.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

?>
<?php
app_module_page_start([
    'active'    => 'finances',
    'title'     => 'Nouveau Compte',
    'subtitle'  => 'Création de compte comptable',
    'icon'      => 'fa-calculator',
    'extra_css' => ['assets/css/app-finances.css'],
]);
app_module_back_toolbar(app_url('finances/comptes.php'), 'Retour aux comptes');
app_module_flash();
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<div class="fin-panel">
    <div class="fin-panel-head">
        <h2><i class="fas fa-book me-2"></i>Informations du compte</h2>
    </div>
    <div class="fin-panel-body fin-form-body">
        <form method="POST" class="row g-3">
                    <div class="col-md-4">
                        <label for="numero_compte" class="form-label">Numéro de Compte</label>
                        <input type="text" class="form-control" id="numero_compte" name="numero_compte" placeholder="Laissé vide pour génération auto" autocomplete="off">
                        <small class="text-muted">Si vide, un numéro sera généré automatiquement. Chaque numéro doit être unique (ex. si « Cmpt1 » existe déjà, choisissez « Cmpt2 » ou laissez vide).</small>
                    </div>

                    <div class="col-md-8">
                        <label for="libelle" class="form-label">Libellé *</label>
                        <input type="text" class="form-control" id="libelle" name="libelle" required placeholder="Ex: Caisse principale">
                    </div>

                    <div class="col-md-6">
                        <label for="type_compte" class="form-label">Type de Compte *</label>
                        <select class="form-select" id="type_compte" name="type_compte" required>
                            <option value="">Choisir...</option>
                            <option value="actif">Actif</option>
                            <option value="passif">Passif</option>
                            <option value="produit">Produit</option>
                            <option value="charge">Charge</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="classe" class="form-label">Classe</label>
                        <input type="text" class="form-control" id="classe" name="classe" placeholder="Ex: 1, 2, 3, 4, 5, 6, 7">
                    </div>

                    <div class="col-md-6">
                        <label for="solde_initial" class="form-label">Solde Initial (FCFA)</label>
                        <input type="number" class="form-control" id="solde_initial" name="solde_initial" value="0.00" step="0.01">
                    </div>

                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut">
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-between">
                            <a href="comptes.php" class="btn btn-secondary">
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
