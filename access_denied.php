<?php
require_once 'config/Auth.php';

$auth = Auth::getInstance();

// Rediriger vers la connexion si pas connecté
if (!$auth->estConnecte()) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès Refusé - Système de Gestion Clinique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .access-denied-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
        }
        .access-denied-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .btn-back {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .icon-container {
            margin-bottom: 1.5rem;
        }
        .access-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="access-denied-container">
                    <div class="access-denied-header">
                        <div class="icon-container">
                            <i class="fas fa-ban access-icon"></i>
                        </div>
                        <h2 class="mb-2">Accès Refusé</h2>
                        <p class="mb-0">Permissions insuffisantes</p>
                    </div>
                    
                    <div class="p-4 text-center">
                        <div class="mb-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h4 class="text-danger mb-3">Accès non autorisé</h4>
                            <p class="text-muted">
                                Vous n'avez pas les permissions nécessaires pour accéder à cette page.
                                <br>Votre rôle actuel : <strong><?php echo ucfirst($auth->getUserRole()); ?></strong>
                            </p>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Rôles et permissions :</strong>
                            <br>
                            <small class="text-muted">
                                • <strong>Admin :</strong> Accès complet à toutes les fonctionnalités<br>
                                • <strong>Médecin :</strong> Gestion des patients, consultations, analyses<br>
                                • <strong>Secrétaire :</strong> Gestion des rendez-vous, patients, paiements<br>
                                • <strong>Infirmier :</strong> Gestion des soins, analyses, patients
                            </small>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-block">
                            <a href="dashboard.php" class="btn btn-back text-white me-md-2">
                                <i class="fas fa-home me-2"></i>Retour au Dashboard
                            </a>
                            <a href="logout.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sign-out-alt me-2"></i>Se déconnecter
                            </a>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <h6 class="text-muted mb-3">Besoin d'aide ?</h6>
                            <p class="text-muted small">
                                Si vous pensez qu'il s'agit d'une erreur, contactez votre administrateur système.
                                <br>Vous pouvez également vous déconnecter et vous reconnecter avec un compte ayant les bonnes permissions.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Système sécurisé - © 2024 Clinique et Hôpital
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation d'entrée
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.access-denied-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>

