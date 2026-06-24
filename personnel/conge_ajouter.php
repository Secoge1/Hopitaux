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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type_conge = trim($_POST['type_conge'] ?? '');
    $date_debut = trim($_POST['date_debut'] ?? '');
    $date_fin = trim($_POST['date_fin'] ?? '');

    if (empty($type_conge) || empty($date_debut) || empty($date_fin)) {
        $error = "Veuillez remplir le type de congé, la date de début et la date de fin.";
    } elseif (strtotime($date_fin) < strtotime($date_debut)) {
        $error = "La date de fin doit être postérieure ou égale à la date de début.";
    } else {
        try {
            $personnelModel->createConge([
                'personnel_id' => $id,
                'type_conge' => $type_conge,
                'date_debut' => $date_debut,
                'date_fin' => $date_fin,
                'statut' => $_POST['statut'] ?? 'en_attente',
                'motif' => !empty($_POST['motif']) ? trim($_POST['motif']) : null,
                'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null
            ]);
            header("Location: conges.php?id=$id&conge=1");
            exit;
        } catch (Exception $e) {
            $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}

$typesConges = [
    'conges_payes' => 'Congés payés',
    'conges_sans_solde' => 'Congés sans solde',
    'maladie' => 'Maladie',
    'maternite' => 'Maternité',
    'paternite' => 'Paternité',
    'formation' => 'Formation',
    'autre' => 'Autre'
];

$statuts = [
    'en_attente' => 'En attente',
    'approuve' => 'Approuvé',
    'refuse' => 'Refusé'
];

?>
<?php
app_module_page_start([
    'active'   => 'personnel',
    'title'    => 'Ajouter un Congé',
    'subtitle' => $personnel['nom'] . ' ' . $personnel['prenom'],
    'icon'     => 'fa-user-tie',
]);
app_module_back_toolbar(app_url('personnel/conges.php?id=' . $id), 'Retour aux congés');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-calendar-plus me-2"></i>Nouveau congé</h5>
            </div>
            <div class="card-body">
                <form method="post" action="conge_ajouter.php?id=<?php echo $id; ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="type_conge" class="form-label">Type de congé <span class="text-danger">*</span></label>
                            <select name="type_conge" id="type_conge" class="form-select" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach ($typesConges as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (isset($_POST['type_conge']) && $_POST['type_conge'] === $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="statut" class="form-label">Statut</label>
                            <select name="statut" id="statut" class="form-select">
                                <?php foreach ($statuts as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (isset($_POST['statut']) ? $_POST['statut'] : 'en_attente') === $value ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="date_debut" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" name="date_debut" id="date_debut" class="form-control" required
                                   value="<?php echo htmlspecialchars($_POST['date_debut'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="date_fin" class="form-label">Date de fin <span class="text-danger">*</span></label>
                            <input type="date" name="date_fin" id="date_fin" class="form-control" required
                                   value="<?php echo htmlspecialchars($_POST['date_fin'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label for="motif" class="form-label">Motif</label>
                            <input type="text" name="motif" id="motif" class="form-control" placeholder="Ex : vacances, raisons personnelles..."
                                   value="<?php echo htmlspecialchars($_POST['motif'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Commentaires éventuels..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Enregistrer le congé
                            </button>
                            <a href="conges.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">Annuler</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php app_module_page_end(); ?>
