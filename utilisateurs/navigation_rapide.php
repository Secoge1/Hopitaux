<?php
require_once __DIR__ . '/_legacy_guard.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Utilisateur.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    header('Location: index.php');
    exit;
}

// Créer une instance de la base de données
$database = new Database();
$db = $database->getConnection();

// Créer une instance du modèle Utilisateur
$utilisateurModel = new Utilisateur($db);

// Récupérer les détails de l'utilisateur
$user = $utilisateurModel->getById($user_id);

if (!$user) {
    header('Location: index.php?error=user_not_found');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation Rapide - <?php echo htmlspecialchars($user['nom_utilisateur']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/auto-responsive.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-banner { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 3rem 0;
        }
        .feature-card { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            transition: all 0.3s ease;
            border: none;
            height: 100%;
        }
        .feature-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .feature-icon { 
            width: 80px; 
            height: 80px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 2rem; 
            color: white;
            margin: 0 auto 20px;
        }
        .quick-access { 
            background: linear-gradient(45deg, #28a745, #20c997); 
            color: white; 
            border-radius: 15px; 
            padding: 25px;
        }
        .user-info { 
            background: rgba(255,255,255,0.1); 
            border-radius: 10px; 
            padding: 20px;
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Bannière héro -->
    <div class="hero-banner">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-4 mb-3">
                        <i class="fas fa-rocket me-3"></i>Navigation Rapide
                    </h1>
                    <p class="lead mb-4">
                        Accès direct aux fonctionnalités pour 
                        <strong><?php echo htmlspecialchars($user['nom_utilisateur']); ?></strong>
                    </p>
                </div>
                <div class="col-md-4">
                    <div class="user-info text-center">
                        <div class="mb-3">
                            <i class="fas fa-crown fa-3x"></i>
                        </div>
                        <h5><?php echo htmlspecialchars($user['nom_utilisateur']); ?></h5>
                        <p class="mb-2">Administrateur Principal</p>
                        <small>ID: #<?php echo $user['id']; ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <!-- Accès rapide principal -->
        <div class="quick-access mb-5">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3 class="mb-3">
                        <i class="fas fa-bolt me-2"></i>Accès Rapide Principal
                    </h3>
                    <p class="mb-0">Fonctionnalités essentielles pour la gestion des utilisateurs</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard_admin.php" class="btn btn-light btn-lg">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Fonctionnalités principales -->
        <div class="row mb-5">
            <div class="col-md-4 mb-4">
                <div class="feature-card p-4 text-center">
                    <div class="feature-icon" style="background: #dc3545;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h4>Vue Avancée</h4>
                    <p class="text-muted">Visualisation complète avec graphiques et statistiques détaillées</p>
                    <a href="visualiser_avance.php" class="btn btn-primary">
                        <i class="fas fa-arrow-right me-2"></i>Accéder
                    </a>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="feature-card p-4 text-center">
                    <div class="feature-icon" style="background: #28a745;">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <h4>Dashboard Admin</h4>
                    <p class="text-muted">Tableau de bord administrateur avec métriques en temps réel</p>
                    <a href="dashboard_admin.php" class="btn btn-success">
                        <i class="fas fa-arrow-right me-2"></i>Accéder
                    </a>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="feature-card p-4 text-center">
                    <div class="feature-icon" style="background: #17a2b8;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4>Gestion Utilisateurs</h4>
                    <p class="text-muted">Liste complète avec actions rapides et gestion des comptes</p>
                    <a href="index.php" class="btn btn-info">
                        <i class="fas fa-arrow-right me-2"></i>Accéder
                    </a>
                </div>
            </div>
        </div>

        <!-- Fonctionnalités secondaires -->
        <div class="row mb-5">
            <div class="col-md-3 mb-4">
                <div class="feature-card p-4 text-center">
                    <div class="feature-icon" style="background: #6f42c1;">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h5>Nouvel Utilisateur</h5>
                    <p class="text-muted small">Créer un nouveau compte utilisateur</p>
                    <a href="ajouter.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-plus me-1"></i>Ajouter
                    </a>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="feature-card p-4 text-center">
                    <div class="feature-icon" style="background: #fd7e14;">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <h5>Gestion Rôles</h5>
                    <p class="text-muted small">Définir et gérer les rôles utilisateurs</p>
                    <a href="roles.php" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-cog me-1"></i>Gérer
                    </a>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="feature-card p-4 text-center">
                    <div class="feature-icon" style="background: #20c997;">
                        <i class="fas fa-key"></i>
                    </div>
                    <h5>Permissions</h5>
                    <p class="text-muted small">Gérer les permissions et accès</p>
                    <a href="permissions.php" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-shield-alt me-1"></i>Configurer
                    </a>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="feature-card p-4 text-center">
                    <div class="feature-icon" style="background: #6c757d;">
                        <i class="fas fa-home"></i>
                    </div>
                    <h5>Dashboard Principal</h5>
                    <p class="text-muted small">Retour au tableau de bord principal</p>
                    <a href="../index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Retour
                    </a>
                </div>
            </div>
        </div>

        <!-- Actions rapides pour l'utilisateur ID: 1 -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user-cog me-2"></i>Actions Spéciales - Utilisateur #1
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-eye me-2 text-info"></i>Visualisation</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <a href="voir.php?id=1" class="text-decoration-none">
                                            <i class="fas fa-user me-2"></i>Vue Standard
                                        </a>
                                    </li>
                                    <li class="mb-2">
                                        <a href="visualiser_avance.php" class="text-decoration-none">
                                            <i class="fas fa-chart-line me-2"></i>Vue Avancée
                                        </a>
                                    </li>
                                    <li class="mb-2">
                                        <a href="dashboard_admin.php" class="text-decoration-none">
                                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard Admin
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-edit me-2 text-warning"></i>Modification</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <a href="modifier.php?id=1" class="text-decoration-none">
                                            <i class="fas fa-user-edit me-2"></i>Modifier le Profil
                                        </a>
                                    </li>
                                    <li class="mb-2">
                                        <a href="index.php" class="text-decoration-none">
                                            <i class="fas fa-cog me-2"></i>Gérer les Autres
                                        </a>
                                    </li>
                                    <li class="mb-2">
                                        <a href="ajouter.php" class="text-decoration-none">
                                            <i class="fas fa-user-plus me-2"></i>Créer Utilisateur
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations Rapides</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Nom:</strong> <?php echo htmlspecialchars($user['nom_utilisateur']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                        </div>
                        <div class="mb-3">
                            <strong>Rôle:</strong> 
                            <span class="badge bg-danger"><?php echo ucfirst($user['role']); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Statut:</strong> 
                            <span class="badge bg-success"><?php echo ucfirst($user['statut']); ?></span>
                        </div>
                        <div>
                            <strong>Créé le:</strong> 
                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($user['date_creation'])); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/auto-responsive.js"></script>
</body>
</html>





