<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/CurrencyConfig.php';
require_once __DIR__ . '/../includes/pdf_branding.php';
require_once __DIR__ . '/../models/Paiement.php';

$auth = Auth::getInstance();
$auth->requireAuth();
module_require_roles('paiements');

if (!isset($_GET['id']) || $_GET['id'] === '') {
    die('ID du paiement manquant');
}

$paiementId = (int) $_GET['id'];
$print_mode = isset($_GET['print']) && $_GET['print'] === '1';

function fp_type_label(string $type): string
{
    $types = [
        'carte' => 'Carte bancaire',
        'virement' => 'Virement bancaire',
        'especes' => 'Espèces',
        'cheque' => 'Chèque',
        'securite_sociale' => 'Sécurité sociale',
        'mutuelle' => 'Mutuelle',
        'mobile_money' => 'Mobile Money',
        'autre' => 'Autre',
    ];
    return $types[$type] ?? ucfirst($type);
}

function fp_statut_label(string $statut): string
{
    $statuts = [
        'en_attente' => 'En attente',
        'partiel' => 'Paiement partiel',
        'paye' => 'Payé',
        'annule' => 'Annulé',
        'rembourse' => 'Remboursé',
    ];
    return $statuts[$statut] ?? ucfirst($statut);
}

function fp_datetime(?string $date): string
{
    return $date ? date('d/m/Y H:i', strtotime($date)) : 'Non spécifiée';
}

function fp_date(?string $date): string
{
    return $date ? date('d/m/Y', strtotime($date)) : 'Non spécifiée';
}

function fp_logo_html(SystemParameters $systemParams): string
{
    return $systemParams->getPdfLogoHtml(['max_height' => 56, 'max_width' => 140, 'margin' => '0']);
}

function fp_kv(string $label, string $value, bool $strong = false): string
{
    $content = $strong ? '<strong>' . $value . '</strong>' : $value;
    return '<div class="facture-dl-item"><dt>' . htmlspecialchars($label) . '</dt><dd>' . $content . '</dd></div>';
}

function fp_strip_item(string $label, string $value): string
{
    return '<div class="facture-strip-item"><span class="facture-strip-label">' . htmlspecialchars($label) . '</span><span class="facture-strip-value">' . $value . '</span></div>';
}

function fp_block(string $title, string $variant, string $items, string $cols = '3'): string
{
    return '<section class="facture-block facture-block--' . htmlspecialchars($variant) . '">'
        . '<h2 class="facture-block-title">' . htmlspecialchars($title) . '</h2>'
        . '<dl class="facture-dl facture-dl--' . htmlspecialchars($cols) . 'col">' . $items . '</dl>'
        . '</section>';
}

