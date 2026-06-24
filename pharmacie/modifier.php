<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('pharmacie'));

require_once __DIR__ . '/../models/Medicament.php';

$medicamentModel = new Medicament();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $medicament = $medicamentModel->getById($id);
    if (!$medicament) {
        header("Location: index.php");
        exit;
    }
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

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

        if ($medicamentModel->update($id, $data)) {
            header("Location: voir.php?id=$id&success=1");
            exit;
        } else {
            $error = "Erreur lors de la modification.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

?>
<?php
app_module_page_start([
    'active'   => 'pharmacie',
    'title'    => 'Modifier le Médicament',
    'subtitle' => 'Modification des informations',
    'icon'     => 'fa-pills',
]);
app_module_back_toolbar(app_url('pharmacie/voir.php?id=' . $id), 'Retour à la fiche');
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
                        <input type="text" class="form-control" id="nom_commercial" name="nom_commercial" value="<?php echo htmlspecialchars($medicament['nom_commercial']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="nom_generique" class="form-label">Nom Générique</label>
                        <input type="text" class="form-control" id="nom_generique" name="nom_generique" value="<?php echo htmlspecialchars($medicament['nom_generique'] ?? ''); ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="categorie" class="form-label">Catégorie</label>
                        <input type="text" class="form-control" id="categorie" name="categorie" value="<?php echo htmlspecialchars($medicament['categorie'] ?? ''); ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="forme" class="form-label">Forme</label>
                        <select class="form-select" id="forme" name="forme">
                            <option value="comprime" <?php echo $medicament['forme'] === 'comprime' ? 'selected' : ''; ?>>Comprimé</option>
                            <option value="sirop" <?php echo $medicament['forme'] === 'sirop' ? 'selected' : ''; ?>>Sirop</option>
                            <option value="injection" <?php echo $medicament['forme'] === 'injection' ? 'selected' : ''; ?>>Injection</option>
                            <option value="gel" <?php echo $medicament['forme'] === 'gel' ? 'selected' : ''; ?>>Gel</option>
                            <option value="pommade" <?php echo $medicament['forme'] === 'pommade' ? 'selected' : ''; ?>>Pommade</option>
                            <option value="autre" <?php echo $medicament['forme'] === 'autre' ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="dosage" class="form-label">Dosage</label>
                        <input type="text" class="form-control" id="dosage" name="dosage" value="<?php echo htmlspecialchars($medicament['dosage'] ?? ''); ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="unite" class="form-label">Unité</label>
                        <input type="text" class="form-control" id="unite" name="unite" value="<?php echo htmlspecialchars($medicament['unite'] ?? ''); ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="stock_actuel" class="form-label">Stock Actuel</label>
                        <input type="number" class="form-control" id="stock_actuel" name="stock_actuel" value="<?php echo $medicament['stock_actuel']; ?>" min="0">
                    </div>

                    <div class="col-md-3">
                        <label for="stock_minimum" class="form-label">Stock Minimum</label>
                        <input type="number" class="form-control" id="stock_minimum" name="stock_minimum" value="<?php echo $medicament['stock_minimum']; ?>" min="0">
                    </div>

                    <div class="col-md-3">
                        <label for="stock_maximum" class="form-label">Stock Maximum</label>
                        <input type="number" class="form-control" id="stock_maximum" name="stock_maximum" value="<?php echo $medicament['stock_maximum']; ?>" min="0">
                    </div>

                    <div class="col-md-4">
                        <label for="prix_unitaire" class="form-label">Prix Unitaire (FCFA) *</label>
                        <input type="number" class="form-control" id="prix_unitaire" name="prix_unitaire" value="<?php echo $medicament['prix_unitaire']; ?>" step="0.01" required>
                    </div>

                    <div class="col-md-4">
                        <label for="fournisseur" class="form-label">Fournisseur</label>
                        <input type="text" class="form-control" id="fournisseur" name="fournisseur" value="<?php echo htmlspecialchars($medicament['fournisseur'] ?? ''); ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="date_peremption" class="form-label">Date de Péremption</label>
                        <input type="date" class="form-control" id="date_peremption" name="date_peremption" value="<?php echo $medicament['date_peremption'] ? date('Y-m-d', strtotime($medicament['date_peremption'])) : ''; ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="lot" class="form-label">Numéro de Lot</label>
                        <input type="text" class="form-control" id="lot" name="lot" value="<?php echo htmlspecialchars($medicament['lot'] ?? ''); ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut">
                            <option value="disponible" <?php echo $medicament['statut'] === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                            <option value="rupture" <?php echo $medicament['statut'] === 'rupture' ? 'selected' : ''; ?>>Rupture</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($medicament['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-between">
                            <a href="voir.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Enregistrer les modifications
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
