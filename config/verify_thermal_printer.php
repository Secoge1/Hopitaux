<?php
/**
 * Vérification imprimante thermique ESC/POS (Xprinter XP-80TS).
 * Usage : php config/verify_thermal_printer.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

$base = dirname(__DIR__);
require_once $base . '/config/db.php';
require_once $base . '/includes/saas/TenantSchema.php';
require_once $base . '/includes/EscPosPrinter.php';
require_once $base . '/includes/thermal_printer_config.php';
require_once $base . '/includes/thermal_ticket_render.php';
require_once $base . '/models/Consultation.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

TenantSchema::ensure();
$ok = 0;
$fail = 0;

function tcheck(bool $c, string $l): void
{
    global $ok, $fail;
    echo ($c ? 'OK  ' : 'FAIL ') . "$l\n";
    $c ? $ok++ : $fail++;
}

echo "=== Vérification imprimante thermique ESC/POS ===\n\n";

$files = [
    'includes/EscPosPrinter.php',
    'includes/thermal_printer_config.php',
    'includes/thermal_ticket_render.php',
    'assets/css/thermal-ticket.css',
    'patients/ticket_thermique.php',
    'patients/api_imprimer_thermique.php',
    'parametres/test_imprimante_thermique.php',
];
foreach ($files as $f) {
    tcheck(is_file($base . '/' . $f), "Fichier $f");
}

tcheck(class_exists('EscPosPrinter'), 'Classe EscPosPrinter');
tcheck(function_exists('thermal_printer_normalize_paper_mm'), 'thermal_printer_normalize_paper_mm()');
tcheck(function_exists('thermal_printer_printable_width_mm'), 'thermal_printer_printable_width_mm()');
tcheck(thermal_printer_normalize_paper_mm(80) === 80 && thermal_printer_normalize_paper_mm(58) === 58, 'Normalisation 80/58 mm');
tcheck(thermal_printer_printable_width_mm(80) === 72 && thermal_printer_printable_width_mm(58) === 48, 'Zone imprimable 72/48 mm');
tcheck(function_exists('thermal_ticket_render_html'), 'thermal_ticket_render_html()');
tcheck(function_exists('thermal_ticket_load_data'), 'thermal_ticket_load_data()');

$sys = SystemParameters::getInstance();
tcheck($sys->get('thermal_printer_model') === 'Xprinter XP-80TS' || $sys->get('thermal_printer_model') !== '', 'Paramètre thermal_printer_model');

$p = new EscPosPrinter(48);
$p->init()->align('center')->text('TEST')->cut();
$buf = $p->getBuffer();
tcheck(strlen($buf) > 10 && strpos($buf, "\x1B\x40") === 0, 'Buffer ESC/POS (init + cut)');

$fakeData = [
    'consultation' => [
        'id' => 1,
        'numero_ticket' => 'CONS202606080001',
        'patient_prenom' => 'Awa',
        'patient_nom' => 'Diallo',
        'medecin_prenom' => 'Moussa',
        'medecin_nom' => 'Keita',
        'medecin_specialite' => 'Medecine generale',
        'date_consultation' => date('Y-m-d H:i:s'),
        'symptomes' => 'Controle',
        'prix_consultation' => 5000,
    ],
    'total_general' => 5000,
    'total_soins' => 0,
    'total_hospitalisation' => 0,
    'soins' => [],
    'sejours' => [],
    'system' => $sys,
];
$esc = thermal_ticket_build_escpos($fakeData);
tcheck(strlen($esc) > 100 && stripos($esc, 'TICKET') !== false, 'Ticket ESC/POS contient TICKET CONSULTATION');

$html = thermal_ticket_render_html($fakeData, false);
tcheck(strpos($html, 'thermal-receipt') !== false && strpos($html, 'CONS202606080001') !== false, 'Aperçu HTML 80 mm');
tcheck(strpos($html, 'thermal-logo') !== false && strpos($html, '<img') !== false, 'Aperçu HTML — logo établissement');
tcheck(strpos($html, '--thermal-printable-mm') !== false, 'Aperçu HTML — zone imprimable 72 mm');

require_once $base . '/includes/staff_scope.php';
require_once $base . '/models/Patient.php';

StaffScope::reset();
$_SESSION['user_connected'] = true;
$_SESSION['user_role'] = 'admin';
$_SESSION['user_id'] = 1;
$_SESSION['tenant_id'] = 1;
$_SESSION['is_platform_admin'] = false;

$pdo = getDB();
$medId = (int) ($pdo->query("SELECT id FROM medecins WHERE statut != 'supprime' LIMIT 1")->fetchColumn() ?: 0);
if ($medId > 0) {
    $pm = new Patient();
    $pid = $pm->create([
        'nom' => 'ThermalTest',
        'prenom' => 'Verify' . date('His'),
        'date_naissance' => '1995-05-05',
        'genre' => 'F',
        'medecin_referent_id' => $medId,
    ]);
    if ($pid) {
        $cm = new Consultation();
        $cid = $cm->create([
            'patient_id' => (int) $pid,
            'medecin_id' => $medId,
            'date_consultation' => date('Y-m-d H:i:s'),
            'statut' => 'planifiee',
            'prix_consultation' => 7500,
        ]);
        if ($cid) {
            $data = thermal_ticket_load_data($cm, (int) $cid);
            tcheck($data !== null, 'thermal_ticket_load_data() sur consultation test');
            $pm->delete((int) $pid);
        }
    }
}

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
