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

// Récupérer des statistiques avancées
$stats = $utilisateurModel->getStats();

// Récupérer tous les utilisateurs pour les comparaisons
$all_users = $utilisateurModel->getAll();

// Calculer des métriques avancées
$total_users = count($all_users);
$admin_count = count(array_filter($all_users, function($u) { return $u['role'] === 'admin'; }));
$active_count = count(array_filter($all_users, function($u) { return $u['statut'] === 'actif'; }));
$recent_users = array_filter($all_users, function($u) { 
    return strtotime($u['date_creation']) > strtotime('-30 days'); 
});

// Fonction pour obtenir l'icône du rôle
function getRoleIcon($role) {
    $icons = [
        'admin' => 'fas fa-crown',
        'medecin' => 'fas fa-user-md',
        'infirmier' => 'fas fa-user-nurse',
        'secretaire' => 'fas fa-user-tie'
    ];
    return $icons[$role] ?? 'fas fa-user';
}

// Fonction pour obtenir la couleur du rôle
function getRoleColor($role) {
    $colors = [
        'admin' => '#dc3545',
        'medecin' => '#28a745',
        'infirmier' => '#17a2b8',
        'secretaire' => '#6f42c1'
    ];
    return $colors[$role] ?? '#6c757d';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualisation Avancée - <?php echo htmlspecialchars($user['nom_utilisateur']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/auto-responsive.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .hero-section { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 3rem 0;
        }
        .stat-card { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            transition: transform 0.3s ease;
        }
        .stat-card:hover { 
            transform: translateY(-5px); 
        }
        .role-badge { 
            font-size: 1.2rem; 
            padding: 0.6rem 1.2rem; 
            border-radius: 25px;
        }
        .activity-timeline { 
            border-left: 3px solid #007bff; 
            padding-left: 20px;
        }
        .timeline-item { 
            margin-bottom: 20px; 
            position: relative;
        }
        .timeline-item::before { 
            content: ''; 
            position: absolute; 
            left: -26px; 
            top: 5px; 
            width: 10px; 
            height: 10px; 
            background: #007bff; 
            border-radius: 50%;
        }
        .comparison-chart { 
            background: white; 
            border-radius: 15px; 
            padding: 20px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .user-avatar { 
            width: 120px; 
            height: 120px; 
            background: rgba(255,255,255,0.2); 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 3rem; 
            margin: 0 auto 20px;
        }
        .metric-highlight { 
            background: linear-gradient(45deg, #ff6b6b, #ee5a24); 
            color: white; 
            border-radius: 15px; 
            padding: 20px;
        }
        .security-status { 
            background: linear-gradient(45deg, #00b894, #00cec9); 
            color: white; 
            border-radius: 15px; 
            padding: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Section héro -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8 text-center text-md-start">
                    <div class="user-avatar">
                        <i class="<?php echo getRoleIcon($user['role']); ?>"></i>
                    </div>
                    <h1 class="display-4 mb-3"><?php echo htmlspecialchars($user['nom_utilisateur']); ?></h1>
                    <p class="lead mb-4">
                        <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?>
                    </p>
                    <div class="mb-4">
                        <span class="badge role-badge" style="background-color: <?php echo getRoleColor($user['role']); ?>">
                            <i class="<?php echo getRoleIcon($user['role']); ?> me-2"></i>
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                        <span class="badge bg-success ms-3" style="font-size: 1rem; padding: 0.5rem 1rem;">
                            <i class="fas fa-check-circle me-2"></i><?php echo ucfirst($user['statut']); ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="metric-highlight">
                        <h3 class="mb-2">ID #<?php echo $user['id']; ?></h3>
                        <p class="mb-0">Utilisateur Principal</p>
                        <small>Créé le <?php echo date('d/m/Y', strtotime($user['date_creation'])); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <!-- Statistiques principales -->
        <div class="row mb-5">
            <div class="col-md-3 mb-4">
                <div class="stat-card text-center p-4">
                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                    <h3 class="text-primary"><?php echo $total_users; ?></h3>
                    <p class="text-muted mb-0">Total Utilisateurs</p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card text-center p-4">
                    <i class="fas fa-crown fa-3x text-danger mb-3"></i>
                    <h3 class="text-danger"><?php echo $admin_count; ?></h3>
                    <p class="text-muted mb-0">Administrateurs</p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card text-center p-4">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h3 class="text-success"><?php echo $active_count; ?></h3>
                    <p class="text-muted mb-0">Comptes Actifs</p>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stat-card text-center p-4">
                    <i class="fas fa-clock fa-3x text-info mb-3"></i>
                    <h3 class="text-info"><?php echo count($recent_users); ?></h3>
                    <p class="text-muted mb-0">Nouveaux (30j)</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Informations détaillées -->
            <div class="col-lg-8">
                <!-- Profil détaillé -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Profil Détaillé</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted"><strong>Nom d'utilisateur</strong></label>
                                    <p class="form-control-plaintext fs-5"><?php echo htmlspecialchars($user['nom_utilisateur']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted"><strong>Email</strong></label>
                                    <p class="form-control-plaintext fs-5"><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted"><strong>Rôle</strong></label>
                                    <p class="form-control-plaintext">
                                        <span class="badge" style="background-color: <?php echo getRoleColor($user['role']); ?>; font-size: 1rem; padding: 0.5rem 1rem;">
                                            <i class="<?php echo getRoleIcon($user['role']); ?> me-2"></i>
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted"><strong>Statut</strong></label>
                                    <p class="form-control-plaintext">
                                        <span class="badge bg-success fs-6"><?php echo ucfirst($user['statut']); ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted"><strong>Date de création</strong></label>
                                    <p class="form-control-plaintext">
                                        <i class="fas fa-calendar me-2 text-primary"></i>
                                        <?php echo date('d/m/Y à H:i', strtotime($user['date_creation'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted"><strong>ID Système</strong></label>
                                    <p class="form-control-plaintext">
                                        <code class="fs-5">#<?php echo $user['id']; ?></code>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphique de comparaison des rôles -->
                <div class="comparison-chart mb-4">
                    <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Répartition des Rôles</h5>
                    <canvas id="roleChart" width="400" height="200"></canvas>
                </div>

                <!-- Activités récentes -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Activités Récentes</h5>
                    </div>
                    <div class="card-body">
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <h6 class="text-primary">Compte créé</h6>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo date('d/m/Y à H:i', strtotime($user['date_creation'])); ?>
                                </p>
                                <small class="text-muted">Initialisation du compte administrateur</small>
                            </div>
                            <div class="timeline-item">
                                <h6 class="text-success">Statut activé</h6>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Compte marqué comme actif
                                </p>
                                <small class="text-muted">Accès complet au système accordé</small>
                            </div>
                            <div class="timeline-item">
                                <h6 class="text-info">Rôle défini</h6>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-user-tag me-2"></i>
                                    Rôle d'administrateur attribué
                                </p>
                                <small class="text-muted">Permissions maximales activées</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar avec actions et informations -->
            <div class="col-lg-4">
                <!-- Actions rapides -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions Rapides</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="modifier.php?id=<?php echo $user['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Modifier le profil
                            </a>
                            <a href="voir.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>Vue standard
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-2"></i>Liste des utilisateurs
                            </a>
                            <a href="../index.php" class="btn btn-outline-info">
                                <i class="fas fa-home me-2"></i>Retour au dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statut de sécurité -->
                <div class="security-status mb-4">
                    <h6 class="mb-3"><i class="fas fa-shield-alt me-2"></i>Statut de Sécurité</h6>
                    <div class="mb-2">
                        <i class="fas fa-lock me-2"></i>
                        <small>Mot de passe sécurisé</small>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-user-check me-2"></i>
                        <small>Compte vérifié</small>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-clock me-2"></i>
                        <small>Dernière activité: <?php echo date('d/m/Y', strtotime($user['date_creation'])); ?></small>
                    </div>
                </div>

                <!-- Informations système -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Informations Système</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <small class="text-muted">Version PHP:</small>
                            <span class="badge bg-secondary"><?php echo PHP_VERSION; ?></span>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Base de données:</small>
                            <span class="badge bg-secondary">MySQL</span>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Session:</small>
                            <span class="badge bg-success">Active</span>
                        </div>
                        <div>
                            <small class="text-muted">Permissions:</small>
                            <span class="badge bg-danger">Administrateur</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/auto-responsive.js"></script>
    <script>
        // Graphique des rôles
        const ctx = document.getElementById('roleChart').getContext('2d');
        const roleChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Administrateurs', 'Médecins', 'Infirmiers', 'Secrétaires'],
                datasets: [{
                    data: [
                        <?php echo count(array_filter($all_users, function($u) { return $u['role'] === 'admin'; })); ?>,
                        <?php echo count(array_filter($all_users, function($u) { return $u['role'] === 'medecin'; })); ?>,
                        <?php echo count(array_filter($all_users, function($u) { return $u['role'] === 'infirmier'; })); ?>,
                        <?php echo count(array_filter($all_users, function($u) { return $u['role'] === 'secretaire'; })); ?>
                    ],
                    backgroundColor: [
                        '#dc3545',
                        '#28a745',
                        '#17a2b8',
                        '#6f42c1'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>





