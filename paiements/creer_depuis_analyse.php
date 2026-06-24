<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/saas/saas_helpers.php';
require_once __DIR__ . '/../models/Paiement.php';
require_once __DIR__ . '/../models/Analyse.php';

module_require_write('paiements');

if (!payment_finance_sync_enabled()) {
    header('Location: ' . app_url('laboratoire/index.php?error=' . urlencode('Synchronisation non activée pour cet établissement.')));
    exit;
}

$analyseId = isset($_REQUEST['analyse_id']) ? (int) $_REQUEST['analyse_id'] : 0;
if ($analyseId < 1) {
    header('Location: ' . app_url('paiements/index.php?error=' . urlencode('Analyse invalide.')));
    exit;
}

$analyseModel = new Analyse();
$analyse = $analyseModel->getById($analyseId);
if (!$analyse) {
    header('Location: ' . app_url('paiements/index.php?error=' . urlencode('Analyse introuvable.')));
    exit;
}

$paiementModel = new Paiement();
$existing = $paiementModel->getByAnalyseId($analyseId);
if ($existing) {
    header('Location: ' . app_url('paiements/voir.php?id=' . (int) $existing['id']));
    exit;
}

try {
    $auth = Auth::getInstance();
    $paiementId = $paiementModel->createFromAnalyse($analyseId, [
        'cree_par' => $auth->getUtilisateur()['id'] ?? null,
    ]);

    if (!$paiementId) {
        throw new RuntimeException('Échec de la création du paiement.');
    }

    header('Location: ' . app_url('paiements/voir.php?id=' . (int) $paiementId . '&created=1'));
    exit;
} catch (Throwable $e) {
    error_log('creer_depuis_analyse #' . $analyseId . ': ' . $e->getMessage());
    $msg = urlencode($e->getMessage());
    header('Location: ' . app_url('laboratoire/voir.php?id=' . $analyseId . '&paiement_error=' . $msg));
    exit;
}
