<?php
/**
 * Gestion des Documents Médicaux
 * Interface moderne pour gérer les différents types de documents par catégorie
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('patients'));

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    header("Location: index.php");
    exit();
}

$patientModel = new Patient();
$patient = $patientModel->getById($patient_id);

if (!$patient) {
    header("Location: index.php");
    exit();
}

// Connexion à la base de données
try {
    $pdo = getDB();
} catch(Exception $e) {
    die("Erreur de connexion: " . $e->getMessage());
}

// Traitement de l'upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $categorie = $_POST['categorie'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if ($categorie && isset($_FILES['document'])) {
        $file = $_FILES['document'];
        $uploadDir = "../uploads/patients/{$patient_id}/";
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        $fileType = $file['type'];
        
        // Vérifier le type de fichier
        $allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
            'application/pdf', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ];
        
        // Vérifier les erreurs d'upload
        if ($fileError !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par PHP (2M)',
                UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire',
                UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé',
                UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
                UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
                UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'upload du fichier'
            ];
            $error = $uploadErrors[$fileError] ?? 'Erreur d\'upload inconnue';
        } elseif (!in_array($fileType, $allowedTypes)) {
            $error = "Type de fichier non autorisé: $fileType. Types autorisés: " . implode(', ', array_map(function($type) {
                return pathinfo($type, PATHINFO_EXTENSION) ?: $type;
            }, $allowedTypes));
        } elseif ($fileSize > 2 * 1024 * 1024) { // 2MB
            $error = "Le fichier est trop volumineux. Taille maximale: 2MB, votre fichier: " . number_format($fileSize / 1024 / 1024, 2) . "MB";
        } else {
            // Générer un nom unique
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueName = uniqid() . '_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $uniqueName;
            
            if (move_uploaded_file($fileTmpName, $uploadPath)) {
                $columns = ['patient_id', 'nom_fichier', 'nom_original', 'type_mime', 'taille', 'categorie', 'description', 'date_upload'];
                $placeholders = array_fill(0, count($columns) - 1, '?');
                $placeholders[] = 'NOW()';
                $values = [$patient_id, $uniqueName, $fileName, $fileType, $fileSize, $categorie, $description];
                TenantScope::bindInsert($pdo, 'documents_patients', $columns, $placeholders, $values);
                $stmt = $pdo->prepare(
                    'INSERT INTO documents_patients (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
                );
                $stmt->execute($values);
                
                $success = "Document uploadé avec succès !";
            } else {
                $error = "Erreur lors de l'upload du fichier.";
            }
        }
    }
}

// Vérifier si le dossier médical existe
$dossierMedicalPath = "../uploads/patients/{$patient_id}/dossier_medical.pdf";
$dossierMedicalExists = file_exists($dossierMedicalPath);

// Catégories de documents
$categories = [
    'photos_medicales' => [
        'title' => 'Photos médicales',
        'icon' => 'fas fa-image',
        'color' => 'success',
        'description' => 'Images et photos médicales'
    ],
    'rapports' => [
        'title' => 'Rapports',
        'icon' => 'fas fa-file-alt',
        'color' => 'info',
        'description' => 'Rapports médicaux et comptes-rendus'
    ],
    'analyses' => [
        'title' => 'Analyses',
        'icon' => 'fas fa-flask',
        'color' => 'warning',
        'description' => 'Résultats d\'analyses de laboratoire'
    ],
    'ordonnances' => [
        'title' => 'Ordonnances',
        'icon' => 'fas fa-prescription',
        'color' => 'primary',
        'description' => 'Prescriptions et ordonnances médicales'
    ]
];

// Récupérer les documents récents et compter les documents pour chaque catégorie
$documentsRecents = [];
$stats = [];

// IMPORTANT: Initialiser d'abord toutes les catégories à 0
foreach ($categories as $key => $cat) {
    $stats[$key] = 0;
}

// Ensuite, récupérer les vrais comptages depuis la base de données
foreach ($categories as $key => $cat) {
    $where = ['patient_id = ?', 'categorie = ?'];
    $params = [$patient_id, $key];
    TenantScope::appendWhere($pdo, 'documents_patients', $where, $params);

    $stmt = $pdo->prepare(
        'SELECT * FROM documents_patients WHERE ' . implode(' AND ', $where) . ' ORDER BY date_upload DESC LIMIT 3'
    );
    $stmt->execute($params);
    $documentsRecents[$key] = $stmt->fetchAll();
    
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM documents_patients WHERE ' . implode(' AND ', $where));
    $stmt->execute($params);
    $count = $stmt->fetch();
    $stats[$key] = (int)$count['total'];
}

// Debug: Afficher les valeurs pour vérification
error_log("DEBUG - Stats pour patient $patient_id: " . print_r($stats, true));

app_module_page_start([
    'active'    => 'patients',
    'title'     => 'Documents',
    'subtitle'  => htmlspecialchars($patient['nom'] . ' ' . $patient['prenom']) . ' — Dossier ' . htmlspecialchars($patient['numero_dossier']),
    'icon'      => 'fa-folder-plus',
    'extra_css' => ['assets/css/app-patients.css'],
]);
app_module_back_toolbar(app_url('patients/index.php'), 'Retour à la liste', [
    ['href' => app_url('patients/voir.php?id=' . $patient_id), 'label' => 'Voir le patient', 'icon' => 'fa-eye', 'class' => 'btn-info'],
    ['href' => app_url('patients/gerer_documents.php?patient_id=' . $patient_id), 'label' => 'Gestion avancée', 'icon' => 'fa-cogs', 'class' => 'btn-outline-primary'],
]);
app_module_flash();
?>
<style>
    .document-card { transition: transform 0.2s, box-shadow 0.2s; border: none; border-radius: 12px; }
    .document-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .category-icon { font-size: 2rem; margin-bottom: 1rem; }
    .upload-area { border: 2px dashed #dee2e6; border-radius: 8px; padding: 2rem; text-align: center; transition: all 0.3s; }
    .upload-area:hover { border-color: #007bff; background-color: #f8f9fa; }
    .document-count { font-size: 0.9rem; color: #6c757d; }
    .btn-category { border-radius: 8px; font-weight: 500; }
    .file-input-hidden { display: none !important; }
    .choose-file-btn { margin-top: 1rem; padding: 0.5rem 1rem; font-size: 0.9rem; }
</style>

        <!-- Messages d'alerte -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Catégories de documents -->
        <div class="row">
            <!-- Dossier médical -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card document-card h-100">
                    <div class="card-body text-center">
                        <div class="category-icon text-danger">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h5 class="card-title">Dossier médical</h5>
                        <p class="card-text text-muted">PDF complet</p>
                        <?php if ($dossierMedicalExists): ?>
                            <a href="download.php?patient_id=<?php echo $patient_id; ?>&file=dossier_medical.pdf" 
                               class="btn btn-danger btn-category">
                                <i class="fas fa-download me-1"></i>Télécharger
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-danger btn-category" disabled>
                                <i class="fas fa-plus me-1"></i>Générer
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Photos médicales -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card document-card h-100">
                    <div class="card-body text-center">
                        <div class="category-icon text-success">
                            <i class="fas fa-image"></i>
                        </div>
                        <h5 class="card-title">Photos médicales</h5>
                        <p class="document-count"><?php echo $stats['photos_medicales']; ?> fichier(s)</p>
                        <button class="btn btn-success btn-category" data-bs-toggle="modal" data-bs-target="#uploadModal" 
                                data-category="photos_medicales" data-title="Photos médicales">
                            <i class="fas fa-plus me-1"></i>Ajouter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Rapports -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card document-card h-100">
                    <div class="card-body text-center">
                        <div class="category-icon text-info">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h5 class="card-title">Rapports</h5>
                        <p class="document-count"><?php echo $stats['rapports']; ?> fichier(s)</p>
                        <button class="btn btn-info btn-category" data-bs-toggle="modal" data-bs-target="#uploadModal" 
                                data-category="rapports" data-title="Rapports">
                            <i class="fas fa-plus me-1"></i>Ajouter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Analyses -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card document-card h-100">
                    <div class="card-body text-center">
                        <div class="category-icon text-warning">
                            <i class="fas fa-flask"></i>
                        </div>
                        <h5 class="card-title">Analyses</h5>
                        <p class="document-count"><?php echo $stats['analyses']; ?> fichier(s)</p>
                        <button class="btn btn-warning btn-category" data-bs-toggle="modal" data-bs-target="#uploadModal" 
                                data-category="analyses" data-title="Analyses">
                            <i class="fas fa-plus me-1"></i>Ajouter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ordonnances -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card document-card h-100">
                    <div class="card-body text-center">
                        <div class="category-icon text-primary">
                            <i class="fas fa-prescription"></i>
                        </div>
                        <h5 class="card-title">Ordonnances</h5>
                        <p class="document-count"><?php echo $stats['ordonnances']; ?> fichier(s)</p>
                        <button class="btn btn-primary btn-category" data-bs-toggle="modal" data-bs-target="#uploadModal" 
                                data-category="ordonnances" data-title="Ordonnances">
                            <i class="fas fa-plus me-1"></i>Ajouter
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents récents -->
        <div class="row mt-4">
            <?php foreach ($categories as $key => $cat): ?>
                <?php if (!empty($documentsRecents[$key])): ?>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="<?php echo $cat['icon']; ?> me-2 text-<?php echo $cat['color']; ?>"></i><?php echo $cat['title']; ?></h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($documentsRecents[$key] as $doc): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted"><?php echo htmlspecialchars($doc['nom_original']); ?></small>
                                        <div>
                                            <a href="view_document.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="download.php?patient_id=<?php echo $patient_id; ?>&file=<?php echo $doc['nom_fichier']; ?>" 
                                               class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Bouton de gestion -->
        <div class="text-center mt-4">
            <a href="gestion_documents.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-secondary btn-lg">
                <i class="fas fa-folder me-2"></i>Gérer tous les documents
            </a>
        </div>

    <!-- Modal d'upload -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="categorie" id="uploadCategorie">
                        
                        <div class="mb-3">
                            <label class="form-label">Catégorie</label>
                            <input type="text" class="form-control" id="uploadCategorieLabel" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Description du document..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Fichier</label>
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                <p class="mb-0">Glissez-déposez un fichier ici</p>
                                <p class="text-muted small">ou</p>
                                <button type="button" class="btn btn-primary choose-file-btn" id="chooseFileBtn">
                                    <i class="fas fa-folder-open me-2"></i>Choisir un fichier
                                </button>
                                <input type="file" name="document" id="fileInput" class="file-input-hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.txt" required>
                            </div>
                            <div id="fileInfo" class="mt-2 d-none">
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong id="fileName"></strong> sélectionné
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>Uploader
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php ob_start(); ?>
<script>
document.getElementById('uploadModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const category = button.getAttribute('data-category');
            const title = button.getAttribute('data-title');
            
            document.getElementById('uploadCategorie').value = category;
            document.getElementById('uploadCategorieLabel').value = title;
        });

        // Gestion de l'upload de fichiers
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const uploadArea = document.getElementById('uploadArea');
        const chooseFileBtn = document.getElementById('chooseFileBtn');

        // Cliquer sur le bouton pour ouvrir le sélecteur de fichiers
        chooseFileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.click();
        });

        // Afficher les informations du fichier sélectionné
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                const file = this.files[0];
                fileName.textContent = file.name;
                fileInfo.classList.remove('d-none');
                uploadArea.style.borderColor = '#28a745';
                uploadArea.style.backgroundColor = '#f8fff9';
                chooseFileBtn.innerHTML = '<i class="fas fa-check me-2"></i>Fichier sélectionné';
                chooseFileBtn.classList.remove('btn-primary');
                chooseFileBtn.classList.add('btn-success');
            }
        });

        // Drag and drop pour l'upload
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#007bff';
            this.style.backgroundColor = '#f8f9fa';
        });

        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#dee2e6';
            this.style.backgroundColor = 'transparent';
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#dee2e6';
            this.style.backgroundColor = 'transparent';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                const file = files[0];
                fileName.textContent = file.name;
                fileInfo.classList.remove('d-none');
                this.style.borderColor = '#28a745';
                this.style.backgroundColor = '#f8fff9';
                chooseFileBtn.innerHTML = '<i class="fas fa-check me-2"></i>Fichier sélectionné';
                chooseFileBtn.classList.remove('btn-primary');
                chooseFileBtn.classList.add('btn-success');
            }
        });

        // Réinitialiser le formulaire quand le modal se ferme
        document.getElementById('uploadModal').addEventListener('hidden.bs.modal', function () {
            fileInput.value = '';
            fileInfo.classList.add('d-none');
            uploadArea.style.borderColor = '#dee2e6';
            uploadArea.style.backgroundColor = 'transparent';
            chooseFileBtn.innerHTML = '<i class="fas fa-folder-open me-2"></i>Choisir un fichier';
            chooseFileBtn.classList.remove('btn-success');
            chooseFileBtn.classList.add('btn-primary');
});
</script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
