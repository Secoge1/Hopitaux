<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('maintenance'));

require_once __DIR__ . '/../models/Maintenance.php';

$maintenanceModel = new Maintenance();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'nom' => $_POST['nom'],
            'categorie' => $_POST['categorie'] ?: null,
            'marque' => $_POST['marque'] ?: null,
            'modele' => $_POST['modele'] ?: null,
            'date_acquisition' => $_POST['date_acquisition'] ?: null,
            'valeur' => $_POST['valeur'] ?: null,
            'localisation' => $_POST['localisation'] ?: null,
            'statut' => $_POST['statut'] ?? 'disponible',
            'date_derniere_maintenance' => $_POST['date_derniere_maintenance'] ?: null,
            'prochaine_maintenance' => $_POST['prochaine_maintenance'] ?: null,
            'notes' => $_POST['notes'] ?: null
        ];

        if ($maintenanceModel->createEquipement($data)) {
            header("Location: index.php?success=1");
            exit;
        } else {
            $error = "Erreur lors de la création de l'équipement.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

?>
<?php
app_module_page_start([
    'active'   => 'maintenance',
    'title'    => 'Nouvel Équipement',
    'subtitle' => 'Ajouter un équipement',
    'icon'     => 'fa-tools',
]);
app_module_back_toolbar(app_url('maintenance/index.php'), 'Retour à la liste');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Informations de l'Équipement</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label for="nom" class="form-label">Nom de l'Équipement *</label>
                        <input type="text" class="form-control" id="nom" name="nom" required placeholder="Ex: Scanner IRM, Échographe">
                    </div>

                    <div class="col-md-6">
                        <label for="categorie" class="form-label">Catégorie</label>
                        <input type="text" class="form-control" id="categorie" name="categorie" placeholder="Ex: Imagerie, Diagnostic">
                    </div>

                    <div class="col-md-4">
                        <label for="marque" class="form-label">Marque</label>
                        <input type="text" class="form-control" id="marque" name="marque" placeholder="Ex: Philips, Siemens">
                    </div>

                    <div class="col-md-4">
                        <label for="modele" class="form-label">Modèle</label>
                        <input type="text" class="form-control" id="modele" name="modele" placeholder="Ex: Model XYZ-2024">
                    </div>

                    <div class="col-md-4">
                        <label for="date_acquisition" class="form-label">Date d'Acquisition</label>
                        <input type="date" class="form-control" id="date_acquisition" name="date_acquisition">
                    </div>

                    <div class="col-md-4">
                        <label for="valeur" class="form-label">Valeur (FCFA)</label>
                        <input type="number" class="form-control" id="valeur" name="valeur" step="0.01" placeholder="0.00">
                    </div>

                    <div class="col-md-4">
                        <label for="localisation" class="form-label">Localisation</label>
                        <input type="text" class="form-control" id="localisation" name="localisation" placeholder="Ex: Salle 101, Bloc opératoire">
                    </div>

                    <div class="col-md-4">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut">
                            <option value="disponible">Disponible</option>
                            <option value="en_utilisation">En Utilisation</option>
                            <option value="en_maintenance">En Maintenance</option>
                            <option value="hors_service">Hors Service</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="date_derniere_maintenance" class="form-label">Dernière Maintenance</label>
                        <input type="date" class="form-control" id="date_derniere_maintenance" name="date_derniere_maintenance">
                    </div>

                    <div class="col-md-6">
                        <label for="prochaine_maintenance" class="form-label">Prochaine Maintenance</label>
                        <input type="date" class="form-control" id="prochaine_maintenance" name="prochaine_maintenance">
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Notes supplémentaires..."></textarea>
                    </div>

                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-warning">
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
