<?php
require_once __DIR__ . '/_legacy_guard.php';

// Inclure la configuration de la base de données et le modèle
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Utilisateur.php';

// Créer une instance de la base de données
$database = new Database();
$db = $database->getConnection();

// Créer une instance du modèle Utilisateur
$utilisateurModel = new Utilisateur($db);

// Récupérer les filtres depuis l'URL
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$statut = $_GET['statut'] ?? '';

// Récupérer tous les utilisateurs
$utilisateurs = $utilisateurModel->getAll();
$stats = $utilisateurModel->getStats();

// Filtrer les utilisateurs si des filtres sont appliqués
if ($search || $role || $statut) {
    $utilisateurs = array_filter($utilisateurs, function($user) use ($search, $role, $statut) {
        $matchSearch = !$search || 
                      stripos($user['nom_utilisateur'], $search) !== false || 
                      stripos($user['email'], $search) !== false;
        $matchRole = !$role || $user['role'] === $role;
        $matchStatut = !$statut || $user['statut'] === $statut;
        
        return $matchSearch && $matchRole && $matchStatut;
    });
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        
        switch ($_POST['action']) {
            case 'activate':
                $utilisateurModel->modifier($user_id, '', '', '', 'actif');
                header('Location: index.php?success=activated');
                exit;
                break;
                
            case 'deactivate':
                $utilisateurModel->modifier($user_id, '', '', '', 'inactif');
                header('Location: index.php?success=deactivated');
                exit;
                break;
                
            case 'delete':
                $utilisateurModel->supprimer($user_id);
                header('Location: index.php?success=deleted');
                exit;
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - <?php echo htmlspecialchars(getNomEtablissement()); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/wptouch-inspired.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/modern-design.css" rel="stylesheet">
    <link href="../assets/css/system_logo.css" rel="stylesheet">
    <style>
        .user-card { transition: transform 0.2s; }
        .user-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .role-admin { background: #dc3545; color: white; }
        .role-medecin { background: #28a745; color: white; }
        .role-infirmier { background: #17a2b8; color: white; }
        .role-secretaire { background: #6f42c1; color: white; }
        .status-actif { background: #e8f5e8; color: #2e7d32; }
        .status-inactif { background: #ffebee; color: #c62828; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-buttons .btn { font-size: 0.8rem; padding: 0.25rem 0.5rem; }
    </style>
</head>
<body class="bg-light">
    <div class="container" style="padding: 1.5rem;">
        <div class="page-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <?php 
                require_once '../includes/header_logo.php';
                echo getSystemLogoHeader('compact');
                ?>
                <div class="ms-3 text-white">
                    <h3 class="mb-0">Gestion des Utilisateurs</h3>
                    <small>Administration des comptes et des rôles</small>
                </div>
                <div class="mt-2 mt-md-0 ms-auto">
                    <a href="../index.php" class="btn btn-light btn-sm me-2">
                        <i class="fas fa-home me-1"></i>Accueil
                    </a>
                    <a href="roles.php" class="btn btn-light btn-sm me-2">
                        <i class="fas fa-user-tag me-1"></i>Rôles
                    </a>
                    <a href="permissions.php" class="btn btn-light btn-sm me-2">
                        <i class="fas fa-key me-1"></i>Permissions
                    </a>
                    <a href="ajouter.php" class="btn btn-light btn-sm">
                        <i class="fas fa-user-plus me-1"></i>Nouvel Utilisateur
                    </a>
                </div>
            </div>
        </div>

        <!-- Messages de succès -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php
                switch ($_GET['success']) {
                    case 'activated':
                        echo '<i class="fas fa-check-circle me-2"></i>Utilisateur activé avec succès !';
                        break;
                    case 'deactivated':
                        echo '<i class="fas fa-pause-circle me-2"></i>Utilisateur désactivé avec succès !';
                        break;
                    case 'deleted':
                        echo '<i class="fas fa-trash me-2"></i>Utilisateur supprimé avec succès !';
                        break;
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiques des utilisateurs -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-primary"><?php echo $stats['total'] ?? 0; ?></h4>
                        <p class="text-muted mb-0">Total utilisateurs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-success"><?php echo $stats['actifs'] ?? 0; ?></h4>
                        <p class="text-muted mb-0">Utilisateurs actifs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-warning"><?php echo $stats['inactifs'] ?? 0; ?></h4>
                        <p class="text-muted mb-0">Inactifs</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-info"><?php echo count(array_unique(array_column($utilisateurs, 'role'))); ?></h4>
                        <p class="text-muted mb-0">Rôles utilisés</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recherche et filtres -->
        <div class="search-card">
            <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Nom, email, rôle..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="role">
                            <option value="">Tous les rôles</option>
                            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                            <option value="medecin" <?php echo $role === 'medecin' ? 'selected' : ''; ?>>Médecin</option>
                            <option value="infirmier" <?php echo $role === 'infirmier' ? 'selected' : ''; ?>>Infirmier</option>
                            <option value="secretaire" <?php echo $role === 'secretaire' ? 'selected' : ''; ?>>Secrétaire</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="statut">
                            <option value="">Tous les statuts</option>
                            <option value="actif" <?php echo $statut === 'actif' ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactif" <?php echo $statut === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Rechercher
                        </button>
                    </div>
            </form>
        </div>

        <!-- Liste des utilisateurs -->
        <?php if (empty($utilisateurs)): ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h4 class="mb-3">Aucun utilisateur trouvé</h4>
                <p class="text-muted"><?php echo $search || $role || $statut ? 'Essayez de modifier vos critères de recherche.' : 'Commencez par ajouter votre premier utilisateur.'; ?></p>
                <div class="mt-3">
                    <a href="ajouter.php" class="btn btn-primary me-2">
                        <i class="fas fa-user-plus me-2"></i>Nouvel Utilisateur
                    </a>
                    <?php if ($search || $role || $statut): ?>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Effacer les filtres
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($utilisateurs as $user): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card user-card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-user me-2"></i>
                                    <strong><?php echo htmlspecialchars($user['nom_utilisateur']); ?></strong>
                                </div>
                                <span class="badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <i class="fas fa-envelope me-2 text-muted"></i>
                                    <small><?php echo htmlspecialchars($user['email']); ?></small>
                                </div>
                                <div class="mb-2">
                                    <i class="fas fa-calendar me-2 text-muted"></i>
                                    <small>Créé le <?php echo date('d/m/Y', strtotime($user['date_creation'])); ?></small>
                                </div>
                                <div class="mb-3">
                                    <span class="badge status-<?php echo $user['statut']; ?>">
                                        <?php echo ucfirst($user['statut']); ?>
                                    </span>
                                </div>
                                
                                <!-- Actions rapides -->
                                <div class="action-buttons">
                                    <a href="voir.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary" title="Voir">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="modifier.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-warning" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($user['statut'] === 'actif'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Désactiver cet utilisateur ?')">
                                            <input type="hidden" name="action" value="deactivate">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Désactiver">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Activer">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer définitivement cet utilisateur ?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Résumé des actions -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Actions Rapides Disponibles</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Actions par utilisateur :</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-eye text-primary me-2"></i><strong>Voir</strong> - Consulter les détails</li>
                            <li><i class="fas fa-edit text-warning me-2"></i><strong>Modifier</strong> - Éditer les informations</li>
                            <li><i class="fas fa-play text-success me-2"></i><strong>Activer</strong> - Réactiver un compte</li>
                            <li><i class="fas fa-pause text-warning me-2"></i><strong>Désactiver</strong> - Suspendre temporairement</li>
                            <li><i class="fas fa-trash text-danger me-2"></i><strong>Supprimer</strong> - Supprimer définitivement</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Fonctionnalités :</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-search text-info me-2"></i><strong>Recherche</strong> - Par nom, email ou rôle</li>
                            <li><i class="fas fa-filter text-info me-2"></i><strong>Filtres</strong> - Par rôle et statut</li>
                            <li><i class="fas fa-chart-bar text-info me-2"></i><strong>Statistiques</strong> - Vue d'ensemble</li>
                            <li><i class="fas fa-user-plus text-success me-2"></i><strong>Ajout</strong> - Nouveaux utilisateurs</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/logo-handler.js"></script>
    <script src="../assets/js/wptouch-inspired.js"></script>
</body>
</html>





