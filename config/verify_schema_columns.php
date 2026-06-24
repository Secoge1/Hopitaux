<?php
/**
 * Vérifie que les colonnes utilisées dans les INSERT/UPDATE des modèles existent en BDD.
 *
 * Usage : php config/verify_schema_columns.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = dirname(__DIR__);
require_once $base . '/config/db.php';

$passed = 0;
$failed = 0;
$warnings = 0;

function tok(string $msg): void {
    global $passed;
    $passed++;
    echo "[OK] $msg\n";
}

function tfail(string $msg): void {
    global $failed;
    $failed++;
    echo "[FAIL] $msg\n";
}

function twarn(string $msg): void {
    global $warnings;
    $warnings++;
    echo "[WARN] $msg\n";
}

echo "=== Vérification colonnes code vs BDD ===\n\n";

$pdo = getDB();
$schema = [];
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
    $schema[$table] = array_flip($cols);
}

$dynamicColumns = ['tenant_id'];

/** UPDATE explicites à contrôler : [fichier, table, colonnes] */
$explicitUpdates = [
    ['models/Analyse.php', 'analyses', ['fichier_image']],
    ['models/Paiement.php', 'paiements', ['ecriture_comptable_id', 'analyse_id']],
    ['models/Communication.php', 'messages_internes', ['lu', 'date_lecture', 'archive']],
    ['models/Patient.php', 'patients', ['statut', 'date_suppression']],
    ['models/Medecin.php', 'medecins', ['statut', 'date_suppression']],
];

$modelInserts = [];
$modelFiles = glob($base . '/models/*.php') ?: [];
foreach ($modelFiles as $file) {
    $content = file_get_contents($file);
    $basename = basename($file);

    if (!preg_match_all(
        "/\\\$columns\s*=\s*\[([^\]]+)\].*?INSERT\s+INTO\s+`?([a-z_]+)`?\s*\(/si",
        $content,
        $matches,
        PREG_SET_ORDER
    )) {
        continue;
    }

    foreach ($matches as $m) {
        $table = $m[2];
        preg_match_all("/'([a-z_][a-z0-9_]*)'/", $m[1], $colMatches);
        $columns = $colMatches[1] ?? [];
        if (empty($columns)) {
            continue;
        }
        $key = "{$basename} → {$table}";
        $modelInserts[$key] = ['table' => $table, 'columns' => $columns];
    }
}

echo "--- INSERT modèles ---\n";
foreach ($modelInserts as $label => $info) {
    $table = $info['table'];
    if (!isset($schema[$table])) {
        tfail("{$label} — table `{$table}` absente");
        continue;
    }
    $missing = [];
    foreach ($info['columns'] as $col) {
        if (in_array($col, $dynamicColumns, true)) {
            continue;
        }
        if (!isset($schema[$table][$col])) {
            $missing[] = $col;
        }
    }
    if (empty($missing)) {
        tok("{$label} — " . count($info['columns']) . " colonne(s)");
    } else {
        tfail("{$label} — colonne(s) inconnue(s) : " . implode(', ', $missing));
    }
}

echo "\n--- UPDATE explicites ---\n";
foreach ($explicitUpdates as [$rel, $table, $columns]) {
    if (!isset($schema[$table])) {
        tfail("{$rel} → {$table} — table absente");
        continue;
    }
    $missing = array_filter($columns, static fn($c) => !isset($schema[$table][$c]));
    if (empty($missing)) {
        tok("{$rel} → {$table}");
    } else {
        foreach ($missing as $col) {
            twarn("{$rel} → {$table}.{$col} — absente (gestion gracieuse ou migration TenantSchema)");
        }
    }
}

$obsoleteInSql = ['numero_police', 'taux_couverture', 'cree_par'];
echo "\n--- Alias obsolètes dans INSERT SQL ---\n";
$directSqlHits = [];
$phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
foreach ($phpFiles as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') {
        continue;
    }
    $path = $f->getPathname();
    if (strpos($path, 'vendor') !== false || strpos($path, 'verify_schema_columns') !== false) {
        continue;
    }
    $content = file_get_contents($path);
    if (!preg_match('/INSERT\s+INTO/i', $content)) {
        continue;
    }
    foreach ($obsoleteInSql as $col) {
        if (preg_match('/INSERT\s+INTO[^;]*\b' . preg_quote($col, '/') . '\b/i', $content)) {
            $rel = str_replace($base . DIRECTORY_SEPARATOR, '', $path);
            if ($col === 'cree_par' && strpos($rel, 'models\\Medicament.php') !== false) {
                continue;
            }
            $directSqlHits[] = "{$rel} → {$col}";
        }
    }
}
if (empty($directSqlHits)) {
    tok('Aucun INSERT avec alias obsolètes (numero_police, taux_couverture, cree_par/mouvements)');
} else {
    foreach ($directSqlHits as $hit) {
        tfail("INSERT obsolète : {$hit}");
    }
}

echo "\n=== Résumé : {$passed} OK, {$failed} FAIL, {$warnings} WARN ===\n";
exit($failed > 0 ? 1 : 0);
