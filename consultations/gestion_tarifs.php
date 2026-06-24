<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('consultations'));

require_once __DIR__ . '/../models/TarifConsultation.php';
require_once __DIR__ . '/../models/CategorieHospitalisation.php';
require_once __DIR__ . '/../models/SoinsConsultation.php';

$tarifModel = new TarifConsultation();
$categorieModel = new CategorieHospitalisation();
$soinsModel = new SoinsConsultation();

$message = '';
$error = '';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_tarif':
                    $data = [
                        'type_consultation' => $_POST['type_consultation'],
                        'specialite' => $_POST['specialite'] ?: null,
                        'prix' => $_POST['prix'],
                        'description' => $_POST['description'] ?: null,
                        'statut' => $_POST['statut'] ?: 'actif'
                    ];
                    if ($tarifModel->create($data)) {
                        $message = "Tarif ajouté avec succès !";
                    } else {
                        $error = "Erreur lors de l'ajout du tarif.";
                    }
                    break;
                    
                case 'add_categorie':
                    $data = [
                        'nom' => $_POST['nom'],
                        'description' => $_POST['description'] ?: null,
                        'prix_jour' => $_POST['prix_jour'],
                        'statut' => $_POST['statut'] ?: 'actif'
                    ];
                    if ($categorieModel->create($data)) {
                        $message = "Catégorie d'hospitalisation ajoutée avec succès !";
                    } else {
                        $error = "Erreur lors de l'ajout de la catégorie.";
                    }
                    break;
                    
                case 'update_tarif':
                    $data = [
                        'type_consultation' => $_POST['type_consultation'],
                        'specialite' => $_POST['specialite'] ?: null,
                        'prix' => $_POST['prix'],
                        'description' => $_POST['description'] ?: null,
                        'statut' => $_POST['statut']
                    ];
                    if ($tarifModel->update($_POST['id'], $data)) {
                        $message = "Tarif mis à jour avec succès !";
                    } else {
                        $error = "Erreur lors de la mise à jour du tarif.";
                    }
                    break;
                    
                case 'update_categorie':
                    $data = [
                        'nom' => $_POST['nom'],
                        'description' => $_POST['description'] ?: null,
                        'prix_jour' => $_POST['prix_jour'],
                        'statut' => $_POST['statut']
                    ];
                    if ($categorieModel->update($_POST['id'], $data)) {
                        $message = "Catégorie mise à jour avec succès !";
                    } else {
                        $error = "Erreur lors de la mise à jour de la catégorie.";
                    }
                    break;
                    
                case 'delete_tarif':
                    if ($tarifModel->delete($_POST['id'])) {
                        $message = "Tarif supprimé avec succès !";
                    } else {
                        $error = "Erreur lors de la suppression du tarif.";
                    }
                    break;
                    
                case 'delete_categorie':
                    if ($categorieModel->delete($_POST['id'])) {
                        $message = "Catégorie supprimée avec succès !";
                    } else {
                        $error = "Erreur lors de la suppression de la catégorie.";
                    }
                    break;
                    
                case 'add_soin':
                    $data = [
                        'nom' => $_POST['nom'],
                        'description' => $_POST['description'] ?: null,
                        'prix' => $_POST['prix'],
                        'type_soin' => $_POST['type_soin'],
                        'duree_minutes' => $_POST['duree_minutes'] ?: 30,
                        'statut' => $_POST['statut'] ?: 'actif'
                    ];
                    if ($soinsModel->create($data)) {
                        $message = "Soin ajouté avec succès !";
                    } else {
                        $error = "Erreur lors de l'ajout du soin.";
                    }
                    break;
                    
                case 'update_soin':
                    $data = [
                        'nom' => $_POST['nom'],
                        'description' => $_POST['description'] ?: null,
                        'prix' => $_POST['prix'],
                        'type_soin' => $_POST['type_soin'],
                        'duree_minutes' => $_POST['duree_minutes'] ?: 30,
                        'statut' => $_POST['statut']
                    ];
                    if ($soinsModel->update($_POST['id'], $data)) {
                        $message = "Soin mis à jour avec succès !";
                    } else {
                        $error = "Erreur lors de la mise à jour du soin.";
                    }
                    break;
                    
                case 'delete_soin':
                    if ($soinsModel->delete($_POST['id'])) {
                        $message = "Soin supprimé avec succès !";
                    } else {
                        $error = "Erreur lors de la suppression du soin.";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer tous les tarifs, catégories et soins (actifs et inactifs)
$tarifs = $tarifModel->getAll();
$categories = $categorieModel->getAll();
$soins = $soinsModel->getAll();

// Filtrer par statut si demandé
$statut_filter = isset($_GET['statut']) ? $_GET['statut'] : 'all';

if ($statut_filter !== 'all') {
    $tarifs = array_filter($tarifs, function($tarif) use ($statut_filter) {
        return $tarif['statut'] === $statut_filter;
    });
    
    $categories = array_filter($categories, function($categorie) use ($statut_filter) {
        return $categorie['statut'] === $statut_filter;
    });
    
    $soins = array_filter($soins, function($soin) use ($statut_filter) {
        return $soin['statut'] === $statut_filter;
    });
}

app_module_page_start([
    'active'   => 'consultations',
    'title'    => 'Gestion des Tarifs, Soins et Catégories',
    'subtitle' => 'Configuration tarifaire',
    'icon'     => 'fa-stethoscope',
]);
app_module_back_toolbar(app_url('consultations/index.php'), 'Retour aux consultations', []);
app_module_flash();
?>
<style>
.card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .btn-gradient { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border: none; }
        .btn-gradient:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4); }
        
        /* Amélioration de l'espacement et de la lisibilité */
        .card { 
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); 
            border: none;
            margin-bottom: 1.5rem;
        }
        .card-body { 
            padding: 1.5rem; 
        }
        .table-responsive { 
            border-radius: 8px; 
            overflow: hidden;
        }
        .table th { 
            background-color: #f8f9fa; 
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.75rem;
        }
        .table td { 
            padding: 0.75rem;
            vertical-align: middle;
        }
        .badge { 
            font-size: 0.75rem; 
            padding: 0.4em 0.8em;
        }
        .btn-sm { 
            padding: 0.375rem 0.75rem; 
            font-size: 0.875rem;
        }
        
        /* Styles spécifiques pour l'affichage des prix */
        .price-cell {
            text-align: right;
            font-weight: 600;
            color: #28a745;
            background-color: #f8fff9;
            border-left: 3px solid #28a745;
        }
        .price-value {
            font-size: 1.1em;
            font-weight: 700;
            color: #155724;
        }
        .price-currency {
            font-size: 0.9em;
            color: #6c757d;
            margin-left: 2px;
        }
        .table td.price-cell {
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }
        
        /* Amélioration des colonnes de prix */
        .table th.price-header {
            text-align: right;
            background-color: #e8f5e8;
            color: #155724;
            font-weight: 700;
        }
        
        /* Hover effect pour les cellules de prix */
        .price-cell:hover {
            background-color: #e8f5e8;
            transition: background-color 0.2s ease;
        }
        
        /* Responsive amélioré */
        @media (max-width: 768px) {
            .card-body { 
                padding: 1rem; 
            }
            .table th, .table td { 
                padding: 0.5rem; 
                font-size: 0.85rem;
            }
            .btn-sm { 
                padding: 0.25rem 0.5rem; 
                font-size: 0.8rem;
            }
        }
        
        /* Amélioration des modales */
        .modal-dialog { 
            max-width: 600px; 
        }
        .modal-body { 
            padding: 1.5rem; 
        }
