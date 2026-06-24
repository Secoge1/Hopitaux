<?php
/**
 * Vérification profils professionnels (type_profil) et affichage sans « Dr. » abusif.
 * CLI : php config/verify_medecin_profil.php
 * URL : /config/verify_medecin_profil.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/medecin_profil.php';

$isCli = PHP_SAPI === 'cli';
$lines = [];
$fail = 0;

function vp(string $msg, bool $ok): void
{
    global $lines, $fail, $isCli;
    $line = ($ok ? '[OK] ' : '[FAIL] ') . $msg;
    $lines[] = $line;
    if (!$ok) {
        $fail++;
    }
    if ($isCli) {
        echo $line . PHP_EOL;
    }
}

// Helpers
vp('medecin_profil_format_name(medecin)', medecin_profil_format_name(['prenom' => 'Jean', 'nom' => 'Dupont', 'type_profil' => 'medecin']) === 'Dr. Jean Dupont');
vp('medecin_profil_format_name(sage_femme)', medecin_profil_format_name(['prenom' => 'Aminata', 'nom' => 'Coulibaly', 'type_profil' => 'sage_femme']) === 'SF. Aminata Coulibaly');
vp('medecin_profil_format_name(infirmier)', medecin_profil_format_name(['prenom' => 'Awa', 'nom' => 'Keita', 'type_profil' => 'infirmier']) === 'Inf. Awa Keita');
vp('medecin_profil_format_name(laborantin)', medecin_profil_format_name(['prenom' => 'Fatou', 'nom' => 'Diallo', 'type_profil' => 'laborantin']) === 'Lab. Fatou Diallo');
vp('medecin_profil_format_name(technicien)', medecin_profil_format_name(['prenom' => 'Ibrahim', 'nom' => 'Traoré', 'type_profil' => 'technicien']) === 'Tech. Ibrahim Traoré');
vp('medecin_profil_format_name(pharmacien)', medecin_profil_format_name(['prenom' => 'Moussa', 'nom' => 'Diarra', 'type_profil' => 'pharmacien']) === 'Pharm. Moussa Diarra');
vp('medecin_profil_format_joined()', medecin_profil_format_joined([
    'medecin_prenom' => 'Fatou',
    'medecin_nom' => 'Diallo',
    'medecin_type_profil' => 'laborantin',
]) === 'Lab. Fatou Diallo');
vp('medecin_profil_attribution_label(laborantin)', medecin_profil_attribution_label('laborantin') === 'Laborantin(e)');
vp('medecin_profil_option_label()', medecin_profil_option_label([
    'prenom' => 'Moussa',
    'nom' => 'Traoré',
    'specialite' => 'Cardiologie',
    'type_profil' => 'medecin',
]) === 'Cardiologie - Dr. Moussa Traoré');

// Colonne BDD (si WAMP / MySQL disponible)
$pdo = null;
if (is_file(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
    if (function_exists('getDBSoft')) {
        $pdo = getDBSoft();
    }
}
if ($pdo instanceof PDO) {
    $hasType = (bool) $pdo->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medecins' AND COLUMN_NAME = 'type_profil'"
    )->fetchColumn();
    vp('Colonne medecins.type_profil', $hasType);
} else {
    vp('Connexion BDD (optionnelle)', true);
}

// Fichiers clés
$files = [
    'includes/medecin_profil.php',
    'medecins/modifier.php',
    'medecins/ajouter.php',
    'config/backfill_medecins_type_profil.php',
];
foreach ($files as $f) {
    vp("Fichier {$f}", is_file(__DIR__ . '/../' . $f));
}

// Plus de « Dr. » en dur dans les vues principales (hors helper + home marketing)
$scanDirs = ['consultations', 'rendez-vous', 'patients', 'laboratoire', 'paiements', 'medecins'];
$hardcoded = 0;
foreach ($scanDirs as $dir) {
    $path = __DIR__ . '/../' . $dir;
    if (!is_dir($path)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($it as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        if ($content !== false && preg_match("/['\"]Dr\\.\\s['\"]|>Dr\\.\\s|<.*Dr\\.\\s/m", $content)) {
            $hardcoded++;
            vp('Dr. en dur : ' . str_replace(__DIR__ . '/../', '', $file->getPathname()), false);
        }
    }
}
if ($hardcoded === 0) {
    vp('Aucun Dr. en dur dans les modules principaux', true);
}

$syntaxOut = (string) shell_exec('php -l "' . __DIR__ . '/../includes/medecin_profil.php"');
vp('Syntaxe medecin_profil.php', strpos($syntaxOut, 'No syntax errors') !== false);

$summary = $fail === 0
    ? 'Toutes les vérifications sont passées.'
    : "{$fail} vérification(s) en échec.";

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}
echo implode("\n", $lines) . "\n\n" . $summary . "\n";
exit($fail > 0 ? 1 : 0);
