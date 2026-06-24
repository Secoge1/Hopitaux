<?php
/**
 * Configuration de la base de données
 */

$dbLocal = __DIR__ . '/db.local.php';
if (is_file($dbLocal)) {
    require_once $dbLocal;
}

// Inclure la configuration de la devise seulement si pas déjà incluse
if (!defined('CURRENCY_CODE')) {
    require_once __DIR__ . '/../includes/currency_helper.php';
}

// Définir les constantes de base de données seulement si pas déjà définies
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'cp2640311p29_efficasante');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

function getDBSoft(): ?PDO
{
    static $pdo = null;
    static $failed = false;

    if ($failed) {
        return null;
    }
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Erreur de connexion DB: ' . $e->getMessage());
        $failed = true;
        $pdo = null;
        return null;
    }
}

function getDB()
{
    $pdo = getDBSoft();
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    die('Une erreur est survenue. Veuillez réessayer plus tard.');
}
