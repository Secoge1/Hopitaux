<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('medecins'));

require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../includes/medecin_profil.php';
$medecinModel = new Medecin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$medecin = $medecinModel->getById($id);
if (!$medecin) {
    header("Location: index.php");
    exit();
}

if (!$auth->estAdmin()) {
    $ctx = StaffScope::context();
    $ownMedecinId = (int) ($ctx['medecin_id'] ?? 0);
    if (!StaffScope::isActive() || $ownMedecinId !== $id) {
        header('Location: ' . app_url('access_denied.php'));
        exit;
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $typeProfil = $_POST['type_profil'] ?? ($medecin['type_profil'] ?? 'medecin');
        if (!medecin_profil_is_valid($typeProfil)) {
            $typeProfil = 'medecin';
        }
        if (!$auth->estAdmin()) {
            $typeProfil = $medecin['type_profil'] ?? 'medecin';
        }

        $specValue = medecin_profil_resolve_specialite_from_post($_POST, $typeProfil);
        if ($specValue === '' && !medecin_profil_is_paramedical($typeProfil)) {
            throw new Exception('La spécialité est obligatoire.');
        }

        $data = [
            'numero_licence' => $_POST['numero_licence'],
            'type_profil' => $typeProfil,
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'specialite' => $specValue,
            'telephone' => $_POST['telephone'] ?: null,
            'email' => $_POST['email'] ?: null,
            'adresse' => $_POST['adresse'] ?: null,
            'ville' => $_POST['ville'] ?: null,
            'code_postal' => $_POST['code_postal'] ?: null,
            'pays' => $_POST['pays'] ?: 'France',
            'date_embauche' => $_POST['date_embauche'],
            'statut' => $_POST['statut']
        ];

        if ($medecinModel->update($id, $data)) {
            require_once __DIR__ . '/../includes/CacheSystem.php';
            CacheSystem::getInstance()->invalidateDashboardCache();
            $message = 'Professionnel modifié avec succès !';
            $medecin = $medecinModel->getById($id);
        } else {
            $error = 'Erreur lors de la modification.';
        }
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Exception $e) {
        $error = 'Erreur : ' . $e->getMessage();
    }
}

$isOwnProfile = !$auth->estAdmin() && StaffScope::isActive() && !empty(StaffScope::context()['medecin_id']);
$currentType = $medecin['type_profil'] ?? 'medecin';
$specState = medecin_profil_specialite_form_state($currentType, (string) ($medecin['specialite'] ?? ''));
$specialites = medecin_profil_specialites_list();

