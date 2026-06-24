<?php
/**
 * Vérification : assignation technicien laboratoire + visibilité laborantin.
 * Usage : php config/verify_lab_technicien.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/saas/TenantContext.php';
require_once __DIR__ . '/../includes/staff_scope.php';
require_once __DIR__ . '/../includes/staff_link.php';
require_once __DIR__ . '/../models/Analyse.php';
require_once __DIR__ . '/../models/Personnel.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

TenantContext::ensureSchema();
$pdo = getDB();
$tenantId = (int) ($pdo->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 1);
$ok = 0;
$fail = 0;

function vok(string $label): void
{
    global $ok;
    $ok++;
    echo "OK  $label\n";
}

function vfail(string $label): void
{
    global $fail;
    $fail++;
    echo "FAIL $label\n";
}

function loginAs(PDO $pdo, string $login, int $tenantId): bool
{
    StaffScope::reset();
    $_SESSION['user_connected'] = true;
    $_SESSION['is_platform_admin'] = false;
    $_SESSION['tenant_id'] = $tenantId;
    TenantContext::setTenantId($tenantId);

    $stmt = $pdo->prepare('SELECT id, role FROM utilisateurs WHERE nom_utilisateur = ? AND tenant_id = ? LIMIT 1');
    $stmt->execute([$login, $tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['user_role'] = $row['role'] ?? '';
    $_SESSION['user_name'] = $login;
    return true;
}

echo "=== Vérification technicien laboratoire ===\n\n";

$hasCol = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE()
     AND table_name = 'analyses' AND column_name = 'technicien_id' LIMIT 1"
)->fetchColumn();
$hasCol ? vok('Colonne analyses.technicien_id') : vfail('Colonne analyses.technicien_id manquante');

$labList = (new Personnel())->listTechniciensLaboratoire();
count($labList) > 0 ? vok('Personnel::listTechniciensLaboratoire() — ' . count($labList) . ' entrée(s)') : vfail('listTechniciensLaboratoire() vide');

loginAs($pdo, 'admin', $tenantId);
StaffScope::canPickTechnicienOnAnalyse() ? vok('Admin peut choisir le technicien') : vfail('Admin cannot pick technicien');

$fakePick = StaffScope::technicienIdForAnalyseForm(count($labList) ? (int) $labList[0]['id'] : 1);
$fakePick > 0 ? vok('technicienIdForAnalyseForm(admin) valide un ID personnel') : vfail('technicienIdForAnalyseForm(admin) échoue');

if (!loginAs($pdo, 'laborantin', $tenantId)) {
    vfail('Compte démo « laborantin » introuvable');
    echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
    exit(1);
}

$ctx = StaffScope::context();
!StaffScope::canPickTechnicienOnAnalyse() ? vok('Laborantin ne choisit pas le technicien (auto)') : vfail('Laborantin can pick technicien');

if (!$ctx['linked'] || !($ctx['personnel_id'] ?? 0)) {
    vfail('Laborantin démo non rattaché — impossible de tester la suite');
    echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
    exit(1);
}
vok('Laborantin rattaché personnel #' . $ctx['personnel_id']);

$autoId = StaffScope::technicienIdForAnalyseForm(99999);
$autoId === (int) $ctx['personnel_id']
    ? vok('technicienIdForAnalyseForm(laborantin) = personnel_id (ignore POST)')
    : vfail("technicienIdForAnalyseForm(laborantin) attendu {$ctx['personnel_id']}, obtenu " . ($autoId ?? 'null'));

$claim = StaffScope::technicienIdForAnalyseClaim(0);
$claim === (int) $ctx['personnel_id']
    ? vok('technicienIdForAnalyseClaim() sur analyse sans technicien')
    : vfail('technicienIdForAnalyseClaim() incorrect');

$patientId = (int) $pdo->query('SELECT id FROM patients WHERE tenant_id = ' . (int) $tenantId . ' ORDER BY id ASC LIMIT 1')->fetchColumn();
$medecinId = (int) $pdo->query('SELECT id FROM medecins WHERE tenant_id = ' . (int) $tenantId . ' ORDER BY id ASC LIMIT 1')->fetchColumn();
if ($patientId < 1 || $medecinId < 1) {
    vfail('Patient ou médecin de test introuvable');
    echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
    exit(1);
}

$analyseModel = new Analyse();
$beforeCount = count($analyseModel->getAll(1, 5000));
$newId = $analyseModel->create([
    'patient_id' => $patientId,
    'medecin_id' => $medecinId,
    'type_analyse' => 'sang',
    'priorite' => 'normale',
    'description' => 'Test auto verify_lab_technicien',
    'statut' => 'en_attente',
    'technicien_id' => $autoId,
]);

if (!$newId) {
    vfail('Analyse::create() avec technicien_id');
    echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
    exit(1);
}
vok('Analyse test créée #' . $newId);

$stmt = $pdo->prepare('SELECT technicien_id FROM analyses WHERE id = ? AND tenant_id = ?');
$stmt->execute([(int) $newId, $tenantId]);
$dbTechId = (int) $stmt->fetchColumn();
$dbTechId === (int) $ctx['personnel_id']
    ? vok('technicien_id en base = personnel laborantin')
    : vfail("technicien_id en base : {$dbTechId} au lieu de {$ctx['personnel_id']}");

$afterCount = count($analyseModel->getAll(1, 5000));
$afterCount === $beforeCount + 1
    ? vok("Laborantin voit la nouvelle analyse ({$afterCount} = {$beforeCount}+1)")
    : vfail("Liste laborantin : avant {$beforeCount}, après {$afterCount}");

$row = $analyseModel->getById((int) $newId);
$row && !empty($row['technicien_nom'])
    ? vok('getById() joint le nom du technicien')
    : vfail('getById() sans technicien_nom');

StaffScope::canAccessAnalyse($row) ? vok('canAccessAnalyse() laborantin sur sa analyse') : vfail('canAccessAnalyse() refusé');

loginAs($pdo, 'admin', $tenantId);
$adminRow = $analyseModel->getById((int) $newId);
$adminRow ? vok('Admin voit l\'analyse test') : vfail('Admin ne voit pas l\'analyse test');

$del = $pdo->prepare('DELETE FROM analyses WHERE id = ? AND tenant_id = ?');
$del->execute([(int) $newId, $tenantId]);
$del->rowCount() === 1 ? vok('Analyse test supprimée') : vfail('Suppression analyse test');

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
