<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('personnel'));

require_once __DIR__ . '/../models/Personnel.php';

$personnelModel = new Personnel();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $personnel = $personnelModel->getById($id);
    if (!$personnel) {
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

        if ($personnelModel->update($id, $data)) {
            $message = "Membre du personnel modifié avec succès !";
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
    'active'   => 'personnel',
    'title'    => 'Modifier le Personnel',
    'subtitle' => 'Modification des informations',
    'icon'     => 'fa-user-tie',
]);
app_module_back_toolbar(app_url('personnel/voir.php?id=' . $id), 'Retour à la fiche');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Informations du Personnel</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-12">
                        <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Informations Personnelles</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="nom" class="form-label">Nom *</label>
                        <input type="text" class="form-control" id="nom" name="nom" value="<?php echo htmlspecialchars($personnel['nom']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="prenom" class="form-label">Prénom *</label>
                        <input type="text" class="form-control" id="prenom" name="prenom" value="<?php echo htmlspecialchars($personnel['prenom']); ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label for="date_naissance" class="form-label">Date de naissance</label>
                        <input type="date" class="form-control" id="date_naissance" name="date_naissance" value="<?php echo $personnel['date_naissance'] ? date('Y-m-d', strtotime($personnel['date_naissance'])) : ''; ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="sexe" class="form-label">Sexe</label>
                        <select class="form-select" id="sexe" name="sexe">
                            <option value="">Choisir...</option>
                            <option value="M" <?php echo $personnel['sexe'] === 'M' ? 'selected' : ''; ?>>Homme</option>
                            <option value="F" <?php echo $personnel['sexe'] === 'F' ? 'selected' : ''; ?>>Femme</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-address-book me-2"></i>Coordonnées</h6>
                    </div>

                    <div class="col-md-6">
                        <label for="telephone" class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" value="<?php echo htmlspecialchars($personnel['telephone'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($personnel['email'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="adresse" class="form-label">Adresse</label>
                        <textarea class="form-control" id="adresse" name="adresse" rows="2"><?php echo htmlspecialchars($personnel['adresse'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-md-3">
                        <label for="ville" class="form-label">Ville</label>
                        <input type="text" class="form-control" id="ville" name="ville" value="<?php echo htmlspecialchars($personnel['ville'] ?? ''); ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="code_postal" class="form-label">Code Postal</label>
                        <input type="text" class="form-control" id="code_postal" name="code_postal" value="<?php echo htmlspecialchars($personnel['code_postal'] ?? ''); ?>">
                    </div>

                    <div class="col-12">
                        <h6 class="text-primary mb-3 mt-4"><i class="fas fa-briefcase me-2"></i>Informations Professionnelles</h6>
                    </div>

                    <div class="col-md-4">
                        <label for="poste" class="form-label">Poste *</label>
                        <input type="text" class="form-control" id="poste" name="poste" value="<?php echo htmlspecialchars($personnel['poste']); ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label for="departement" class="form-label">Département</label>
                        <input type="text" class="form-control" id="departement" name="departement" value="<?php echo htmlspecialchars($personnel['departement'] ?? ''); ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="date_embauche" class="form-label">Date d'embauche *</label>
                        <input type="date" class="form-control" id="date_embauche" name="date_embauche" value="<?php echo $personnel['date_embauche'] ? date('Y-m-d', strtotime($personnel['date_embauche'])) : ''; ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label for="type_contrat" class="form-label">Type de Contrat</label>
                        <select class="form-select" id="type_contrat" name="type_contrat">
                            <option value="CDI" <?php echo $personnel['type_contrat'] === 'CDI' ? 'selected' : ''; ?>>CDI</option>
                            <option value="CDD" <?php echo $personnel['type_contrat'] === 'CDD' ? 'selected' : ''; ?>>CDD</option>
                            <option value="Stage" <?php echo $personnel['type_contrat'] === 'Stage' ? 'selected' : ''; ?>>Stage</option>
                            <option value="Interim" <?php echo $personnel['type_contrat'] === 'Interim' ? 'selected' : ''; ?>>Intérim</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="salaire" class="form-label">Salaire (FCFA)</label>
                        <input type="number" class="form-control" id="salaire" name="salaire" value="<?php echo $personnel['salaire'] ?? ''; ?>" step="0.01">
                    </div>

                    <div class="col-md-4">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut">
                            <option value="actif" <?php echo $personnel['statut'] === 'actif' ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactif" <?php echo $personnel['statut'] === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                            <option value="suspendu" <?php echo $personnel['statut'] === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="pays" class="form-label">Pays</label>
                        <input type="text" class="form-control" id="pays" name="pays" value="<?php echo htmlspecialchars($personnel['pays'] ?? 'Mali'); ?>">
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($personnel['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <hr>
                        <div class="d-flex justify-content-between">
                            <a href="voir.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer les modifications
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php app_module_page_end(); ?>
