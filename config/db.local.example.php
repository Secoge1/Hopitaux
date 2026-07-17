<?php
/**
 * Configuration MySQL locale / production (ne pas versionner db.local.php).
 * Copier ce fichier en config/db.local.php et renseigner les identifiants hébergeur.
 *
 * Exemple OVH / mutualisé :
 *   DB_HOST = mysqlXXX.hosting.com ou localhost
 *   DB_NAME = cp2640311p29_efficasante
 *   DB_USER = cp2640311p29
 *   DB_PASS = votre_mot_de_passe
 *
 * PharmaPro production (pharma.secogesarl.com) :
 *   Copier config/db.pharma.production.example.php → config/db.pharma.production.php
 */

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
    define('DB_PASS', 'votre_mot_de_passe_mysql');
}
