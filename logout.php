<?php
require_once 'config/Auth.php';

// Déconnecter l'utilisateur
$auth = Auth::getInstance();
$auth->deconnecter();

// Rediriger vers la page de connexion
header("Location: login.php");
exit();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Déconnexion - <?php echo htmlspecialchars(getNomEtablissement()); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/auto-responsive.css" rel="stylesheet">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-body py-5">
                        <i class="fas fa-sign-out-alt fa-3x text-success mb-3"></i>
                        <h3>Déconnexion réussie</h3>
                        <p class="text-muted">Vous avez été déconnecté avec succès.</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

