<?php
/**
 * Traitement POST — création consultation + redirection ticket thermique.
 * Formulaire affiché sur ticket_caisse.php?type=consultation
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('patients'));

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/TarifConsultation.php';
require_once __DIR__ . '/../includes/staff_scope.php';

if (!StaffScope::canRegisterConsultationFromPatients()) {
    $_SESSION['flash_message'] = 'Vous n\'êtes pas autorisé à enregistrer une consultation.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $patientId = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
    $qs = $patientId ? 'patient_id=' . $patientId . '&type=consultation' : '';
    header('Location: ticket_caisse.php' . ($qs ? '?' . $qs : ''));
    exit;
}

$patientModel = new Patient();
$medecinModel = new Medecin();
$consultationModel = new Consultation();
$tarifModel = new TarifConsultation();
$ctx = StaffScope::context();

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

    if (StaffScope::canAssignPatientMedecin() && !empty($_POST['assign_referent'])) {
        $patientModel->assignMedecinReferent($pid, $medecinId);
    }

    $typePost = trim((string) ($_POST['type_consultation'] ?? 'consultation_simple'));
    if ($typePost === '__autre__') {
        $typeAutre = trim((string) ($_POST['type_consultation_autre'] ?? ''));
        if ($typeAutre === '') {
            throw new RuntimeException('Veuillez préciser le type de consultation lorsque « Autre (préciser) » est sélectionné.');
        }
        $typeConsultation = preg_replace('/\s+/', '_', mb_strtolower($typeAutre, 'UTF-8'));
    } elseif (in_array($typePost, ['consultation_simple', 'consultation_specialisee', 'urgence', 'controle'], true)) {
        $typeConsultation = $typePost;
    } else {
        $typeConsultation = 'consultation_simple';
    }

    $medecin = $medecinModel->getById($medecinId);
    $specialite = trim((string) ($medecin['specialite'] ?? ''));
    $tarif = null;
    if ($typePost !== '__autre__') {
        $tarif = $specialite !== ''
            ? $tarifModel->getByTypeAndSpecialite($typeConsultation, $specialite)
            : null;
        if (!$tarif) {
            $tarif = $tarifModel->getByTypeAndSpecialite($typeConsultation, null);
        }
    }

    $prixFromPost = null;
    if (isset($_POST['prix_consultation']) && trim((string) $_POST['prix_consultation']) !== '') {
        $prixFromPost = (float) $_POST['prix_consultation'];
    }
    $prixFromTarif = $tarif ? (float) $tarif['prix'] : 0.0;

    if ($typePost === '__autre__') {
        $prixConsultation = $prixFromPost ?? 0.0;
    } elseif ($prixFromPost !== null && $prixFromPost > 0) {
        $prixConsultation = $prixFromPost;
    } elseif ($prixFromTarif > 0) {
        $prixConsultation = $prixFromTarif;
    } else {
        $prixConsultation = $prixFromPost ?? 0.0;
    }

    if ($prixConsultation < 0) {
        throw new RuntimeException('Le prix de la consultation est invalide.');
    }
    if ($typePost === '__autre__' && $prixConsultation <= 0) {
        throw new RuntimeException('Veuillez saisir le prix pour un type de consultation personnalisé.');
    }

    $consultationId = $consultationModel->create([
        'patient_id' => $pid,
        'medecin_id' => $medecinId,
        'date_consultation' => date('Y-m-d H:i:s'),
        'symptomes' => trim($_POST['symptomes'] ?? '') ?: null,
        'statut' => 'planifiee',
        'prix_consultation' => $prixConsultation,
        'type_consultation' => $typeConsultation,
        'hospitalisation_requise' => 0,
    ]);

    if (!$consultationId) {
        throw new RuntimeException('Erreur lors de la création de la consultation.');
    }

    $print = !empty($_POST['imprimer_ticket']);
    $return = urlencode(app_url('patients/ticket_caisse.php?patient_id=' . (int) $pid . '&type=consultation'));
    $url = 'ticket_thermique.php?consultation_id=' . (int) $consultationId
        . '&return=' . $return
        . ($print ? '&print=1' : '');
    header('Location: ' . $url);
    exit;
} catch (Throwable $e) {
    $pid = (int) ($_POST['patient_id'] ?? 0);
    $qs = 'type=consultation&error=' . urlencode($e->getMessage());
    if ($pid > 0) {
        $qs = 'patient_id=' . $pid . '&' . $qs;
    }
    header('Location: ticket_caisse.php?' . $qs);
    exit;
}