function fp_render_facture(array $paiement, SystemParameters $systemParams): string
{
    $nomClinique = $systemParams->get('nom_etablissement') ?: 'Clinique et Hôpital';
    $adresseClinique = $systemParams->get('adresse') ?: '';
    $villeClinique = $systemParams->get('ville') ?: '';
    $telephoneClinique = $systemParams->get('telephone') ?: '';
    $emailClinique = $systemParams->get('email') ?: '';

    $contactParts = array_filter([
        $adresseClinique ? htmlspecialchars($adresseClinique) : '',
        $villeClinique ? htmlspecialchars($villeClinique) : '',
        $telephoneClinique ? 'Tél. ' . htmlspecialchars($telephoneClinique) : '',
        $emailClinique ? htmlspecialchars($emailClinique) : '',
    ]);

    $statut = htmlspecialchars($paiement['statut']);
    $patientNom = htmlspecialchars(trim(($paiement['patient_nom'] ?? '') . ' ' . ($paiement['patient_prenom'] ?? '')));

    ob_start();
    ?>
<div class="facture-doc facture-doc--a4">
    <header class="facture-doc-head">
        <div class="facture-doc-brand">
            <div class="facture-doc-logo"><?= fp_logo_html($systemParams) ?></div>
            <div class="facture-doc-brand-text">
                <h1><?= htmlspecialchars($nomClinique) ?></h1>
                <?php if ($contactParts): ?>
                <p><?= implode(' · ', $contactParts) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="facture-doc-meta">
            <div class="facture-doc-meta-kicker">Facture de paiement</div>
            <div class="facture-doc-meta-num"><?= htmlspecialchars($paiement['numero_facture']) ?></div>
            <span class="facture-status facture-status--<?= $statut ?>"><?= htmlspecialchars(fp_statut_label($paiement['statut'])) ?></span>
        </div>
    </header>

    <div class="facture-doc-strip">
        <?= fp_strip_item('Date paiement', fp_datetime($paiement['date_paiement'] ?? null)) ?>
        <?= fp_strip_item('Type', htmlspecialchars(fp_type_label($paiement['type_paiement'] ?? ''))) ?>
        <?= fp_strip_item('Devise', CURRENCY_SYMBOL) ?>
        <?= fp_strip_item('Émis le', date('d/m/Y H:i')) ?>
    </div>

    <div class="facture-doc-body">
        <?php
        $patientItems = fp_kv('Nom', $patientNom, true)
            . fp_kv('N° dossier', htmlspecialchars($paiement['numero_dossier'] ?? '—'))
            . fp_kv('Sexe', htmlspecialchars($paiement['sexe'] ?? '—'))
            . fp_kv('Naissance', fp_date($paiement['date_naissance'] ?? null))
            . fp_kv('Téléphone', htmlspecialchars($paiement['telephone'] ?? '—'))
            . fp_kv('Email', htmlspecialchars($paiement['email'] ?? '—'));
        if (!empty($paiement['adresse'])) {
            $patientItems .= fp_kv('Adresse', htmlspecialchars($paiement['adresse']));
        }
        echo fp_block('Patient', 'patient', $patientItems, '3');
        ?>

        <?php if (!empty($paiement['consultation_id'])):
            $consultItems = fp_kv('N° consult.', '#' . (int) $paiement['consultation_id'], true)
                . fp_kv('Date', fp_datetime($paiement['date_consultation'] ?? null))
                . fp_kv('Médecin', htmlspecialchars(medecin_profil_format_joined($paiement)))
                . fp_kv('Spécialité', htmlspecialchars($paiement['specialite'] ?? '—'));
            if (!empty($paiement['diagnostic'])) {
                $consultItems .= fp_kv('Diagnostic', htmlspecialchars($paiement['diagnostic']));
            }
            if (!empty($paiement['traitement'])) {
                $consultItems .= fp_kv('Traitement', htmlspecialchars($paiement['traitement']));
            }
            echo fp_block('Consultation', 'consult', $consultItems, '2');
        endif; ?>

        <?php
        $detailItems = '';
        if (!empty($paiement['reference_paiement'])) {
            $detailItems .= fp_kv('Référence', htmlspecialchars($paiement['reference_paiement']));
        }
        if (!empty($paiement['description'])) {
            $detailItems .= fp_kv('Description', htmlspecialchars($paiement['description']));
        }
        if (!empty($paiement['notes'])) {
            $detailItems .= fp_kv('Notes', htmlspecialchars($paiement['notes']));
        }
        if ($detailItems !== '') {
            echo fp_block('Compléments', 'payment', $detailItems, '2');
        }
        ?>

        <div class="facture-total">
            <div class="facture-total-inner">
                <span class="facture-total-label">Montant total</span>
                <span class="facture-total-value"><?= formatFCFA($paiement['montant']) ?></span>
            </div>
        </div>

        <footer class="facture-doc-foot">
            <p>Document généré automatiquement · <?= htmlspecialchars($nomClinique) ?><?php if ($telephoneClinique): ?> · <?= htmlspecialchars($telephoneClinique) ?><?php endif; ?></p>
        </footer>
    </div>
</div>
    <?php
    return (string) ob_get_clean();
}

try {
    $paiementModel = new Paiement();
    $paiement = $paiementModel->getById($paiementId);

    if (!$paiement) {
        die('Paiement non trouvé');
    }

    $systemParams = pdf_tenant_system_params();
    $factureHtml = fp_render_facture($paiement, $systemParams);
    $cssUrl = htmlspecialchars(app_url('assets/css/facture-paiement.css'));
    $backUrl = htmlspecialchars(app_url('paiements/voir.php?id=' . $paiementId));
    $title = 'Facture ' . htmlspecialchars($paiement['numero_facture']);

    if ($print_mode) {
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= $cssUrl ?>" rel="stylesheet">
</head>
<body class="facture-print-page">
    <div class="facture-print-controls">
        <button type="button" class="btn-fp btn-fp--primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimer</button>
        <a href="<?= $backUrl ?>" class="btn-fp btn-fp--ghost"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
    <div class="facture-preview-wrap facture-preview-wrap--a4">
        <?= $factureHtml ?>
    </div>
    <script>setTimeout(function () { window.print(); }, 300);</script>
</body>
</html>
        <?php
        exit;
    }

    require_once __DIR__ . '/../includes/app_module_layout.php';
    extract(app_module_context('paiements'));

    app_module_page_start([
        'active'    => 'paiements',
        'title'     => 'Facture ' . $paiement['numero_facture'],
        'subtitle'  => 'Facture de paiement',
        'icon'      => 'fa-file-invoice',
        'extra_css' => ['assets/css/facture-paiement.css'],
    ]);
    app_module_back_toolbar(app_url('paiements/voir.php?id=' . $paiementId), 'Retour au paiement', [
        ['href' => app_url('paiements/facture_paiement.php?id=' . $paiementId . '&print=1'), 'label' => 'Imprimer', 'icon' => 'fa-print', 'class' => 'btn-primary'],
    ]);
    app_module_flash();
    ?>
    <div class="facture-preview-wrap">
        <?= $factureHtml ?>
    </div>
    <?php
    app_module_page_end();
} catch (Exception $e) {
    die('Erreur lors de la génération de la facture : ' . $e->getMessage());
}
