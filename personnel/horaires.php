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
    $horairesExistants = $personnelModel->getHoraires($id);
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $horaires = [];
    $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    foreach ($jours as $jour) {
        $debut = $_POST['heure_debut'][$jour] ?? '';
        $fin = $_POST['heure_fin'][$jour] ?? '';
        if ($debut !== '' && $fin !== '') {
            $horaires[] = [
                'jour_semaine' => $jour,
                'heure_debut' => $debut,
                'heure_fin' => $fin,
                'pause_debut' => !empty($_POST['pause_debut'][$jour]) ? $_POST['pause_debut'][$jour] : null,
                'pause_fin' => !empty($_POST['pause_fin'][$jour]) ? $_POST['pause_fin'][$jour] : null
            ];
        }
    }
    try {
        $personnelModel->setHoraires($id, $horaires);
        header("Location: voir.php?id=$id&horaires=1");
        exit;
    } catch (Exception $e) {
        $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
}

// Indexer les horaires existants par jour pour préremplir le formulaire
$parJour = [];
foreach ($horairesExistants as $h) {
    $parJour[$h['jour_semaine']] = $h;
}

$joursSemaine = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
$joursLabels = [
    'lundi' => 'Lundi', 'mardi' => 'Mardi', 'mercredi' => 'Mercredi',
    'jeudi' => 'Jeudi', 'vendredi' => 'Vendredi', 'samedi' => 'Samedi', 'dimanche' => 'Dimanche'
];

?>
<?php
app_module_page_start([
    'active'   => 'personnel',
    'title'    => 'Horaires',
    'subtitle' => $personnel['nom'] . ' ' . $personnel['prenom'],
    'icon'     => 'fa-user-tie',
]);
app_module_back_toolbar(app_url('personnel/voir.php?id=' . $id), 'Retour à la fiche');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-week me-2"></i>Horaires hebdomadaires</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Indiquez les heures de travail pour chaque jour. Laissez vide pour les jours non travaillés.</p>
                <form method="post" action="horaires.php?id=<?php echo $id; ?>">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Jour</th>
                                    <th>Heure début</th>
                                    <th>Heure fin</th>
                                    <th>Pause début</th>
                                    <th>Pause fin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($joursSemaine as $jour): ?>
                                <?php $h = $parJour[$jour] ?? null; ?>
                                <tr class="horaire-row">
                                    <td><strong><?php echo $joursLabels[$jour]; ?></strong></td>
                                    <td>
                                        <input type="time" name="heure_debut[<?php echo $jour; ?>]" 
                                               class="form-control" value="<?php echo $h ? date('H:i', strtotime($h['heure_debut'])) : ''; ?>">
                                    </td>
                                    <td>
                                        <input type="time" name="heure_fin[<?php echo $jour; ?>]" 
                                               class="form-control" value="<?php echo $h ? date('H:i', strtotime($h['heure_fin'])) : ''; ?>">
                                    </td>
                                    <td>
                                        <input type="time" name="pause_debut[<?php echo $jour; ?>]" 
                                               class="form-control" value="<?php echo $h && $h['pause_debut'] ? date('H:i', strtotime($h['pause_debut'])) : ''; ?>"
                                               placeholder="Optionnel">
                                    </td>
                                    <td>
                                        <input type="time" name="pause_fin[<?php echo $jour; ?>]" 
                                               class="form-control" value="<?php echo $h && $h['pause_fin'] ? date('H:i', strtotime($h['pause_fin'])) : ''; ?>"
                                               placeholder="Optionnel">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Enregistrer les horaires
                        </button>
                        <a href="voir.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php app_module_page_end(); ?>
