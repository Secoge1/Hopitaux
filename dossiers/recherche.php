<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('dossiers'));

// Inclure la configuration de la devise
require_once '../config/currency.php';
?>
<?php
app_module_page_start([
    'active'   => 'dossiers',
    'title'    => 'Recherche Dossiers',
    'subtitle' => 'Recherche avancée',
    'icon'     => 'fa-folder',
]);
app_module_back_toolbar(app_url('dossiers/index.php'), 'Retour à la liste');
app_module_flash();
?>
<div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Critères de Recherche</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <!-- Informations personnelles -->
                            <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Informations Personnelles</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Nom</label>
                                <input type="text" class="form-control" name="nom" placeholder="Nom de famille">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Prénom</label>
                                <input type="text" class="form-control" name="prenom" placeholder="Prénom">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Numéro de dossier</label>
                                <input type="text" class="form-control" name="dossier" placeholder="N° dossier">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Date de naissance</label>
                                <input type="date" class="form-control" name="date_naissance">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Genre</label>
                                <select class="form-select" name="genre">
                                    <option value="">Tous</option>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                            </div>

                            <!-- Critères médicaux -->
                            <h6 class="text-primary mb-3 mt-4"><i class="fas fa-stethoscope me-2"></i>Critères Médicaux</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Groupe sanguin</label>
                                <select class="form-select" name="groupe_sanguin">
                                    <option value="">Tous</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Statut du dossier</label>
                                <select class="form-select" name="statut">
                                    <option value="">Tous</option>
                                    <option value="actif">Actif</option>
                                    <option value="inactif">Inactif</option>
                                    <option value="archive">Archivé</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Priorité</label>
                                <select class="form-select" name="priorite">
                                    <option value="">Toutes</option>
                                    <option value="haute">Haute</option>
                                    <option value="moyenne">Moyenne</option>
                                    <option value="basse">Basse</option>
                                </select>
                            </div>

                            <!-- Critères de date -->
                            <h6 class="text-primary mb-3 mt-4"><i class="fas fa-calendar me-2"></i>Critères de Date</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Dernière consultation</label>
                                <div class="row">
                                    <div class="col-6">
                                        <input type="date" class="form-control" name="date_debut" placeholder="De">
                                    </div>
                                    <div class="col-6">
                                        <input type="date" class="form-control" name="date_fin" placeholder="À">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Création du dossier</label>
                                <div class="row">
                                    <div class="col-6">
                                        <input type="date" class="form-control" name="creation_debut" placeholder="De">
                                    </div>
                                    <div class="col-6">
                                        <input type="date" class="form-control" name="creation_fin" placeholder="À">
                                    </div>
                                </div>
                            </div>

                            <!-- Boutons -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-search me-2"></i>Rechercher
                                </button>
                                <a href="recherche.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Effacer
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <!-- Résultats de recherche -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Résultats de la Recherche</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Recherche avancée :</strong> Utilisez les critères à gauche pour affiner votre recherche de dossiers patients.
                        </div>

                        <!-- Statistiques de recherche -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="text-center p-3 border rounded">
                                    <h4 class="text-primary">0</h4>
                                    <small class="text-muted">Résultats trouvés</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 border rounded">
                                    <h4 class="text-success">0</h4>
                                    <small class="text-muted">Dossiers actifs</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 border rounded">
                                    <h4 class="text-warning">0</h4>
                                    <small class="text-muted">En attente</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 border rounded">
                                    <h4 class="text-info">0</h4>
                                    <small class="text-muted">Consultations</small>
                                </div>
                            </div>
                        </div>

                        <?php
                        // Inclure le modèle Dossier
                        require_once '../models/Dossier.php';
                        
                        $dossierModel = new Dossier();
                        
                        // Récupération des filtres
                        $nom = $_GET['nom'] ?? '';
                        $prenom = $_GET['prenom'] ?? '';
                        $dossier = $_GET['dossier'] ?? '';
                        $date_naissance = $_GET['date_naissance'] ?? '';
                        $genre = $_GET['genre'] ?? '';
                        $groupe_sanguin = $_GET['groupe_sanguin'] ?? '';
                        $statut = $_GET['statut'] ?? '';
                        $priorite = $_GET['priorite'] ?? '';
                        $date_debut = $_GET['date_debut'] ?? '';
                        $date_fin = $_GET['date_fin'] ?? '';
                        $creation_debut = $_GET['creation_debut'] ?? '';
                        $creation_fin = $_GET['creation_fin'] ?? '';
                        
                        // Construire les filtres
                        $filters = [];
                        if ($nom) $filters['search'] = $nom;
                        if ($prenom) $filters['search'] = $prenom;
                        if ($dossier) $filters['search'] = $dossier;
                        if ($date_naissance) $filters['date_naissance'] = $date_naissance;
                        if ($genre) $filters['genre'] = $genre;
                        if ($groupe_sanguin) $filters['groupe_sanguin'] = $groupe_sanguin;
                        if ($statut) $filters['statut'] = $statut;
                        if ($priorite) $filters['priorite'] = $priorite;
                        if ($creation_debut) $filters['creation_debut'] = $creation_debut;
                        if ($creation_fin) $filters['creation_fin'] = $creation_fin;
                        
                        // Effectuer la recherche
                        $resultats = [];
                        $totalResultats = 0;
                        $statsRecherche = ['actifs' => 0, 'en_attente' => 0, 'consultations' => 0];
                        
                        if (!empty($filters) || isset($_GET['nom']) || isset($_GET['prenom']) || isset($_GET['dossier'])) {
                            $resultats = $dossierModel->getAll(1, 100, $filters);
                            $totalResultats = $dossierModel->count($filters);
                            
                            // Calculer les statistiques de recherche
                            $statsRecherche['actifs'] = count(array_filter($resultats, function($r) { return $r['statut'] === 'actif'; }));
                            $statsRecherche['en_attente'] = count(array_filter($resultats, function($r) { return $r['statut'] === 'inactif'; }));
                            $statsRecherche['consultations'] = count($resultats);
                        }
                        ?>
                        
                        <!-- Statistiques de recherche -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="text-center p-3 border rounded">
                                    <h4 class="text-primary"><?php echo $totalResultats; ?></h4>
                                    <small class="text-muted">Résultats trouvés</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 border rounded">
                                    <h4 class="text-success"><?php echo $statsRecherche['actifs']; ?></h4>
                                    <small class="text-muted">Dossiers actifs</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 border rounded">
                                    <h4 class="text-warning"><?php echo $statsRecherche['en_attente']; ?></h4>
                                    <small class="text-muted">Dossiers inactifs</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 border rounded">
                                    <h4 class="text-info"><?php echo $statsRecherche['consultations']; ?></h4>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($resultats) && !empty($filters)): ?>
                            <!-- Aucun résultat -->
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucun résultat trouvé</h5>
                                <p class="text-muted">Aucun dossier ne correspond aux critères de recherche.</p>
                                <a href="recherche.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i>Effacer les filtres
                                </a>
                            </div>
                        <?php elseif (empty($filters) && !isset($_GET['nom']) && !isset($_GET['prenom']) && !isset($_GET['dossier'])): ?>
                            <!-- Message d'information initial -->
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">Aucune recherche effectuée</h5>
                                <p class="text-muted">Utilisez les critères de recherche à gauche pour commencer votre recherche.</p>
                            </div>
                        <?php else: ?>
                            <!-- Résultats de la recherche -->
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Dossier</th>
                                            <th>Patient</th>
                                            <th>Âge</th>
                                            <th>Groupe</th>
                                            <th>Statut</th>
                                            <th>Priorité</th>
                                            <th>Date création</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resultats as $dossier): ?>
                                            <tr>
                                                <td><strong>#<?php echo $dossier['id']; ?></strong></td>
                                                <td>
                                                    <?php echo htmlspecialchars($dossier['nom'] . ' ' . $dossier['prenom']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($dossier['numero_dossier']); ?></small>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if ($dossier['date_naissance']) {
                                                        $age = date_diff(date_create($dossier['date_naissance']), date_create('today'))->y;
                                                        echo $age . ' ans';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($dossier['groupe_sanguin']): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($dossier['groupe_sanguin']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $dossier['statut'] === 'actif' ? 'success' : ($dossier['statut'] === 'inactif' ? 'warning' : 'secondary'); ?>">
                                                        <?php echo ucfirst($dossier['statut']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $dossier['priorite'] === 'haute' ? 'danger' : ($dossier['priorite'] === 'moyenne' ? 'warning' : 'success'); ?>">
                                                        <?php echo ucfirst($dossier['priorite']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y H:i', strtotime($dossier['date_creation'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="voir.php?id=<?php echo $dossier['id']; ?>" class="btn btn-outline-info" title="Voir">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="modifier.php?id=<?php echo $dossier['id']; ?>" class="btn btn-outline-warning" title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- Exemples de résultats (à masquer en production) -->
                        <div class="d-none">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Dossier</th>
                                            <th>Patient</th>
                                            <th>Âge</th>
                                            <th>Statut</th>
                                            <th>Priorité</th>
                                            <th>Dernière consultation</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>P001</strong></td>
                                            <td>Jean Dupont</td>
                                            <td>45 ans</td>
                                            <td><span class="badge bg-success">Actif</span></td>
                                            <td><span class="badge bg-warning">Moyenne</span></td>
                                            <td>15/01/2024</td>
                                            <td>
                                                <a href="voir.php?id=1" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Options d'export -->
                <div class="card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-download me-2"></i>Options d'Export</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <a href="export_recherche.php?format=pdf" class="btn btn-danger w-100 mb-2">
                                    <i class="fas fa-file-pdf me-2"></i>Export PDF
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="export_recherche.php?format=excel" class="btn btn-success w-100 mb-2" disabled>
                                    <i class="fas fa-file-excel me-2"></i>Export Excel
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="export_recherche.php?format=csv" class="btn btn-info w-100 mb-2" disabled>
                                    <i class="fas fa-file-csv me-2"></i>Export CSV
                                </a>
                            </div>
                        </div>
                        <small class="text-muted">Les formats Excel et CSV seront disponibles prochainement.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
ob_start();
?>
<script src="assets/js/auto-responsive.js"></script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
