<?php
// Démarrer la session sans output
ob_start();
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../models/Paiement.php';

$auth = Auth::getInstance();
$auth->requireLogin('../login.php');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    ob_end_clean();
    exit('ID de paiement manquant');
}

$paiementId = (int)$_GET['id'];

$tcpdfPath = '../vendor/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    ob_end_clean();
    exit('TCPDF non trouvé');
}

require_once $tcpdfPath;

$paiementModel = new Paiement();
$paiement = $paiementModel->getById($paiementId);

if (!$paiement) {
    ob_end_clean();
    exit('Paiement non trouvé');
}

require_once __DIR__ . '/../includes/pdf_branding.php';
$systemParams = pdf_tenant_system_params();

// Nettoyer tout output buffer avant de générer le PDF
ob_end_clean();

// Générer le PDF du paiement individuel
generatePaiementPDF($paiement, $systemParams);

function generatePaiementPDF($paiement, $systemParams) {
    try {
        // Créer une nouvelle instance de TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Définir les informations du document
        $pdf->SetCreator($systemParams->get('nom_etablissement'));
        $pdf->SetAuthor('Système de Gestion');
        $pdf->SetTitle('Facture - ' . $paiement['numero_facture']);
        $pdf->SetSubject('Facture de Paiement');
        
        // Supprimer les en-têtes et pieds de page par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Définir les marges (plus petites pour maximiser l'espace)
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(TRUE, 10);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Définir la police
        $pdf->SetFont('helvetica', '', 9);
        
        // Créer le contenu HTML du PDF optimisé pour une page
        $html = createPaiementHTMLOptimized($paiement, $systemParams);
        
        // Écrire le HTML dans le PDF
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Générer le nom du fichier
        $filename = 'facture_' . $paiement['numero_facture'] . '_' . date('Y-m-d') . '.pdf';
        
        // Envoyer le PDF au navigateur
        $pdf->Output($filename, 'D');
        
    } catch (Exception $e) {
        exit('Erreur lors de la génération du PDF');
    }
}

