<?php
/**
 * API suggestions consultations — authentifiée, filtrée par tenant.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../models/Consultation.php';

module_api_guard('consultations');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    module_api_json([]);
}

try {
    $consultationModel = new Consultation();
    $consultations = $consultationModel->getAll(1, 10, $query);

    $suggestions = [];
    foreach ($consultations as $consultation) {
        $suggestions[] = [
            'id' => $consultation['id'],
            'date_consultation' => $consultation['date_consultation'],
            'patient_nom_complet' => ($consultation['patient_prenom'] ?? '') . ' ' . ($consultation['patient_nom'] ?? ''),
            'patient_numero_dossier' => $consultation['numero_dossier'] ?? '',
            'medecin_nom_complet' => medecin_profil_format_joined($consultation),
            'medecin_specialite' => $consultation['medecin_specialite'] ?? '',
            'type_consultation' => $consultation['type_consultation'] ?? '',
            'symptomes' => !empty($consultation['symptomes']) ? substr($consultation['symptomes'], 0, 50) : null,
            'prix' => $consultation['prix_consultation'] ?? 0,
            'statut' => $consultation['statut'] ?? '',
            'hospitalisation' => $consultation['hospitalisation_requise'] ?? false,
        ];
    }

    module_api_json($suggestions);
} catch (Exception $e) {
    module_api_json(['error' => $e->getMessage()], 500);
}
