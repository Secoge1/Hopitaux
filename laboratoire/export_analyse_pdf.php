<?php
ob_start();
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/pdf_branding.php';
require_once __DIR__ . '/../models/Analyse.php';

module_require_roles('laboratoire');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$analyseId = (int)$_GET['id'];

$tcpdfPath = '../vendor/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    header('Location: ../install_tcpdf_manual.php');
    exit;
}

require_once $tcpdfPath;

$analyseModel = new Analyse();
$analyse = $analyseModel->getById($analyseId);

if (!$analyse) {
    header('Location: index.php');
    exit;
}

// Récupérer les paramètres du système
$systemParams = pdf_tenant_system_params();

// Nettoyer le tampon de sortie avant la génération du PDF
ob_end_clean();

// Générer le PDF de l'analyse
generateAnalysePDF($analyse, $systemParams);

function generateAnalysePDF($analyse, $systemParams) {
    try {
        // Créer une nouvelle instance de TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Définir les informations du document
        $pdf->SetCreator($systemParams->get('nom_etablissement'));
        $pdf->SetAuthor('Système de Gestion Laboratoire');
        $pdf->SetTitle('Rapport d\'Analyse #' . $analyse['id']);
        $pdf->SetSubject('Rapport d\'Analyse de Laboratoire');
        
        // Supprimer les en-têtes et pieds de page par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Définir les marges
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(TRUE, 10);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Définir la police
        $pdf->SetFont('helvetica', '', 9);
        
        // Créer le contenu HTML du PDF
        $html = createAnalyseHTML($analyse, $systemParams);
        
        // Écrire le HTML dans le PDF
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Générer le nom du fichier
        $filename = 'analyse_' . $analyse['id'] . '_' . date('Y-m-d') . '.pdf';
        
        // Envoyer le PDF au navigateur
        $pdf->Output($filename, 'D');
        
    } catch (Exception $e) {
        header('Location: index.php?error=pdf_generation_failed');
        exit;
    }
}

