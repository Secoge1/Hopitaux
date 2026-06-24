<?php
require_once __DIR__ . '/_legacy_guard.php';

// Inclure la configuration de la base de données et le modèle
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Utilisateur.php';

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_GET['id'];

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

// Traitement du formulaire
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_utilisateur = trim($_POST['nom_utilisateur']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $statut = $_POST['statut'];
    $nouveau_mot_de_passe = trim($_POST['nouveau_mot_de_passe']);
    
    // Validation
    $errors = [];
    
    if (empty($nom_utilisateur)) {
        $errors[] = "Le nom d'utilisateur est requis";
    }
    
    if (empty($email)) {
        $errors[] = "L'email est requis";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email n'est pas valide";
    }
    
    if (!empty($nouveau_mot_de_passe) && strlen($nouveau_mot_de_passe) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
    }
    
    // Vérifier si l'email existe déjà (sauf pour cet utilisateur)
    if ($email !== $user['email'] && $utilisateurModel->emailExiste($email, $user_id)) {
        $errors[] = "Cet email est déjà utilisé par un autre utilisateur";
    }
    
    // Vérifier si le nom d'utilisateur existe déjà (sauf pour cet utilisateur)
    if ($nom_utilisateur !== $user['nom_utilisateur'] && $utilisateurModel->nomUtilisateurExiste($nom_utilisateur, $user_id)) {
        $errors[] = "Ce nom d'utilisateur est déjà utilisé";
    }
    
    if (empty($errors)) {
        // Mettre à jour l'utilisateur
        $success = $utilisateurModel->modifier($user_id, $nom_utilisateur, $email, $role, $statut);
        
        if ($success) {
            // Mettre à jour le mot de passe si fourni
            if (!empty($nouveau_mot_de_passe)) {
                $utilisateurModel->changerMotDePasse($user_id, $nouveau_mot_de_passe);
            }
            
            $message = "Utilisateur mis à jour avec succès !";
            $messageType = "success";
            
            // Mettre à jour les données locales
            $user['nom_utilisateur'] = $nom_utilisateur;
            $user['email'] = $email;
            $user['role'] = $role;
            $user['statut'] = $statut;
        } else {
            $message = "Erreur lors de la mise à jour de l'utilisateur";
            $messageType = "danger";
        }
    } else {
        $message = implode("<br>", $errors);
        $messageType = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Utilisateur - <?php echo htmlspecialchars(getNomEtablissement()); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/auto-responsive.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-card { border-left: 4px solid #ffc107; }
        .user-info { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- En-tête avec navigation -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-user-edit me-2 text-warning"></i>Modifier l'Utilisateur</h3>
            <div>
                <a href="voir.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary btn-sm me-2">
                    <i class="fas fa-eye me-1"></i>Voir
                </a>
                <a href="index.php" class="btn btn-outline-secondary btn-sm me-2">
                    <i class="fas fa-arrow-left me-1"></i>Retour
                </a>
                <a href="../index.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-home me-1"></i>Accueil
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Informations de l'utilisateur -->
        <div class="card user-info mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-1">
                            <i class="fas fa-user me-2"></i>
                            <?php echo htmlspecialchars($user['nom_utilisateur']); ?>
                        </h5>
                        <p class="mb-1 text-muted">
                            <i class="fas fa-envelope me-2"></i>
                            <?php echo htmlspecialchars($user['email']); ?>
                        </p>
                        <div class="mt-2">
                            <span class="badge bg-primary me-2"><?php echo ucfirst($user['role']); ?></span>
                            <span class="badge bg-<?php echo $user['statut'] === 'actif' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($user['statut']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            Créé le <?php echo date('d/m/Y', strtotime($user['date_creation'])); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire de modification -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card form-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier les Informations</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nom_utilisateur" class="form-label">
                                            <i class="fas fa-user me-1"></i>Nom d'utilisateur *
                                        </label>
                                        <input type="text" class="form-control" id="nom_utilisateur" name="nom_utilisateur" 
                                               value="<?php echo htmlspecialchars($user['nom_utilisateur']); ?>" required>
                                        <div class="form-text">Nom unique pour identifier l'utilisateur</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope me-1"></i>Email *
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        <div class="form-text">Adresse email de connexion</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="role" class="form-label">
                                            <i class="fas fa-user-tag me-1"></i>Rôle *
                                        </label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                            <option value="medecin" <?php echo $user['role'] === 'medecin' ? 'selected' : ''; ?>>Médecin</option>
                                            <option value="infirmier" <?php echo $user['role'] === 'infirmier' ? 'selected' : ''; ?>>Infirmier</option>
                                            <option value="secretaire" <?php echo $user['role'] === 'secretaire' ? 'selected' : ''; ?>>Secrétaire</option>
                                        </select>
                                        <div class="form-text">Définit les permissions de l'utilisateur</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="statut" class="form-label">
                                            <i class="fas fa-toggle-on me-1"></i>Statut *
                                        </label>
                                        <select class="form-select" id="statut" name="statut" required>
                                            <option value="actif" <?php echo $user['statut'] === 'actif' ? 'selected' : ''; ?>>Actif</option>
                                            <option value="inactif" <?php echo $user['statut'] === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                                        </select>
                                        <div class="form-text">Détermine si le compte est accessible</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="nouveau_mot_de_passe" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Nouveau mot de passe
                                </label>
                                <input type="password" class="form-control" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" 
                                       placeholder="Laissez vide pour conserver l'actuel">
                                <div class="form-text">Minimum 6 caractères. Laissez vide pour conserver le mot de passe actuel.</div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                </button>
                                <a href="voir.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Aide et informations -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6><i class="fas fa-shield-alt me-2 text-warning"></i>Sécurité</h6>
                            <small class="text-muted">Le mot de passe actuel sera conservé si aucun nouveau n'est saisi.</small>
                        </div>
                        <div class="mb-3">
                            <h6><i class="fas fa-user-tag me-2 text-primary"></i>Rôles</h6>
                            <small class="text-muted">Chaque rôle a des permissions spécifiques dans le système.</small>
                        </div>
                        <div>
                            <h6><i class="fas fa-toggle-on me-2 text-success"></i>Statut</h6>
                            <small class="text-muted">Un utilisateur inactif ne peut pas se connecter au système.</small>
                        </div>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions Rapides</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="voir.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-2"></i>Voir les détails
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-list me-2"></i>Liste des utilisateurs
                            </a>
                            <a href="../index.php" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-home me-2"></i>Retour au dashboard
                            </a>
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





