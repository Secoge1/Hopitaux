<?php
/**
 * Vérification : médecin crée un patient depuis son espace → visible dans sa liste.
 * Usage : php config/verify_medecin_patient_flow.php [nom_utilisateur]
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base = dirname(__DIR__);
require_once $base . '/config/db.php';
require_once $base . '/config/Auth.php';
require_once $base . '/includes/staff_scope.php';
require_once $base . '/includes/staff_link.php';
require_once $base . '/includes/saas/TenantContext.php';
require_once $base . '/models/Patient.php';

$login = $argv[1] ?? 'medecin';
$ok = 0;
$fail = 0;

function vok(string $msg): void {
    global $ok;
    $ok++;
    echo "OK  $msg\n";
}

function vfail(string $msg): void {
    global $fail;
    $fail++;
    echo "FAIL  $msg\n";
}

echo "=== Vérification flux médecin → patient ===\n\n";

$pdo = getDB();

$hasCol = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE()
     AND table_name = 'patients' AND column_name = 'medecin_referent_id' LIMIT 1"
)->fetchColumn();
$hasCol ? vok('Colonne patients.medecin_referent_id') : vfail('Colonne patients.medecin_referent_id manquante');

$stmt = $pdo->prepare('SELECT id, nom_utilisateur, role, tenant_id FROM utilisateurs WHERE nom_utilisateur = ? LIMIT 1');
$stmt->execute([$login]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    vfail("Utilisateur « {$login} » introuvable");
    echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
    exit(1);
}
vok("Compte trouvé : {$user['nom_utilisateur']} (rôle {$user['role']})");

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = $user['nom_utilisateur'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_statut'] = 'actif';
$_SESSION['user_connected'] = true;
$_SESSION['tenant_id'] = (int) ($user['tenant_id'] ?? 1);
$_SESSION['last_activity'] = time();

$auth = Auth::getInstance();
$auth->estMedecin() ? vok('Auth::estMedecin()') : vfail('Le compte test n\'est pas médecin');

$ctx = StaffScope::context();
if ($ctx['linked'] && ($ctx['medecin_id'] ?? 0) > 0) {
    vok('Compte rattaché à fiche médecin #' . $ctx['medecin_id']);
} else {
    vfail('Compte NON rattaché à une fiche médecin — création visible impossible');
}

$referent = StaffScope::medecinReferentIdForPatientCreate();
($referent > 0) ? vok("medecinReferentIdForPatientCreate() = {$referent}") : vfail('medecinReferentIdForPatientCreate() vide');

$beforeCount = (new Patient())->getCount();
vok("Patients visibles avant test : {$beforeCount}");

$testNom = 'VerifyFlow' . date('His');
$testPrenom = 'TestAuto';
$patientModel = new Patient();
$newId = false;

try {
    $newId = $patientModel->create([
        'nom' => $testNom,
        'prenom' => $testPrenom,
        'date_naissance' => '1990-01-15',
        'genre' => 'M',
        'statut' => 'actif',
    ]);
} catch (Throwable $e) {
    vfail('Création patient : ' . $e->getMessage());
}

if ($newId) {
    vok("Patient créé #{$newId}");
    $row = $pdo->prepare('SELECT medecin_referent_id, tenant_id FROM patients WHERE id = ?');
    $row->execute([(int) $newId]);
    $p = $row->fetch(PDO::FETCH_ASSOC);
    if ($p && (int) ($p['medecin_referent_id'] ?? 0) === (int) $referent) {
        vok('medecin_referent_id correct en BDD');
    } else {
        vfail('medecin_referent_id incorrect ou absent (attendu ' . $referent . ', reçu ' . ($p['medecin_referent_id'] ?? 'null') . ')');
    }

    $fetched = $patientModel->getById((int) $newId);
    $fetched ? vok('getById() accessible pour le médecin') : vfail('getById() refuse l\'accès au patient créé');

    $afterCount = $patientModel->getCount();
    if ($afterCount === $beforeCount + 1) {
        vok("Liste médecin : {$beforeCount} → {$afterCount} (+1)");
    } else {
        vfail("Liste incohérente : avant {$beforeCount}, après {$afterCount}");
    }

    $stats = $patientModel->getStats();
    if ((int) ($stats['total'] ?? -1) === $afterCount) {
        vok('getStats().total = getCount()');
    } else {
        vfail('getStats().total (' . ($stats['total'] ?? '?') . ') ≠ getCount() (' . $afterCount . ')');
    }

    $pdo->prepare("UPDATE patients SET statut = 'supprime' WHERE id = ?")->execute([(int) $newId]);
    vok('Patient test nettoyé (statut supprime)');
} else {
    vfail('Échec création patient');
}

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
