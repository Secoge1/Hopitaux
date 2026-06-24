<?php
require_once __DIR__ . '/../includes/init.php';
$auth = Auth::getInstance();
$auth->requireAuth();

// Inclure la configuration de la devise et le modèle
require_once '../config/currency.php';
require_once '../models/Paiement.php';

// Vérifier si un ID de paiement est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID de paiement manquant');
}

$paiementId = (int)$_GET['id'];
$paiementModel = new Paiement();

// Récupérer les détails du paiement
$paiement = $paiementModel->getById($paiementId);

if (!$paiement) {
    die('Paiement non trouvé');
}

// Générer le PDF du paiement individuel
generatePaiementPDF($paiement);

function generatePaiementPDF($paiement) {
    // En-têtes pour forcer le téléchargement
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="facture_' . $paiement['numero_facture'] . '_' . date('Y-m-d') . '.pdf"');
    
    // Créer le contenu HTML du PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta charset="UTF-8">
        <title>Facture - ' . $paiement['numero_facture'] . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
            .title { font-size: 28px; font-weight: bold; color: #333; }
            .subtitle { font-size: 18px; color: #666; margin-top: 10px; }
            .info-section { margin-bottom: 30px; }
            .info-row { margin-bottom: 10px; }
            .label { font-weight: bold; color: #333; }
            .value { color: #666; }
            .patient-info { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 30px; }
            .paiement-details { background-color: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 20px; }
            .amount { font-size: 24px; font-weight: bold; color: #28a745; text-align: center; padding: 20px; background-color: #d4edda; border-radius: 5px; }
            .status-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
            .status-paye { background-color: #d4edda; color: #155724; }
            .status-en_attente { background-color: #fff3cd; color: #856404; }
            .status-partiel { background-color: #d1ecf1; color: #0c5460; }
            .status-annule { background-color: #f8d7da; color: #721c24; }
            .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 20px; }
            .logo { text-align: center; margin-bottom: 20px; }
            .logo-text { font-size: 20px; font-weight: bold; color: #007bff; }
        </style>
    </head>
    <body>
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
            <h3>Informations du Patient</h3>
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
            <h3>Détails du Paiement</h3>
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
            Montant : ' . formatCurrency($paiement['montant']) . '
        </div>
        
        ' . ($paiement['notes'] ? '
        <div class="info-section">
            <h3>Notes</h3>
            <p>' . htmlspecialchars($paiement['notes']) . '</p>
        </div>' : '') . '
        
        <div class="footer">
            <p><strong>Clinique et Hôpital</strong></p>
            <p>Ce document a été généré automatiquement le ' . date('d/m/Y à H:i') . '</p>
            <p>Pour toute question, contactez notre service de facturation</p>
        </div>
    </body>
    </html>';
    
    // Convertir le HTML en PDF (utiliser une bibliothèque comme TCPDF ou mPDF)
    // Pour l'instant, on affiche le HTML (vous devrez installer une bibliothèque PDF)
    echo $html;
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
