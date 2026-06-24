<?php
/**
 * Export d'analyse en HTML optimisé pour l'impression/PDF
 * Alternative plus fiable à TCPDF pour le déploiement web
 */

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

$analyseModel = new Analyse();
$analyse = $analyseModel->getById($analyseId);

if (!$analyse) {
    header('Location: index.php');
    exit;
}

// Récupérer les paramètres du système
$systemParams = pdf_tenant_system_params();

// Générer le HTML optimisé pour l'impression
generateAnalyseHTML($analyse, $systemParams);

function generateAnalyseHTML($analyse, $systemParams) {
    $logoBlock = $systemParams->getPdfLogoBlockHtml(['max_height' => 90, 'max_width' => 320]);
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
    
    // Créer le contenu HTML
    $html = createAnalyseContent($analyse, $systemParams);
    
    // En-têtes pour HTML
    header('Content-Type: text/html; charset=UTF-8');
    
    // Page HTML complète optimisée pour l'impression
    echo '<!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Rapport d\'Analyse #' . $analyse['id'] . ' - ' . ($systemParams->get('nom_etablissement') ?? 'Clinique') . '</title>
        <style>
            /* Reset et base */
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: Arial, sans-serif; 
                font-size: 12px; 
                line-height: 1.4; 
                color: #333; 
                background: white;
                margin: 0;
                padding: 0;
            }
            
            /* Styles d\'impression */
            @media print {
                body { margin: 0; padding: 20px; }
                .no-print { display: none !important; }
                .page-break { page-break-before: always; }
                .header { position: fixed; top: 0; left: 0; right: 0; }
                .content { margin-top: 120px; }
            }
            
            /* En-tête */
            .header {
                background: linear-gradient(135deg, #007bff, #0056b3);
                color: white;
                padding: 20px;
                text-align: center;
                margin-bottom: 30px;
                border-radius: 0 0 10px 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .header h1 {
                font-size: 24px;
                margin-bottom: 5px;
                font-weight: bold;
            }
            
            .header .subtitle {
                font-size: 16px;
                opacity: 0.9;
            }
            
            .header .logo {
                max-width: 80px;
                height: auto;
                margin-bottom: 15px;
            }
            
            /* Conteneur principal */
            .container {
                max-width: 800px;
                margin: 0 auto;
                padding: 0 20px;
            }
            
            /* Sections */
            .section {
                background: white;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 25px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            
            .section h3 {
                color: #007bff;
                font-size: 16px;
                margin-bottom: 15px;
                padding-bottom: 8px;
                border-bottom: 2px solid #e0e0e0;
                font-weight: bold;
            }
            
            /* Grille d\'informations */
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            
            .info-row {
                margin-bottom: 12px;
            }
            
            .info-label {
                font-weight: bold;
                color: #555;
                margin-bottom: 4px;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .info-value {
                color: #333;
                font-size: 13px;
                padding: 4px 0;
            }
            
            /* Badges de statut */
            .badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .badge-status {
                background: #e3f2fd;
                color: #1976d2;
                border: 1px solid #bbdefb;
            }
            
            .badge-priority {
                background: #fff3e0;
                color: #f57c00;
                border: 1px solid #ffcc02;
            }
            
            .badge-success {
                background: #e8f5e8;
                color: #388e3c;
                border: 1px solid #c8e6c9;
            }
            
            .badge-warning {
                background: #fff8e1;
                color: #f57c00;
                border: 1px solid #ffecb3;
            }
            
            /* Boutons d\'action */
            .actions {
                text-align: center;
                margin: 30px 0;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 10px;
                border: 1px solid #e9ecef;
            }
            
            .btn {
                display: inline-block;
                padding: 12px 24px;
                margin: 5px;
                background: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                font-weight: bold;
                font-size: 14px;
                border: none;
                cursor: pointer;
                transition: background 0.3s ease;
            }
            
            .btn:hover {
                background: #0056b3;
            }
            
            .btn-success {
                background: #28a745;
            }
            
            .btn-success:hover {
                background: #1e7e34;
            }
            
            .btn-secondary {
                background: #6c757d;
            }
            
            .btn-secondary:hover {
                background: #545b62;
            }
            
            /* Pied de page */
            .footer {
                text-align: center;
                margin-top: 40px;
                padding: 20px;
                border-top: 1px solid #e0e0e0;
                color: #666;
                font-size: 11px;
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .info-grid {
                    grid-template-columns: 1fr;
                }
                
                .container {
                    padding: 0 15px;
                }
                
                .section {
                    padding: 15px;
                }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="logo">
                ' . $logoBlock . '
            </div>
            <h1>Rapport d\'Analyse</h1>
            <div class="subtitle">' . ($systemParams->get('nom_etablissement') ?? 'Clinique et Hôpital') . '</div>
        </div>
        
        <div class="container">
            ' . $html . '
            
            <div class="actions no-print">
                <h3>Actions disponibles</h3>
                <button onclick="window.print()" class="btn btn-success">
                    🖨️ Imprimer / Sauvegarder en PDF
                </button>
                <a href="index.php" class="btn btn-secondary">
                    ← Retour au laboratoire
                </a>
                <a href="../index.php" class="btn btn-secondary">
                    🏠 Accueil
                </a>
            </div>
            
            <div class="footer">
                <p><strong>Généré le :</strong> ' . date('d/m/Y à H:i') . '</p>
                <p><strong>Par :</strong> ' . ($systemParams->get('nom_etablissement') ?? 'Système de Gestion') . '</p>
                <p><small>Ce document est généré automatiquement par le système de gestion de laboratoire</small></p>
            </div>
        </div>
        
        <script>
            // Instructions d\'impression
            window.onload = function() {
                console.log(\'📋 Instructions pour sauvegarder en PDF :\');
                console.log(\'1. Appuyez sur Ctrl+P (ou Cmd+P sur Mac)\');
                console.log(\'2. Choisissez "Sauvegarder en PDF" comme destination\');
                console.log(\'3. Cliquez sur "Sauvegarder"\');
                
                // Auto-focus pour faciliter l\'impression
                document.body.focus();
            };
            
            // Fonction d\'impression personnalisée
            function printReport() {
                window.print();
            }
        </script>
    </body>
    </html>';
}

function createAnalyseContent($analyse, $systemParams) {
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
    
    $html = '';
    
    // Informations de l'analyse
    $html .= '
    <div class="section">
        <h3>📋 Informations de l\'Analyse</h3>
        <div class="info-grid">
            <div>
                <div class="info-row">
                    <div class="info-label">Numéro d\'analyse</div>
                    <div class="info-value"><strong>#' . $analyse['id'] . '</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Type d\'analyse</div>
                    <div class="info-value">' . htmlspecialchars($analyse['type_analyse'] ?? 'Non spécifié') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Priorité</div>
                    <div class="info-value">
                        <span class="badge badge-priority">' . getPrioriteLabel($analyse['priorite'] ?? 'normale') . '</span>
                    </div>
                </div>
            </div>
            <div>
                <div class="info-row">
                    <div class="info-label">Statut</div>
                    <div class="info-value">
                        <span class="badge badge-status">' . getStatutLabel($analyse['statut']) . '</span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date de création</div>
                    <div class="info-value">' . formatDate($analyse['date_creation']) . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date d\'analyse</div>
                    <div class="info-value">' . ($analyse['date_analyse'] ? formatDate($analyse['date_analyse']) : 'Non définie') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date des résultats</div>
                    <div class="info-value">' . ($analyse['date_resultats'] ? formatDate($analyse['date_resultats']) : 'Non définie') . '</div>
                </div>
            </div>
        </div>
    </div>';
    
    // Informations du patient
    $html .= '
    <div class="section">
        <h3>👤 Informations du Patient</h3>
        <div class="info-grid">
            <div>
                <div class="info-row">
                    <div class="info-label">Nom complet</div>
                    <div class="info-value"><strong>' . htmlspecialchars(($analyse['patient_nom'] ?? '') . ' ' . ($analyse['patient_prenom'] ?? '')) . '</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Numéro de dossier</div>
                    <div class="info-value">' . htmlspecialchars($analyse['numero_dossier'] ?? 'Non spécifié') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Sexe</div>
                    <div class="info-value">' . htmlspecialchars($analyse['sexe'] ?? 'Non spécifié') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date de naissance</div>
                    <div class="info-value">' . ($analyse['date_naissance'] ? formatDate($analyse['date_naissance']) : 'Non spécifiée') . '</div>
                </div>
            </div>
            <div>
                <div class="info-row">
                    <div class="info-label">Téléphone</div>
                    <div class="info-value">' . htmlspecialchars($analyse['telephone'] ?? 'Non spécifié') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email</div>
                    <div class="info-value">' . htmlspecialchars($analyse['email'] ?? 'Non spécifié') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Adresse</div>
                    <div class="info-value">' . htmlspecialchars($analyse['adresse'] ?? 'Non spécifiée') . '</div>
                </div>
            </div>
        </div>
    </div>';
    
    // Informations du médecin
    $html .= '
    <div class="section">
        <h3>👨‍⚕️ Informations du Médecin</h3>
        <div class="info-grid">
            <div>
                <div class="info-row">
                    <div class="info-label">Médecin prescripteur</div>
                    <div class="info-value"><strong>' . htmlspecialchars(medecin_profil_format_joined($analyse)) . '</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Spécialité</div>
                    <div class="info-value">' . htmlspecialchars($analyse['specialite'] ?? 'Non spécifiée') . '</div>
                </div>
            </div>
        </div>
    </div>';
    
    // Description de l'analyse
    if (!empty($analyse['description'])) {
        $html .= '
        <div class="section">
            <h3>📝 Description de l\'Analyse</h3>
            <div class="info-row">
                <div class="info-value">' . htmlspecialchars($analyse['description']) . '</div>
            </div>
        </div>';
    }
    
    // Instructions spéciales
    if (!empty($analyse['instructions'])) {
        $html .= '
        <div class="section">
            <h3>⚠️ Instructions Spéciales</h3>
            <div class="info-row">
                <div class="info-value">' . htmlspecialchars($analyse['instructions']) . '</div>
            </div>
        </div>';
    }
    
    // Résultats de l'analyse
    if (!empty($analyse['resultats'])) {
        $html .= '
        <div class="section">
            <h3>🔬 Résultats de l\'Analyse</h3>
            <div class="info-row">
                <div class="info-value">' . htmlspecialchars($analyse['resultats']) . '</div>
            </div>
        </div>';
    }
    
    return $html;
}
?>
