<?php
/**
 * Traitement des actions sur les rendez-vous (confirmer, annuler, terminer).
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../models/RendezVous.php';

module_require_roles('rdv');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?error=invalid_method');
    exit;
}

$action = trim($_POST['action'] ?? '');
$rdv_id = (int) ($_POST['rdv_id'] ?? 0);

if (!$rdv_id) {
    header('Location: index.php?error=invalid_id');
    exit;
}

$rdvModel = new RendezVous();
$rdv = $rdvModel->getById($rdv_id);

if (!$rdv) {
    header('Location: index.php?error=rdv_not_found');
    exit;
}

$redirect = 'index.php?error=unknown_action';

try {
    switch ($action) {
        case 'confirmer':
            if ($rdv['statut'] === 'planifie') {
                $redirect = $rdvModel->confirmer($rdv_id)
                    ? 'index.php?success=confirmed'
                    : 'index.php?error=confirmation_failed';
            } else {
                $redirect = 'index.php?error=invalid_status';
            }
            break;

        case 'annuler':
            if (in_array($rdv['statut'], ['planifie', 'confirme'], true)) {
                $redirect = $rdvModel->annuler($rdv_id)
                    ? 'index.php?success=cancelled'
                    : 'index.php?error=cancellation_failed';
            } else {
                $redirect = 'index.php?error=invalid_status';
            }
            break;

        case 'terminer':
            if ($rdv['statut'] === 'confirme') {
                $redirect = $rdvModel->terminer($rdv_id)
                    ? 'index.php?success=completed'
                    : 'index.php?error=completion_failed';
            } else {
                $redirect = 'index.php?error=invalid_status';
            }
            break;
    }
} catch (Exception $e) {
    $redirect = 'index.php?error=system_error';
}

header('Location: ' . $redirect);
exit;
