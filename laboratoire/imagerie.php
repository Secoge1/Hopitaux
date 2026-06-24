<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('laboratoire'));

require_once __DIR__ . '/../models/Analyse.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../models/Personnel.php';

$analyseModel = new Analyse();
$patientModel = new Patient();
$medecinModel = new Medecin();
$personnelModel = new Personnel();

$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';
$type_examen = $_GET['type_examen'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;

$filters = [];
if ($search) $filters['search'] = $search;
if ($statut) $filters['statut'] = $statut;
if ($type_examen) $filters['type_examen'] = $type_examen;

$analyses = $analyseModel->getImagerie($page, $limit, $filters);
$stats = $analyseModel->getStats();
$typesExamens = $analyseModel->getTypesExamensImagerie();
$statuts = $analyseModel->getStatuts();


app_module_page_start([
    'active'   => 'laboratoire',
    'title'    => 'Imagerie Médicale',
    'subtitle' => 'Radiologie, échographie, scanner, IRM',
    'icon'     => 'fa-flask',
]);
app_module_back_toolbar(app_url('laboratoire/index.php'), 'Retour au laboratoire', [['href' => app_url('laboratoire/ajouter.php?type=imagerie'), 'label' => 'Nouvel Examen', 'icon' => 'fa-plus', 'class' => 'btn-primary']]);
app_module_flash();
?>
<style>
.page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            margin: -1.5rem -1.5rem 2rem -1.5rem;
            border-radius: 0 0 20px 20px;
        }
        .imagerie-card {
            border-left: 4px solid;
            transition: transform 0.3s ease;
        }
        .imagerie-card:hover {
            transform: translateX(5px);
        }
        .imagerie-card.radiologie { border-left-color: #007bff; }
        .imagerie-card.echographie { border-left-color: #28a745; }
        .imagerie-card.scanner { border-left-color: #ffc107; }
        .imagerie-card.irm { border-left-color: #dc3545; }
        .imagerie-card.mammographie { border-left-color: #e83e8c; }
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
        }
</style>
                <div>
                    <a href="index.php" class="btn btn-light me-2">
                        <i class="fas fa-flask me-2"></i>Laboratoire
                    </a>
                    <a href="ajouter.php?type=imagerie" class="btn btn-light">
                        <i class="fas fa-plus me-2"></i>Nouvel Examen
                    </a>
                </div>
            </div>
        </div>

        <!-- Recherche -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Rechercher un patient..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="type_examen">
                            <option value="">Tous les types</option>
                            <?php foreach ($typesExamens as $key => $label): ?>
                                <?php if (in_array($key, ['radiologie', 'echographie', 'scanner', 'irm', 'mammographie'])): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $type_examen === $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="statut">
                            <option value="">Tous les statuts</option>
                            <?php foreach ($statuts as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $statut === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Rechercher
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des examens -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Examens d'Imagerie</h5>
            </div>
            <div class="card-body">
                <?php if (empty($analyses)): ?>
                    <p class="text-muted text-center">Aucun examen d'imagerie trouvé</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($analyses as $analyse): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card imagerie-card imagerie-card-<?php echo $analyse['type_examen'] ?? 'laboratoire'; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <span class="badge bg-<?php 
                                                    echo ($analyse['type_examen'] ?? 'laboratoire') === 'radiologie' ? 'primary' : 
                                                        (($analyse['type_examen'] ?? 'laboratoire') === 'echographie' ? 'success' : 
                                                        (($analyse['type_examen'] ?? 'laboratoire') === 'scanner' ? 'warning' : 
                                                        (($analyse['type_examen'] ?? 'laboratoire') === 'irm' ? 'danger' : 'info'))); 
                                                ?> me-2">
                                                    <?php echo $typesExamens[$analyse['type_examen'] ?? 'laboratoire'] ?? 'Laboratoire'; ?>
                                                </span>
                                                <?php echo htmlspecialchars($analyse['patient_nom'] ?? ''); ?> <?php echo htmlspecialchars($analyse['patient_prenom'] ?? ''); ?>
                                            </h6>
                                            <small class="text-muted">
                                                Dossier: <?php echo htmlspecialchars($analyse['numero_dossier'] ?? ''); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php 
                                            echo $analyse['statut'] === 'termine' ? 'success' : 
                                                ($analyse['statut'] === 'en_cours' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo $statuts[$analyse['statut']] ?? $analyse['statut']; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($analyse['description']): ?>
                                        <p class="mb-2"><small><?php echo nl2br(htmlspecialchars(substr($analyse['description'], 0, 100))); ?>...</small></p>
                                    <?php endif; ?>
                                    
                                    <?php if ($analyse['fichier_image']): ?>
                                        <div class="mb-2">
                                            <img src="../uploads/imagerie/<?php echo htmlspecialchars($analyse['fichier_image']); ?>" 
                                                 alt="Image" 
                                                 class="image-preview"
                                                 onclick="window.open(this.src, '_blank')">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-user-md me-1"></i>
                                            <?php echo htmlspecialchars($analyse['medecin_nom'] ?? ''); ?> <?php echo htmlspecialchars($analyse['medecin_prenom'] ?? ''); ?>
                                            <?php if ($analyse['technicien_nom']): ?>
                                                <br><i class="fas fa-user-cog me-1"></i>
                                                Tech: <?php echo htmlspecialchars($analyse['technicien_nom']); ?> <?php echo htmlspecialchars($analyse['technicien_prenom'] ?? ''); ?>
                                            <?php endif; ?>
                                        </small>
                                        <div>
                                            <a href="voir.php?id=<?php echo $analyse['id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="modifier.php?id=<?php echo $analyse['id']; ?>" class="btn btn-sm btn-outline-warning" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($analyse['date_creation'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
<?php app_module_page_end(); ?>
