<?php
/**
 * Gestion des documents du patient
 * Permet d'ajouter, visualiser et télécharger les documents médicaux
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('patients'));

require_once __DIR__ . '/../models/Patient.php';

$patientModel = new Patient();

// Accepter soit 'id' soit 'patient_id' pour la compatibilité
$id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$categorieFilter = isset($_GET['categorie']) ? $_GET['categorie'] : '';

if (!$id) {
    header("Location: index.php");
    exit();
}

$patient = $patientModel->getById($id);
if (!$patient) {
    header("Location: index.php");
    exit();
}

// Créer le dossier de stockage s'il n'existe pas
$uploadDir = __DIR__ . '/../uploads/patients/' . $patient['id'];
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$message = '';
$error = '';

// Traitement de l'upload
if ($_POST && isset($_FILES['document'])) {
    $file = $_FILES['document'];
    $categorie = $_POST['categorie'] ?? 'general';
    $description = $_POST['description'] ?? '';
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ];
        
        if (in_array($file['type'], $allowedTypes)) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $originalName = $file['name']; // Conserver le nom original
            $filename = date('Y-m-d_H-i-s') . '_' . $categorie . '.' . $extension;
            $filepath = $uploadDir . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Créer un fichier .info pour stocker les métadonnées
                $infoFile = $uploadDir . '/' . $filename . '.info';
                $metadata = [
                    'original_name' => $originalName,
                    'categorie' => $categorie,
                    'description' => $description,
                    'upload_date' => date('Y-m-d H:i:s'),
                    'file_size' => $file['size'],
                    'mime_type' => $file['type']
                ];
                file_put_contents($infoFile, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
                $message = "Document uploadé avec succès !";
            } else {
                $error = "Erreur lors de l'upload du fichier.";
            }
        } else {
            $error = "Type de fichier non autorisé.";
        }
    } else {
        $error = "Erreur lors de l'upload : " . $file['error'];
    }
}

// Suppression de document
if (isset($_GET['delete'])) {
    $filename = $_GET['delete'];
    $filepath = $uploadDir . '/' . $filename;
    $infoFile = $uploadDir . '/' . $filename . '.info';
    
    $deleted = false;
    
    // Supprimer le fichier principal
    if (file_exists($filepath) && unlink($filepath)) {
        $deleted = true;
    }
    
    // Supprimer le fichier .info associé s'il existe
    if (file_exists($infoFile) && unlink($infoFile)) {
        // Fichier .info supprimé
    }
    
    if ($deleted) {
        $message = "Document supprimé avec succès !";
    } else {
        $error = "Erreur lors de la suppression.";
    }
}

// Lister les documents existants avec filtrage par catégorie
$documents = [];
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && !str_ends_with($file, '.info')) {
            $filepath = $uploadDir . '/' . $file;
            $infoFile = $uploadDir . '/' . $file . '.info';
            
            // Lire les métadonnées si elles existent
            $metadata = [];
            if (file_exists($infoFile)) {
                $metadata = json_decode(file_get_contents($infoFile), true) ?: [];
            }
            
            // Appliquer le filtre de catégorie si spécifié
            if ($categorieFilter && (!isset($metadata['categorie']) || $metadata['categorie'] !== $categorieFilter)) {
                continue;
            }
            
            $documents[] = [
                'filename' => $file,
                'original_name' => $metadata['original_name'] ?? $file,
                'categorie' => $metadata['categorie'] ?? 'general',
                'description' => $metadata['description'] ?? '',
                'upload_date' => $metadata['upload_date'] ?? date('Y-m-d H:i:s', filemtime($filepath)),
                'size' => filesize($filepath),
                'type' => mime_content_type($filepath)
            ];
        }
    }
    
    // Trier par date d'upload (plus récent en premier)
    usort($documents, function($a, $b) {
        return strtotime($b['upload_date']) - strtotime($a['upload_date']);
    });
}

// Fonction pour formater la taille des fichiers
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Titre de la page selon la catégorie
$pageTitle = "Gestion des Documents";
if ($categorieFilter) {
    $categorieLabels = [
        'photos_medicales' => 'Photos Médicales',
        'rapports' => 'Rapports',
        'analyses' => 'Analyses de Laboratoire',
        'ordonnances' => 'Ordonnances',
        'certificats' => 'Certificats Médicaux',
        'autres' => 'Autres Documents'
    ];
    $pageTitle .= " - " . ($categorieLabels[$categorieFilter] ?? ucfirst($categorieFilter));
}

app_module_page_start([
    'active'    => 'patients',
    'title'     => $pageTitle,
    'subtitle'  => htmlspecialchars($patient['prenom'] . ' ' . $patient['nom']) . ' — Dossier #' . htmlspecialchars($patient['numero_dossier']),
    'icon'      => 'fa-notes-medical',
    'extra_css' => ['assets/css/app-patients.css'],
]);
app_module_back_toolbar(app_url('patients/index.php'), 'Retour à la liste', [
    ['href' => app_url('patients/voir.php?id=' . $patient['id']), 'label' => 'Voir le patient', 'icon' => 'fa-eye', 'class' => 'btn-info'],
]);
app_module_flash();
?>

        <!-- Filtres par catégorie -->
        <div class="card mb-4">
            <div class="card-body">
                <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Filtrer par catégorie :</h6>
                <div class="d-flex flex-wrap">
                    <a href="?patient_id=<?php echo $patient['id']; ?>" 
                       class="btn btn-outline-secondary btn-category <?php echo !$categorieFilter ? 'active' : ''; ?>">
                        <i class="fas fa-folder me-2"></i>Toutes
                    </a>
                    <a href="?patient_id=<?php echo $patient['id']; ?>&categorie=photos_medicales" 
                       class="btn btn-outline-secondary btn-category <?php echo $categorieFilter === 'photos_medicales' ? 'active' : ''; ?>">
                        <i class="fas fa-camera me-2"></i>Photos
                    </a>
                    <a href="?patient_id=<?php echo $patient['id']; ?>&categorie=rapports" 
                       class="btn btn-outline-secondary btn-category <?php echo $categorieFilter === 'rapports' ? 'active' : ''; ?>">
                        <i class="fas fa-file-medical me-2"></i>Rapports
                    </a>
                    <a href="?patient_id=<?php echo $patient['id']; ?>&categorie=analyses" 
                       class="btn btn-outline-secondary btn-category <?php echo $categorieFilter === 'analyses' ? 'active' : ''; ?>">
                        <i class="fas fa-flask me-2"></i>Analyses
                    </a>
                    <a href="?patient_id=<?php echo $patient['id']; ?>&categorie=ordonnances" 
                       class="btn btn-outline-secondary btn-category <?php echo $categorieFilter === 'ordonnances' ? 'active' : ''; ?>">
                        <i class="fas fa-prescription me-2"></i>Ordonnances
                    </a>
                    <a href="?patient_id=<?php echo $patient['id']; ?>&categorie=certificats" 
                       class="btn btn-outline-secondary btn-category <?php echo $categorieFilter === 'certificats' ? 'active' : ''; ?>">
                        <i class="fas fa-certificate me-2"></i>Certificats
                    </a>
                    <a href="?patient_id=<?php echo $patient['id']; ?>&categorie=autres" 
                       class="btn btn-outline-secondary btn-category <?php echo $categorieFilter === 'autres' ? 'active' : ''; ?>">
                        <i class="fas fa-file me-2"></i>Autres
                    </a>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Colonne principale -->
            <div class="col-md-8">
                <!-- Formulaire d'upload -->
                <div class="card document-card">
                    <div class="card-header bg-primary text-white document-header">
                        <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Ajouter un Document</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="categorie" class="form-label">Catégorie *</label>
                                    <select name="categorie" id="categorie" class="form-select" required>
                                        <option value="">Sélectionner une catégorie</option>
                                        <option value="photos_medicales" <?php echo $categorieFilter === 'photos_medicales' ? 'selected' : ''; ?>>Photos Médicales</option>
                                        <option value="rapports" <?php echo $categorieFilter === 'rapports' ? 'selected' : ''; ?>>Rapports</option>
                                        <option value="analyses" <?php echo $categorieFilter === 'analyses' ? 'selected' : ''; ?>>Analyses de Laboratoire</option>
                                        <option value="ordonnances" <?php echo $categorieFilter === 'ordonnances' ? 'selected' : ''; ?>>Ordonnances</option>
                                        <option value="certificats" <?php echo $categorieFilter === 'certificats' ? 'selected' : ''; ?>>Certificats Médicaux</option>
                                        <option value="autres" <?php echo $categorieFilter === 'autres' ? 'selected' : ''; ?>>Autres Documents</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="document" class="form-label">Fichier *</label>
                                    <input type="file" name="document" id="document" class="form-control" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="3" placeholder="Description du document..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Télécharger le document
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Liste des documents -->
                <div class="card document-card">
                    <div class="card-header bg-success text-white document-header">
                        <h5 class="mb-0">
                            <i class="fas fa-files-o me-2"></i>
                            Documents <?php echo $categorieFilter ? "($categorieFilter)" : ""; ?>
                            <span class="badge bg-light text-dark ms-2"><?php echo count($documents); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($documents)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucun document trouvé<?php echo $categorieFilter ? " dans cette catégorie" : ""; ?></p>
                                <?php if ($categorieFilter): ?>
                                    <a href="?patient_id=<?php echo $patient['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-eye me-2"></i>Voir tous les documents
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($documents as $doc): ?>
                                <div class="document-item p-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center">
                                                <div class="text-center me-3">
                                                    <?php if (strpos($doc['type'], 'image/') === 0): ?>
                                                        <i class="fas fa-file-image text-success file-icon"></i>
                                                    <?php elseif (strpos($doc['type'], 'pdf') !== false): ?>
                                                        <i class="fas fa-file-pdf text-danger file-icon"></i>
                                                    <?php elseif (strpos($doc['type'], 'word') !== false): ?>
                                                        <i class="fas fa-file-word text-primary file-icon"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-file text-secondary file-icon"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($doc['original_name']); ?></h6>
                                                    <div class="mb-2">
                                                        <span class="badge bg-primary category-badge me-2">
                                                            <?php echo ucfirst($doc['categorie']); ?>
                                                        </span>
                                                        <small class="text-muted">
                                                            <?php echo formatFileSize($doc['size']); ?> • 
                                                            <?php echo date('d/m/Y H:i', strtotime($doc['upload_date'])); ?>
                                                        </small>
                                                    </div>
                                                    <?php if ($doc['description']): ?>
                                                        <p class="text-muted mb-0 small"><?php echo htmlspecialchars($doc['description']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <div class="btn-group" role="group">
                                                <a href="view_document.php?patient_id=<?php echo $patient['id']; ?>&file=<?php echo urlencode($doc['filename']); ?>&original_name=<?php echo urlencode($doc['original_name']); ?>" 
                                                   class="btn btn-sm btn-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="download.php?patient_id=<?php echo $patient['id']; ?>&file=<?php echo urlencode($doc['filename']); ?>&original_name=<?php echo urlencode($doc['original_name']); ?>" 
                                                   class="btn btn-sm btn-success" title="Télécharger">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <a href="?patient_id=<?php echo $patient['id']; ?>&delete=<?php echo urlencode($doc['filename']); ?>" 
                                                   class="btn btn-sm btn-danger" title="Supprimer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce document ?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Colonne latérale -->
            <div class="col-md-4">
                <!-- Informations sur les types de fichiers -->
                <div class="card document-card">
                    <div class="card-header bg-info text-white document-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Types de Fichiers Supportés</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="fas fa-file-pdf text-danger me-2"></i>
                            <strong>PDF</strong> - Rapports, ordonnances
                        </div>
                        <div class="mb-3">
                            <i class="fas fa-file-word text-primary me-2"></i>
                            <strong>Word</strong> - Documents texte
                        </div>
                        <div class="mb-3">
                            <i class="fas fa-file-image text-success me-2"></i>
                            <strong>Images</strong> - Photos, radiographies
                        </div>
                        <div class="mb-3">
                            <i class="fas fa-file-alt text-secondary me-2"></i>
                            <strong>Texte</strong> - Notes, rapports
                        </div>
                    </div>
                </div>

                <!-- Statistiques des documents -->
                <div class="card document-card">
                    <div class="card-header bg-warning text-white document-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistiques</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                            <div class="mb-3">
                                <h3 class="text-primary"><?php echo count($documents); ?></h3>
                                <small class="text-muted">Documents au total</small>
                            </div>
                            
                            <?php
                            $totalSize = 0;
                            foreach ($documents as $doc) {
                                $totalSize += $doc['size'];
                            }
                            ?>
                            <div class="mb-3">
                                <h4 class="text-success"><?php echo formatFileSize($totalSize); ?></h4>
                                <small class="text-muted">Taille totale</small>
                            </div>
                            
                            <div>
                                <h5 class="text-info"><?php echo count(array_filter($documents, function($d) { return strpos($d['type'], 'image/') === 0; })); ?></h5>
                                <small class="text-muted">Images</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="card document-card">
                    <div class="card-header bg-success text-white document-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions Rapides</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="dossier_medical.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-notes-medical me-2"></i>Dossier médical
                            </a>
                            <a href="../consultations/ajouter.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success" target="_blank">
                                <i class="fas fa-stethoscope me-2"></i>Nouvelle consultation
                            </a>
                            <a href="voir.php?id=<?php echo $patient['id']; ?>" class="btn btn-info">
                                <i class="fas fa-eye me-2"></i>Voir patient
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Boutons d'action en bas -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="dossier_medical.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-notes-medical me-2"></i>Dossier médical complet
                </a>
                <a href="voir.php?id=<?php echo $patient['id']; ?>" class="btn btn-info btn-lg me-3">
                    <i class="fas fa-eye me-2"></i>Voir patient
                </a>
                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Retour à la liste
                </a>
            </div>
        </div>

<?php app_module_page_end();

