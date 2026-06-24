<?php
/**
 * Visualisation des documents patients
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('patients'));

require_once __DIR__ . '/../models/Patient.php';

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$filename = isset($_GET['file']) ? $_GET['file'] : '';

if (!$patient_id || !$filename) {
    http_response_code(400);
    die('Paramètres manquants : patient_id et file sont requis');
}

try {
    $patientModel = new Patient();
    $patient = $patientModel->getById($patient_id);
    
    if (!$patient) {
        http_response_code(404);
        die('Patient non trouvé');
    }
    
    $filepath = __DIR__ . '/../uploads/patients/' . $patient_id . '/' . basename($filename);
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        die('Fichier non trouvé sur le serveur');
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    die('Erreur de base de données');
}

// Récupérer les métadonnées depuis le fichier .info
$infoFile = "../uploads/patients/{$patient_id}/{$filename}.info";
$originalName = $filename;
$categorie = 'general';
$description = '';
$uploadDate = filemtime($filepath);

if (file_exists($infoFile)) {
    $metadata = json_decode(file_get_contents($infoFile), true);
    if ($metadata) {
        $originalName = $metadata['original_name'] ?? $filename;
        $categorie = $metadata['categorie'] ?? 'general';
        $description = $metadata['description'] ?? '';
        $uploadDate = isset($metadata['upload_date']) ? strtotime($metadata['upload_date']) : filemtime($filepath);
    }
}

// Déterminer la catégorie à partir du nom de fichier si pas de fichier .info
if ($categorie === 'general') {
    if (strpos($filename, '_ordonnance.') !== false) $categorie = 'ordonnance';
    elseif (strpos($filename, '_analyse.') !== false) $categorie = 'analyse';
    elseif (strpos($filename, '_radiographie.') !== false) $categorie = 'radiographie';
    elseif (strpos($filename, '_echographie.') !== false) $categorie = 'echographie';
    elseif (strpos($filename, '_rapport.') !== false) $categorie = 'rapport';
    elseif (strpos($filename, '_photo.') !== false) $categorie = 'photo';
    elseif (strpos($filename, '_general.') !== false) $categorie = 'general';
}

// Déterminer le type MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Vérifier si c'est une image
$is_image = strpos($mime_type, 'image/') === 0;
$is_pdf = $mime_type === 'application/pdf';
$is_text = strpos($mime_type, 'text/') === 0;

// Si c'est un document Word ou autre, proposer le téléchargement
if (!$is_image && !$is_pdf && !$is_text) {
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $originalName . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

// Extraire les informations du nom de fichier
$file_info = pathinfo($filename);
$file_extension = $file_info['extension'];
$file_size = filesize($filepath);

$streamUrl = function_exists('app_url')
    ? app_url('patients/stream_document.php?patient_id=' . $patient_id . '&file=' . urlencode($filename))
    : 'stream_document.php?patient_id=' . $patient_id . '&file=' . urlencode($filename);

app_module_page_start([
    'active'    => 'patients',
    'title'     => 'Visualisation du Document',
    'subtitle'  => htmlspecialchars($originalName) . ' — ' . htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']),
    'icon'      => 'fa-eye',
    'extra_css' => ['assets/css/app-patients.css'],
]);
app_module_back_toolbar(app_url('patients/gerer_documents.php?patient_id=' . $patient_id), 'Retour aux documents', [
    ['href' => 'download.php?patient_id=' . $patient_id . '&file=' . urlencode($filename) . '&original_name=' . urlencode($originalName), 'label' => 'Télécharger', 'icon' => 'fa-download', 'class' => 'btn-success'],
]);
app_module_flash();
?>
<style>
    .document-viewer { min-height: 80vh; background: #f8f9fa; border-radius: 8px; padding: 20px; }
    .document-info { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .image-container { text-align: center; background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .image-container img { max-width: 100%; max-height: 70vh; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
    .pdf-container { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .pdf-container iframe { width: 100%; height: 70vh; border: none; border-radius: 4px; }
    .text-container { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); font-family: 'Courier New', monospace; white-space: pre-wrap; max-height: 70vh; overflow-y: auto; }
</style>

        <!-- Informations du document -->
        <div class="document-info">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-file me-2"></i>Informations du document</h5>
                    <p><strong>Nom du fichier :</strong> <?php echo htmlspecialchars($originalName); ?></p>
                    <p><strong>Catégorie :</strong> <?php echo htmlspecialchars(ucfirst($categorie)); ?></p>
                    <p><strong>Type MIME :</strong> <?php echo htmlspecialchars($mime_type); ?></p>
                    <p><strong>Extension :</strong> <?php echo strtoupper($file_extension); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Taille :</strong> <?php echo number_format($file_size / 1024, 2); ?> KB</p>
                    <p><strong>Date d'upload :</strong> <?php echo date('d/m/Y H:i', $uploadDate); ?></p>
                    <?php if ($description): ?>
                        <p><strong>Description :</strong> <?php echo htmlspecialchars($description); ?></p>
                    <?php endif; ?>
                    <p><strong>Nom système :</strong> <small class="text-muted"><?php echo htmlspecialchars($filename); ?></small></p>
                </div>
            </div>
        </div>

        <!-- Visualisation du document -->
        <div class="document-viewer">
            <?php if ($is_image): ?>
                <!-- Affichage des images -->
                <div class="image-container">
                    <img src="data:<?php echo $mime_type; ?>;base64,<?php echo base64_encode(file_get_contents($filepath)); ?>" 
                         alt="<?php echo htmlspecialchars($originalName); ?>">
                </div>
                
            <?php elseif ($is_pdf): ?>
                <!-- Affichage des PDFs (URL serveur — les data: URI sont bloqués par le navigateur) -->
                <div class="pdf-container">
                    <iframe src="<?php echo htmlspecialchars($streamUrl, ENT_QUOTES, 'UTF-8'); ?>"
                            title="<?php echo htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8'); ?>"></iframe>
                </div>
                <p class="text-center text-muted small mt-2 mb-0">
                    Si l’aperçu ne s’affiche pas,
                    <a href="download.php?patient_id=<?php echo $patient_id; ?>&file=<?php echo urlencode($filename); ?>&original_name=<?php echo urlencode($originalName); ?>">téléchargez le PDF</a>.
                </p>
                
            <?php elseif ($is_text): ?>
                <!-- Affichage des fichiers texte -->
                <div class="text-container">
                    <?php echo htmlspecialchars(file_get_contents($filepath)); ?>
                </div>
                
            <?php else: ?>
                <!-- Type de fichier non supporté -->
                <div class="text-center py-5">
                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                    <h5>Type de fichier non supporté pour la visualisation</h5>
                    <p class="text-muted">Ce type de fichier ne peut pas être affiché directement.</p>
                    <a href="download.php?patient_id=<?php echo $patient_id; ?>&file=<?php echo urlencode($filename); ?>&original_name=<?php echo urlencode($originalName); ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-download me-1"></i>Télécharger le fichier
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="text-center mt-4">
            <a href="gerer_documents.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-secondary me-2">
                <i class="fas fa-folder me-1"></i>Retour aux documents
            </a>
            <a href="download.php?patient_id=<?php echo $patient_id; ?>&file=<?php echo urlencode($filename); ?>&original_name=<?php echo urlencode($originalName); ?>" 
               class="btn btn-success me-2">
                <i class="fas fa-download me-1"></i>Télécharger
            </a>
            <a href="voir.php?id=<?php echo $patient_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-user me-1"></i>Voir le patient
            </a>
        </div>

<?php app_module_page_end();