function createAnalyseHTML($analyse, $systemParams) {
    // Fonction de formatage de date
    function formatDate($date) {
        return date('d/m/Y H:i', strtotime($date));
    }
    
    // Fonction de label de statut
    function getStatutLabel($statut) {
        $statuts = [
            'en_attente' => 'En attente',
            'en_cours' => 'En cours',
            'termine' => 'Terminé',
            'annule' => 'Annulé'
        ];
        return $statuts[$statut] ?? ucfirst($statut);
    }
    
    // Fonction de label de priorité
    function getPrioriteLabel($priorite) {
        $priorites = [
            'normale' => 'Normale',
            'urgente' => 'Urgente',
            'critique' => 'Critique'
        ];
        return $priorites[$priorite] ?? ucfirst($priorite);
    }
    
    $html = '
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 15px; font-size: 9px; }
        .info-section { margin-bottom: 20px; }
        .info-row { margin-bottom: 6px; }
        .label { font-weight: bold; color: #333; display: inline-block; width: 120px; }
        .value { color: #666; }
        .patient-info { background-color: #f8f9fa; padding: 12px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #dee2e6; }
        .analyse-details { background-color: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 12px; margin-bottom: 20px; }
        .medecin-info { background-color: #e3f2fd; padding: 12px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #bbdefb; }
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-weight: bold; font-size: 10px; }
        .status-en_attente { background: #fff3cd; color: #856404; }
        .status-en_cours { background: #d1ecf1; color: #0c5460; }
        .status-termine { background: #d4edda; color: #155724; }
        .status-annule { background: #f8d7da; color: #721c24; }
        .priorite-normale { background: #6c757d; color: white; }
        .priorite-urgente { background: #ffc107; color: black; }
        .priorite-critique { background: #dc3545; color: white; }
        .two-columns { display: flex; justify-content: space-between; }
        .column { width: 48%; }
    </style>
    
    ' . $systemParams->generatePDFHeaderWithLogo('Rapport d\'Analyse', 'Laboratoire') . '
    
    <div class="analyse-details">
        <h3 style="margin: 0 0 12px 0; color: #007bff; font-size: 14px;">Informations de l\'Analyse</h3>
        <div class="two-columns">
            <div class="column">
                <div class="info-row">
                    <span class="label">Numéro :</span>
                    <span class="value"><strong>#' . $analyse['id'] . '</strong></span>
                </div>
                <div class="info-row">
                    <span class="label">Type :</span>
                    <span class="value">' . htmlspecialchars($analyse['type_analyse'] ?? 'Non spécifié') . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Priorité :</span>
                    <span class="value">
                        <span class="status-badge priorite-' . ($analyse['priorite'] ?? 'normale') . '">
                            ' . getPrioriteLabel($analyse['priorite'] ?? 'normale') . '
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label">Statut :</span>
                    <span class="value">
                        <span class="status-badge status-' . $analyse['statut'] . '">
                            ' . getStatutLabel($analyse['statut']) . '
                        </span>
                    </span>
                </div>
            </div>
            <div class="column">
                <div class="info-row">
                    <span class="label">Date création :</span>
                    <span class="value">' . formatDate($analyse['date_creation']) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Date analyse :</span>
                    <span class="value">' . ($analyse['date_analyse'] ? formatDate($analyse['date_analyse']) : 'Non définie') . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Date résultats :</span>
                    <span class="value">' . ($analyse['date_resultats'] ? formatDate($analyse['date_resultats']) : 'Non définie') . '</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="patient-info">
        <h3 style="margin: 0 0 12px 0; color: #28a745; font-size: 14px;">Informations du Patient</h3>
        <div class="two-columns">
            <div class="column">
                <div class="info-row">
                    <span class="label">Nom complet :</span>
                    <span class="value"><strong>' . htmlspecialchars(($analyse['patient_nom'] ?? '') . ' ' . ($analyse['patient_prenom'] ?? '')) . '</strong></span>
                </div>
                <div class="info-row">
                    <span class="label">Numéro dossier :</span>
                    <span class="value">' . htmlspecialchars($analyse['numero_dossier'] ?? 'Non spécifié') . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Sexe :</span>
                    <span class="value">' . htmlspecialchars($analyse['sexe'] ?? 'Non spécifié') . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Date naissance :</span>
                    <span class="value">' . ($analyse['date_naissance'] ? formatDate($analyse['date_naissance']) : 'Non spécifiée') . '</span>
                </div>
            </div>
            <div class="column">
                <div class="info-row">
                    <span class="label">Téléphone :</span>
                    <span class="value">' . htmlspecialchars($analyse['telephone'] ?? 'Non spécifié') . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Email :</span>
                    <span class="value">' . htmlspecialchars($analyse['email'] ?? 'Non spécifié') . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Adresse :</span>
                    <span class="value">' . htmlspecialchars($analyse['adresse'] ?? 'Non spécifiée') . '</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="medecin-info">
        <h3 style="margin: 0 0 12px 0; color: #1976d2; font-size: 14px;">Informations du professionnel</h3>
        <div class="two-columns">
            <div class="column">
                <div class="info-row">
                    <span class="label">' . htmlspecialchars(medecin_profil_attribution_label_from_row($analyse)) . ' :</span>
                    <span class="value">' . htmlspecialchars(medecin_profil_format_joined($analyse)) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Spécialité :</span>
                    <span class="value">' . htmlspecialchars($analyse['specialite'] ?? 'Non spécifiée') . '</span>
                </div>
            </div>
        </div>
    </div>';
    
    // Ajouter la description si disponible
    if (!empty($analyse['description'])) {
        $html .= '
        <div class="analyse-details">
            <h3 style="margin: 0 0 12px 0; color: #ffc107; font-size: 14px;">Description de l\'Analyse</h3>
            <p style="margin: 0; line-height: 1.4;">' . htmlspecialchars($analyse['description']) . '</p>
        </div>';
    }
    
    // Ajouter les instructions si disponibles
    if (!empty($analyse['instructions'])) {
        $html .= '
        <div class="analyse-details">
            <h3 style="margin: 0 0 12px 0; color: #6f42c1; font-size: 14px;">Instructions Spéciales</h3>
            <p style="margin: 0; line-height: 1.4;">' . htmlspecialchars($analyse['instructions']) . '</p>
        </div>';
    }
    
    // Ajouter les résultats si disponibles
    if (!empty($analyse['resultats'])) {
        $html .= '
        <div class="analyse-details">
            <h3 style="margin: 0 0 12px 0; color: #28a745; font-size: 14px;">Résultats de l\'Analyse</h3>
            <p style="margin: 0; line-height: 1.4;">' . htmlspecialchars($analyse['resultats']) . '</p>
        </div>';
    }
    
    // Ajouter le pied de page
    $html .= $systemParams->generatePDFFooter();
    
    return $html;
}
?>

