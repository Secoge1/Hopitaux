<?php
/**
 * Vérifie le filtrage par personnel (StaffScope) : schéma, liaison démo, comptages.
 *
 * Usage : php config/verify_staff_scope.php
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
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Analyse.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

TenantContext::ensureSchema();
$pdo = getDB();
$tenantId = 1;
$ok = 0;
$fail = 0;

function check(bool $cond, string $label): void
{
    global $ok, $fail;
    if ($cond) {
        echo "OK  $label\n";
        $ok++;
    } else {
        echo "FAIL $label\n";
        $fail++;
    }
}

function loginAs(PDO $pdo, string $login, int $tenantId): void
{
    StaffScope::reset();
    $_SESSION['user_connected'] = true;
    $_SESSION['is_platform_admin'] = false;
    $_SESSION['tenant_id'] = $tenantId;
    TenantContext::setTenantId($tenantId);

    $stmt = $pdo->prepare('SELECT id, role FROM utilisateurs WHERE nom_utilisateur = ? AND tenant_id = ? LIMIT 1');
    $stmt->execute([$login, $tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['user_id'] = (int) ($row['id'] ?? 0);
    $_SESSION['user_role'] = $row['role'] ?? '';
    $_SESSION['user_name'] = $login;
}

$requiredColumns = [
    ['medecins', 'utilisateur_id'],
    ['personnel', 'utilisateur_id'],
    ['analyses', 'technicien_id'],
    ['consultation_soins', 'personnel_id'],
];

foreach ($requiredColumns as [$table, $col]) {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $col]);
    check((bool) $stmt->fetchColumn(), "Colonne {$table}.{$col}");
}

check(is_file(__DIR__ . '/../includes/staff_scope.php'), 'Fichier includes/staff_scope.php');
check(is_file(__DIR__ . '/../includes/staff_link.php'), 'Fichier includes/staff_link.php');
check(function_exists('app_role_staff_link_type'), 'Helper app_role_staff_link_type');
check(StaffLink::linkTypeForRole('medecin') === 'medecin', 'StaffLink type médecin');
check(StaffLink::linkTypeForRole('sage_femme') === 'medecin', 'StaffLink type sage-femme → fiche médecin');
check(StaffLink::linkTypeForRole('laborantin') === 'medecin', 'StaffLink type laborantin → fiche Médecins');
check(StaffLink::linkTypeForRole('admin') === null, 'StaffLink type admin = null');

// Comptages tenant (admin)
loginAs($pdo, 'admin', $tenantId);
$adminConsult = (new Consultation())->getCount();
$adminPatients = (new Patient())->getCount();
$adminAnalyses = count((new Analyse())->getAll(1, 1000));

// Médecin lié → sous-ensemble
loginAs($pdo, 'medecin', $tenantId);
$ctx = StaffScope::context();
check($ctx['active'] && $ctx['linked'] && $ctx['medecin_id'] > 0, 'Médecin démo lié à une fiche medecins');
$medLink = StaffLink::getLinkForUser((int) $_SESSION['user_id']);
check($medLink['type'] === 'medecin' && $medLink['id'] > 0, 'StaffLink médecin démo via utilisateurs');
$medConsult = (new Consultation())->getCount();
check($medConsult > 0 && $medConsult <= $adminConsult, "Consultations médecin ($medConsult) <= admin ($adminConsult)");
$medPatients = (new Patient())->getCount();
check($medPatients > 0 && $medPatients <= $adminPatients, "Patients médecin ($medPatients) <= admin ($adminPatients)");
$medPatStats = (new Patient())->getStats();
check((int) ($medPatStats['total'] ?? -1) === $medPatients, 'Patient::getStats().total = getCount() médecin');
check((int) ($medPatStats['actif'] ?? -1) <= $medPatients, 'Patient::getStats().actif cohérent médecin');
$hasReferentCol = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE()
     AND table_name = 'patients' AND column_name = 'medecin_referent_id' LIMIT 1"
)->fetchColumn();
check($hasReferentCol, 'Colonne patients.medecin_referent_id');
try {
    $medStats = (new Consultation())->getStats();
    check(isset($medStats['total']), 'Consultation::getStats() médecin (SQL OK)');
    $medLabStats = (new Analyse())->getStats();
    check(isset($medLabStats['total']), 'Analyse::getStats() médecin (SQL OK)');
} catch (Throwable $e) {
    check(false, 'getStats modules médecin : ' . $e->getMessage());
}

// Infirmier lié → patients via soins
loginAs($pdo, 'infirmier', $tenantId);
$ctx = StaffScope::context();
check($ctx['active'] && $ctx['linked'] && $ctx['personnel_id'] > 0, 'Infirmier démo lié à une fiche personnel');
$infPatients = (new Patient())->getCount();
check($infPatients > 0 && $infPatients <= $adminPatients, "Patients infirmier ($infPatients) <= admin ($adminPatients)");

// Laborantin lié → analyses assignées
loginAs($pdo, 'laborantin', $tenantId);
$ctx = StaffScope::context();
check($ctx['active'] && $ctx['linked'] && ($ctx['personnel_id'] ?? 0) > 0, 'Laborantin démo lié à une fiche personnel');
$labLink = StaffLink::getLinkForUser((int) $_SESSION['user_id']);
check($labLink['type'] === 'medecin' && $labLink['id'] > 0, 'StaffLink laborantin démo via utilisateurs');
$labAnalyses = count((new Analyse())->getAll(1, 1000));
check($labAnalyses > 0 && $labAnalyses < $adminAnalyses, "Analyses laborantin ($labAnalyses) < admin ($adminAnalyses)");

// Compte scopé sans liaison → aucune donnée clinique
loginAs($pdo, 'technicien', $tenantId);
$ctx = StaffScope::context();
if ($ctx['active'] && !$ctx['linked']) {
    check((int) (new Consultation())->getCount() === 0, 'Technicien non lié : 0 consultation');
    check((int) (new Patient())->getCount() === 0, 'Technicien non lié : 0 patient');
} else {
    check(true, 'Technicien (lié ou hors rôles scopés)');
}

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
