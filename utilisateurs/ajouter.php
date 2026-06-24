<?php
require_once __DIR__ . '/_legacy_guard.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvel Utilisateur - <?php echo htmlspecialchars(getNomEtablissement()); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/auto-responsive.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-user-plus me-2 text-success"></i>Nouvel Utilisateur</h3>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Retour
            </a>
        </div>

        <div class="card text-center py-5">
            <div class="card-body">
                <i class="fas fa-user-plus fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Formulaire d'ajout d'utilisateur</h4>
                <p class="text-muted">Ce formulaire sera bientôt fonctionnel pour créer de nouveaux utilisateurs.</p>
                <div class="mt-3">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Retour au Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Fonctionnalités à venir</h5>
            </div>
            <div class="card-body">
                <ul>
                    <li>Création d'utilisateurs avec rôles</li>
                    <li>Gestion des mots de passe sécurisés</li>
                    <li>Attribution de permissions</li>
                    <li>Activation/désactivation de comptes</li>
                </ul>
                <p class="mb-0"><strong>Statut :</strong> <span class="badge bg-warning">En développement</span></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/auto-responsive.js"></script>
</body>
</html>