</style>
        <!-- Filtres -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="statut" class="form-label">Filtrer par statut</label>
                        <select class="form-select" id="statut" name="statut" onchange="this.form.submit()">
                            <option value="all" <?php echo $statut_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="actif" <?php echo $statut_filter === 'actif' ? 'selected' : ''; ?>>Actifs seulement</option>
                            <option value="inactif" <?php echo $statut_filter === 'inactif' ? 'selected' : ''; ?>>Inactifs seulement</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <a href="gestion_tarifs.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Effacer les filtres
                        </a>
                    </div>
                </form>
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
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Tarifs de consultation -->
            <div class="col-lg-4 col-md-6 mb-4" id="section-tarifs">
                <div class="card mb-4">
                    <div class="card-header text-white">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Tarifs de Consultation</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Liste des tarifs 
                                <span class="badge bg-primary"><?php echo count($tarifs); ?></span>
                                <?php if ($statut_filter !== 'all'): ?>
                                    <small class="text-muted">(<?php echo $statut_filter === 'actif' ? 'actifs' : 'inactifs'; ?>)</small>
                                <?php endif; ?>
                            </h6>
                            <button class="btn btn-gradient btn-sm" data-bs-toggle="modal" data-bs-target="#addTarifModal">
                                <i class="fas fa-plus me-1"></i>Ajouter
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Spécialité</th>
                                        <th class="price-header">Prix</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tarifs as $tarif): ?>
                                        <tr class="<?php echo $tarif['statut'] === 'inactif' ? 'table-secondary' : ''; ?>">
                                            <td>
                                                <?php echo ucfirst(str_replace('_', ' ', $tarif['type_consultation'])); ?>
                                                <?php if ($tarif['statut'] === 'inactif'): ?>
                                                    <span class="badge bg-secondary ms-1">Inactif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $tarif['specialite'] ?: '-'; ?></td>
                                            <td class="price-cell">
                                                <span class="price-value"><?php echo number_format($tarif['prix'], 0, ',', ' '); ?></span>
                                                <span class="price-currency">FCFA</span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-tarif-id="<?php echo $tarif['id']; ?>"
                                                        data-tarif-type="<?php echo htmlspecialchars($tarif['type_consultation']); ?>"
                                                        data-tarif-specialite="<?php echo htmlspecialchars($tarif['specialite'] ?: ''); ?>"
                                                        data-tarif-prix="<?php echo $tarif['prix']; ?>"
                                                        data-tarif-description="<?php echo htmlspecialchars($tarif['description'] ?: ''); ?>"
                                                        data-tarif-statut="<?php echo $tarif['statut']; ?>"
                                                        onclick="editTarifFromData(this)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce tarif ?')">
                                                    <input type="hidden" name="action" value="delete_tarif">
                                                    <input type="hidden" name="id" value="<?php echo $tarif['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Soins de consultation -->
            <div class="col-lg-4 col-md-6 mb-4" id="section-soins">
                <div class="card mb-4">
                    <div class="card-header text-white">
                        <h5 class="mb-0"><i class="fas fa-stethoscope me-2"></i>Soins de Consultation</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Liste des soins 
                                <span class="badge bg-primary"><?php echo count($soins); ?></span>
                                <?php if ($statut_filter !== 'all'): ?>
                                    <small class="text-muted">(<?php echo $statut_filter === 'actif' ? 'actifs' : 'inactifs'; ?>)</small>
                                <?php endif; ?>
                            </h6>
                            <button class="btn btn-gradient btn-sm" data-bs-toggle="modal" data-bs-target="#addSoinModal">
                                <i class="fas fa-plus me-1"></i>Ajouter
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Soin</th>
                                        <th class="price-header">Prix</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($soins as $soin): ?>
                                        <tr class="<?php echo $soin['statut'] === 'inactif' ? 'table-secondary' : ''; ?>">
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($soin['nom']); ?></strong>
                                                    <?php if ($soin['statut'] === 'inactif'): ?>
                                                        <span class="badge bg-secondary ms-1">Inactif</span>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted">
                                                    <span class="badge bg-info me-1">
                                                        <?php echo ucfirst(str_replace('_', ' ', $soin['type_soin'])); ?>
                                                    </span>
                                                    <?php if ($soin['duree_minutes']): ?>
                                                        <i class="fas fa-clock me-1"></i><?php echo $soin['duree_minutes']; ?>min
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td class="price-cell">
                                                <span class="price-value"><?php echo number_format($soin['prix'], 0, ',', ' '); ?></span>
                                                <span class="price-currency">FCFA</span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-soin-id="<?php echo $soin['id']; ?>"
                                                        data-soin-nom="<?php echo htmlspecialchars($soin['nom']); ?>"
                                                        data-soin-description="<?php echo htmlspecialchars($soin['description'] ?: ''); ?>"
                                                        data-soin-prix="<?php echo $soin['prix']; ?>"
                                                        data-soin-type="<?php echo htmlspecialchars($soin['type_soin']); ?>"
                                                        data-soin-duree="<?php echo $soin['duree_minutes']; ?>"
                                                        data-soin-statut="<?php echo $soin['statut']; ?>"
                                                        onclick="editSoinFromData(this)"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce soin ?')">
                                                    <input type="hidden" name="action" value="delete_soin">
                                                    <input type="hidden" name="id" value="<?php echo $soin['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Catégories d'hospitalisation -->
            <div class="col-lg-4 col-md-12 mb-4" id="section-hospitalisation">
                <div class="card mb-4">
                    <div class="card-header text-white">
                        <h5 class="mb-0"><i class="fas fa-bed me-2"></i>Catégories d'Hospitalisation</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Liste des catégories 
                                <span class="badge bg-primary"><?php echo count($categories); ?></span>
                                <?php if ($statut_filter !== 'all'): ?>
                                    <small class="text-muted">(<?php echo $statut_filter === 'actif' ? 'actives' : 'inactives'; ?>)</small>
                                <?php endif; ?>
                            </h6>
                            <button class="btn btn-gradient btn-sm" data-bs-toggle="modal" data-bs-target="#addCategorieModal">
                                <i class="fas fa-plus me-1"></i>Ajouter
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prix/jour</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $categorie): ?>
                                        <tr class="<?php echo $categorie['statut'] === 'inactif' ? 'table-secondary' : ''; ?>">
                                            <td>
                                                <?php echo htmlspecialchars($categorie['nom']); ?>
                                                <?php if ($categorie['statut'] === 'inactif'): ?>
                                                    <span class="badge bg-secondary ms-1">Inactif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="price-cell">
                                                <span class="price-value"><?php echo number_format($categorie['prix_jour'], 0, ',', ' '); ?></span>
                                                <span class="price-currency">FCFA/jour</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $categorie['statut'] === 'actif' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($categorie['statut']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-categorie-id="<?php echo $categorie['id']; ?>"
                                                        data-categorie-nom="<?php echo htmlspecialchars($categorie['nom']); ?>"
                                                        data-categorie-prix="<?php echo $categorie['prix_jour']; ?>"
                                                        data-categorie-description="<?php echo htmlspecialchars($categorie['description'] ?: ''); ?>"
                                                        data-categorie-statut="<?php echo $categorie['statut']; ?>"
                                                        onclick="editCategorieFromData(this)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette catégorie ?')">
                                                    <input type="hidden" name="action" value="delete_categorie">
                                                    <input type="hidden" name="id" value="<?php echo $categorie['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ajout Tarif -->
    <div class="modal fade" id="addTarifModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un tarif</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="tarifForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_tarif">
                        
                        <div class="mb-3">
                            <label for="type_consultation" class="form-label">Type de consultation *</label>
                            <select class="form-select" id="type_consultation" name="type_consultation" required>
                                <option value="consultation_simple">Consultation simple</option>
                                <option value="consultation_specialisee">Consultation spécialisée</option>
                                <option value="urgence">Urgence</option>
                                <option value="controle">Contrôle</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="specialite" class="form-label">Spécialité</label>
                            <input type="text" class="form-control" id="specialite" name="specialite" placeholder="Ex: Cardiologie, Dermatologie...">
                        </div>
                        
                        <div class="mb-3">
                            <label for="prix" class="form-label">Prix (FCFA) *</label>
                            <input type="number" class="form-control" id="prix" name="prix" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Édition Tarif -->
    <div class="modal fade" id="editTarifModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le tarif</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editTarifForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_tarif">
                        <input type="hidden" name="id" id="edit_tarif_id">
                        
                        <div class="mb-3">
                            <label for="edit_type_consultation" class="form-label">Type de consultation *</label>
                            <select class="form-select" id="edit_type_consultation" name="type_consultation" required>
                                <option value="consultation_simple">Consultation simple</option>
                                <option value="consultation_specialisee">Consultation spécialisée</option>
                                <option value="urgence">Urgence</option>
                                <option value="controle">Contrôle</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_specialite" class="form-label">Spécialité</label>
                            <input type="text" class="form-control" id="edit_specialite" name="specialite" placeholder="Ex: Cardiologie, Dermatologie...">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_prix" class="form-label">Prix (FCFA) *</label>
                            <input type="number" class="form-control" id="edit_prix" name="prix" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_statut" class="form-label">Statut</label>
                            <select class="form-select" id="edit_statut" name="statut">
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Modifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Ajout Catégorie -->
    <div class="modal fade" id="addCategorieModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="categorieForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_categorie">
                        
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom de la catégorie *</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="prix_jour" class="form-label">Prix par jour (FCFA) *</label>
                            <input type="number" class="form-control" id="prix_jour" name="prix_jour" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Édition Catégorie -->
    <div class="modal fade" id="editCategorieModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier la catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editCategorieForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_categorie">
                        <input type="hidden" name="id" id="edit_categorie_id">
                        
                        <div class="mb-3">
                            <label for="edit_nom" class="form-label">Nom de la catégorie *</label>
                            <input type="text" class="form-control" id="edit_nom" name="nom" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_prix_jour" class="form-label">Prix par jour (FCFA) *</label>
                            <input type="number" class="form-control" id="edit_prix_jour" name="prix_jour" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_statut" class="form-label">Statut</label>
                            <select class="form-select" id="edit_statut" name="statut">
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Modifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Ajout Soin -->
    <div class="modal fade" id="addSoinModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un soin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="soinForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_soin">
                        
                        <div class="mb-3">
                            <label for="nom_soin" class="form-label">Nom du soin *</label>
                            <input type="text" class="form-control" id="nom_soin" name="nom" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="type_soin" class="form-label">Type de soin *</label>
                            <select class="form-select" id="type_soin" name="type_soin" required>
                                <option value="examen_clinique">Examen clinique</option>
                                <option value="soins_infirmiers">Soins infirmiers</option>
                                <option value="actes_medicaux">Actes médicaux</option>
                                <option value="examens_complementaires">Examens complémentaires</option>
                                <option value="soins_speciaux">Soins spéciaux</option>
                                <option value="chirurgie_ambulatoire">Chirurgie ambulatoire</option>
                                <option value="urgence">Urgence</option>
                                <option value="prevention">Prévention</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="prix_soin" class="form-label">Prix (FCFA) *</label>
                            <input type="number" class="form-control" id="prix_soin" name="prix" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="duree_soin" class="form-label">Durée (minutes)</label>
                            <input type="number" class="form-control" id="duree_soin" name="duree_minutes" min="5" value="30">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description_soin" class="form-label">Description</label>
                            <textarea class="form-control" id="description_soin" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="statut_soin" class="form-label">Statut</label>
                            <select class="form-select" id="statut_soin" name="statut">
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Édition Soin -->
    <div class="modal fade" id="editSoinModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le soin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editSoinForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_soin">
                        <input type="hidden" name="id" id="edit_soin_id">
                        
                        <div class="mb-3">
                            <label for="edit_nom_soin" class="form-label">Nom du soin *</label>
                            <input type="text" class="form-control" id="edit_nom_soin" name="nom" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_type_soin" class="form-label">Type de soin *</label>
                            <select class="form-select" id="edit_type_soin" name="type_soin" required>
                                <option value="examen_clinique">Examen clinique</option>
                                <option value="soins_infirmiers">Soins infirmiers</option>
                                <option value="actes_medicaux">Actes médicaux</option>
                                <option value="examens_complementaires">Examens complémentaires</option>
                                <option value="soins_speciaux">Soins spéciaux</option>
                                <option value="chirurgie_ambulatoire">Chirurgie ambulatoire</option>
                                <option value="urgence">Urgence</option>
                                <option value="prevention">Prévention</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_prix_soin" class="form-label">Prix (FCFA) *</label>
                            <input type="number" class="form-control" id="edit_prix_soin" name="prix" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_duree_soin" class="form-label">Durée (minutes)</label>
                            <input type="number" class="form-control" id="edit_duree_soin" name="duree_minutes" min="5" value="30">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description_soin" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description_soin" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_statut_soin" class="form-label">Statut</label>
                            <select class="form-select" id="edit_statut_soin" name="statut">
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Modifier</button>
                    </div>
                </form>
            </div>
        </div>
<?php ob_start(); ?>
<script>
        function editTarifFromData(button) {
            console.log('Bouton cliqué:', button);
            
            // Récupérer les données depuis les attributs data
            const tarif = {
                id: button.dataset.tarifId,
                type_consultation: button.dataset.tarifType,
                specialite: button.dataset.tarifSpecialite,
                prix: button.dataset.tarifPrix,
                description: button.dataset.tarifDescription,
                statut: button.dataset.tarifStatut
            };
            
            console.log('Données du tarif extraites:', tarif);
            
            // Vérifier que les éléments existent
            const elements = {
                id: document.getElementById('edit_tarif_id'),
                type: document.getElementById('edit_type_consultation'),
                specialite: document.getElementById('edit_specialite'),
                prix: document.getElementById('edit_prix'),
                description: document.getElementById('edit_description'),
                statut: document.getElementById('edit_statut')
            };
            
            // Vérifier que tous les éléments existent
            for (const [key, element] of Object.entries(elements)) {
                if (!element) {
                    console.error('Élément manquant:', key);
                    alert('Erreur: Élément de formulaire manquant (' + key + ')');
                    return;
                }
            }
            
            // Remplir le formulaire d'édition avec les données du tarif
            elements.id.value = tarif.id || '';
            elements.type.value = tarif.type_consultation || '';
            elements.specialite.value = tarif.specialite || '';
            elements.prix.value = tarif.prix || '';
            elements.description.value = tarif.description || '';
            elements.statut.value = tarif.statut || 'actif';
            
            console.log('Formulaire rempli avec:', {
                id: elements.id.value,
                type: elements.type.value,
                specialite: elements.specialite.value,
                prix: elements.prix.value,
                description: elements.description.value,
                statut: elements.statut.value
            });
            
            // Ouvrir le modal d'édition
            new bootstrap.Modal(document.getElementById('editTarifModal')).show();
        }
        
        function editCategorieFromData(button) {
            console.log('Bouton cliqué:', button);
            
            // Récupérer les données depuis les attributs data
            const categorie = {
                id: button.dataset.categorieId,
                nom: button.dataset.categorieNom,
                prix_jour: button.dataset.categoriePrix,
                description: button.dataset.categorieDescription,
                statut: button.dataset.categorieStatut
            };
            
            console.log('Données de la catégorie extraites:', categorie);
            
            // Vérifier que les éléments existent
            const elements = {
                id: document.getElementById('edit_categorie_id'),
                nom: document.getElementById('edit_nom'),
                prix_jour: document.getElementById('edit_prix_jour'),
                description: document.getElementById('edit_description'),
                statut: document.getElementById('edit_statut')
            };
            
            // Vérifier que tous les éléments existent
            for (const [key, element] of Object.entries(elements)) {
                if (!element) {
                    console.error('Élément manquant:', key);
                    alert('Erreur: Élément de formulaire manquant (' + key + ')');
                    return;
                }
            }
            
            // Remplir le formulaire d'édition avec les données de la catégorie
            elements.id.value = categorie.id || '';
            elements.nom.value = categorie.nom || '';
            elements.prix_jour.value = categorie.prix_jour || '';
            elements.description.value = categorie.description || '';
            elements.statut.value = categorie.statut || 'actif';
            
            console.log('Formulaire rempli avec:', {
                id: elements.id.value,
                nom: elements.nom.value,
                prix_jour: elements.prix_jour.value,
                description: elements.description.value,
                statut: elements.statut.value
            });
            
            // Ouvrir le modal d'édition
            new bootstrap.Modal(document.getElementById('editCategorieModal')).show();
        }
        
        function editSoinFromData(button) {
            console.log('Bouton soin cliqué:', button);
            
            // Récupérer les données depuis les attributs data
            const soin = {
                id: button.dataset.soinId,
                nom: button.dataset.soinNom,
                description: button.dataset.soinDescription,
                prix: button.dataset.soinPrix,
                type_soin: button.dataset.soinType,
                duree_minutes: button.dataset.soinDuree,
                statut: button.dataset.soinStatut
            };
            
            console.log('Données du soin extraites:', soin);
            
            // Vérifier que les éléments existent
            const elements = {
                id: document.getElementById('edit_soin_id'),
                nom: document.getElementById('edit_nom_soin'),
                description: document.getElementById('edit_description_soin'),
                prix: document.getElementById('edit_prix_soin'),
                type_soin: document.getElementById('edit_type_soin'),
                duree_minutes: document.getElementById('edit_duree_soin'),
                statut: document.getElementById('edit_statut_soin')
            };
            
            // Vérifier que tous les éléments existent
            for (const [key, element] of Object.entries(elements)) {
                if (!element) {
                    console.error('Élément manquant:', key);
                    alert('Erreur: Élément de formulaire manquant (' + key + ')');
                    return;
                }
            }
            
            // Remplir le formulaire d'édition avec les données du soin
            elements.id.value = soin.id || '';
            elements.nom.value = soin.nom || '';
            elements.description.value = soin.description || '';
            elements.prix.value = soin.prix || '';
            elements.type_soin.value = soin.type_soin || '';
            elements.duree_minutes.value = soin.duree_minutes || '30';
            elements.statut.value = soin.statut || 'actif';
            
            console.log('Formulaire soin rempli avec:', {
                id: elements.id.value,
                nom: elements.nom.value,
                description: elements.description.value,
                prix: elements.prix.value,
                type_soin: elements.type_soin.value,
                duree_minutes: elements.duree_minutes.value,
                statut: elements.statut.value
            });
            
            // Ouvrir le modal d'édition
            new bootstrap.Modal(document.getElementById('editSoinModal')).show();
        }
        
        // Réinitialiser les formulaires d'ajout quand les modales se ferment
        document.addEventListener('DOMContentLoaded', function() {
            // Réinitialiser le formulaire d'ajout de tarif
            document.getElementById('addTarifModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('tarifForm').reset();
            });
            
            // Réinitialiser le formulaire d'ajout de catégorie
            document.getElementById('addCategorieModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('categorieForm').reset();
            });
            
            // Réinitialiser le formulaire d'ajout de soin
            document.getElementById('addSoinModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('soinForm').reset();
            });
            
            // Réinitialiser les formulaires d'édition quand ils se ferment
            document.getElementById('editTarifModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('editTarifForm').reset();
            });
            
            document.getElementById('editCategorieModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('editCategorieForm').reset();
            });
            
            document.getElementById('editSoinModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('editSoinForm').reset();
            });
        });
    </script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
