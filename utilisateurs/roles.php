<?php
require_once __DIR__ . '/_legacy_guard.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Rôles - <?php echo htmlspecialchars(getNomEtablissement()); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/auto-responsive.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .role-card { transition: transform 0.2s; }
        .role-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .permission-item { padding: 8px; margin: 2px 0; border-radius: 4px; background: #f8f9fa; }
        .permission-granted { background: #d4edda; border-left: 4px solid #28a745; }
        .permission-denied { background: #f8d7da; border-left: 4px solid #dc3545; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-user-tag me-2 text-info"></i>Gestion des Rôles</h3>
            <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm me-2">
                    <i class="fas fa-arrow-left me-1"></i>Retour
                </a>
                <a href="nouveau_role.php" class="btn btn-success btn-sm">
                    <i class="fas fa-plus me-1"></i>Nouveau Rôle
                </a>
            </div>
        </div>

        <!-- Rôles prédéfinis -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card role-card h-100">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-crown me-2"></i>Administrateur</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Accès complet à toutes les fonctionnalités du système</p>
                        <div class="mb-3">
                            <strong>Permissions principales :</strong>
                            <div class="permission-item permission-granted">Gestion des utilisateurs</div>
                            <div class="permission-item permission-granted">Configuration système</div>
                            <div class="permission-item permission-granted">Accès aux rapports</div>
                            <div class="permission-item permission-granted">Gestion des sauvegardes</div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="modifier_role.php?id=admin" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit me-1"></i>Modifier
                            </a>
                            <a href="voir_role.php?id=admin" class="btn btn-info btn-sm">
                                <i class="fas fa-eye me-1"></i>Voir
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card role-card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user-md me-2"></i>Médecin</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Accès aux fonctionnalités médicales et gestion des patients</p>
                        <div class="mb-3">
                            <strong>Permissions principales :</strong>
                            <div class="permission-item permission-granted">Gestion des patients</div>
                            <div class="permission-item permission-granted">Consultations</div>
                            <div class="permission-item permission-granted">Prescriptions</div>
                            <div class="permission-item permission-denied">Configuration système</div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="modifier_role.php?id=medecin" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit me-1"></i>Modifier
                            </a>
                            <a href="voir_role.php?id=medecin" class="btn btn-info btn-sm">
                                <i class="fas fa-eye me-1"></i>Voir
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card role-card h-100">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-user-nurse me-2"></i>Infirmier</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Accès aux soins et suivi des patients</p>
                        <div class="mb-3">
                            <strong>Permissions principales :</strong>
                            <div class="permission-item permission-granted">Suivi des patients</div>
                            <div class="permission-item permission-granted">Soins infirmiers</div>
                            <div class="permission-item permission-granted">Observations</div>
                            <div class="permission-item permission-denied">Prescriptions</div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="modifier_role.php?id=infirmier" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit me-1"></i>Modifier
                            </a>
                            <a href="voir_role.php?id=infirmier" class="btn btn-info btn-sm">
                                <i class="fas fa-eye me-1"></i>Voir
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card role-card h-100">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Secrétaire</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Accueil et gestion administrative</p>
                        <div class="mb-3">
                            <strong>Permissions principales :</strong>
                            <div class="permission-item permission-granted">Accueil des patients</div>
                            <div class="permission-item permission-granted">Planification RDV</div>
                            <div class="permission-item permission-granted">Facturation</div>
                            <div class="permission-item permission-denied">Données médicales</div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="modifier_role.php?id=secretaire" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit me-1"></i>Modifier
                            </a>
                            <a href="voir_role.php?id=secretaire" class="btn btn-info btn-sm">
                                <i class="fas fa-eye me-1"></i>Voir
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Création de rôles personnalisés -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Créer un Rôle Personnalisé</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Rôles personnalisés :</strong> Créez des rôles adaptés à vos besoins spécifiques avec des permissions personnalisées.
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h6>Avantages des rôles personnalisés :</h6>
                        <ul>
                            <li>Adaptation aux processus métier</li>
                            <li>Gestion fine des accès</li>
                            <li>Flexibilité organisationnelle</li>
                            <li>Audit des permissions</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Exemples de rôles :</h6>
                        <ul>
                            <li>Responsable de service</li>
                            <li>Stagiaire médical</li>
                            <li>Technicien de laboratoire</li>
                            <li>Gestionnaire administratif</li>
                        </ul>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="nouveau_role.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus me-2"></i>Créer un Nouveau Rôle
                    </a>
                </div>
            </div>
        </div>

        <!-- Informations sur la gestion des rôles -->
        <div class="card mt-4">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>À propos de la gestion des rôles</h6>
            </div>
            <div class="card-body">
                <p>Le système de rôles permet de :</p>
                <ul>
                    <li><strong>Sécuriser l'accès :</strong> Contrôler qui peut faire quoi dans le système</li>
                    <li><strong>Simplifier la gestion :</strong> Attribuer des permissions par groupe plutôt qu'individuellement</li>
                    <li><strong>Auditer les accès :</strong> Suivre qui a accès à quoi et quand</li>
                    <li><strong>Adapter l'interface :</strong> Afficher uniquement les fonctionnalités autorisées</li>
                </ul>
                <p class="mb-0"><strong>Statut :</strong> <span class="badge bg-warning">En développement</span></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/auto-responsive.js"></script>
</body>
</html>





