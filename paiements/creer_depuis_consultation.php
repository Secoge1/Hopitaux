<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/saas/saas_helpers.php';
require_once __DIR__ . '/../models/Paiement.php';
require_once __DIR__ . '/../models/Consultation.php';

module_require_write('paiements');

if (!payment_finance_sync_enabled()) {
    header('Location: ' . app_url('consultations/index.php?error=' . urlencode('Synchronisation non activée pour cet établissement.')));
    exit;
}

$consultationId = isset($_REQUEST['consultation_id']) ? (int) $_REQUEST['consultation_id'] : 0;
if ($consultationId < 1) {
    header('Location: ' . app_url('paiements/index.php?error=' . urlencode('Consultation invalide.')));
    exit;
}

$consultationModel = new Consultation();
$consultation = $consultationModel->getById($consultationId);
if (!$consultation) {
    header('Location: ' . app_url('paiements/index.php?error=' . urlencode('Consultation introuvable.')));
    exit;
}

$paiementModel = new Paiement();
$existing = $paiementModel->getByConsultationId($consultationId);
if ($existing) {
    header('Location: ' . app_url('paiements/voir.php?id=' . (int) $existing['id']));
    exit;
}

try {
    $auth = Auth::getInstance();
    $paiementId = $paiementModel->createFromConsultation($consultationId, [
        'cree_par' => $auth->getUtilisateur()['id'] ?? null,
    ]);

    if (!$paiementId) {
        throw new RuntimeException('Échec de la création du paiement.');
    }

    header('Location: ' . app_url('paiements/voir.php?id=' . (int) $paiementId . '&created=1'));
    exit;
} catch (Throwable $e) {
    error_log('creer_depuis_consultation #' . $consultationId . ': ' . $e->getMessage());
    $msg = urlencode($e->getMessage());
    header('Location: ' . app_url('consultations/voir.php?id=' . $consultationId . '&paiement_error=' . $msg));
    exit;
}
