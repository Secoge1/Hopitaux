<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('personnel'));

require_once __DIR__ . '/../models/Personnel.php';

$personnelModel = new Personnel();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'date_naissance' => $_POST['date_naissance'] ?: null,
            'sexe' => $_POST['sexe'] ?: null,
            'telephone' => $_POST['telephone'] ?: null,
            'email' => $_POST['email'] ?: null,
            'adresse' => $_POST['adresse'] ?: null,
            'ville' => $_POST['ville'] ?: null,
            'code_postal' => $_POST['code_postal'] ?: null,
            'pays' => $_POST['pays'] ?? 'Mali',
            'poste' => $_POST['poste'],
            'departement' => $_POST['departement'] ?: null,
            'date_embauche' => $_POST['date_embauche'],
            'salaire' => $_POST['salaire'] ?: null,
            'type_contrat' => $_POST['type_contrat'] ?? 'CDI',
            'statut' => $_POST['statut'] ?? 'actif',
            'notes' => $_POST['notes'] ?: null
        ];

        if ($personnelModel->create($data)) {
            $message = "Membre du personnel créé avec succès !";
            header("Location: index.php?success=1");
            exit;
        } else {
            $error = "Erreur lors de la création du membre du personnel.";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

?>
<?php
app_module_page_start([
    'active'   => 'personnel',
    'title'    => 'Nouveau Membre du Personnel',
    'subtitle' => 'Ajouter un nouveau membre à l\'équipe',
    'icon'     => 'fa-user-tie',
]);
app_module_back_toolbar(app_url('personnel/index.php'), 'Retour à la liste');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Informations du Personnel</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <!-- Informations personnelles -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Informations Personnelles</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="nom" class="form-label">Nom *</label>
                        <input type="text" class="form-control" id="nom" name="nom" required>
                    </div>

                    <div class="col-md-6">
                        <label for="prenom" class="form-label">Prénom *</label>
                        <input type="text" class="form-control" id="prenom" name="prenom" required>
                    </div>

                    <div class="col-md-4">
                        <label for="date_naissance" class="form-label">Date de naissance</label>
                        <input type="date" class="form-control" id="date_naissance" name="date_naissance">
                    </div>

                    <div class="col-md-4">
                        <label for="sexe" class="form-label">Sexe</label>
                        <select class="form-select" id="sexe" name="sexe">
                            <option value="">Choisir...</option>
                            <option value="M">Homme</option>
                            <option value="F">Femme</option>
                        </select>
                    </div>

                    <!-- Coordonnées -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-address-book me-2"></i>Coordonnées</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="telephone" class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" placeholder="00223 00 00 00 00">
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="personnel@email.com">
                    </div>

                    <div class="col-md-6">
                        <label for="adresse" class="form-label">Adresse</label>
                        <textarea class="form-control" id="adresse" name="adresse" rows="2"></textarea>
                    </div>

                    <div class="col-md-3">
                        <label for="ville" class="form-label">Ville</label>
                        <input type="text" class="form-control" id="ville" name="ville">
                    </div>

                    <div class="col-md-3">
                        <label for="code_postal" class="form-label">Code Postal</label>
                        <input type="text" class="form-control" id="code_postal" name="code_postal">
                    </div>

                    <!-- Informations professionnelles -->
                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-briefcase me-2"></i>Informations Professionnelles</h6>
                    </div>

                    <div class="col-md-4">
                        <label for="poste" class="form-label">Poste *</label>
                        <input type="text" class="form-control" id="poste" name="poste" required placeholder="Ex: Infirmier, Secrétaire, etc.">
                    </div>

                    <div class="col-md-4">
                        <label for="departement" class="form-label">Département</label>
                        <input type="text" class="form-control" id="departement" name="departement" placeholder="Ex: Urgences, Consultation, etc.">
                    </div>

                    <div class="col-md-4">
                        <label for="date_embauche" class="form-label">Date d'embauche *</label>
                        <input type="date" class="form-control" id="date_embauche" name="date_embauche" required>
                    </div>

                    <div class="col-md-4">
                        <label for="type_contrat" class="form-label">Type de Contrat</label>
                        <select class="form-select" id="type_contrat" name="type_contrat">
                            <option value="CDI">CDI</option>
                            <option value="CDD">CDD</option>
                            <option value="Stage">Stage</option>
                            <option value="Interim">Intérim</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="salaire" class="form-label">Salaire (FCFA)</label>
                        <input type="number" class="form-control" id="salaire" name="salaire" step="0.01" placeholder="0.00">
                    </div>

                    <div class="col-md-4">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut">
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                            <option value="suspendu">Suspendu</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="pays" class="form-label">Pays</label>
                        <input type="text" class="form-control" id="pays" name="pays" value="Mali">
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
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
<?php app_module_page_end(); ?>
