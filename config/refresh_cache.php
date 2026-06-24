<?php
/**
 * SCRIPT DE RAFRAÎCHISSEMENT DU CACHE
 * À appeler après chaque modification importante (ajout/suppression)
 * 
 * Peut être appelé via:
 * 1. AJAX après une action
 * 2. include() dans le code PHP après une modification
 * 3. URL directe: https://votre-site.com/config/refresh_cache.php
 */

require_once __DIR__ . '/cache_manager.php';

// Forcer le rechargement du cache
CacheManager::refresh();

// Si appelé via AJAX, retourner JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Cache rafraîchi avec succès',
        'version' => CacheManager::getVersion(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Si appelé directement
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Cache rafraîchi</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 600px; 
            margin: 50px auto; 
            padding: 20px;
            background: #f5f5f5;
        }
        .success {
            background: #4CAF50;
            color: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .info {
            background: white;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            border-left: 4px solid #2196F3;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        a:hover {
            background: #0b7dda;
        }
    </style>
</head>
<body>
    <div class="success">
        <h2>✅ Cache rafraîchi avec succès !</h2>
        <p>Nouvelle version: <?php echo CacheManager::getVersion(); ?></p>
    </div>
    
    <div class="info">
        <h3>ℹ️ Informations</h3>
        <ul>
            <li><strong>Date/Heure:</strong> <?php echo date('d/m/Y H:i:s'); ?></li>
            <li><strong>Version cache:</strong> <?php echo CacheManager::getVersion(); ?></li>
            <li><strong>OpCache:</strong> <?php echo function_exists('opcache_reset') ? 'Vidé ✅' : 'Non disponible'; ?></li>
        </ul>
    </div>
    
    <a href="../index.php">← Retour à l'accueil</a>
</body>
</html>
