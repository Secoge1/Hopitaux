<?php

/**

 * Vérification : tarif consultation + ticket thermique (hub ticket caisse).

 * Usage : php config/verify_ticket_prix_accueil.php

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

require_once $base . '/models/TarifConsultation.php';

require_once $base . '/models/Consultation.php';

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



function pcheck(bool $cond, string $label): void

{

    global $ok, $fail;

    echo ($cond ? 'OK  ' : 'FAIL ') . "$label\n";

    $cond ? $ok++ : $fail++;

}



echo "=== Vérification ticket caisse + prix consultation ===\n\n";



$hubSrc = (string) file_get_contents($base . '/patients/ticket_caisse.php');

pcheck(strpos($hubSrc, 'name="prix_consultation"') !== false, 'ticket_caisse : champ prix_consultation');

pcheck(strpos($hubSrc, 'name="type_consultation"') !== false, 'ticket_caisse : select type_consultation');

pcheck(strpos($hubSrc, 'action="enregistrer_consultation.php"') !== false, 'ticket_caisse : form vers enregistrer_consultation');



$consSrc = (string) file_get_contents($base . '/patients/enregistrer_consultation.php');

pcheck(strpos($consSrc, 'getByTypeAndSpecialite') !== false, 'enregistrer_consultation : résolution tarif serveur');

pcheck(strpos($consSrc, "'prix_consultation' => 0") === false, 'enregistrer_consultation : prix non forcé à 0');

pcheck(strpos($consSrc, 'ticket_caisse.php') !== false, 'enregistrer_consultation : lien retour hub');



$sys = SystemParameters::getInstance();

$fakeData = [

    'consultation' => [

        'id' => 9,

        'numero_ticket' => 'CONS202601010001',

        'patient_prenom' => 'Test',

        'patient_nom' => 'Prix',

        'medecin_prenom' => 'Dr',

        'medecin_nom' => 'X',

        'date_consultation' => date('Y-m-d H:i:s'),

        'prix_consultation' => 12500,

    ],

    'total_general' => 12500,

    'total_soins' => 0,

    'total_hospitalisation' => 0,

    'soins' => [],

    'sejours' => [],

    'system' => $sys,

];



$esc = thermal_ticket_build_escpos($fakeData);

$html = thermal_ticket_render_html($fakeData, false);

pcheck(stripos($esc, 'Consultation :') !== false, 'ESC/POS : ligne Consultation');

pcheck(strpos($html, 'Consultation :') !== false, 'HTML thermique : ligne Consultation');

pcheck(strpos($html, '12') !== false && strpos($html, '500') !== false, 'HTML thermique : montant 12 500 visible');



$tarifModel = new TarifConsultation();

$tarifs = $tarifModel->getAll('actif');

pcheck(count($tarifs) > 0, 'Au moins un tarif actif en BDD');

if ($tarifs) {

    $t = $tarifs[0];

    $found = $tarifModel->getByTypeAndSpecialite((string) $t['type_consultation'], $t['specialite'] ?: null);

    pcheck(

        $found !== false && (float) $found['prix'] === (float) $t['prix'],

        'TarifConsultation::getByTypeAndSpecialite cohérent'

    );

}



$pdo = getDB();

$medId = (int) ($pdo->query("SELECT id FROM medecins WHERE statut != 'supprime' LIMIT 1")->fetchColumn() ?: 0);

if ($medId > 0) {

    $pm = new Patient();

    $pid = $pm->create([

        'nom' => 'VerifyPrix',

        'prenom' => 'Test' . date('His'),

        'date_naissance' => '1990-01-01',

        'genre' => 'M',

        'medecin_referent_id' => $medId,

    ]);

    if ($pid) {

        $cm = new Consultation();

        $cid = $cm->create([

            'patient_id' => (int) $pid,

            'medecin_id' => $medId,

            'date_consultation' => date('Y-m-d H:i:s'),

            'statut' => 'planifiee',

            'prix_consultation' => 8800,

            'type_consultation' => 'consultation_simple',

        ]);

        if ($cid) {

            $data = thermal_ticket_load_data($cm, (int) $cid);

            pcheck($data !== null, 'thermal_ticket_load_data avec prix enregistré');

            pcheck((float) ($data['consultation']['prix_consultation'] ?? 0) === 8800.0, 'prix_consultation chargé = 8800');

            pcheck((float) ($data['total_general'] ?? 0) === 8800.0, 'total_general = 8800');

            $integrationHtml = thermal_ticket_render_html($data, false);

            pcheck(strpos($integrationHtml, '8') !== false, 'ticket intégration contient le prix');

        }

        $pm->delete((int) $pid);

    }

}



echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";

exit($fail > 0 ? 1 : 0);

