<?php
require_once __DIR__ . '/../includes/init.php';
$auth = Auth::getInstance();
$auth->requireAuth();

// Inclure la configuration de la devise et le modèle
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Paiement.php';

// Inclure TCPDF directement
$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    // Rediriger vers la page d'installation si TCPDF n'est pas installé
    header('Location: ' . efficasante_web_base_path() . '/install_tcpdf_manual.php');
    exit;
}

require_once $tcpdfPath;

// Vérifier si un ID de paiement est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$paiementId = (int)$_GET['id'];
$paiementModel = new Paiement();

// Récupérer les détails du paiement
$paiement = $paiementModel->getById($paiementId);

if (!$paiement) {
    header('Location: index.php');
    exit;
}

// Générer le PDF du paiement individuel
generatePaiementPDF($paiement);

function generatePaiementPDF($paiement) {
    try {
        // Créer une nouvelle instance de TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Définir les informations du document
        $pdf->SetCreator('Clinique et Hôpital');
        $pdf->SetAuthor('Système de Gestion');
        $pdf->SetTitle('Facture - ' . $paiement['numero_facture']);
        $pdf->SetSubject('Facture de Paiement');
        
        // Supprimer les en-têtes et pieds de page par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Définir les marges
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Définir la police
        $pdf->SetFont('helvetica', '', 10);
        
        // Créer le contenu HTML du PDF
        $html = createPaiementHTML($paiement);
        
        // Écrire le HTML dans le PDF
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Générer le nom du fichier
        $filename = 'facture_' . $paiement['numero_facture'] . '_' . date('Y-m-d') . '.pdf';
        
        // Envoyer le PDF au navigateur
        $pdf->Output($filename, 'D');
        
    } catch (Exception $e) {
        // En cas d'erreur, rediriger vers une page d'erreur
        header('Location: index.php?error=pdf_generation_failed');
        exit;
    }
}

function createPaiementHTML($paiement) {
    $html = '
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .title { font-size: 24px; font-weight: bold; color: #333; margin: 0; }
        .subtitle { font-size: 16px; color: #666; margin: 10px 0 0 0; }
        .info-section { margin-bottom: 25px; }
        .info-row { margin-bottom: 8px; }
        .label { font-weight: bold; color: #333; display: inline-block; width: 150px; }
        .value { color: #666; }
        .patient-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 25px; border: 1px solid #dee2e6; }
        .paiement-details { background-color: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 25px; }
        .amount { font-size: 20px; font-weight: bold; color: #28a745; text-align: center; padding: 15px; background-color: #d4edda; border-radius: 5px; border: 1px solid #c3e6cb; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 15px; font-weight: bold; font-size: 12px; }
        .status-paye { background-color: #d4edda; color: #155724; }
        .status-en_attente { background-color: #fff3cd; color: #856404; }
        .status-partiel { background-color: #d1ecf1; color: #0c5460; }
        .status-annule { background-color: #f8d7da; color: #721c24; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 15px; }
        .logo { text-align: center; margin-bottom: 15px; }
                    .logo-text { font-size: 18px; font-weight: bold; color: #007bff; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; }
    </style>
    
    <div class="header">
        <div class="logo">
            <div class="logo-text">🏥</div>
        </div>
        <div class="title">Clinique et Hôpital</div>
        <div class="subtitle">Facture de Paiement</div>
    </div>
    
    <div class="info-section">
        <div class="info-row">
            <span class="label">Numéro de facture :</span>
            <span class="value">' . htmlspecialchars($paiement['numero_facture']) . '</span>
        </div>
        <div class="info-row">
            <span class="label">Date de génération :</span>
            <span class="value">' . date('d/m/Y H:i') . '</span>
        </div>
        <div class="info-row">
            <span class="label">Devise :</span>
            <span class="value">' . CURRENCY_NAME . ' (' . CURRENCY_SYMBOL . ')</span>
        </div>
    </div>
    
    <div class="patient-info">
        <h3 style="margin: 0 0 15px 0; color: #28a745;">Informations du Patient</h3>
        <div class="info-row">
            <span class="label">Nom complet :</span>
            <span class="value">' . htmlspecialchars($paiement['patient_nom'] . ' ' . $paiement['patient_prenom']) . '</span>
        </div>
        <div class="info-row">
            <span class="label">Numéro de dossier :</span>
            <span class="value">' . htmlspecialchars($paiement['numero_dossier']) . '</span>
        </div>
        ' . ($paiement['consultation_id'] ? '
        <div class="info-row">
            <span class="label">Consultation :</span>
            <span class="value">#' . $paiement['consultation_id'] . ' - ' . date('d/m/Y', strtotime($paiement['date_consultation'])) . '</span>
        </div>' : '') . '
    </div>
    
    <div class="paiement-details">
        <h3 style="margin: 0 0 15px 0; color: #ffc107;">Détails du Paiement</h3>
        <div class="info-row">
            <span class="label">Type de paiement :</span>
            <span class="value">' . htmlspecialchars(getTypePaiementLabel($paiement['type_paiement'])) . '</span>
        </div>
        <div class="info-row">
            <span class="label">Statut :</span>
            <span class="value">
                <span class="status-badge status-' . $paiement['statut'] . '">
                    ' . htmlspecialchars(getStatutLabel($paiement['statut'])) . '
                </span>
            </span>
        </div>
        <div class="info-row">
            <span class="label">Date de paiement :</span>
            <span class="value">' . date('d/m/Y H:i', strtotime($paiement['date_paiement'])) . '</span>
        </div>
        ' . ($paiement['description'] ? '
        <div class="info-row">
            <span class="label">Description :</span>
            <span class="value">' . htmlspecialchars($paiement['description']) . '</span>
        </div>' : '') . '
        ' . ($paiement['reference_paiement'] ? '
        <div class="info-row">
            <span class="label">Référence :</span>
            <span class="value">' . htmlspecialchars($paiement['reference_paiement']) . '</span>
        </div>' : '') . '
    </div>
    
    <div class="amount">
        Montant : ' . formatFCFA($paiement['montant']) . '
    </div>
    
    ' . ($paiement['notes'] ? '
    <div class="info-section">
        <h3 style="margin: 0 0 15px 0; color: #6c757d;">Notes</h3>
        <p style="margin: 0; padding: 10px; background-color: #f8f9fa; border-radius: 5px; border-left: 4px solid #6c757d;">' . htmlspecialchars($paiement['notes']) . '</p>
    </div>' : '') . '
    
    <div class="footer">
        <p style="margin: 5px 0;"><strong>Clinique et Hôpital</strong></p>
        <p style="margin: 5px 0;">Ce document a été généré automatiquement le ' . date('d/m/Y à H:i') . '</p>
        <p style="margin: 5px 0;">Pour toute question, contactez notre service de facturation</p>
    </div>';
    
    return $html;
}

function getTypePaiementLabel($type) {
    $types = [
        'carte' => 'Carte bancaire',
        'virement' => 'Virement bancaire',
        'especes' => 'Espèces',
        'cheque' => 'Chèque',
        'securite_sociale' => 'Sécurité sociale',
        'mutuelle' => 'Mutuelle',
        'mobile_money' => 'Mobile Money',
        'autre' => 'Autre'
    ];
    
    return $types[$type] ?? ucfirst($type);
}

function getStatutLabel($statut) {
    $statuts = [
        'en_attente' => 'En attente',
        'partiel' => 'Paiement partiel',
        'paye' => 'Payé',
        'annule' => 'Annulé',
        'rembourse' => 'Remboursé'
    ];
    
    return $statuts[$statut] ?? ucfirst($statut);
}
?>
