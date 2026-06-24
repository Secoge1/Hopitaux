<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('assurances'));

require_once __DIR__ . '/../models/Assurance.php';

$assuranceModel = new Assurance();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'nom' => $_POST['nom'],
            'type' => $_POST['type'] ?? 'assurance',
            'numero_agrement' => $_POST['numero_agrement'] ?: null,
            'telephone' => $_POST['telephone'] ?: null,
            'email' => $_POST['email'] ?: null,
            'adresse' => $_POST['adresse'] ?: null,
            'taux_remboursement' => $_POST['taux_remboursement'] ?? 0.00,
            'statut' => $_POST['statut'] ?? 'actif',
            'notes' => $_POST['notes'] ?: null
        ];

        if ($assuranceModel->create($data)) {
            header("Location: index.php?success=1");
            exit;
        } else {
            $error = "Erreur lors de la création de l'assurance.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

?>
<?php
app_module_page_start([
    'active'   => 'assurances',
    'title'    => 'Nouvelle Assurance',
    'subtitle' => 'Ajouter une assurance',
    'icon'     => 'fa-shield-alt',
]);
app_module_back_toolbar(app_url('assurances/index.php'), 'Retour à la liste');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Informations de l'Assurance</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label for="nom" class="form-label">Nom de l'Assurance *</label>
                        <input type="text" class="form-control" id="nom" name="nom" required>
                    </div>

                    <div class="col-md-6">
                        <label for="type" class="form-label">Type *</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="assurance">Assurance</option>
                            <option value="mutuelle">Mutuelle</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="numero_agrement" class="form-label">Numéro d'Agrément</label>
                        <input type="text" class="form-control" id="numero_agrement" name="numero_agrement">
                    </div>

                    <div class="col-md-6">
                        <label for="taux_remboursement" class="form-label">Taux de Remboursement (%)</label>
                        <input type="number" class="form-control" id="taux_remboursement" name="taux_remboursement" value="0" step="0.01" min="0" max="100">
                    </div>

                    <div class="col-md-6">
                        <label for="telephone" class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" id="telephone" name="telephone">
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>

                    <div class="col-12">
                        <label for="adresse" class="form-label">Adresse</label>
                        <textarea class="form-control" id="adresse" name="adresse" rows="2"></textarea>
                    </div>

                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut">
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
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
                            <button type="submit" class="btn btn-info">
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
