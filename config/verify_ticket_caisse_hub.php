<?php
/**
 * Vérification rigoureuse — hub ticket caisse (consultation + analyse laboratoire).
 * Usage : php config/verify_ticket_caisse_hub.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

$base = dirname(__DIR__);
require_once $base . '/config/db.php';
require_once $base . '/includes/saas/TenantSchema.php';
require_once $base . '/includes/thermal_ticket_render.php';
require_once $base . '/includes/thermal_lab_ticket_render.php';
require_once $base . '/models/TarifConsultation.php';
require_once $base . '/models/TarifAnalyseLaboratoire.php';
require_once $base . '/models/Consultation.php';
require_once $base . '/models/Analyse.php';
require_once $base . '/models/Patient.php';
require_once $base . '/includes/staff_scope.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

TenantSchema::ensure();
$_SESSION['user_connected'] = true;
$_SESSION['user_role'] = 'admin';
$_SESSION['user_id'] = 1;
$_SESSION['tenant_id'] = 1;

$ok = 0;
$fail = 0;

function hcheck(bool $cond, string $label): void
{
    global $ok, $fail;
    echo ($cond ? 'OK  ' : 'FAIL ') . "$label\n";
    $cond ? $ok++ : $fail++;
}

echo "=== Vérification rigoureuse — hub Ticket Caisse ===\n\n";

$requiredFiles = [
    'patients/ticket_caisse.php',
    'patients/enregistrer_consultation.php',
    'patients/enregistrer_analyse.php',
    'patients/ticket_thermique.php',
    'patients/ticket_thermique_labo.php',
    'patients/api_imprimer_thermique.php',
    'patients/api_imprimer_thermique_labo.php',
    'includes/thermal_lab_ticket_render.php',
];
foreach ($requiredFiles as $f) {
    hcheck(is_file($base . '/' . $f), "Fichier $f");
}

echo "\n--- Structure & liens UI ---\n";

$listSrc = (string) file_get_contents($base . '/patients/_list_view.php');
hcheck(strpos($listSrc, 'ticket_caisse.php?patient_id=') !== false, 'Liste patients → ticket_caisse.php');

$voirSrc = (string) file_get_contents($base . '/patients/voir.php');
hcheck(strpos($voirSrc, 'ticket_caisse.php?patient_id=') !== false, 'Fiche patient → ticket_caisse.php');

$hubSrc = (string) file_get_contents($base . '/patients/ticket_caisse.php');
hcheck(strpos($hubSrc, 'type=consultation') !== false, 'Hub : carte consultation');
hcheck(strpos($hubSrc, 'type=analyse') !== false, 'Hub : carte analyse');
hcheck(strpos($hubSrc, 'action="enregistrer_consultation.php"') !== false, 'Hub : form POST consultation');
hcheck(strpos($hubSrc, 'action="enregistrer_analyse.php"') !== false, 'Hub : form POST analyse');
hcheck(strpos($hubSrc, 'name="prix_consultation"') !== false, 'Hub : champ prix consultation');
hcheck(strpos($hubSrc, 'name="prix_analyse"') !== false, 'Hub : champ prix analyse');
hcheck(strpos($hubSrc, '$patient ? (int) ($patient[\'medecin_referent_id\']') !== false, 'Hub : medecin_referent_id protégé si patient null');

$consPostSrc = (string) file_get_contents($base . '/patients/enregistrer_consultation.php');
hcheck(strpos($consPostSrc, 'getByTypeAndSpecialite') !== false, 'POST consultation : tarif serveur');
hcheck(strpos($consPostSrc, 'ticket_caisse.php') !== false, 'POST consultation : retour hub');
hcheck(strpos($consPostSrc, 'ticket_thermique.php') !== false, 'POST consultation : redirect thermique');

$anaPostSrc = (string) file_get_contents($base . '/patients/enregistrer_analyse.php');
hcheck(strpos($anaPostSrc, 'TarifAnalyseLaboratoire::getPrixForCode') !== false, 'POST analyse : tarif serveur');
hcheck(strpos($anaPostSrc, 'ticket_thermique_labo.php') !== false, 'POST analyse : redirect thermique labo');

echo "\n--- Permissions ---\n";
hcheck(method_exists('StaffScope', 'canRegisterAnalyseFromPatients'), 'StaffScope::canRegisterAnalyseFromPatients()');
hcheck(StaffScope::canRegisterConsultationFromPatients(), 'Admin peut enregistrer consultation');
hcheck(StaffScope::canRegisterAnalyseFromPatients(), 'Admin peut enregistrer analyse');

echo "\n--- Rendu tickets thermiques ---\n";

$sys = SystemParameters::getInstance();

$fakeCons = [
    'consultation' => [
        'id' => 1, 'numero_ticket' => 'CONS202601010001',
        'patient_prenom' => 'Awa', 'patient_nom' => 'Diallo',
        'medecin_prenom' => 'Moussa', 'medecin_nom' => 'Keita',
        'date_consultation' => date('Y-m-d H:i:s'),
        'prix_consultation' => 10000,
    ],
    'total_general' => 10000, 'total_soins' => 0, 'total_hospitalisation' => 0,
    'soins' => [], 'sejours' => [], 'system' => $sys,
];
$escCons = thermal_ticket_build_escpos($fakeCons);
$htmlCons = thermal_ticket_render_html($fakeCons, false);
hcheck(stripos($escCons, 'TICKET CONSULTATION') !== false, 'Consultation ESC/POS : en-tête');
hcheck(stripos($escCons, 'TOTAL A PAYER') !== false, 'Consultation ESC/POS : total');
hcheck(stripos($escCons, 'caisse') !== false, 'Consultation ESC/POS : message caisse');
hcheck(strpos($htmlCons, 'Consultation :') !== false, 'Consultation HTML : ligne prix');

$fakeLab = [
    'analyse' => [
        'id' => 2, 'numero_ticket' => 'ANAL202601010001',
        'patient_prenom' => 'Awa', 'patient_nom' => 'Diallo',
        'medecin_prenom' => 'Moussa', 'medecin_nom' => 'Keita',
        'type_analyse' => 'sang', 'date_creation' => date('Y-m-d H:i:s'),
        'prix_analyse' => 5000, 'description' => 'Bilan',
    ],
    'type_label' => 'Analyse sanguine',
    'total_general' => 5000,
    'system' => $sys,
];
hcheck(function_exists('thermal_lab_ticket_build_escpos'), 'thermal_lab_ticket_build_escpos()');
$escLab = thermal_lab_ticket_build_escpos($fakeLab);
$htmlLab = thermal_lab_ticket_render_html($fakeLab, false);
hcheck(stripos($escLab, 'TICKET LABORATOIRE') !== false, 'Labo ESC/POS : en-tête');
hcheck(stripos($escLab, 'Analyse :') !== false, 'Labo ESC/POS : ligne prix');
hcheck(stripos($escLab, 'TOTAL A PAYER') !== false, 'Labo ESC/POS : total');
hcheck(strpos($htmlLab, 'TICKET LABORATOIRE') !== false, 'Labo HTML : en-tête');
hcheck(strpos($htmlLab, '5') !== false, 'Labo HTML : montant visible');

echo "\n--- Intégration BDD ---\n";

$pdo = getDB();
$medId = (int) ($pdo->query("SELECT id FROM medecins WHERE statut != 'supprime' LIMIT 1")->fetchColumn() ?: 0);
hcheck($medId > 0, 'Médecin actif disponible');

if ($medId > 0) {
    $pm = new Patient();
    $pid = $pm->create([
        'nom' => 'VerifyHub',
        'prenom' => 'T' . date('His'),
        'date_naissance' => '1990-06-15',
        'genre' => 'F',
        'medecin_referent_id' => $medId,
    ]);

    if ($pid) {
        hcheck(true, "Patient test #$pid créé");

        $cm = new Consultation();
        $cid = $cm->create([
            'patient_id' => (int) $pid,
            'medecin_id' => $medId,
            'date_consultation' => date('Y-m-d H:i:s'),
            'statut' => 'planifiee',
            'prix_consultation' => 12000,
            'type_consultation' => 'consultation_simple',
        ]);
        if ($cid) {
            $dCons = thermal_ticket_load_data($cm, (int) $cid);
            hcheck($dCons !== null, 'thermal_ticket_load_data() consultation BDD');
            hcheck((float) ($dCons['total_general'] ?? 0) === 12000.0, 'Total consultation BDD = 12000');
            hcheck(!empty($dCons['consultation']['numero_ticket']), 'numero_ticket consultation généré');
        } else {
            hcheck(false, 'Création consultation test');
        }

        $am = new Analyse();
        $aid = $am->create([
            'patient_id' => (int) $pid,
            'medecin_id' => $medId,
            'type_analyse' => 'sang',
            'prix_analyse' => 5000,
            'statut' => 'en_attente',
        ]);
        if ($aid) {
            $dLab = thermal_lab_ticket_load_data($am, (int) $aid);
            hcheck($dLab !== null, 'thermal_lab_ticket_load_data() analyse BDD');
            hcheck((float) ($dLab['total_general'] ?? 0) === 5000.0, 'Total analyse BDD = 5000');
            hcheck(!empty($dLab['analyse']['numero_ticket']), 'numero_ticket analyse généré');
            hcheck(strpos((string) $dLab['analyse']['numero_ticket'], 'ANAL') === 0, 'Préfixe ticket ANAL');
        } else {
            hcheck(false, 'Création analyse test');
        }

        // Prix vide → fallback tarif
        $aid2 = $am->create([
            'patient_id' => (int) $pid,
            'medecin_id' => $medId,
            'type_analyse' => 'urine',
            'prix_analyse' => '',
            'statut' => 'en_attente',
        ]);
        if ($aid2) {
            $row = $am->getById((int) $aid2);
            $expected = TarifAnalyseLaboratoire::getPrixForCode('urine');
            hcheck($row && (float) $row['prix_analyse'] === (float) $expected, 'Analyse::create fallback prix vide');
        }

        $pm->delete((int) $pid);
        hcheck(true, 'Patient test supprimé');
    } else {
        hcheck(false, 'Création patient test');
    }
}

echo "\n--- Syntaxe PHP ---\n";
foreach ($requiredFiles as $f) {
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($base . '/' . $f) . ' 2>&1', $out, $code);
    hcheck($code === 0, 'Syntaxe ' . $f);
}

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
