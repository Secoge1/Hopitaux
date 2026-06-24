<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../models/RendezVous.php';

module_api_guard('rdv');

header('Content-Type: application/json');

$rdvModel = new RendezVous();

$start = $_GET['start'] ?? date('Y-m-d');
$end = $_GET['end'] ?? date('Y-m-d', strtotime('+1 month'));

try {
    $rdvs = $rdvModel->getByDateRange($start, $end);
    
    $events = [];
    foreach ($rdvs as $rdv) {
        // Gérer la récurrence
        if ($rdv['recurrence'] && $rdv['recurrence'] !== 'aucune') {
            $recurring_events = generateRecurringEvents($rdv, $start, $end);
            $events = array_merge($events, $recurring_events);
        } else {
            $events[] = [
                'id' => $rdv['id'],
                'patient_nom' => $rdv['patient_nom'] ?? '',
                'patient_prenom' => $rdv['patient_prenom'] ?? '',
                'date_rdv' => $rdv['date_rdv'],
                'heure_rdv' => $rdv['heure_rdv'],
                'statut' => $rdv['statut'],
                'recurrence' => $rdv['recurrence'] ?? 'aucune',
                'motif' => $rdv['motif'] ?? ''
            ];
        }
    }
    
    echo json_encode($events);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function generateRecurringEvents($rdv, $start, $end) {
    $events = [];
    $start_date = new DateTime($rdv['date_rdv']);
    $end_recurrence = $rdv['date_fin_recurrence'] ? new DateTime($rdv['date_fin_recurrence']) : new DateTime($end);
    $range_start = new DateTime($start);
    $range_end = new DateTime($end);
    
    $current = clone $start_date;
    
    while ($current <= $end_recurrence && $current <= $range_end) {
        if ($current >= $range_start) {
            $events[] = [
                'id' => $rdv['id'] . '_' . $current->format('Y-m-d'),
                'patient_nom' => $rdv['patient_nom'] ?? '',
                'patient_prenom' => $rdv['patient_prenom'] ?? '',
                'date_rdv' => $current->format('Y-m-d'),
                'heure_rdv' => $rdv['heure_rdv'],
                'statut' => $rdv['statut'],
                'recurrence' => $rdv['recurrence'],
                'motif' => $rdv['motif'] ?? ''
            ];
        }
        
        switch ($rdv['recurrence']) {
            case 'quotidien':
                $current->modify('+1 day');
                break;
            case 'hebdomadaire':
                $current->modify('+1 week');
                break;
            case 'mensuel':
                $current->modify('+1 month');
                break;
            default:
                break;
        }
    }
    
    return $events;
}

