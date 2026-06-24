<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('finances'));

require_once __DIR__ . '/../models/Finances.php';

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

    if (isset($_GET['success']) && $_GET['success'] === 'validated') {
        $ecriture = $financesModel->getEcritureById($id);
    }
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

$message = '';
$error = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'validated':
            $message = "L'écriture a été validée avec succès. Les soldes des comptes ont été mis à jour.";
            break;
        case '1':
            $message = 'Opération effectuée avec succès.';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'already_validated':
            $error = 'Cette écriture est déjà validée.';
            break;
        case 'validation_failed':
            $error = 'Erreur lors de la validation de l\'écriture.';
            break;
        case 'user_not_found':
            $error = 'Utilisateur non trouvé.';
            break;
        case 'ecriture_not_found':
            $error = 'Écriture non trouvée.';
            break;
        default:
            $error = urldecode((string) $_GET['error']);
            break;
    }
}

app_module_page_start([
    'active'    => 'finances',
    'title'     => 'Détail Écriture',
    'subtitle'  => 'Écriture n° ' . ($ecriture['numero_ecriture'] ?? ''),
    'icon'      => 'fa-calculator',
    'extra_css' => ['assets/css/app-finances.css'],
]);

$voirToolbar = [
    ['href' => app_url('finances/imprimer_ecriture.php?id=' . $id), 'label' => 'Imprimer', 'icon' => 'fa-print', 'class' => 'btn-outline-secondary', 'target' => '_blank'],
];
if ($auth->peutEcrireFinances()) {
    $voirToolbar[] = ['href' => app_url('finances/modifier_ecriture.php?id=' . $id), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-outline-primary'];
    if (!$ecriture['valide']) {
        $voirToolbar[] = ['href' => app_url('finances/valider_ecriture.php?id=' . $id), 'label' => 'Valider', 'icon' => 'fa-check', 'class' => 'btn-outline-success'];
    }
    $voirToolbar[] = ['href' => app_url('finances/supprimer_ecriture.php?id=' . $id), 'label' => 'Supprimer', 'icon' => 'fa-trash', 'class' => 'btn-outline-danger'];
}
app_module_back_toolbar(app_url('finances/index.php'), 'Retour à la liste', $voirToolbar);
app_module_flash();
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php
$paiementLie = null;
if (!empty($ecriture['reference'])) {
    require_once __DIR__ . '/../models/Paiement.php';
    $paiementIdLie = Paiement::parsePaiementIdFromReference($ecriture['reference']);
    if ($paiementIdLie) {
        $paiementLie = (new Paiement())->getById($paiementIdLie);
    }
}
?>

<div class="fin-panel">
    <div class="fin-panel-head">
        <h2><i class="fas fa-info-circle me-2"></i>Informations de l'écriture</h2>
        <?php if ($ecriture['valide']): ?>
        <span class="mod-badge mod-badge--terminee">Validée</span>
        <?php else: ?>
        <span class="mod-badge mod-badge--en_attente">En attente</span>
        <?php endif; ?>
    </div>
    <div class="fin-panel-body">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="fin-detail-label">Date</div>
                <div class="fin-detail-val"><?= date('d/m/Y', strtotime($ecriture['date_ecriture'])) ?></div>
            </div>
            <div class="col-md-6">
                <div class="fin-detail-label">Montant</div>
                <div class="fin-montant fs-4"><?= number_format((float) $ecriture['montant'], 0, ',', ' ') ?> FCFA</div>
            </div>
            <div class="col-md-6">
                <div class="fin-detail-label">Compte débit</div>
                <div class="fin-detail-val">
                    <span class="fin-compte-tag fin-compte-tag--debit">D</span>
                    <?= htmlspecialchars($ecriture['compte_debit_numero'] ?? '') ?> —
                    <?= htmlspecialchars($ecriture['compte_debit_libelle'] ?? '') ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="fin-detail-label">Compte crédit</div>
                <div class="fin-detail-val">
                    <span class="fin-compte-tag fin-compte-tag--credit">C</span>
                    <?= htmlspecialchars($ecriture['compte_credit_numero'] ?? '') ?> —
                    <?= htmlspecialchars($ecriture['compte_credit_libelle'] ?? '') ?>
                </div>
            </div>
            <?php if (!empty($ecriture['reference'])): ?>
            <div class="col-md-6">
                <div class="fin-detail-label">Référence</div>
                <div class="fin-detail-val"><?= htmlspecialchars($ecriture['reference']) ?></div>
            </div>
            <?php endif; ?>
            <div class="col-12">
                <div class="fin-detail-label">Libellé</div>
                <div class="fin-detail-val"><?= nl2br(htmlspecialchars($ecriture['libelle'])) ?></div>
            </div>
            <div class="col-md-6">
                <div class="fin-detail-label">Créée le</div>
                <div class="fin-detail-val text-muted"><?= date('d/m/Y H:i', strtotime($ecriture['date_creation'])) ?></div>
            </div>
            <?php if (!empty($ecriture['cree_par_nom'])): ?>
            <div class="col-md-6">
                <div class="fin-detail-label">Créée par</div>
                <div class="fin-detail-val text-muted"><?= htmlspecialchars($ecriture['cree_par_nom']) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($paiementLie && $auth->aAccesModule('paiements')): ?>
        <div class="mt-4 pt-3 border-top">
            <div class="fin-detail-label">Paiement source</div>
            <p class="mb-2">
                Facture <?php echo htmlspecialchars($paiementLie['numero_facture'] ?? ''); ?>
                — <?php echo number_format((float) ($paiementLie['montant'] ?? 0), 0, ',', ' '); ?> FCFA
            </p>
            <a href="<?php echo app_url('paiements/voir.php?id=' . (int) $paiementLie['id']); ?>" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-credit-card me-1"></i>Voir le paiement
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php app_module_page_end(); ?>
