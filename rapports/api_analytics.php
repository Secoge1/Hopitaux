<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/ReportAnalyticsIntelligence.php';

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $dateRange = $_GET['date_range'] ?? $_POST['date_range'] ?? 30;
    $reportType = $_GET['report_type'] ?? $_POST['report_type'] ?? 'general';
    $months = $_GET['months'] ?? $_POST['months'] ?? 6;
    
    switch ($action) {
        case 'analyze_trends':
            $trends = ReportAnalyticsIntelligence::analyzeSystemTrends($dateRange);
            jsonResponse($trends);
            
        case 'generate_report':
            $parameters = [
                'type' => $reportType,
                'date_range' => $dateRange,
                'filters' => json_decode($_GET['filters'] ?? $_POST['filters'] ?? '{}', true)
            ];
            
            $report = ReportAnalyticsIntelligence::generateCustomReport($parameters);
            jsonResponse($report);
            
        case 'predict_future':
            $predictions = ReportAnalyticsIntelligence::predictFutureTrends($months);
            jsonResponse($predictions);
            
        case 'detect_anomalies':
            $threshold = $_GET['threshold'] ?? $_POST['threshold'] ?? 2.0;
            $anomalies = ReportAnalyticsIntelligence::detectAnomalies($threshold);
            jsonResponse($anomalies);
            
        default:
            jsonResponse(['error' => 'Action non reconnue'], 400);
    }
    
} catch (Exception $e) {
    error_log("Erreur API analytics rapports: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur interne du serveur'], 500);
}
?>



