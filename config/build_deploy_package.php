<?php
/**
 * Génère un dossier prêt pour l'import FTP / hébergement web (sans fichiers dev).
 *
 * Usage:
 *   php config/build_deploy_package.php
 *   php config/build_deploy_package.php --output=C:\deploy\sesante
 *   php config/build_deploy_package.php --zip
 *   php config/build_deploy_package.php --exclude-uploads
 *   php config/build_deploy_package.php --dry-run
 *   php config/build_deploy_package.php --clean
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI uniquement.');
}

$base = dirname(__DIR__);
$opts = parseDeployOptions($argv);

$excludeDirs = [
    'efficasante_web',
    'efficasante_app',
    '.git',
    '.svn',
    '.idea',
    '.vscode',
    'node_modules',
    'vendor/tecnickcom/tcpdf/examples',
    'scripts',
];

if ($opts['exclude_uploads']) {
    $excludeDirs[] = 'uploads';
}

$excludeDirPrefixes = [
    'Hopitaux_deploy_',
    'deploy_package_',
];

$excludeFiles = [
    // Outils de build (ne pas embarquer le générateur lui-même)
    'config/build_deploy_package.php',

    // Secrets / config locale
    'config/db.local.php',
    '.env',
    '.env.local',

    // Scripts dev / migration / diagnostic (config/)
    'config/verify_pre_deploy.php',
    'config/verify_saas.php',
    'config/verify_subscription_invoices.php',
    'config/verify_home_modules.php',
    'config/verify_thermal_printer.php',
    'config/verify_patient_medecin_ticket.php',
    'config/verify_roles.php',
    'config/verify_lab_technicien.php',
    'config/verify_platform_payments_actions.php',
    'config/verify_mistral_integration.php',
    'config/verify_currency_integration.php',
    'config/verify_module_stats.php',
    'config/verify_platform_admin.php',
    'config/verify_medecin_patient_flow.php',
    'config/verify_tarifs_laboratoire.php',
    'config/verify_user_guide_pdf.php',
    'config/verify_mobile_layout.php',
    'config/verify_lab_suggestions_custom_type.php',
    'config/verify_schema_columns.php',
    'config/verify_staff_scope.php',
    'config/verify_tenant_modules.php',
    'config/seed_demo_data.php',
    'config/vider_donnees.php',
    'config/sql/vider_donnees.sql',
    'config/test_module_boot.php',
    'config/show_columns.php',
    'config/inspect_staff_schema.php',
    'config/diagnose_medecin_nav.php',
    'config/diagnose_staff_link.php',
    'config/diagnostic_auto.php',
    'config/diagnostic_erreur.php',
    'config/diagnostic_complet_tables.php',
    'config/diagnostic_database.php',
    'config/diagnostic_simple.php',
    'config/diagnostic_finances.php',
    'config/migrate_saas_multitenant.php',
    'config/run_saas_migration.php',
    'config/backfill_patient_referent.php',
    'config/import_production.php',
    'config/import_production_final.php',
    'config/unlock_saas_tables.php',
    'config/enable_platform_admin.php',
    'config/correction_finale_complete.php',
    'config/corriger_finances_statut.php',
    'config/corriger_budgets_auto.php',
    'config/corriger_budgets.php',
    'config/corriger_messages_internes.php',
    'config/corriger_libelle_comptes.php',
    'config/creer_horaires_personnel.php',
    'config/creer_messages_internes.php',
    'config/creation_finale.php',
    'config/recreer_messages_internes_complet.php',

    // Anciens exports PDF paiements (non référencés)
    'paiements/export_paiement_individual.php',
    'paiements/export_paiement_individual_pdf.php',
    'paiements/export_paiement_individual_pdf_v3.php',
    'paiements/export_pdf.php',

    // Documentation locale
    'README_FRAIS_SOINS.md',
    'README_CORRECTIONS.md',
    'RAPPORT_CORRECTIONS_COMPLET.md',
    'GUIDE_FRAIS_SOINS.md',
    'CORRECTIONS_ANOMALIES.md',
];

$excludeFilePatterns = [
    '/^config\/verify_.*\.php$/',
    '/^config\/diagnostic_.*\.php$/',
    '/^config\/diagnose_.*\.php$/',
    '/^config\/corriger_.*\.php$/',
    '/^config\/correction_.*\.php$/',
    '/^config\/creer_.*\.php$/',
    '/^config\/import_production.*\.php$/',
    '/\.zip$/i',
    '/\.7z$/i',
    '/\.tar\.gz$/i',
];

$outputDir = $opts['output'];
$outputReal = realpath(dirname($outputDir)) ?: dirname($outputDir);
$baseReal = realpath($base);

if ($baseReal && strpos($outputReal . DIRECTORY_SEPARATOR, $baseReal . DIRECTORY_SEPARATOR) === 0) {
    fwrite(STDERR, "Erreur: le dossier de sortie doit être en dehors du projet (ex. C:\\wamp64\\www\\Hopitaux_deploy_...).\n");
    exit(1);
}

$stats = [
    'copied' => 0,
    'skipped' => 0,
    'bytes' => 0,
    'skipped_bytes' => 0,
    'errors' => 0,
];

echo "=== Package déploiement Se.Santé / Hopitaux ===\n";
echo "Source : $base\n";
echo "Cible  : $outputDir\n";
echo ($opts['dry_run'] ? "Mode   : simulation (--dry-run)\n" : "Mode   : copie réelle\n");
echo ($opts['exclude_uploads'] ? "Uploads: exclus\n" : "Uploads: inclus\n");
echo "\n";

if (!$opts['dry_run']) {
    if (is_dir($outputDir)) {
        if ($opts['clean']) {
            removeTree($outputDir);
            echo "[INFO] Dossier cible nettoyé.\n";
        } else {
            fwrite(STDERR, "Erreur: le dossier existe déjà. Utilisez --clean ou changez --output=.\n");
            exit(1);
        }
    }
    if (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
        fwrite(STDERR, "Erreur: impossible de créer $outputDir\n");
        exit(1);
    }
}

$skippedSamples = [];
$walkCtx = [
    'base' => $base,
    'output_dir' => $outputDir,
    'opts' => $opts,
    'exclude_dirs' => $excludeDirs,
    'exclude_dir_prefixes' => $excludeDirPrefixes,
    'exclude_files' => $excludeFiles,
    'exclude_file_patterns' => $excludeFilePatterns,
    'stats' => &$stats,
    'skipped_samples' => &$skippedSamples,
];

walkDeployTree($base, '', $walkCtx);

if (!$opts['dry_run']) {
    writeDeployArtifacts($outputDir, $opts);
}

$manifest = [
    'generated_at' => date('c'),
    'source' => $base,
    'output' => $outputDir,
    'dry_run' => $opts['dry_run'],
    'exclude_uploads' => $opts['exclude_uploads'],
    'stats' => $stats,
    'skipped_samples' => $skippedSamples,
];

if (!$opts['dry_run']) {
    file_put_contents(
        $outputDir . DIRECTORY_SEPARATOR . 'DEPLOY_MANIFEST.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

echo "--- Résumé ---\n";
echo "Fichiers copiés  : {$stats['copied']}\n";
echo "Éléments ignorés : {$stats['skipped']}\n";
echo 'Taille copiée    : ' . formatBytes($stats['bytes']) . "\n";
echo 'Taille ignorée   : ' . formatBytes($stats['skipped_bytes']) . "\n";
if ($stats['errors'] > 0) {
    echo "Erreurs          : {$stats['errors']}\n";
}

if ($skippedSamples !== []) {
    echo "\nExemples exclus (max 25) :\n";
    foreach ($skippedSamples as $line) {
        echo "  - $line\n";
    }
}

$zipPath = null;
if ($opts['zip'] && !$opts['dry_run']) {
    $zipPath = $outputDir . '.zip';
    echo "\nCréation de l'archive ZIP...\n";
    if (createZipFromDir($outputDir, $zipPath)) {
        echo "[OK] Archive : $zipPath (" . formatBytes((int) filesize($zipPath)) . ")\n";
    } else {
        $stats['errors']++;
        fwrite(STDERR, "[FAIL] Échec création ZIP (extension zip manquante ?)\n");
    }
}

if (!$opts['dry_run']) {
    echo "\n--- Après upload sur le serveur ---\n";
    echo "1. Adapter config/config.php (SITE_URL, DB_*) ou créer config/db.local.php\n";
    echo "2. Vérifier droits écriture : uploads/, cache/, backups/, logs/\n";
    echo "3. Ne pas écraser uploads/ prod si le serveur a déjà les fichiers clients\n";
    echo "4. Lire DEPLOY_README.txt dans le package\n";
    echo "\nPackage prêt : $outputDir\n";
}

exit($stats['errors'] > 0 ? 1 : 0);

// ---------------------------------------------------------------------------

function parseDeployOptions(array $argv): array
{
    $base = dirname(__DIR__);
    $defaultOutput = dirname($base) . DIRECTORY_SEPARATOR . 'Hopitaux_deploy_' . date('Ymd_His');

    $opts = [
        'output' => $defaultOutput,
        'zip' => false,
        'dry_run' => false,
        'clean' => false,
        'exclude_uploads' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--zip') {
            $opts['zip'] = true;
        } elseif ($arg === '--dry-run') {
            $opts['dry_run'] = true;
        } elseif ($arg === '--clean') {
            $opts['clean'] = true;
        } elseif ($arg === '--exclude-uploads') {
            $opts['exclude_uploads'] = true;
        } elseif ($arg === '--include-uploads') {
            $opts['exclude_uploads'] = false;
        } elseif (strpos($arg, '--output=') === 0) {
            $opts['output'] = substr($arg, 9);
        } elseif ($arg === '--help' || $arg === '-h') {
            echo "Usage: php config/build_deploy_package.php [options]\n\n";
            echo "Options:\n";
            echo "  --output=PATH       Dossier de sortie (défaut: ../Hopitaux_deploy_YYYYMMDD_HHMMSS)\n";
            echo "  --zip               Crée aussi une archive .zip à côté du dossier\n";
            echo "  --exclude-uploads   N'inclut pas uploads/ (~économie d'espace)\n";
            echo "  --include-uploads   Inclut uploads/ (défaut)\n";
            echo "  --dry-run           Simule sans copier\n";
            echo "  --clean             Supprime le dossier cible s'il existe déjà\n";
            echo "  --help              Affiche cette aide\n";
            exit(0);
        } else {
            fwrite(STDERR, "Option inconnue: $arg ( --help pour l'aide )\n");
            exit(1);
        }
    }

    return $opts;
}

function walkDeployTree(string $fullDir, string $relPrefix, array $ctx): void
{
    $entries = @scandir($fullDir);
    if ($entries === false) {
        $ctx['stats']['errors']++;
        fwrite(STDERR, '[ERR] lecture ' . ($relPrefix === '' ? '.' : $relPrefix) . "\n");
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $rel = $relPrefix === '' ? $entry : $relPrefix . '/' . $entry;
        $fullPath = $fullDir . DIRECTORY_SEPARATOR . $entry;
        $isDir = is_dir($fullPath);

        $dirBlock = $isDir ? isUnderExcludedDir($rel, $ctx['exclude_dirs']) : null;
        if ($dirBlock !== null) {
            $ctx['stats']['skipped']++;
            if (count($ctx['skipped_samples']) < 25) {
                $ctx['skipped_samples'][] = "$rel (dossier $dirBlock)";
            }
            continue;
        }

        $reason = getSkipReason(
            $rel,
            $isDir,
            $ctx['exclude_dirs'],
            $ctx['exclude_dir_prefixes'],
            $ctx['exclude_files'],
            $ctx['exclude_file_patterns']
        );

        if ($reason !== null) {
            $ctx['stats']['skipped']++;
            if (!$isDir) {
                $ctx['stats']['skipped_bytes'] += (int) @filesize($fullPath);
            }
            if (count($ctx['skipped_samples']) < 25) {
                $ctx['skipped_samples'][] = "$rel ($reason)";
            }
            continue;
        }

        $dest = $ctx['output_dir'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

        if ($isDir) {
            if (!$ctx['opts']['dry_run'] && !is_dir($dest) && !mkdir($dest, 0755, true)) {
                $ctx['stats']['errors']++;
                fwrite(STDERR, "[ERR] mkdir $rel\n");
            }
            walkDeployTree($fullPath, $rel, $ctx);
            continue;
        }

        $ctx['stats']['copied']++;
        $ctx['stats']['bytes'] += (int) @filesize($fullPath);

        if ($ctx['opts']['dry_run']) {
            continue;
        }

        $destDir = dirname($dest);
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            $ctx['stats']['errors']++;
            fwrite(STDERR, "[ERR] mkdir parent pour $rel\n");
            continue;
        }

        if (!copy($fullPath, $dest)) {
            $ctx['stats']['errors']++;
            fwrite(STDERR, "[ERR] copie $rel\n");
        }
    }
}

function isUnderExcludedDir(string $rel, array $excludeDirs): ?string
{
    $parts = explode('/', $rel);

    foreach ($excludeDirs as $excluded) {
        $exParts = explode('/', $excluded);
        $match = true;
        foreach ($exParts as $i => $seg) {
            if (!isset($parts[$i]) || strcasecmp($parts[$i], $seg) !== 0) {
                $match = false;
                break;
            }
        }
        if ($match) {
            return $excluded;
        }
        if (count($exParts) === 1) {
            foreach ($parts as $part) {
                if (strcasecmp($part, $excluded) === 0) {
                    return $excluded;
                }
            }
        }
    }

    return null;
}

function getSkipReason(
    string $rel,
    bool $isDir,
    array $excludeDirs,
    array $excludeDirPrefixes,
    array $excludeFiles,
    array $excludeFilePatterns
): ?string {
    $parts = explode('/', $rel);

    foreach ($excludeDirPrefixes as $prefix) {
        if (strpos($parts[0], $prefix) === 0) {
            return 'préfixe deploy';
        }
    }

    foreach ($excludeDirs as $excluded) {
        $exParts = explode('/', $excluded);
        $match = true;
        foreach ($exParts as $i => $seg) {
            if (!isset($parts[$i]) || strcasecmp($parts[$i], $seg) !== 0) {
                $match = false;
                break;
            }
        }
        if ($match) {
            return "dossier $excluded";
        }
        if (count($exParts) === 1 && in_array($excluded, $parts, true)) {
            return "dossier $excluded";
        }
    }

    if (!$isDir) {
        if (in_array($rel, $excludeFiles, true)) {
            return 'fichier exclu';
        }
        foreach ($excludeFilePatterns as $pattern) {
            if (preg_match($pattern, $rel)) {
                return 'motif exclu';
            }
        }
        if (preg_match('/\.md$/i', $rel) && substr_count($rel, '/') === 0) {
            return 'doc racine';
        }
    }

    return null;
}

function writeDeployArtifacts(string $outputDir, array $opts): void
{
    $readme = <<<'TXT'
Package de déploiement Se.Santé — généré automatiquement
=========================================================

AVANT L'UPLOAD
--------------
- Vérifier en local : php config/verify_pre_deploy.php (sur la machine de dev)
- Ce package exclut déjà les scripts dev, migrations et projets React/Flutter.

SUR LE SERVEUR (te.secogesarl.com)
----------------------------------
1. Configurer la base :
   - Éditer config/config.php OU créer config/db.local.php (recommandé)
   - SITE_URL = URL HTTPS réelle du site
   - DB_HOST, DB_NAME, DB_USER, DB_PASS = identifiants MySQL hébergeur

2. Droits d'écriture (chmod 755 ou 775 selon hébergeur) :
   uploads/, uploads/logos/, uploads/patients/
   cache/, backups/, logs/

3. uploads/ :
   - Si le serveur a déjà les logos/fichiers clients, ne pas écraser ce dossier.
   - Utilisez --exclude-uploads lors du prochain build si vous ne déployez que le code.

4. Premier accès après déploiement :
   - TenantSchema::ensure() crée/met à jour les tables SaaS automatiquement
   - Admin plateforme → Facturation : factures rétro-générées pour paiements confirmés

SÉCURITÉ
--------
- config/.htaccess bloque l'accès web aux scripts sensibles restants
- Ne laissez jamais config/db.local.php dans un dépôt public

REGÉNÉRER CE PACKAGE
--------------------
php config/build_deploy_package.php --zip
php config/build_deploy_package.php --exclude-uploads --zip --clean --output=C:\chemin\cible

TXT;

    file_put_contents($outputDir . DIRECTORY_SEPARATOR . 'DEPLOY_README.txt', $readme);

    $configHtaccess = <<<'HTA'
# Bloque l'exécution web des scripts config sensibles (généré par build_deploy_package.php)
<IfModule mod_authz_core.c>
    <FilesMatch "^(verify_|diagnostic_|diagnose_|seed_|vider_|migrate_|backfill_|test_|show_columns|inspect_|import_production|corriger_|correction_|creer_|creation_|recreer_|unlock_|enable_platform|build_deploy)">
        Require all denied
    </FilesMatch>
    <Files "db.local.example.php">
        Require all denied
    </Files>
</IfModule>
<IfModule !mod_authz_core.c>
    <FilesMatch "^(verify_|diagnostic_|diagnose_|seed_|vider_|migrate_|backfill_|test_|show_columns|inspect_|import_production|corriger_|correction_|creer_|creation_|recreer_|unlock_|enable_platform|build_deploy)">
        Order allow,deny
        Deny from all
    </FilesMatch>
</IfModule>
HTA;

    file_put_contents($outputDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . '.htaccess', $configHtaccess);

    $rootHtaccess = $outputDir . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_file($rootHtaccess)) {
        $extra = <<<'HTA'

# --- Ajout package déploiement : protection config ---
<IfModule mod_rewrite.c>
    RewriteRule ^config/(verify_|diagnostic_|diagnose_|seed_|vider_|migrate_|backfill_|test_).* - [F,L]
</IfModule>
HTA;
        $current = file_get_contents($rootHtaccess);
        if (strpos($current, 'package déploiement') === false) {
            file_put_contents($rootHtaccess, rtrim($current) . $extra);
        }
    }

    foreach (['uploads', 'uploads/logos', 'uploads/patients', 'cache', 'backups', 'logs'] as $sub) {
        $path = $outputDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}

function removeTree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

function createZipFromDir(string $sourceDir, string $zipPath): bool
{
    if (!class_exists('ZipArchive')) {
        return false;
    }
    if (is_file($zipPath)) {
        unlink($zipPath);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        return false;
    }

    $sourceDir = rtrim(str_replace('\\', '/', $sourceDir), '/');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = str_replace('\\', '/', $item->getPathname());
        $local = substr($path, strlen($sourceDir) + 1);
        if ($item->isDir()) {
            $zip->addEmptyDir($local);
        } else {
            $zip->addFile($item->getPathname(), $local);
        }
    }

    $zip->close();
    return true;
}

function formatBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' o';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' Ko';
    }
    return round($bytes / 1048576, 1) . ' Mo';
}
