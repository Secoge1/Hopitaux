<?php
/**
 * Export des données d'un utilisateur en PDF
 * Utilisé par la fonctionnalité d'export du profil
 */

require_once '../config/Auth.php';
require_once '../config/database.php';
require_once '../models/Utilisateur.php';

// Vérifier que l'utilisateur est connecté et a le rôle admin
$auth = Auth::getInstance();
$auth->requireRole('admin', '../access_denied.php');

try {
    $database = new Database();
    $db = $database->getConnection();
    $utilisateurModel = new Utilisateur($db);
    
    // Vérifier que l'ID est fourni
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('ID utilisateur manquant');
    }
    
    $id = (int)$_GET['id'];
    
    // Récupérer les données de l'utilisateur
    $utilisateur = $utilisateurModel->getById($id);
    
    if (!$utilisateur) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    // Récupérer des statistiques supplémentaires
    $stats = [];
    
    // Nombre de connexions (si la table existe)
    try {
        require_once __DIR__ . '/../includes/saas/TenantScope.php';
        $where = ['utilisateur_id = ?'];
        $params = [$id];
        TenantScope::appendWhere($db, 'connexions', $where, $params);
        $stmt = $db->prepare('SELECT COUNT(*) as total FROM connexions WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $stats['connexions'] = $stmt->fetch()['total'];
    } catch (Exception $e) {
        $stats['connexions'] = 0;
    }
    
    // Dernière connexion (si la table existe)
    try {
        $where = ['utilisateur_id = ?'];
        $params = [$id];
        TenantScope::appendWhere($db, 'connexions', $where, $params);
        $stmt = $db->prepare('SELECT date_connexion FROM connexions WHERE ' . implode(' AND ', $where) . ' ORDER BY date_connexion DESC LIMIT 1');
        $stmt->execute($params);
        $derniereConnexion = $stmt->fetch();
        $stats['derniere_connexion'] = $derniereConnexion ? $derniereConnexion['date_connexion'] : null;
    } catch (Exception $e) {
        $stats['derniere_connexion'] = null;
    }
    
    // Obtenir les permissions et actions
    $permissions = getPermissionsByRole($utilisateur['role']);
    $actions = getActionsByRole($utilisateur['role']);
    
    // Générer le PDF
    $currentUser = $auth->getUtilisateur();
    $exportedBy = $currentUser ? $currentUser['nom_utilisateur'] : 'Système';
    generatePDF($utilisateur, $stats, $permissions, $actions, $exportedBy);
    
} catch (Exception $e) {
    // En cas d'erreur, afficher une page d'erreur
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Erreur d'Export</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 50px; }
            .error { color: red; border: 1px solid red; padding: 20px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='error'>
            <h2>Erreur lors de l'export</h2>
            <p><strong>Message :</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>Date :</strong> " . date('d/m/Y H:i:s') . "</p>
            <p><a href='javascript:history.back()'>← Retour</a></p>
        </div>
    </body>
    </html>";
}

/**
 * Générer le PDF de l'utilisateur
 */
function generatePDF($utilisateur, $stats, $permissions, $actions, $exportedBy) {
    // Définir les en-têtes pour le HTML optimisé pour impression
    $filename = 'utilisateur_' . $utilisateur['id'] . '_' . date('Y-m-d_H-i-s') . '.html';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Créer le contenu HTML pour le PDF
    $html = generateHTMLContent($utilisateur, $stats, $permissions, $actions, $exportedBy);
    
    // Afficher le HTML
    echo $html;
}

/**
 * Générer le contenu HTML pour le PDF
 */
function generateHTMLContent($utilisateur, $stats, $permissions, $actions, $exportedBy) {
    $roleLabels = [
        'admin' => 'Administrateur',
        'medecin' => 'Médecin',
        'secretaire' => 'Secrétaire',
        'infirmier' => 'Infirmier'
    ];
    
    $statutLabels = [
        'actif' => 'Actif',
        'inactif' => 'Inactif'
    ];
    
    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Utilisateur - ' . htmlspecialchars($utilisateur['nom_utilisateur']) . '</title>
    <style>
        @media print {
            body { 
                margin: 0; 
                padding: 20px; 
                font-family: Arial, sans-serif; 
                background: white !important;
            }
            .no-print { display: none; }
            .page-break { page-break-before: always; }
            .print-button { display: none; }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
            line-height: 1.6;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #0056b3;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 28px;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .user-info {
            display: flex;
            margin-bottom: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        .user-avatar {
            background: #007bff;
            color: white;
            padding: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
        }
        
        .user-avatar .icon {
            font-size: 48px;
        }
        
        .user-details {
            padding: 20px;
            flex-grow: 1;
        }
        
        .user-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .user-email {
            color: #666;
            margin-bottom: 15px;
        }
        
        .badges {
            display: flex;
            gap: 10px;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-role {
            background: #007bff;
            color: white;
        }
        
        .badge-statut {
            background: ' . ($utilisateur['statut'] === 'actif' ? '#28a745' : '#dc3545') . ';
            color: white;
        }
        
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        
        .section-title {
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #007bff;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .info-item {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            background: white;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 14px;
            color: #333;
        }
        
        .permissions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .permissions-list, .actions-list {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            background: white;
        }
        
        .permissions-list h4, .actions-list h4 {
            margin-top: 0;
            color: #007bff;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .permissions-list ul, .actions-list ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .permissions-list li, .actions-list li {
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        .export-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #666;
        }
        
        @media print {
            .user-info { break-inside: avoid; }
            .section { break-inside: avoid; }
            .permissions-grid { break-inside: avoid; }
        }
        
        @media screen and (max-width: 768px) {
            .info-grid, .permissions-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                flex-direction: column;
            }
            
            .user-avatar {
                min-width: auto;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ Imprimer / Sauvegarder PDF</button>
    
    <div class="header">
        <h1>📋 Profil Utilisateur</h1>
        <div class="subtitle">Système de Gestion Clinique et Hospitalière</div>
    </div>
    
    <div class="export-info">
        <strong>Export généré le :</strong> ' . date('d/m/Y à H:i:s') . ' | 
        <strong>Par :</strong> ' . htmlspecialchars($exportedBy) . ' | 
        <strong>Format :</strong> HTML optimisé pour impression PDF
    </div>
    
    <div class="user-info">
        <div class="user-avatar">
            <div class="icon">👤</div>
        </div>
        <div class="user-details">
            <div class="user-name">' . htmlspecialchars($utilisateur['nom_utilisateur']) . '</div>
            <div class="user-email">' . htmlspecialchars($utilisateur['email']) . '</div>
            <div class="badges">
                <span class="badge badge-role">' . $roleLabels[$utilisateur['role']] . '</span>
                <span class="badge badge-statut">' . $statutLabels[$utilisateur['statut']] . '</span>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">📊 Informations Générales</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">ID Utilisateur</div>
                <div class="info-value">' . $utilisateur['id'] . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Nom d\'utilisateur</div>
                <div class="info-value">' . htmlspecialchars($utilisateur['nom_utilisateur']) . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Adresse email</div>
                <div class="info-value">' . htmlspecialchars($utilisateur['email']) . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Rôle</div>
                <div class="info-value">' . $roleLabels[$utilisateur['role']] . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Statut</div>
                <div class="info-value">' . $statutLabels[$utilisateur['statut']] . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Date de création</div>
                <div class="info-value">' . date('d/m/Y H:i', strtotime($utilisateur['date_creation'])) . '</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">⏰ Informations Temporelles</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Date de création</div>
                <div class="info-value">' . date('d/m/Y H:i', strtotime($utilisateur['date_creation'])) . '</div>
            </div>
                         <div class="info-item">
                 <div class="info-label">Dernière modification</div>
                 <div class="info-value">' . (isset($utilisateur['date_modification']) && $utilisateur['date_modification'] ? date('d/m/Y H:i', strtotime($utilisateur['date_modification'])) : 'Non modifié') . '</div>
             </div>
            <div class="info-item">
                <div class="info-label">Dernière connexion</div>
                <div class="info-value">' . ($stats['derniere_connexion'] ? date('d/m/Y H:i', strtotime($stats['derniere_connexion'])) : 'Jamais connecté') . '</div>
            </div>
            <div class="info-item">
                <div class="info-label">Nombre de connexions</div>
                <div class="info-value">' . $stats['connexions'] . '</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">🔐 Permissions et Accès</div>
        <div class="permissions-grid">
            <div class="permissions-list">
                <h4>📋 Modules d\'accès</h4>
                <ul>';
    
    foreach ($permissions as $permission) {
        $html .= '<li>✓ ' . htmlspecialchars($permission) . '</li>';
    }
    
    $html .= '</ul>
            </div>
            <div class="actions-list">
                <h4>⚡ Actions autorisées</h4>
                <ul>';
    
    foreach ($actions as $action) {
        $html .= '<li>→ ' . htmlspecialchars($action) . '</li>';
    }
    
    $html .= '</ul>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p><strong>Document généré automatiquement</strong></p>
        <p>Système de Gestion Clinique et Hospitalière | ' . date('d/m/Y H:i:s') . '</p>
        <p>Ce document est confidentiel et destiné à un usage interne uniquement.</p>
    </div>
    
    <script>
        // Auto-print option (décommentez la ligne suivante pour imprimer automatiquement)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>';
    
    return $html;
}

/**
 * Obtenir les permissions selon le rôle
 */
function getPermissionsByRole($role) {
    switch($role) {
        case 'admin':
            return [
                'Gestion des utilisateurs',
                'Gestion des patients',
                'Gestion des médecins',
                'Gestion des rendez-vous',
                'Gestion des consultations',
                'Gestion des paiements',
                'Paramètres système',
                'Rapports et statistiques'
            ];
        case 'medecin':
            return [
                'Gestion des patients',
                'Gestion des consultations',
                'Gestion des rendez-vous',
                'Historique médical',
                'Prescriptions',
                'Rapports médicaux'
            ];
        case 'secretaire':
            return [
                'Gestion des patients',
                'Gestion des rendez-vous',
                'Gestion des paiements',
                'Accueil et réception'
            ];
        case 'infirmier':
            return [
                'Gestion des patients',
                'Soins infirmiers',
                'Prise de tension',
                'Injections',
                'Pansements'
            ];
        default:
            return ['Aucune permission définie'];
    }
}

/**
 * Obtenir les actions autorisées selon le rôle
 */
function getActionsByRole($role) {
    switch($role) {
        case 'admin':
            return [
                'Créer, modifier, supprimer des utilisateurs',
                'Accès complet à toutes les fonctionnalités',
                'Gestion des permissions',
                'Configuration système',
                'Export de données',
                'Sauvegarde et restauration'
            ];
        case 'medecin':
            return [
                'Consulter et modifier les dossiers patients',
                'Créer et gérer les consultations',
                'Planifier des rendez-vous',
                'Rédiger des prescriptions',
                'Générer des rapports médicaux',
                'Accès aux antécédents médicaux'
            ];
        case 'secretaire':
            return [
                'Créer et modifier les dossiers patients',
                'Planifier et gérer les rendez-vous',
                'Gérer les paiements',
                'Accueillir les patients',
                'Générer des documents',
                'Communication avec les patients'
            ];
        case 'infirmier':
            return [
                'Consulter les dossiers patients',
                'Effectuer les soins infirmiers',
                'Prendre les constantes vitales',
                'Administrer les traitements',
                'Effectuer les pansements',
                'Tenir à jour les observations'
            ];
        default:
            return ['Aucune action autorisée'];
    }
}
?>
