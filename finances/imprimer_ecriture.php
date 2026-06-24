<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
module_require_roles('finances');

require_once __DIR__ . '/../models/Finances.php';
require_once __DIR__ . '/../includes/pdf_branding.php';

$financesModel = new Finances();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $ecriture = $financesModel->getEcritureById($id);
    if (!$ecriture) {
        header("Location: index.php");
        exit;
    }
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

$systemParams = pdf_tenant_system_params();

$nomEtablissement = $systemParams->get('nom_etablissement') ?: 'Établissement de Santé';
$adresse = $systemParams->get('adresse') ?: '';
$ville = $systemParams->get('ville') ?: '';
$telephone = $systemParams->get('telephone') ?: '';
$email = $systemParams->get('email') ?: '';

$logoHTML = $systemParams->getPdfLogoBlockHtml(['max_height' => 90, 'max_width' => 320]);

// Styles d'impression
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
    
    .ecriture-container {
        page-break-inside: avoid;
        padding: 0;
    }
    
    .ecriture-header,
    .ecriture-content,
    .ecriture-footer {
        page-break-inside: avoid;
    }
    
    .ecriture-header {
        padding-bottom: 8px;
        margin-bottom: 10px;
        border-bottom-width: 2px;
    }
    
    .ecriture-header .logo img {
        max-height: 40px;
        max-width: 120px;
        margin-bottom: 5px;
    }
    
    .ecriture-header h1 {
        font-size: 18px;
        margin: 5px 0;
    }
    
    .ecriture-header .subtitle {
        font-size: 12px;
        margin-top: 2px;
    }
    
    .ecriture-header .info {
        font-size: 9px;
        margin-top: 5px;
    }
    
    .info-section {
        margin-bottom: 8px;
    }
    
    .info-row {
        margin-bottom: 3px;
        font-size: 9px;
    }
    
    .label {
        width: 140px;
        font-size: 9px;
        font-weight: bold;
    }
    
    .value {
        font-size: 9px;
    }
    
    .comptes-info {
        padding: 8px;
        margin-bottom: 8px;
        border: 1px solid #ddd;
    }
    
    .comptes-info h3 {
        font-size: 11px;
        margin-top: 0;
        margin-bottom: 5px;
    }
    
    .two-columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    
    .montant-box {
        padding: 10px;
        margin: 15px 0;
        background-color: #f8f9fa;
        border: 2px solid #007bff;
        text-align: center;
    }
    
    .montant-box .montant-label {
        font-size: 10px;
        margin-bottom: 3px;
        color: #666;
    }
    
    .montant-box .montant-value {
        font-size: 20px;
        font-weight: bold;
        color: #007bff;
    }
    
    .ecriture-footer {
        margin-top: 10px;
        padding-top: 8px;
        font-size: 8px;
        border-top: 1px solid #ddd;
    }
    
    .status-badge {
        padding: 2px 6px;
        font-size: 8px;
        border-radius: 3px;
    }
    
    * {
        -webkit-print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
}