function createPaiementHTMLOptimized($paiement, $systemParams) {
    // Fonction de formatage de devise
    function formatFCFA($amount) {
        global $systemParams;
        return $systemParams->formatFCFA($amount);
    }
    
    // Fonction de label de type de paiement
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
    
    // Fonction de label de statut
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
    
    // Fonction de formatage de date
    function formatDate($date) {
        return date('d/m/Y H:i', strtotime($date));
    }
    
    $nomClinique = $systemParams->get('nom_etablissement');
    $adresseClinique = $systemParams->get('adresse');
    $villeClinique = $systemParams->get('ville');
    $telephoneClinique = $systemParams->get('telephone');
    $emailClinique = $systemParams->get('email');
    $devise = $systemParams->get('devise_symbole');
    
    // Utiliser la méthode generatePDFHeaderWithLogo pour l'en-tête avec logo (compacte)
    $html = $systemParams->generatePDFHeaderWithLogo('Facture de Paiement', 'Système de Gestion');
    
    $html .= '
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 10px; font-size: 8px; }
        .info-section { margin-bottom: 15px; }
        .info-row { margin-bottom: 4px; }
        .label { font-weight: bold; color: #333; display: inline-block; width: 100px; }
        .value { color: #666; }
        .patient-info { background-color: #f8f9fa; padding: 8px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #dee2e6; }
        .paiement-details { background-color: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 8px; margin-bottom: 15px; }
        .consultation-info { background-color: #e3f2fd; padding: 8px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #bbdefb; }
        .amount { font-size: 14px; font-weight: bold; color: #28a745; text-align: center; padding: 8px; background-color: #d4edda; border-radius: 4px; border: 1px solid #c3e6cb; }
        .status-badge { display: inline-block; padding: 2px 6px; border-radius: 10px; font-weight: bold; font-size: 8px; }
        .status-paye { background-color: #d4edda; color: #155724; }
        .status-en_attente { background-color: #fff3cd; color: #856404; }
        .status-partiel { background-color: #d1ecf1; color: #0c5460; }
        .status-annule { background-color: #f8d7da; color: #721c24; }
        .footer { margin-top: 15px; text-align: center; font-size: 7px; color: #666; border-top: 1px solid #ddd; padding-top: 8px; }
        .two-columns { display: flex; justify-content: space-between; }
        .column { width: 48%; }
        .system-info { background-color: #fff3cd; padding: 8px; border-radius: 4px; margin-bottom: 12px; border: 1px solid #ffeaa7; }
        .section-title { margin: 0 0 8px 0; font-size: 11px; font-weight: bold; }
    </style>
    
    <div class="system-info">
        <div class="two-columns">
            <div class="column">
                <div class="info-row">
                    <span class="label">Numéro de facture :</span>
                    <span class="value"><strong>' . htmlspecialchars($paiement['numero_facture']) . '</strong></span>
                </div>
                <div class="info-row">
                    <span class="label">Date de génération :</span>
                    <span class="value">' . formatDate(date('Y-m-d H:i:s')) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Devise :</span>
                    <span class="value">' . $devise . '</span>
                </div>
            </div>
            <div class="column">
                <div class="info-row">
                    <span class="label">Statut :</span>
                    <span class="value">
                        <span class="status-badge status-' . $paiement['statut'] . '">
                            ' . htmlspecialchars(getStatutLabel($paiement['statut'])) . '
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label">Type de paiement :</span>
                    <span class="value">' . htmlspecialchars(getTypePaiementLabel($paiement['type_paiement'])) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Date de paiement :</span>
                    <span class="value">' . formatDate($paiement['date_paiement']) . '</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="patient-info">
        <h3 class="section-title" style="color: #28a745;">Informations du Patient</h3>
        <div class="two-columns">
            <div class="column">
                <div class="info-row">
                    <span class="label">Nom complet :</span>
                    <span class="value"><strong>' . htmlspecialchars($paiement['patient_nom'] . ' ' . $paiement['patient_prenom']) . '</strong></span>
                </div>
                <div class="info-row">
                    <span class="label">Numéro de dossier :</span>
                    <span class="value">' . htmlspecialchars($paiement['numero_dossier']) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Sexe :</span>
                    <span class="value">' . htmlspecialchars($paiement['sexe'] ?? 'Non spécifié') . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Date de naissance :</span>
                    <span class="value">' . ($paiement['date_naissance'] ? formatDate($paiement['date_naissance']) : 'Non spécifiée') . '</span>
                </div>
            </div>
            <div class="column">
                <div class="info-row">
                    <span class="label">Téléphone :</span>
                    <span class="value">' . htmlspecialchars($paiement['telephone'] ?? 'Non spécifié') . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Email :</span>
                    <span class="value">' . htmlspecialchars($paiement['email'] ?? 'Non spécifié') . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Adresse :</span>
                    <span class="value">' . htmlspecialchars($paiement['adresse'] ?? 'Non spécifiée') . '</span>
                </div>
            </div>
        </div>
    </div>';
    
    // Ajouter les informations de consultation si disponibles (plus compactes)
    if ($paiement['consultation_id']) {
        $html .= '
        <div class="consultation-info">
            <h3 class="section-title" style="color: #1976d2;">Informations de la Consultation</h3>
            <div class="two-columns">
                <div class="column">
                    <div class="info-row">
                        <span class="label">Consultation :</span>
                        <span class="value"><strong>' . 
                        'Consultation #' . $paiement['consultation_id'] . 
                        (!empty($paiement['diagnostic']) ? ' - ' . htmlspecialchars($paiement['diagnostic']) : 
                        ' - ' . htmlspecialchars($paiement['patient_nom'] . ' ' . $paiement['patient_prenom'])) . 
                        '</strong></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Date consultation :</span>
                        <span class="value">' . formatDate($paiement['date_consultation']) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">' . htmlspecialchars(medecin_profil_attribution_label_from_row($paiement)) . ' :</span>
                        <span class="value">' . htmlspecialchars(medecin_profil_format_joined($paiement)) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Spécialité :</span>
                        <span class="value">' . htmlspecialchars($paiement['specialite'] ?? 'Non spécifiée') . '</span>
                    </div>
                </div>
                <div class="column">
                    <div class="info-row">
                        <span class="label">Diagnostic :</span>
                        <span class="value">' . htmlspecialchars($paiement['diagnostic'] ?? 'Non spécifié') . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Traitement :</span>
                        <span class="value">' . htmlspecialchars($paiement['traitement'] ?? 'Non spécifié') . '</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Ordonnance :</span>
                        <span class="value">' . htmlspecialchars($paiement['ordonnance'] ?? 'Non spécifiée') . '</span>
                    </div>
                </div>
            </div>
        </div>';
    }
    
    // Détails du paiement - CORRIGÉ et optimisé
    $html .= '
    <div class="paiement-details">
        <h3 class="section-title" style="color: #dc3545;">Détails du Paiement</h3>
        <div class="two-columns">
            <div class="column">
                <div class="info-row">
                    <span class="label">Montant :</span>
                    <span class="value">' . formatFCFA($paiement['montant']) . '</span>
                </div>
                <div class="info-row">
                    <span class="label">Date de création :</span>
                    <span class="value">' . formatDate($paiement['date_creation']) . '</span>
                </div>
                ' . ($paiement['date_modification'] ? '
                <div class="info-row">
                    <span class="label">Dernière modification :</span>
                    <span class="value">' . formatDate($paiement['date_modification']) . '</span>
                </div>' : '') . '
            </div>
            <div class="column">
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
                ' . ($paiement['notes'] ? '
                <div class="info-row">
                    <span class="label">Notes :</span>
                    <span class="value">' . htmlspecialchars($paiement['notes']) . '</span>
                </div>' : '') . '
            </div>
        </div>
    </div>
    
    <div class="amount">
        <strong>MONTANT TOTAL : ' . formatFCFA($paiement['montant']) . '</strong>
    </div>
    
    <div class="footer">
        <p>Ce document a été généré automatiquement par le système de gestion de la clinique.</p>
        <p>Pour toute question, veuillez nous contacter au ' . htmlspecialchars($telephoneClinique) . '</p>
    </div>';
    
    return $html;
}
?>
