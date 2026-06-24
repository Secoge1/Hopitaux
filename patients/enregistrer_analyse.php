<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('patients'));

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../models/Analyse.php';
require_once __DIR__ . '/../models/TarifAnalyseLaboratoire.php';
require_once __DIR__ . '/../includes/staff_scope.php';

if (!StaffScope::canRegisterAnalyseFromPatients()) {
    $_SESSION['flash_message'] = 'Vous n\'êtes pas autorisé à enregistrer une analyse.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: index.php');
    exit;
}

$patientModel = new Patient();
$medecinModel = new Medecin();
$analyseModel = new Analyse();
$typesAnalyses = $analyseModel->getTypesAnalyses();
$prixParType = $analyseModel->getPrixParType();
$firstType = array_key_first($typesAnalyses) ?: 'sang';
$defaultPrix = (float) ($prixParType[$firstType] ?? TarifAnalyseLaboratoire::getPrixForCode($firstType));

$patientId = isset($_REQUEST['patient_id']) ? (int) $_REQUEST['patient_id'] : 0;
$patient = $patientId ? $patientModel->getById($patientId) : null;

if ($patientId && !$patient) {
    header('Location: index.php');
    exit;
}

$medecins = $medecinModel->listForAssignment();
$ctx = StaffScope::context();
$defaultMedecinId = $patient ? (int) ($patient['medecin_referent_id'] ?? 0) : 0;
if ($ctx['role'] === 'medecin' && !empty($ctx['medecin_id'])) {
    $defaultMedecinId = (int) $ctx['medecin_id'];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pid = (int) ($_POST['patient_id'] ?? 0);
        if (!$patientModel->getById($pid)) {
            throw new RuntimeException('Patient introuvable.');
        }

        $medecinId = (int) ($_POST['medecin_id'] ?? 0);
        if ($ctx['role'] === 'medecin' && !empty($ctx['medecin_id'])) {
            $medecinId = (int) $ctx['medecin_id'];
        } elseif ($medecinId <= 0) {
            throw new RuntimeException('Veuillez sélectionner un médecin.');
        } else {
            $valid = StaffScope::resolveMedecinReferentIdForForm($medecinId);
            if (!$valid) {
                throw new RuntimeException('Médecin invalide.');
            }
            $medecinId = $valid;
        }

        $typePost = (string) ($_POST['type_analyse'] ?? '');
        if ($typePost === '__autre__') {
            $typeAutre = trim((string) ($_POST['type_analyse_autre'] ?? ''));
            if ($typeAutre === '') {
                throw new RuntimeException('Veuillez préciser le type d\'analyse lorsque « Autre (préciser) » est sélectionné.');
            }
            $typeAnalyse = TarifAnalyseLaboratoire::normalizeCode($typeAutre);
            if ($typeAnalyse === '') {
                throw new RuntimeException('Le type d\'analyse précisé est invalide.');
            }
        } else {
            $typeAnalyse = TarifAnalyseLaboratoire::normalizeCode($typePost);
            if ($typeAnalyse === '' || !isset($typesAnalyses[$typeAnalyse])) {
                throw new RuntimeException('Type d\'analyse invalide.');
            }
        }

        $prixFromPost = null;
        if (isset($_POST['prix_analyse']) && trim((string) $_POST['prix_analyse']) !== '') {
            $prixFromPost = (float) $_POST['prix_analyse'];
        }
        $prixFromTarif = $typePost === '__autre__'
            ? 0.0
            : (float) TarifAnalyseLaboratoire::getPrixForCode($typeAnalyse);

        if ($typePost === '__autre__') {
            $prixAnalyse = $prixFromPost ?? 0.0;
        } elseif ($prixFromPost !== null && $prixFromPost > 0) {
            $prixAnalyse = $prixFromPost;
        } elseif ($prixFromTarif > 0) {
            $prixAnalyse = $prixFromTarif;
        } else {
            $prixAnalyse = $prixFromPost ?? 0.0;
        }

        if ($prixAnalyse < 0) {
            throw new RuntimeException('Le prix de l\'analyse est invalide.');
        }
        if ($typePost === '__autre__' && $prixAnalyse <= 0) {
            throw new RuntimeException('Veuillez saisir le prix pour un type d\'analyse personnalisé.');
        }

        $data = [
            'patient_id' => $pid,
            'medecin_id' => $medecinId,
            'type_analyse' => $typeAnalyse,
            'priorite' => 'normale',
            'description' => trim($_POST['description'] ?? '') ?: null,
            'prix_analyse' => $prixAnalyse,
            'statut' => 'en_attente',
        ];
        $tid = StaffScope::technicienIdForAnalyseForm(null);
        if ($tid) {
            $data['technicien_id'] = $tid;
        }

        $analyseId = $analyseModel->create($data);
        if (!$analyseId) {
            throw new RuntimeException('Erreur lors de la création de l\'analyse.');
        }

        $print = !empty($_POST['imprimer_ticket']);
        $return = urlencode(app_url('patients/ticket_caisse.php?patient_id=' . $pid . '&type=analyse'));
        $url = 'ticket_thermique_labo.php?analyse_id=' . (int) $analyseId
            . '&return=' . $return
            . ($print ? '&print=1' : '');
        header('Location: ' . $url);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $patientId = (int) ($_POST['patient_id'] ?? $patientId);
    }
}

$qs = ['type=analyse'];
if ($patientId > 0) {
    $qs[] = 'patient_id=' . $patientId;
}
if ($error !== '') {
    $qs[] = 'error=' . urlencode($error);
}
header('Location: ticket_caisse.php?' . implode('&', $qs));
exit;
