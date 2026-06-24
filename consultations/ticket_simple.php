<?php
/**
 * Version simplifiée de ticket.php pour tester
 */

require_once __DIR__ . '/../includes/init.php';
$auth = Auth::getInstance();
$auth->requireAuth();

require_once __DIR__ . '/../models/Consultation.php';

echo "=== TEST TICKET SIMPLE ===\n";

$consultationModel = new Consultation();

$consultation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
echo "Consultation ID : $consultation_id\n";

if (!$consultation_id) {
    echo "❌ Aucun ID de consultation fourni\n";
    exit;
}

$consultation = $consultationModel->getById($consultation_id);
if (!$consultation) {
    echo "❌ Consultation non trouvée\n";
    exit;
}

echo "✓ Consultation trouvée : " . $consultation['patient_prenom'] . " " . $consultation['patient_nom'] . "\n";
echo "✓ Numéro de ticket : " . $consultation['numero_ticket'] . "\n";
echo "✓ Prix : " . $consultation['prix_consultation'] . " FCFA\n";

// Test de génération HTML
$ticket_html = $consultationModel->generateTicketHTML($consultation_id);
if ($ticket_html) {
    echo "✓ HTML généré (" . strlen($ticket_html) . " caractères)\n";
} else {
    echo "❌ Erreur génération HTML\n";
}

echo "=== TEST TERMINÉ ===\n";
?>

















