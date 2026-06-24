<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/Patient.php';

module_api_guard('paiements');

header('Content-Type: application/json');

$patient_id = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;

if (!$patient_id) {
    echo json_encode(['error' => 'Patient ID requis']);
    exit;
}

$patientModel = new Patient();
if (!$patientModel->getById($patient_id)) {
    http_response_code(403);
    echo json_encode(['error' => 'Patient introuvable pour cet établissement.']);
    exit;
}

try {
    $consultationModel = new Consultation();
    $pdo = getDB();

    $consultations = $consultationModel->getPatientHistory($patient_id, 50);
    $result = [];

    foreach ($consultations as $consultation) {
        if (($consultation['statut'] ?? '') === 'annulee') {
            continue;
        }

        $consultationId = (int) $consultation['id'];
        $where = ['consultation_id = ?'];
        $params = [$consultationId];
        TenantScope::appendWhere($pdo, 'paiements', $where, $params);
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM paiements WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $hasPayment = (int) ($stmt->fetch()['count'] ?? 0) > 0;

        $montant = (float) $consultationModel->getPrixTotalComplet($consultationId);

        $medecinNom = medecin_profil_format_joined($consultation);
        $dateObj = strtotime($consultation['date_consultation']);
        $typeConsult = $consultation['type_consultation'] ?? '';
        $diag = $consultation['diagnostic'] ?? '';
        if ($diag !== '' && $diag !== 'Non spécifié') {
            $len = function_exists('mb_strlen') ? mb_strlen($diag, 'UTF-8') : strlen($diag);
            $diagShort = ($len > 80)
                ? ((function_exists('mb_substr') ? mb_substr($diag, 0, 77, 'UTF-8') : substr($diag, 0, 77)) . '…')
                : $diag;
        } else {
            $diagShort = 'Non spécifié';
        }

        $result[] = [
            'id' => $consultationId,
            'date' => date('d/m/Y à H:i', $dateObj),
            'date_court' => date('d/m/Y', $dateObj),
            'date_raw' => $consultation['date_consultation'],
            'diagnostic' => $diag !== '' ? $diag : 'Non spécifié',
            'diagnostic_short' => $diagShort,
            'symptomes' => $consultation['symptomes'] ?? '',
            'medecin' => $medecinNom,
            'medecin_label' => medecin_profil_attribution_label_from_row($consultation),
            'specialite' => $consultation['medecin_specialite'] ?? '',
            'type_consultation' => $typeConsult,
            'montant' => $montant,
            'deja_payee' => $hasPayment,
            'statut' => $consultation['statut'] ?? 'terminee',
            'numero_ticket' => $consultation['numero_ticket'] ?? '',
        ];
    }

    echo json_encode(['success' => true, 'consultations' => $result]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
