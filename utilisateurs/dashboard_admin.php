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

if (!$user || $user['role'] !== 'admin') {
    header('Location: index.php?error=access_denied');
    exit;
}

// Récupérer des statistiques avancées
$stats = $utilisateurModel->getStats();
$all_users = $utilisateurModel->getAll();

// Calculer des métriques avancées
$total_users = count($all_users);
$admin_count = count(array_filter($all_users, function($u) { return $u['role'] === 'admin'; }));
$medecin_count = count(array_filter($all_users, function($u) { return $u['role'] === 'medecin'; }));
$infirmier_count = count(array_filter($all_users, function($u) { return $u['role'] === 'infirmier'; }));
$secretaire_count = count(array_filter($all_users, function($u) { return $u['role'] === 'secretaire'; }));
$active_count = count(array_filter($all_users, function($u) { return $u['statut'] === 'actif'; }));
$inactive_count = count(array_filter($all_users, function($u) { return $u['statut'] === 'inactif'; }));

// Utilisateurs récents (créés dans les 7 derniers jours)
$recent_users = array_filter($all_users, function($u) { 
    return strtotime($u['date_creation']) > strtotime('-7 days'); 
});

// Utilisateurs par mois (derniers 6 mois)
$monthly_stats = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthly_users = array_filter($all_users, function($u) use ($month) { 
        return date('Y-m', strtotime($u['date_creation'])) === $month; 
    });
    $monthly_stats[$month] = count($monthly_users);
}

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
    <title>Dashboard Administrateur - <?php echo htmlspecialchars($user['nom_utilisateur']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/auto-responsive.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-header { 
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); 
            color: white; 
            padding: 2rem 0;
        }
        .metric-card { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            transition: all 0.3s ease;
            border: none;
        }
        .metric-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .metric-icon { 
            width: 60px; 
            height: 60px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.5rem; 
            color: white;
        }
        .chart-container { 
            background: white; 
            border-radius: 15px; 
            padding: 20px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .quick-actions { 
            background: linear-gradient(45deg, #007bff, #0056b3); 
            color: white; 
            border-radius: 15px; 
            padding: 20px;
        }
        .recent-activity { 
            background: white; 
            border-radius: 15px; 
            padding: 20px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .activity-item { 
            padding: 10px 0; 
            border-bottom: 1px solid #eee;
        }
        .activity-item:last-child { 
            border-bottom: none; 
        }
        .status-indicator { 
            width: 10px; 
            height: 10px; 
            border-radius: 50%; 
            display: inline-block; 
            margin-right: 10px;
        }
        .status-active { background: #28a745; }
        .status-inactive { background: #dc3545; }
    </style>
</head>
<body class="bg-light">
    <!-- En-tête administrateur -->
    <div class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 mb-2">
                        <i class="fas fa-crown me-3"></i>Dashboard Administrateur
                    </h1>
                    <p class="lead mb-0">
                        Bienvenue, <strong><?php echo htmlspecialchars($user['nom_utilisateur']); ?></strong> 
                        (<?php echo htmlspecialchars($user['email']); ?>)
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="visualiser_avance.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-chart-line me-2"></i>Vue Avancée
                        </a>
                        <a href="index.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-users me-2"></i>Gestion
                        </a>
                        <a href="../index.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-home me-2"></i>Accueil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <!-- Métriques principales -->
        <div class="row mb-5">
            <div class="col-md-3 mb-4">
                <div class="metric-card p-4">
                    <div class="d-flex align-items-center">
                        <div class="metric-icon me-3" style="background: #dc3545;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h3 class="mb-1 text-primary"><?php echo $total_users; ?></h3>
                            <p class="text-muted mb-0">Total Utilisateurs</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="metric-card p-4">
                    <div class="d-flex align-items-center">
                        <div class="metric-icon me-3" style="background: #28a745;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h3 class="mb-1 text-success"><?php echo $active_count; ?></h3>
                            <p class="text-muted mb-0">Comptes Actifs</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="metric-card p-4">
                    <div class="d-flex align-items-center">
                        <div class="metric-icon me-3" style="background: #17a2b8;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h3 class="mb-1 text-info"><?php echo count($recent_users); ?></h3>
                            <p class="text-muted mb-0">Nouveaux (7j)</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="metric-card p-4">
                    <div class="d-flex align-items-center">
                        <div class="metric-icon me-3" style="background: #6f42c1;">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div>
                            <h3 class="mb-1 text-warning"><?php echo round(($active_count / $total_users) * 100, 1); ?>%</h3>
                            <p class="text-muted mb-0">Taux d'Activation</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Graphiques et analyses -->
            <div class="col-lg-8">
                <!-- Répartition des rôles -->
                <div class="chart-container">
                    <h5 class="mb-3"><i class="fas fa-chart-pie me-2"></i>Répartition des Rôles</h5>
                    <canvas id="roleChart" height="100"></canvas>
                </div>

                <!-- Évolution mensuelle -->
                <div class="chart-container">
                    <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Évolution Mensuelle des Utilisateurs</h5>
                    <canvas id="monthlyChart" height="100"></canvas>
                </div>

                <!-- Activités récentes -->
                <div class="recent-activity">
                    <h5 class="mb-3"><i class="fas fa-history me-2"></i>Activités Récentes</h5>
                    <?php if (count($recent_users) > 0): ?>
                        <?php foreach (array_slice($recent_users, 0, 5) as $recent_user): ?>
                            <div class="activity-item">
                                <div class="d-flex align-items-center">
                                    <span class="status-indicator status-<?php echo $recent_user['statut']; ?>"></span>
                                    <div class="flex-grow-1">
                                        <strong><?php echo htmlspecialchars($recent_user['nom_utilisateur']); ?></strong>
                                        <span class="badge ms-2" style="background-color: <?php echo getRoleColor($recent_user['role']); ?>">
                                            <?php echo ucfirst($recent_user['role']); ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($recent_user['date_creation'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">Aucune nouvelle activité récente</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar avec actions et informations -->
            <div class="col-lg-4">
                <!-- Actions rapides -->
                <div class="quick-actions mb-4">
                    <h6 class="mb-3"><i class="fas fa-bolt me-2"></i>Actions Rapides</h6>
                    <div class="d-grid gap-2">
                        <a href="ajouter.php" class="btn btn-light">
                            <i class="fas fa-user-plus me-2"></i>Nouvel Utilisateur
                        </a>
                        <a href="roles.php" class="btn btn-outline-light">
                            <i class="fas fa-user-tag me-2"></i>Gérer les Rôles
                        </a>
                        <a href="permissions.php" class="btn btn-outline-light">
                            <i class="fas fa-key me-2"></i>Permissions
                        </a>
                        <a href="visualiser_avance.php" class="btn btn-outline-light">
                            <i class="fas fa-chart-line me-2"></i>Vue Avancée
                        </a>
                    </div>
                </div>

                <!-- Statistiques détaillées -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistiques Détaillées</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Administrateurs</span>
                                <span class="badge bg-danger"><?php echo $admin_count; ?></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Médecins</span>
                                <span class="badge bg-success"><?php echo $medecin_count; ?></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Infirmiers</span>
                                <span class="badge bg-info"><?php echo $infirmier_count; ?></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Secrétaires</span>
                                <span class="badge bg-warning"><?php echo $secretaire_count; ?></span>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Comptes Actifs</span>
                                <span class="badge bg-success"><?php echo $active_count; ?></span>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between">
                                <span>Comptes Inactifs</span>
                                <span class="badge bg-danger"><?php echo $inactive_count; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations système -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Système</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <small class="text-muted">PHP:</small>
                            <span class="badge bg-secondary"><?php echo PHP_VERSION; ?></span>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Base:</small>
                            <span class="badge bg-secondary">MySQL</span>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Session:</small>
                            <span class="badge bg-success">Active</span>
                        </div>
                        <div>
                            <small class="text-muted">Dernière MAJ:</small>
                            <span class="badge bg-info"><?php echo date('d/m/Y H:i'); ?></span>
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
        const roleCtx = document.getElementById('roleChart').getContext('2d');
        new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: ['Administrateurs', 'Médecins', 'Infirmiers', 'Secrétaires'],
                datasets: [{
                    data: [<?php echo $admin_count; ?>, <?php echo $medecin_count; ?>, <?php echo $infirmier_count; ?>, <?php echo $secretaire_count; ?>],
                    backgroundColor: ['#dc3545', '#28a745', '#17a2b8', '#6f42c1'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Graphique mensuel
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($monthly_stats)); ?>,
                datasets: [{
                    label: 'Nouveaux Utilisateurs',
                    data: <?php echo json_encode(array_values($monthly_stats)); ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>





