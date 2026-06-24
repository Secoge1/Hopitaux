<?php
/**
 * Génération du PDF du dossier médical complet
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
$auth = Auth::getInstance();
module_require_roles('patients');

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../models/RendezVous.php';
require_once __DIR__ . '/../models/Paiement.php';

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    die("ID du patient manquant");
}

try {
    // Récupérer les données du patient
    $patientModel = new Patient();
    $patient = $patientModel->getById($patient_id);
    
    if (!$patient) {
        die("Patient non trouvé");
    }
    
    // Récupérer les données associées
    $consultationModel = new Consultation();
    $rendezVousModel = new RendezVous();
    $paiementModel = new Paiement();
    
    $consultations = $consultationModel->getPatientHistory($patient_id, 50);
    $rendezVous = $rendezVousModel->getPatientRendezVous($patient_id);
    $paiements = $paiementModel->getPatientPaiements($patient_id);
    
    // Récupérer les documents depuis le système de fichiers
    $uploadDir = __DIR__ . '/../uploads/patients/' . $patient_id;
    $documents = [];
    
    if (is_dir($uploadDir)) {
        $categories = ['photos_medicales', 'rapports', 'analyses', 'ordonnances', 'certificats', 'autres'];
        foreach ($categories as $categorie) {
            $count = 0;
            $files = scandir($uploadDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && !str_ends_with($file, '.info')) {
                    $infoFile = $uploadDir . '/' . $file . '.info';
                    if (file_exists($infoFile)) {
                        $metadata = json_decode(file_get_contents($infoFile), true);
                        if ($metadata && isset($metadata['categorie']) && $metadata['categorie'] === $categorie) {
                            $count++;
                        }
                    }
                }
            }
            if ($count > 0) {
                $documents[$categorie] = $count;
            }
        }
    }
    
    // Calculer l'âge
    $age = $patientModel->calculateAge($patient['date_naissance']);
    
    require_once __DIR__ . '/../includes/pdf_branding.php';
    $tenantId = !empty($patient['tenant_id']) ? (int) $patient['tenant_id'] : pdf_tenant_id_from_session();
    $systemParams = pdf_tenant_system_params($tenantId);
    
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

function generateLogoHTML(SystemParameters $systemParams): string
{
    return $systemParams->getPdfLogoBlockHtml([
        'max_height' => 80,
        'max_width' => 120,
        'margin_bottom' => '15px',
    ]);
}

// Vérifier si on veut juste le contenu HTML (pour impression)
$print_only = isset($_GET['print']) && $_GET['print'] === '1';

if ($print_only) {
    // Générer le contenu HTML du PDF pour impression
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dossier Médical - ' . htmlspecialchars($patient['nom'] . ' ' . $patient['prenom']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .section { margin-bottom: 25px; }
        .section-title { background-color: #f0f0f0; padding: 8px; font-weight: bold; margin-bottom: 15px; }
        .info-row { margin-bottom: 8px; }
        .label { font-weight: bold; display: inline-block; width: 150px; }
        .value { display: inline-block; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th, .table td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .table th { background-color: #f5f5f5; font-weight: bold; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #666; }
            .logo { text-align: center; margin-bottom: 20px; }
            .logo img { margin: 0 auto 15px; display: block; }
            .logo svg { margin: 0 auto 15px; display: block; }
            @media print {
                body { margin: 0; padding: 20px; }
                .no-print { display: none; }
            }
    </style>
</head>
<body>
    <div class="header">
            ' . generateLogoHTML($systemParams) . '
        <h1>DOSSIER MÉDICAL COMPLET</h1>
            <h2>' . htmlspecialchars($systemParams->get('nom_etablissement')) . '</h2>
        <p>Généré le ' . date('d/m/Y à H:i') . '</p>
    </div>
    
    <div class="section">
        <div class="section-title">INFORMATIONS PATIENT</div>
        <div class="info-row">
            <span class="label">Nom complet:</span>
            <span class="value">' . htmlspecialchars($patient['nom'] . ' ' . $patient['prenom']) . '</span>
        </div>
        <div class="info-row">
            <span class="label">Numéro dossier:</span>
            <span class="value">' . htmlspecialchars($patient['numero_dossier']) . '</span>
        </div>
        <div class="info-row">
            <span class="label">Date de naissance:</span>
            <span class="value">' . date('d/m/Y', strtotime($patient['date_naissance'])) . ' (' . $age . ' ans)</span>
        </div>
        <div class="info-row">
            <span class="label">Sexe:</span>
                <span class="value">' . ($patient['sexe'] === 'M' ? 'Masculin' : 'Féminin') . '</span>
        </div>
        <div class="info-row">
            <span class="label">Groupe sanguin:</span>
                <span class="value">' . ($patient['groupe_sanguin'] ?: 'Non renseigné') . '</span>
        </div>
        <div class="info-row">
            <span class="label">Téléphone:</span>
                <span class="value">' . ($patient['telephone'] ?: 'Non renseigné') . '</span>
        </div>
        <div class="info-row">
            <span class="label">Email:</span>
                <span class="value">' . ($patient['email'] ?: 'Non renseigné') . '</span>
        </div>
        <div class="info-row">
            <span class="label">Adresse:</span>
                <span class="value">' . ($patient['adresse'] ?: 'Non renseignée') . ', ' . ($patient['ville'] ?: '') . ' ' . ($patient['code_postal'] ?: '') . '</span>
            </div>
            <div class="info-row">
                <span class="label">Profession:</span>
                <span class="value">' . ($patient['profession'] ?: 'Non renseignée') . '</span>
            </div>
            <div class="info-row">
                <span class="label">Statut:</span>
                <span class="value">' . ucfirst($patient['statut']) . '</span>
            </div>
    </div>
    
    <div class="section">
        <div class="section-title">ANTÉCÉDENTS MÉDICAUX</div>
        <div class="info-row">
            <span class="label">Antécédents:</span>
            <span class="value">' . htmlspecialchars($patient['antecedents_medicaux'] ?? 'Aucun antécédent renseigné') . '</span>
        </div>
        <div class="info-row">
            <span class="label">Allergies:</span>
            <span class="value">' . htmlspecialchars($patient['allergies'] ?? 'Aucune allergie connue') . '</span>
        </div>
            <div class="info-row">
                <span class="label">Notes:</span>
                <span class="value">' . htmlspecialchars($patient['notes'] ?? 'Aucune note') . '</span>
            </div>
    </div>
    
    <div class="section">
            <div class="section-title">DOCUMENTS (' . array_sum($documents) . ' document(s))</div>';
    
    if (!empty($documents)) {
        $html .= '
            <table class="table">
                <thead>
                    <tr>
                        <th>Catégorie</th>
                        <th>Nombre</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($documents as $categorie => $total) {
            $html .= '
                    <tr>
                        <td>' . ucfirst(str_replace('_', ' ', $categorie)) . '</td>
                        <td>' . $total . '</td>
                    </tr>';
        }
        
    $html .= '
                </tbody>
            </table>';
    } else {
        $html .= '<p>Aucun document enregistré</p>';
}

$html .= '
    </div>
    
    <div class="section">
        <div class="section-title">CONSULTATIONS (' . count($consultations) . ' consultation(s))</div>';

if (!empty($consultations)) {
    $html .= '
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                        <th>Symptômes</th>
                    <th>Diagnostic</th>
                    <th>Traitement</th>
                        <th>Statut</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($consultations as $consultation) {
        $html .= '
                <tr>
                    <td>' . date('d/m/Y', strtotime($consultation['date_consultation'])) . '</td>
                        <td>' . htmlspecialchars($consultation['symptomes'] ?? 'Non renseignés') . '</td>
                    <td>' . htmlspecialchars($consultation['diagnostic'] ?? 'Non renseigné') . '</td>
                    <td>' . htmlspecialchars($consultation['traitement'] ?? 'Non renseigné') . '</td>
                        <td>' . ucfirst($consultation['statut']) . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>';
} else {
    $html .= '<p>Aucune consultation enregistrée</p>';
}

$html .= '
    </div>
    
    <div class="section">
        <div class="section-title">RENDEZ-VOUS (' . count($rendezVous) . ' rendez-vous)</div>';

if (!empty($rendezVous)) {
    $html .= '
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Motif</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($rendezVous as $rdv) {
        $html .= '
                <tr>
                    <td>' . date('d/m/Y', strtotime($rdv['date_rdv'])) . '</td>
                    <td>' . date('H:i', strtotime($rdv['heure_rdv'])) . '</td>
                    <td>' . htmlspecialchars($rdv['motif'] ?? 'Non renseigné') . '</td>
                    <td>' . ucfirst($rdv['statut']) . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>';
} else {
    $html .= '<p>Aucun rendez-vous enregistré</p>';
}

$html .= '
    </div>
    
    <div class="section">
        <div class="section-title">PAIEMENTS (' . count($paiements) . ' paiement(s))</div>';

if (!empty($paiements)) {
    $html .= '
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Montant</th>
                    <th>Mode</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($paiements as $paiement) {
        $html .= '
                <tr>
                    <td>' . date('d/m/Y', strtotime($paiement['date_paiement'])) . '</td>
                    <td>' . number_format($paiement['montant'], 0, ',', ' ') . ' FCFA</td>
                        <td>' . htmlspecialchars($paiement['type_paiement']) . '</td>
                    <td>' . ucfirst($paiement['statut']) . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>';
} else {
    $html .= '<p>Aucun paiement enregistré</p>';
}

$html .= '
    </div>
    
    <div class="footer">
        <p>Ce document a été généré automatiquement par le système de gestion de la clinique</p>
        <p>Pour toute question, veuillez contacter le personnel médical</p>
    </div>
</body>
</html>';

    // Configurer les en-têtes pour l'impression
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="dossier_medical_' . $patient['id'] . '_' . date('Y-m-d') . '.html"');

    // Afficher le contenu HTML pour impression
echo $html;
    exit;
}

// Interface principale avec boutons d'impression
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Génération Dossier Médical - <?php echo htmlspecialchars($patient['nom'] . ' ' . $patient['prenom']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .print-preview { 
            background: white; 
            padding: 20px; 
            margin: 20px 0; 
            border: 1px solid #ddd; 
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .print-preview .header { 
            text-align: center; 
            border-bottom: 2px solid #333; 
            padding-bottom: 20px; 
            margin-bottom: 30px; 
        }
        .print-preview .section { 
            margin-bottom: 25px; 
        }
        .print-preview .section-title { 
            background-color: #f0f0f0; 
            padding: 8px; 
            font-weight: bold; 
            margin-bottom: 15px; 
        }
        .print-preview .info-row { 
            margin-bottom: 8px; 
        }
        .print-preview .label { 
            font-weight: bold; 
            display: inline-block; 
            width: 150px; 
        }
        .print-preview .value { 
            display: inline-block; 
        }
        .print-preview .table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
        }
        .print-preview .table th, 
        .print-preview .table td { 
            border: 1px solid #ddd; 
            padding: 6px; 
            text-align: left; 
        }
        .print-preview .table th { 
            background-color: #f5f5f5; 
            font-weight: bold; 
        }
        .print-preview .footer { 
            margin-top: 40px; 
            text-align: center; 
            font-size: 10px; 
            color: #666; 
        }
        .logo { 
            text-align: center; 
            margin-bottom: 20px; 
        }
        .logo img { 
            margin: 0 auto 15px; 
            display: block; 
        }
        .logo svg { 
            margin: 0 auto 15px; 
            display: block; 
        }
        @media print {
            .no-print { display: none !important; }
            .print-preview { 
                border: none; 
                box-shadow: none; 
                margin: 0; 
                padding: 0; 
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- En-tête avec boutons d'impression -->
        <div class="row mb-4 no-print">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-file-medical text-primary me-2"></i>
                            Génération du Dossier Médical
                        </h1>
                        <p class="text-muted mb-0">
                            Patient: <strong><?php echo htmlspecialchars($patient['nom'] . ' ' . $patient['prenom']); ?></strong>
                        </p>
                    </div>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Imprimer
                        </button>
                        <a href="?patient_id=<?php echo $patient_id; ?>&print=1" target="_blank" class="btn btn-primary">
                            <i class="fas fa-eye me-2"></i>Voir pour Impression
                        </a>
                        <a href="voir.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Retour
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aperçu du dossier -->
        <div class="print-preview">
            <div class="header">
                <?php echo generateLogoHTML($systemParams); ?>
                <h1>DOSSIER MÉDICAL COMPLET</h1>
                <h2><?php echo htmlspecialchars($systemParams->get('nom_etablissement')); ?></h2>
                <p>Généré le <?php echo date('d/m/Y à H:i'); ?></p>
            </div>
            
            <div class="section">
                <div class="section-title">INFORMATIONS PATIENT</div>
                <div class="info-row">
                    <span class="label">Nom complet:</span>
                    <span class="value"><?php echo htmlspecialchars($patient['nom'] . ' ' . $patient['prenom']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Numéro dossier:</span>
                    <span class="value"><?php echo htmlspecialchars($patient['numero_dossier']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Date de naissance:</span>
                    <span class="value"><?php echo date('d/m/Y', strtotime($patient['date_naissance'])) . ' (' . $age . ' ans)'; ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Sexe:</span>
                    <span class="value"><?php echo ($patient['sexe'] === 'M' ? 'Masculin' : 'Féminin'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Groupe sanguin:</span>
                    <span class="value"><?php echo ($patient['groupe_sanguin'] ?: 'Non renseigné'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Téléphone:</span>
                    <span class="value"><?php echo ($patient['telephone'] ?: 'Non renseigné'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Email:</span>
                    <span class="value"><?php echo ($patient['email'] ?: 'Non renseigné'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Adresse:</span>
                    <span class="value"><?php echo ($patient['adresse'] ?: 'Non renseignée') . ', ' . ($patient['ville'] ?: '') . ' ' . ($patient['code_postal'] ?: ''); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Profession:</span>
                    <span class="value"><?php echo ($patient['profession'] ?: 'Non renseignée'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Statut:</span>
                    <span class="value"><?php echo ucfirst($patient['statut']); ?></span>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">ANTÉCÉDENTS MÉDICAUX</div>
                <div class="info-row">
                    <span class="label">Antécédents:</span>
                    <span class="value"><?php echo htmlspecialchars($patient['antecedents_medicaux'] ?? 'Aucun antécédent renseigné'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Allergies:</span>
                    <span class="value"><?php echo htmlspecialchars($patient['allergies'] ?? 'Aucune allergie connue'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Notes:</span>
                    <span class="value"><?php echo htmlspecialchars($patient['notes'] ?? 'Aucune note'); ?></span>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">DOCUMENTS (<?php echo array_sum($documents); ?> document(s))</div>
                <?php if (!empty($documents)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Catégorie</th>
                                <th>Nombre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $categorie => $total): ?>
                                <tr>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $categorie)); ?></td>
                                    <td><?php echo $total; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun document enregistré</p>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <div class="section-title">CONSULTATIONS (<?php echo count($consultations); ?> consultation(s))</div>
                <?php if (!empty($consultations)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Symptômes</th>
                                <th>Diagnostic</th>
                                <th>Traitement</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consultations as $consultation): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($consultation['date_consultation'])); ?></td>
                                    <td><?php echo htmlspecialchars($consultation['symptomes'] ?? 'Non renseignés'); ?></td>
                                    <td><?php echo htmlspecialchars($consultation['diagnostic'] ?? 'Non renseigné'); ?></td>
                                    <td><?php echo htmlspecialchars($consultation['traitement'] ?? 'Non renseigné'); ?></td>
                                    <td><?php echo ucfirst($consultation['statut']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucune consultation enregistrée</p>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <div class="section-title">RENDEZ-VOUS (<?php echo count($rendezVous); ?> rendez-vous)</div>
                <?php if (!empty($rendezVous)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Heure</th>
                                <th>Motif</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rendezVous as $rdv): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($rdv['date_rdv'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($rdv['heure_rdv'])); ?></td>
                                    <td><?php echo htmlspecialchars($rdv['motif'] ?? 'Non renseigné'); ?></td>
                                    <td><?php echo ucfirst($rdv['statut']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun rendez-vous enregistré</p>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <div class="section-title">PAIEMENTS (<?php echo count($paiements); ?> paiement(s))</div>
                <?php if (!empty($paiements)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Montant</th>
                                <th>Mode</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paiements as $paiement): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($paiement['date_paiement'])); ?></td>
                                    <td><?php echo number_format($paiement['montant'], 0, ',', ' ') . ' FCFA'; ?></td>
                                    <td><?php echo htmlspecialchars($paiement['type_paiement']); ?></td>
                                    <td><?php echo ucfirst($paiement['statut']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun paiement enregistré</p>
                <?php endif; ?>
            </div>
            
            <div class="footer">
                <p>Ce document a été généré automatiquement par le système de gestion de la clinique</p>
                <p>Pour toute question, veuillez contacter le personnel médical</p>
            </div>
        </div>

        <!-- Boutons d'action en bas -->
        <div class="row mt-4 no-print">
            <div class="col-12 text-center">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-lg btn-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Imprimer le Dossier
                    </button>
                    <a href="?patient_id=<?php echo $patient_id; ?>&print=1" target="_blank" class="btn btn-lg btn-outline-primary">
                        <i class="fas fa-external-link-alt me-2"></i>Ouvrir pour Impression
                    </a>
                    <a href="voir.php?id=<?php echo $patient_id; ?>" class="btn btn-lg btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Retour au Patient
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-impression si demandé
        if (window.location.search.includes('autoprint=1')) {
            window.print();
        }
        
        // Message de confirmation après impression
        window.addEventListener('afterprint', function() {
            console.log('Impression terminée');
        });
    </script>
</body>
</html>