@media screen {
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 20px;
        background-color: #f5f5f5;
    }
    
    .ecriture-container {
        max-width: 800px;
        margin: 0 auto;
        background: white;
        padding: 30px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    
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
        font-size: 14px;
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

.ecriture-header {
    text-align: center;
    border-bottom: 3px solid #007bff;
    padding-bottom: 20px;
    margin-bottom: 30px;
}

.ecriture-header .logo {
    margin-bottom: 15px;
}

.ecriture-header .logo img {
    max-height: 80px;
    max-width: 200px;
    object-fit: contain;
    margin: 0 auto;
    display: block;
}

.ecriture-header h1 {
    margin: 10px 0 0 0;
    color: #007bff;
    font-size: 28px;
}

.ecriture-header .subtitle {
    color: #666;
    font-size: 16px;
    margin-top: 5px;
}

.ecriture-header .info {
    margin-top: 10px;
    font-size: 12px;
    color: #666;
}

.info-section {
    margin-bottom: 20px;
}

.info-row {
    margin-bottom: 8px;
    display: flex;
}

.label {
    font-weight: bold;
    color: #333;
    width: 180px;
    flex-shrink: 0;
}

.value {
    color: #666;
    flex: 1;
}

.comptes-info {
    background-color: #e3f2fd;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    border-left: 4px solid #2196F3;
}

.comptes-info h3 {
    margin-top: 0;
    color: #2196F3;
    font-size: 18px;
}

.two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.montant-box {
    padding: 20px;
    margin: 20px 0;
    background-color: #f8f9fa;
    border: 2px solid #007bff;
    border-radius: 5px;
    text-align: center;
}

.montant-box .montant-label {
    font-size: 14px;
    margin-bottom: 5px;
    color: #666;
}

.montant-box .montant-value {
    font-size: 32px;
    font-weight: bold;
    color: #007bff;
}

.ecriture-footer {
    margin-top: 30px;
    padding-top: 15px;
    font-size: 11px;
    color: #666;
    border-top: 1px solid #ddd;
    text-align: center;
}

.status-badge {
    padding: 4px 8px;
    font-size: 12px;
    border-radius: 4px;
    display: inline-block;
}

.status-validated {
    background-color: #28a745;
    color: white;
}

.status-pending {
    background-color: #ffc107;
    color: #333;
}
</style>
CSS;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Écriture Comptable - <?php echo htmlspecialchars($ecriture['numero_ecriture']); ?></title>
    <?php echo $printStyles; ?>
</head>
<body>
    <div class="print-controls">
        <button onclick="window.print()">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <button onclick="window.close()">
            <i class="fas fa-times"></i> Fermer
        </button>
    </div>

    <div class="ecriture-container">
        <div class="ecriture-header">
            <?php echo $logoHTML; ?>
            <h1>ÉCRITURE COMPTABLE</h1>
            <div class="subtitle"><?php echo htmlspecialchars($nomEtablissement); ?></div>
            <div class="info">
                <?php if ($adresse): ?>
                    <?php echo htmlspecialchars($adresse); ?><br>
                <?php endif; ?>
                <?php if ($ville): ?>
                    <?php echo htmlspecialchars($ville); ?><br>
                <?php endif; ?>
                <?php if ($telephone): ?>
                    Tél: <?php echo htmlspecialchars($telephone); ?><br>
                <?php endif; ?>
                <?php if ($email): ?>
                    Email: <?php echo htmlspecialchars($email); ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="ecriture-content">
            <div class="info-section">
                <div class="info-row">
                    <span class="label">Numéro d'écriture:</span>
                    <span class="value"><?php echo htmlspecialchars($ecriture['numero_ecriture']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Date de l'écriture:</span>
                    <span class="value"><?php echo date('d/m/Y', strtotime($ecriture['date_ecriture'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Statut:</span>
                    <span class="value">
                        <span class="status-badge <?php echo $ecriture['valide'] ? 'status-validated' : 'status-pending'; ?>">
                            <?php echo $ecriture['valide'] ? 'Validée' : 'En attente'; ?>
                        </span>
                    </span>
                </div>
                <?php if ($ecriture['reference']): ?>
                <div class="info-row">
                    <span class="label">Référence:</span>
                    <span class="value"><?php echo htmlspecialchars($ecriture['reference']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="comptes-info">
                <h3><i class="fas fa-book"></i> Comptes</h3>
                <div class="two-columns">
                    <div>
                        <div class="info-row">
                            <span class="label">Compte Débit:</span>
                        </div>
                        <div class="info-row">
                            <span class="value">
                                <strong><?php echo htmlspecialchars($ecriture['compte_debit_numero'] ?? ''); ?></strong><br>
                                <?php echo htmlspecialchars($ecriture['compte_debit_libelle'] ?? ''); ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="info-row">
                            <span class="label">Compte Crédit:</span>
                        </div>
                        <div class="info-row">
                            <span class="value">
                                <strong><?php echo htmlspecialchars($ecriture['compte_credit_numero'] ?? ''); ?></strong><br>
                                <?php echo htmlspecialchars($ecriture['compte_credit_libelle'] ?? ''); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="montant-box">
                <div class="montant-label">Montant</div>
                <div class="montant-value"><?php echo number_format($ecriture['montant'], 0, ',', ' '); ?> FCFA</div>
            </div>

            <div class="info-section">
                <div class="info-row">
                    <span class="label">Libellé:</span>
                </div>
                <div class="value" style="margin-top: 5px; padding: 10px; background-color: #f8f9fa; border-radius: 3px;">
                    <?php echo nl2br(htmlspecialchars($ecriture['libelle'])); ?>
                </div>
            </div>

            <div class="info-section">
                <div class="two-columns">
                    <div>
                        <div class="info-row">
                            <span class="label">Créée le:</span>
                            <span class="value"><?php echo date('d/m/Y H:i', strtotime($ecriture['date_creation'])); ?></span>
                        </div>
                        <?php if ($ecriture['cree_par_nom']): ?>
                        <div class="info-row">
                            <span class="label">Créée par:</span>
                            <span class="value"><?php echo htmlspecialchars($ecriture['cree_par_nom']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($ecriture['valide'] && isset($ecriture['date_validation'])): ?>
                    <div>
                        <div class="info-row">
                            <span class="label">Validée le:</span>
                            <span class="value"><?php echo date('d/m/Y H:i', strtotime($ecriture['date_validation'])); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="ecriture-footer">
            <p>Document généré le <?php echo date('d/m/Y à H:i'); ?> - <?php echo htmlspecialchars($nomEtablissement); ?></p>
            <p style="font-size: 9px; color: #999; margin-top: 5px;">
                Ce document est un original généré par le système de gestion.
            </p>
        </div>
    </div>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        // Auto-impression optionnelle (décommenter si nécessaire)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>
