<?php
require_once __DIR__ . '/_legacy_guard.php';

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<p>❌ Aucun ID fourni</p>";
    echo "<p><a href='index.php'>Retour à la liste</a></p>";
    exit;
}

$user_id = (int)$_GET['id'];

// Inclure la configuration et le modèle
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Utilisateur.php';

try {
    // Créer une instance de la base de données
    $database = new Database();
    $db = $database->getConnection();
    
    // Créer une instance du modèle Utilisateur
    $utilisateurModel = new Utilisateur($db);
    
    // Récupérer les détails de l'utilisateur
    $user = $utilisateurModel->getById($user_id);
    
    if (!$user) {
        echo "<p>❌ Utilisateur non trouvé</p>";
        echo "<p><a href='index.php'>Retour à la liste</a></p>";
        exit;
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erreur: " . $e->getMessage() . "</p>";
    echo "<p><a href='index.php'>Retour à la liste</a></p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Utilisateur - <?php echo htmlspecialchars(getNomEtablissement()); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/auto-responsive.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .user-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .role-badge { font-size: 1.1rem; padding: 0.5rem 1rem; }
        .status-badge { font-size: 1rem; padding: 0.4rem 0.8rem; }
        .info-card { border-left: 4px solid #007bff; }
        .role-admin { background: #dc3545; }
        .role-medecin { background: #28a745; }
        .role-infirmier { background: #17a2b8; }
        .role-secretaire { background: #6f42c1; }
        .status-actif { background: #28a745; }
        .status-inactif { background: #dc3545; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- En-tête avec navigation -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-user me-2 text-primary"></i>Détails de l'Utilisateur</h3>
            <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm me-2">
                    <i class="fas fa-arrow-left me-1"></i>Retour
                </a>
                <a href="modifier.php?id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm me-2">
                    <i class="fas fa-edit me-1"></i>Modifier
                </a>
                <a href="../index.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-home me-1"></i>Accueil
                </a>
            </div>
        </div>

        <!-- En-tête de l'utilisateur -->
        <div class="card user-header mb-4">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-user-circle fa-5x"></i>
                </div>
                <h2 class="mb-2"><?php echo htmlspecialchars($user['nom_utilisateur']); ?></h2>
                <div class="mb-3">
                    <span class="badge role-badge role-<?php echo $user['role']; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    <span class="badge status-badge status-<?php echo $user['statut']; ?> ms-2">
                        <?php echo ucfirst($user['statut']); ?>
                    </span>
                </div>
                <p class="mb-0">
                    <i class="fas fa-envelope me-2"></i>
                    <?php echo htmlspecialchars($user['email']); ?>
                </p>
            </div>
        </div>

        <!-- Informations détaillées -->
        <div class="row">
            <div class="col-md-8">
                <!-- Informations personnelles -->
                <div class="card info-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Informations Personnelles</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted">Nom d'utilisateur</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($user['nom_utilisateur']); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted">Email</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted">Rôle</label>
                                    <p class="form-control-plaintext">
                                        <span class="badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted">Statut</label>
                                    <p class="form-control-plaintext">
                                        <span class="badge status-<?php echo $user['statut']; ?>">
                                            <?php echo ucfirst($user['statut']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted">Date de création</label>
                                    <p class="form-control-plaintext">
                                        <?php echo date('d/m/Y H:i', strtotime($user['date_creation'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted">ID système</label>
                                    <p class="form-control-plaintext"><?php echo $user['id']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Actions rapides -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions Rapides</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="modifier.php?id=<?php echo $user['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Modifier
                            </a>
                            <?php if ($user['statut'] === 'actif'): ?>
                                <form method="POST" action="index.php" class="d-grid">
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-outline-warning" onclick="return confirm('Désactiver cet utilisateur ?')">
                                        <i class="fas fa-pause me-2"></i>Désactiver
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="index.php" class="d-grid">
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-outline-success">
                                        <i class="fas fa-play me-2"></i>Activer
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="index.php" class="d-grid">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Supprimer définitivement cet utilisateur ?')">
                                    <i class="fas fa-trash me-2"></i>Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Informations système -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations Système</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <small class="text-muted">ID unique</small>
                            <p class="mb-0"><strong><?php echo $user['id']; ?></strong></p>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Créé le</small>
                            <p class="mb-0"><strong><?php echo date('d/m/Y H:i', strtotime($user['date_creation'])); ?></strong></p>
                        </div>
                        <div class="mb-0">
                            <small class="text-muted">Dernière modification</small>
                            <p class="mb-0"><strong><?php echo date('d/m/Y H:i', strtotime($user['date_creation'])); ?></strong></p>
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





