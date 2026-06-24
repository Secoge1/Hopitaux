<?php
/**
 * API suggestions patients — authentifiée, filtrée par tenant.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';

module_api_guard('patients');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    module_api_json([]);
}

try {
    require_once __DIR__ . '/../models/Patient.php';
    $patientModel = new Patient();
    $patients = $patientModel->search($query, 10);

    $suggestions = [];
    foreach ($patients as $patient) {
        $suggestions[] = [
            'id' => $patient['id'],
            'numero_dossier' => $patient['numero_dossier'],
            'nom' => $patient['nom'],
            'prenom' => $patient['prenom'],
            'nom_complet' => $patient['prenom'] . ' ' . $patient['nom'],
            'telephone' => $patient['telephone'] ?? '',
            'age' => $patient['age'] ?? null,
            'statut' => $patient['statut'],
        ];
    }

    module_api_json($suggestions);
} catch (Exception $e) {
    module_api_json(['error' => $e->getMessage()], 500);
}
