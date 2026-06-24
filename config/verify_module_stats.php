<?php
/**
 * Vérifie la cohérence des compteurs (dashboard + modules).
 * Usage CLI : php config/verify_module_stats.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/saas/DashboardStats.php';
require_once __DIR__ . '/../includes/saas/TenantContext.php';

TenantContext::bindFromSession();
$tenantId = TenantContext::getTenantId();

$results = [];
$ok = 0;
$fail = 0;
$warn = 0;

function check(string $label, $expected, $actual, string $note = ''): void
{
    global $results, $ok, $fail;
    $match = (string) $expected === (string) $actual;
    $results[] = [
        'label' => $label,
        'expected' => $expected,
        'actual' => $actual,
        'status' => $match ? 'OK' : 'FAIL',
        'note' => $note,
    ];
    if ($match) {
        $ok++;
    } else {
        $fail++;
    }
}

try {
    require_once __DIR__ . '/../models/Patient.php';
    require_once __DIR__ . '/../models/Medecin.php';
    require_once __DIR__ . '/../models/Consultation.php';
    require_once __DIR__ . '/../models/RendezVous.php';
    require_once __DIR__ . '/../models/Analyse.php';
    require_once __DIR__ . '/../models/Paiement.php';

    $patientModel = new Patient();
    $medecinModel = new Medecin();
    $consultationModel = new Consultation();
    $rdvModel = new RendezVous();
    $analyseModel = new Analyse();
    $paiementModel = new Paiement();

    $dash = DashboardStats::get();
    $pStats = $patientModel->getStats();
    $mStats = $medecinModel->getStats();
    $cStats = $consultationModel->getStats();
    $rStats = $rdvModel->getStats();
    $aStats = $analyseModel->getStats();
    $payStats = $paiementModel->getStats();

    check('Dashboard patients = module total', $pStats['total'] ?? 0, $dash['patients'] ?? 0);
    check('Patients getCount = getStats total', $pStats['total'] ?? 0, $patientModel->getCount('', ''));
    check('Dashboard médecins = module total', $mStats['total'] ?? 0, $dash['medecins_actifs'] ?? 0);
    check('Dashboard consult. jour = module aujourd_hui', $cStats['aujourd_hui'] ?? 0, $dash['consultations_aujourd_hui'] ?? 0);
    check('Dashboard RDV jour = module aujourd_hui', $rStats['aujourd_hui'] ?? 0, $dash['rdv_aujourd_hui'] ?? 0);
    check('Dashboard analyses = module en_cours', $aStats['en_cours'] ?? 0, $dash['analyses_en_cours'] ?? 0);
    check('Dashboard paiements total = module total', $payStats['total'] ?? 0, $dash['paiements_total'] ?? 0);
    check('Consultations getCount = getStats total', $cStats['total'] ?? 0, $consultationModel->getCount('', '', ''));
    check('RDV getCount = getStats total', $rStats['total'] ?? 0, $rdvModel->getCount('', '', ''));
    check('Analyses count = getStats total', $aStats['total'] ?? 0, $analyseModel->count([]));
    check('Paiements count = getStats total', $payStats['total'] ?? 0, $paiementModel->count([]));

    if (!$tenantId) {
        $warn++;
        $results[] = ['label' => 'Tenant context', 'status' => 'WARN', 'note' => 'Aucun tenant_id en session CLI — lancer via navigateur connecté pour test tenant.'];
    }
} catch (Throwable $e) {
    $fail++;
    $results[] = ['label' => 'Exception', 'status' => 'FAIL', 'note' => $e->getMessage()];
}

echo "=== Vérification compteurs SeSanté ===\n";
echo 'Tenant ID : ' . ($tenantId ?: '(non défini)') . "\n\n";

foreach ($results as $r) {
    $status = $r['status'] ?? '?';
    echo "[$status] {$r['label']}";
    if (isset($r['expected'], $r['actual'])) {
        echo " — attendu: {$r['expected']}, obtenu: {$r['actual']}";
    }
    if (!empty($r['note'])) {
        echo " ({$r['note']})";
    }
    echo "\n";
}

echo "\nRésumé : $ok OK | $fail FAIL | $warn WARN\n";
exit($fail > 0 ? 1 : 0);