app_module_page_start([
    'active'   => 'medecins',
    'title'    => $isOwnProfile ? 'Modifier mon profil' : 'Modifier un professionnel',
    'subtitle' => $isOwnProfile ? 'Mettez à jour vos informations professionnelles' : medecin_profil_format_name($medecin),
    'icon'     => 'fa-user-edit',
]);
if ($auth->estAdmin()) {
    app_module_back_toolbar(app_url('medecins/index.php'));
} else {
    app_module_back_toolbar(app_url('medecins/voir.php?id=' . $id), 'Retour au profil');
}
app_module_flash();
?>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card app-mod-form-card">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label for="numero_licence" class="form-label">Numéro de Licence *</label>
                        <input type="text" class="form-control" id="numero_licence" name="numero_licence"
                               value="<?php echo htmlspecialchars($medecin['numero_licence'] ?? ''); ?>" required>
                    </div>

                    <?php if ($auth->estAdmin()): ?>
                    <div class="col-md-6">
                        <label for="type_profil" class="form-label">Profil *</label>
                        <select class="form-select" id="type_profil" name="type_profil" required>
                            <?php medecin_profil_select_options($currentType); ?>
                        </select>
                        <small class="text-muted">Médecin, sage-femme, infirmier(ère), laborantin(e), etc.</small>
                    </div>
                    <?php else: ?>
                    <div class="col-md-6">
                        <label class="form-label">Profil</label>
                        <input type="text" class="form-control bg-light" readonly disabled
                               value="<?php echo htmlspecialchars(medecin_profil_label($currentType)); ?>">
                    </div>
                    <?php endif; ?>

                    <div class="col-md-6" id="specialite_medecin_block">
                        <label for="specialite" class="form-label">Spécialité *</label>
                        <select class="form-select" id="specialite" name="specialite" onchange="syncSpecialiteAutre(this.value)">
                            <option value="">Choisir une spécialité</option>
                            <?php foreach ($specialites as $spec): ?>
                                <option value="<?php echo htmlspecialchars($spec); ?>" <?php echo $specState['select'] === $spec ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="Autre" <?php echo $specState['is_autre'] ? 'selected' : ''; ?>>Autre (préciser)</option>
                        </select>
                        <div class="mt-2" id="specialite_autre_wrapper" style="display: <?php echo $specState['is_autre'] ? 'block' : 'none'; ?>;">
                            <label for="specialite_autre" class="form-label">Précisez la spécialité *</label>
                            <input type="text" class="form-control" id="specialite_autre" name="specialite_autre"
                                   value="<?php echo htmlspecialchars($specState['autre']); ?>"
                                   placeholder="Saisir la spécialité" autocomplete="off" maxlength="120">
                        </div>
                    </div>

                    <div class="col-md-6 <?php echo medecin_profil_is_paramedical($currentType) ? '' : 'd-none'; ?>" id="specialite_libre_block">
                        <label for="specialite_libre" class="form-label">Fonction / service</label>
                        <input type="text" class="form-control" id="specialite_libre" name="specialite_libre"
                               value="<?php echo htmlspecialchars($specState['libre']); ?>"
                               placeholder="Ex. Laboratoire, Urgences…" maxlength="120">
                    </div>

                    <div class="col-md-6">
                        <label for="nom" class="form-label">Nom *</label>
                        <input type="text" class="form-control" id="nom" name="nom"
                               value="<?php echo htmlspecialchars($medecin['nom']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="prenom" class="form-label">Prénom *</label>
                        <input type="text" class="form-control" id="prenom" name="prenom"
                               value="<?php echo htmlspecialchars($medecin['prenom']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="telephone" class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" id="telephone" name="telephone"
                               value="<?php echo htmlspecialchars($medecin['telephone'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($medecin['email'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="date_embauche" class="form-label">Date d'embauche *</label>
                        <input type="date" class="form-control" id="date_embauche" name="date_embauche"
                               value="<?php echo $medecin['date_embauche']; ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut *</label>
                        <select class="form-select" id="statut" name="statut" required>
                            <option value="actif" <?php echo $medecin['statut'] === 'actif' ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactif" <?php echo $medecin['statut'] === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                            <option value="conge" <?php echo $medecin['statut'] === 'conge' ? 'selected' : ''; ?>>En congé</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="adresse" class="form-label">Adresse</label>
                        <textarea class="form-control" id="adresse" name="adresse" rows="2"><?php echo htmlspecialchars($medecin['adresse'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-md-4">
                        <label for="ville" class="form-label">Ville</label>
                        <input type="text" class="form-control" id="ville" name="ville"
                               value="<?php echo htmlspecialchars($medecin['ville'] ?? ''); ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="code_postal" class="form-label">Code postal</label>
                        <input type="text" class="form-control" id="code_postal" name="code_postal"
                               value="<?php echo htmlspecialchars($medecin['code_postal'] ?? ''); ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="pays" class="form-label">Pays</label>
                        <input type="text" class="form-control" id="pays" name="pays"
                               value="<?php echo htmlspecialchars($medecin['pays'] ?? 'France'); ?>">
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                        <a href="voir.php?id=<?php echo $medecin['id']; ?>" class="btn btn-secondary ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>

<script>
function syncSpecialiteAutre(val) {
    var wrap = document.getElementById('specialite_autre_wrapper');
    var input = document.getElementById('specialite_autre');
    if (!wrap || !input) return;
    var isAutre = (val === 'Autre');
    wrap.style.display = isAutre ? 'block' : 'none';
    input.required = isAutre;
    if (!isAutre) input.value = '';
}

function syncTypeProfil(typeVal) {
    var specBlock = document.getElementById('specialite_medecin_block');
    var specLibreBlock = document.getElementById('specialite_libre_block');
    var sel = document.getElementById('specialite');
    var paramedical = ['infirmier', 'laborantin', 'technicien', 'pharmacien'];
    var isParamedical = paramedical.indexOf(typeVal) !== -1;
    if (specBlock) specBlock.style.display = isParamedical ? 'none' : '';
    if (specLibreBlock) specLibreBlock.style.display = isParamedical ? '' : 'none';
    if (sel) sel.required = !isParamedical;
}

(function() {
    var typeSel = document.getElementById('type_profil');
    var sel = document.getElementById('specialite');
    if (typeSel) {
        typeSel.addEventListener('change', function() { syncTypeProfil(this.value); });
        syncTypeProfil(typeSel.value);
    }
    if (sel) {
        syncSpecialiteAutre(sel.value);
    }
})();
</script>

<?php app_module_page_end(); ?>
