<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
require_once __DIR__ . '/../includes/app_home_modules.php';
extract(app_module_context('medecins'));

require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../includes/medecin_profil.php';
require_once __DIR__ . '/../includes/medecin_settings.php';

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

$isOwnProfile = $auth->estClinicienScope() && !$auth->estAdmin();
$profilType = $medecin['type_profil'] ?? 'medecin';
$pageTitle = $isOwnProfile ? 'Mon profil' : 'Fiche professionnelle';

app_module_page_start([
    'active'   => 'medecins',
    'title'    => $pageTitle,
    'subtitle' => htmlspecialchars(medecin_profil_format_name($medecin)),
    'icon'     => 'fa-user-md',
]);
if ($auth->estAdmin()) {
    app_module_back_toolbar(app_url('medecins/index.php'), 'Retour à la liste', [
        ['href' => app_url('medecins/modifier.php?id=' . $medecin['id']), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-warning'],
    ]);
} elseif ($auth->estSecretaire() && secretaire_medecin_add_allowed()) {
    app_module_back_toolbar(app_url('medecins/index.php'), 'Retour à la liste');
} else {
    app_module_toolbar([
        ['href' => app_url('medecins/modifier.php?id=' . $medecin['id']), 'label' => 'Modifier mon profil', 'icon' => 'fa-edit', 'class' => 'btn-warning'],
    ]);
}
app_module_flash();
?>

        <?php if ($isOwnProfile): ?>
        <div class="card app-mod-form-card mb-4 border-primary">
            <div class="card-body">
                <h5 class="card-title text-primary mb-3"><i class="fas fa-briefcase-medical me-2"></i>Mes modules</h5>
                <p class="text-muted small mb-3">Accédez directement à vos activités cliniques depuis votre profil.</p>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach (app_medecin_workspace_links($auth) as $link): ?>
                    <a href="<?= app_url($link['href']) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas <?= htmlspecialchars($link['icon']) ?> me-1"></i><?= htmlspecialchars($link['label']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card app-mod-form-card">
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h4><?php echo htmlspecialchars(medecin_profil_format_name($medecin)); ?></h4>
                        <p class="text-muted mb-1">
                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars(medecin_profil_label($profilType)); ?></span>
                        </p>
                        <p class="text-muted mb-0">Numéro de licence: <?php echo $medecin['numero_licence'] ? htmlspecialchars($medecin['numero_licence']) : 'Non renseigné'; ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="status-badge status-<?php echo $medecin['statut']; ?>">
                            <?php echo ucfirst($medecin['statut']); ?>
                        </span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h5 class="text-primary mb-3">Informations Professionnelles</h5>
                        
                        <div class="mb-3">
                            <strong><?php echo medecin_profil_is_paramedical($profilType) ? 'Fonction / service' : 'Spécialité'; ?> :</strong><br>
                            <span class="badge bg-info"><?php echo $medecin['specialite'] ? htmlspecialchars($medecin['specialite']) : 'Non renseigné'; ?></span>
                        </div>

                        <div class="mb-3">
                            <strong>Date d'embauche :</strong><br>
                            <?php echo date('d/m/Y', strtotime($medecin['date_embauche'])); ?>
                        </div>

                        <div class="mb-3">
                            <strong>Date de création :</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($medecin['date_creation'])); ?>
                        </div>

                        <?php if (isset($medecin['date_modification']) && $medecin['date_modification'] !== $medecin['date_creation']): ?>
                        <div class="mb-3">
                            <strong>Dernière modification :</strong><br>
                            <?php echo date('d/m/Y H:i', strtotime($medecin['date_modification'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <h5 class="text-primary mb-3">Coordonnées</h5>
                        
                        <div class="mb-3">
                            <strong>Téléphone :</strong><br>
                            <?php echo $medecin['telephone'] ?: 'Non renseigné'; ?>
                        </div>

                        <div class="mb-3">
                            <strong>Email :</strong><br>
                            <?php echo $medecin['email'] ?: 'Non renseigné'; ?>
                        </div>

                        <div class="mb-3">
                            <strong>Adresse :</strong><br>
                            <?php echo $medecin['adresse'] ?: 'Non renseignée'; ?>
                        </div>

                        <div class="mb-3">
                            <strong>Ville :</strong><br>
                            <?php echo $medecin['ville'] ?: 'Non renseignée'; ?>
                        </div>

                        <div class="mb-3">
                            <strong>Code postal :</strong><br>
                            <?php echo $medecin['code_postal'] ?: 'Non renseigné'; ?>
                        </div>

                        <div class="mb-3">
                            <strong>Pays :</strong><br>
                            <?php echo $medecin['pays'] ?: 'France'; ?>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12 text-center">
                        <a href="modifier.php?id=<?php echo $medecin['id']; ?>" class="btn btn-warning btn-lg me-3">
                            <i class="fas fa-edit me-2"></i>Modifier cette fiche
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i>Retour à la liste
                        </a>
                    </div>
                </div>
            </div>
        </div>

<?php app_module_page_end();





