<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('pharmacie'));

require_once __DIR__ . '/../models/Medicament.php';

$medicamentModel = new Medicament();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'nom_commercial' => $_POST['nom_commercial'],
            'nom_generique' => $_POST['nom_generique'] ?: null,
            'categorie' => $_POST['categorie'] ?: null,
            'forme' => $_POST['forme'] ?? 'comprime',
            'dosage' => $_POST['dosage'] ?: null,
            'unite' => $_POST['unite'] ?: null,
            'stock_actuel' => $_POST['stock_actuel'] ?? 0,
            'stock_minimum' => $_POST['stock_minimum'] ?? 10,
            'stock_maximum' => $_POST['stock_maximum'] ?? 1000,
            'prix_unitaire' => $_POST['prix_unitaire'],
            'fournisseur' => $_POST['fournisseur'] ?: null,
            'date_peremption' => $_POST['date_peremption'] ?: null,
            'lot' => $_POST['lot'] ?: null,
            'statut' => $_POST['statut'] ?? 'disponible',
            'notes' => $_POST['notes'] ?: null
        ];

        if ($medicamentModel->create($data)) {
            header("Location: index.php?success=1");
            exit;
        } else {
            $error = "Erreur lors de la création du médicament.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

?>
<?php
app_module_page_start([
    'active'   => 'pharmacie',
    'title'    => 'Nouveau Médicament',
    'subtitle' => 'Ajouter un médicament au stock',
    'icon'     => 'fa-pills',
]);
app_module_back_toolbar(app_url('pharmacie/index.php'), 'Retour à la liste');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-pills me-2"></i>Informations du Médicament</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label for="nom_commercial" class="form-label">Nom Commercial *</label>
                        <input type="text" class="form-control" id="nom_commercial" name="nom_commercial" required>
                    </div>

                    <div class="col-md-6">
                        <label for="nom_generique" class="form-label">Nom Générique</label>
                        <input type="text" class="form-control" id="nom_generique" name="nom_generique">
                    </div>

                    <div class="col-md-4">
                        <label for="categorie" class="form-label">Catégorie</label>
                        <input type="text" class="form-control" id="categorie" name="categorie" placeholder="Ex: Antibiotique, Antalgique">
                    </div>

                    <div class="col-md-4">
                        <label for="forme" class="form-label">Forme</label>
                        <select class="form-select" id="forme" name="forme">
                            <option value="comprime">Comprimé</option>
                            <option value="sirop">Sirop</option>
                            <option value="injection">Injection</option>
                            <option value="gel">Gel</option>
                            <option value="pommade">Pommade</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="dosage" class="form-label">Dosage</label>
                        <input type="text" class="form-control" id="dosage" name="dosage" placeholder="Ex: 500mg">
                    </div>

                    <div class="col-md-3">
                        <label for="unite" class="form-label">Unité</label>
                        <input type="text" class="form-control" id="unite" name="unite" placeholder="Ex: boîte, flacon">
                    </div>

                    <div class="col-md-3">
                        <label for="stock_actuel" class="form-label">Stock Actuel</label>
                        <input type="number" class="form-control" id="stock_actuel" name="stock_actuel" value="0" min="0">
                    </div>

                    <div class="col-md-3">
                        <label for="stock_minimum" class="form-label">Stock Minimum</label>
                        <input type="number" class="form-control" id="stock_minimum" name="stock_minimum" value="10" min="0">
                    </div>

                    <div class="col-md-3">
                        <label for="stock_maximum" class="form-label">Stock Maximum</label>
                        <input type="number" class="form-control" id="stock_maximum" name="stock_maximum" value="1000" min="0">
                    </div>

                    <div class="col-md-4">
                        <label for="prix_unitaire" class="form-label">Prix Unitaire (FCFA) *</label>
                        <input type="number" class="form-control" id="prix_unitaire" name="prix_unitaire" step="0.01" required>
                    </div>

                    <div class="col-md-4">
                        <label for="fournisseur" class="form-label">Fournisseur</label>
                        <input type="text" class="form-control" id="fournisseur" name="fournisseur">
                    </div>

                    <div class="col-md-4">
                        <label for="date_peremption" class="form-label">Date de Péremption</label>
                        <input type="date" class="form-control" id="date_peremption" name="date_peremption">
                    </div>

                    <div class="col-md-4">
                        <label for="lot" class="form-label">Numéro de Lot</label>
                        <input type="text" class="form-control" id="lot" name="lot">
                    </div>

                    <div class="col-md-4">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut">
                            <option value="disponible">Disponible</option>
                            <option value="rupture">Rupture</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>

                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
ob_start();
?>
<script src="../assets/js/auto-responsive.js"></script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
