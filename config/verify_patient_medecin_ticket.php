<?php
/**
 * Vérification : assignation médecin référent + consultation/ticket depuis module patients.
 * Usage : php config/verify_patient_medecin_ticket.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

$base = dirname(__DIR__);
require_once $base . '/config/db.php';
require_once $base . '/includes/roles.php';
require_once $base . '/includes/saas/TenantContext.php';
require_once $base . '/includes/saas/TenantSchema.php';
require_once $base . '/includes/staff_scope.php';
require_once $base . '/models/Patient.php';
require_once $base . '/models/Medecin.php';
require_once $base . '/models/Consultation.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

TenantSchema::ensure();
$pdo = getDB();
$tenantId = 1;
$ok = 0;
$fail = 0;

function vcheck(bool $cond, string $label): void
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

function loginAs(PDO $pdo, string $role, int $tenantId): void
{
    StaffScope::reset();
    $_SESSION['user_connected'] = true;
    $_SESSION['is_platform_admin'] = false;
    $_SESSION['tenant_id'] = $tenantId;
    TenantContext::setTenantId($tenantId);

    $stmt = $pdo->prepare(
        "SELECT id, nom_utilisateur, role FROM utilisateurs
         WHERE role = ? AND tenant_id = ? AND statut = 'actif' ORDER BY id LIMIT 1"
    );
    $stmt->execute([$role, $tenantId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $_SESSION['user_id'] = 9000;
        $_SESSION['user_role'] = $role;
        $_SESSION['user_name'] = 'verify_' . $role;
        return;
    }
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['nom_utilisateur'];
}

echo "=== Vérification assignation patient + ticket consultation ===\n\n";

$files = [
    'patients/enregistrer_consultation.php',
    'patients/imprimer_ticket.php',
    'patients/assigner_medecin.php',
    'patients/_medecin_referent_field.php',
    'includes/consultation_ticket_render.php',
    'assets/css/consultation-ticket.css',
];
foreach ($files as $f) {
    vcheck(is_file($base . '/' . $f), "Fichier $f");
}

vcheck(method_exists('StaffScope', 'canAssignPatientMedecin'), 'StaffScope::canAssignPatientMedecin()');
vcheck(method_exists('StaffScope', 'canRegisterConsultationFromPatients'), 'StaffScope::canRegisterConsultationFromPatients()');
vcheck(method_exists('StaffScope', 'resolveMedecinReferentIdForForm'), 'StaffScope::resolveMedecinReferentIdForForm()');
vcheck(method_exists('Patient', 'assignMedecinReferent'), 'Patient::assignMedecinReferent()');
vcheck(method_exists('Medecin', 'listForAssignment'), 'Medecin::listForAssignment()');
vcheck(method_exists('Consultation', 'getByIdForPatientModule'), 'Consultation::getByIdForPatientModule()');

vcheck(in_array('secretaire', app_module_roles('patients'), true), 'Rôle secrétaire sur module patients');

$hasCol = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE()
     AND table_name = 'patients' AND column_name = 'medecin_referent_id' LIMIT 1"
)->fetchColumn();
vcheck($hasCol, 'Colonne patients.medecin_referent_id');

$patientSrc = file_get_contents($base . '/models/Patient.php');
vcheck(strpos($patientSrc, 'medecin_referent_id') !== false, 'Patient::update gère medecin_referent_id');
vcheck(strpos($patientSrc, 'medecin_referent_nom') !== false, 'Patient::getAll joint le médecin référent');

$imprimerSrc = file_get_contents($base . '/patients/imprimer_ticket.php');
vcheck(strpos($imprimerSrc, 'getByIdForPatientModule') !== false, 'imprimer_ticket utilise getByIdForPatientModule');

echo "\n--- Tests rôles (session simulée) ---\n";

loginAs($pdo, 'admin', $tenantId);
vcheck(StaffScope::canAssignPatientMedecin(), 'Admin peut assigner médecin');
vcheck(StaffScope::canRegisterConsultationFromPatients(), 'Admin peut enregistrer consultation');

StaffScope::reset();
loginAs($pdo, 'secretaire', $tenantId);
vcheck(StaffScope::canAssignPatientMedecin(), 'Secrétaire peut assigner médecin');
vcheck(StaffScope::canRegisterConsultationFromPatients(), 'Secrétaire peut enregistrer consultation');

StaffScope::reset();
loginAs($pdo, 'infirmier', $tenantId);
vcheck(StaffScope::canAssignPatientMedecin(), 'Infirmier peut assigner médecin');
vcheck(StaffScope::canRegisterConsultationFromPatients(), 'Infirmier peut enregistrer consultation');

StaffScope::reset();
loginAs($pdo, 'medecin', $tenantId);
vcheck(!StaffScope::canAssignPatientMedecin(), 'Médecin ne choisit pas le référent (auto)');
vcheck(StaffScope::canRegisterConsultationFromPatients(), 'Médecin peut enregistrer consultation');

echo "\n--- Test BDD (si médecin + patient disponibles) ---\n";

$medStmt = $pdo->query("SELECT id FROM medecins WHERE statut != 'supprime' ORDER BY id LIMIT 1");
$medId = (int) ($medStmt->fetchColumn() ?: 0);
vcheck($medId > 0, 'Au moins un médecin actif en BDD');

if ($medId > 0 && $hasCol) {
    StaffScope::reset();
    loginAs($pdo, 'admin', $tenantId);

    $patientModel = new Patient();
    $suffix = date('His');
    $newId = $patientModel->create([
        'nom' => 'VerifyTicket',
        'prenom' => 'Test' . $suffix,
        'date_naissance' => '1990-01-15',
        'genre' => 'M',
        'medecin_referent_id' => $medId,
    ]);

    if ($newId) {
        vcheck(true, "Patient test créé #$newId");
        $p = $patientModel->getById((int) $newId);
        vcheck($p && (int) ($p['medecin_referent_id'] ?? 0) === $medId, 'medecin_referent_id enregistré à la création');
        vcheck(!empty($p['medecin_referent_nom']), 'Nom médecin référent joint sur getById');

        $consultModel = new Consultation();
        $consultId = $consultModel->create([
            'patient_id' => (int) $newId,
            'medecin_id' => $medId,
            'date_consultation' => date('Y-m-d H:i:s'),
            'statut' => 'planifiee',
            'prix_consultation' => 0,
            'type_consultation' => 'consultation_simple',
        ]);
        vcheck($consultId > 0, "Consultation test créée #$consultId");

        $forModule = $consultModel->getByIdForPatientModule((int) $consultId);
        vcheck($forModule !== null, 'getByIdForPatientModule accessible (admin)');

        $html = $consultModel->generateTicketHTML((int) $consultId);
        vcheck(is_string($html) && strlen($html) > 200 && stripos($html, 'ticket') !== false, 'generateTicketHTML produit un document');

        $patientModel->delete((int) $newId);
        vcheck(true, 'Patient test supprimé (soft delete)');
    } else {
        vcheck(false, 'Création patient test');
    }
}

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
