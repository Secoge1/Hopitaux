<?php
require_once '../config/config.php';
require_once '../models/Analyse.php';

// Vérifier que l'ID de l'analyse est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID de l\'analyse manquant');
}

$analyse_id = (int)$_GET['id'];
$print_mode = isset($_GET['print']);

try {
    // Créer une instance de la classe Analyse
    $analyseModel = new Analyse();
    
    // Récupérer les données de l'analyse
    $analyse = $analyseModel->getById($analyse_id);
    
    if (!$analyse) {
        die('Analyse non trouvée');
    }
    
    // Générer le HTML du ticket
    $ticketHTML = $analyseModel->generateTicketHTML($analyse_id);
    
    // Styles d'impression pour forcer une seule page A4
    $printStyles = <<<CSS
<style>
@media print {
    @page {
        size: A4;
        margin: 0.5cm;
    }
    
    body {
        margin: 0;
        padding: 0;
        font-size: 10px;
        line-height: 1.2;
    }
    
    .print-controls {
        display: none !important;
    }
    
    .ticket-container {
        page-break-inside: avoid;
        max-height: 25cm;
        overflow: hidden;
    }
    
    .ticket-header,
    .ticket-content,
    .ticket-footer {
        page-break-inside: avoid;
    }
    
    .header {
        padding-bottom: 5px !important;
        margin-bottom: 8px !important;
        border-bottom-width: 1px !important;
    }
    
    .header .logo {
        margin-bottom: 5px !important;
    }
    
    .header .logo img {
        max-height: 40px !important;
        max-width: 180px !important;
        width: auto !important;
        height: auto !important;
        object-fit: contain !important;
    }
    
    .header h1 {
        font-size: 14px !important;
        margin: 3px 0 !important;
    }
    
    .header h2 {
        font-size: 12px !important;
        margin: 2px 0 !important;
    }
    
    .header h3 {
        font-size: 11px !important;
        margin: 2px 0 !important;
    }
    
    .header p {
        font-size: 9px !important;
        margin: 2px 0 !important;
    }
    
    .info-section {
        margin-bottom: 6px !important;
    }
    
    .info-section h3 {
        font-size: 10px !important;
        margin-bottom: 4px !important;
    }
    
    .info-row {
        margin-bottom: 2px !important;
        font-size: 9px !important;
    }
    
    .label {
        font-size: 9px !important;
    }
    
    .value {
        font-size: 9px !important;
    }
    
    .total {
        font-size: 12px !important;
        padding-top: 5px !important;
        margin-top: 8px !important;
        border-top-width: 1px !important;
    }
    
    p {
        font-size: 9px !important;
        margin: 3px 0 !important;
    }
    
    * {
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
}

@media screen {
    .print-controls {
        position: fixed;
        top: 10px;
        right: 10px;
        z-index: 9999;
        background: white;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .print-controls button {
        padding: 8px 12px;
        margin-right: 8px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }
    
    .print-controls button:hover {
        background: #0056b3;
    }
    
    .print-controls button:last-child {
        margin-right: 0;
        background: #dc3545;
    }
    
    .print-controls button:last-child:hover {
        background: #c82333;
    }
}
</style>
CSS;

    // Contrôles écran (masqués à l'impression)
    $controls = <<<HTML
<div class="print-controls">
    <button onclick="window.print()">🖨️ Imprimer</button>
    <button onclick="window.close()">✖ Fermer</button>
</div>
HTML;

    // Injecter les styles d'impression et les contrôles
    $ticketHTML = preg_replace('/<body[^>]*>/', '$0' . $printStyles . $controls, $ticketHTML, 1);
    
    // Afficher le ticket
    echo $ticketHTML;
    
    // Auto-impression si demandée
    if ($print_mode) {
        echo '<script>
            window.addEventListener("load", function () {
                setTimeout(function () { window.print(); }, 350);
            }, { once: true });
        </script>';
    }
    
} catch (Exception $e) {
    die('Erreur lors de la génération du ticket: ' . $e->getMessage());
}
?>