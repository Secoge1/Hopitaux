<?php

/**

 * Traitement POST partagé — admin plateforme.

 */

require_once __DIR__ . '/../includes/app_urls.php';

require_once __DIR__ . '/../includes/saas/SubscriptionCheckout.php';

require_once __DIR__ . '/../includes/saas/SubscriptionPlan.php';

require_once __DIR__ . '/../includes/saas/SubscriptionService.php';

require_once __DIR__ . '/../includes/PlatformBranding.php';

if (!function_exists('admin_platform_redirect_after_post')) {
    function admin_platform_redirect_after_post(string $page, string $message, string $messageType): void
    {
        $flashType = $messageType === 'danger' ? 'error' : $messageType;
        redirectWithMessage(app_url('admin_platform/' . $page), $message, $flashType);
    }
}

function admin_platform_handle_post(): array

{

    $message = '';

    $messageType = 'success';



    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        return compact('message', 'messageType');

    }



    $checkout = new SubscriptionCheckout();



    if (isset($_POST['confirm_payment'])) {

        $orderId = (int) ($_POST['order_id'] ?? 0);

        $order = $checkout->getOrder($orderId);

        if (!$order) {

            $message = 'Commande introuvable.';

            $messageType = 'danger';

        } elseif (($order['payment_status'] ?? '') !== 'pending') {

            $message = 'Seules les commandes en attente peuvent être confirmées.';

            $messageType = 'warning';

        } else {

            $result = $checkout->markPaidManually($orderId, $_POST['payment_method'] ?? '');

            if (!empty($result['success'])) {

                $ref = $order['ref_command'] ?? '';

                $tenantId = (int) ($result['tenant_id'] ?? $order['tenant_id'] ?? 0);

                $message = 'Paiement confirmé';

                if ($ref !== '') {

                    $message .= ' — réf. ' . $ref;

                }

                if ($tenantId > 0) {

                    $message .= ' (établissement #' . $tenantId . ' activé)';

                }

                if (!empty($result['invoice_number'])) {

                    $message .= ' — facture ' . $result['invoice_number'] . ' générée';

                }

                $message .= '.';

                $messageType = 'success';

            } else {

                $message = $result['message'] ?? 'Erreur lors de la confirmation.';

                $messageType = 'danger';

            }

        }

    } elseif (isset($_POST['update_tenant'])) {

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);

        $pdo = getDB();

        $expires = trim($_POST['expires_at'] ?? '');

        $expiresVal = $expires !== '' ? $expires : null;

        $licenseType = SubscriptionPlan::normalizeSlug($_POST['license_type'] ?? 'annual');

        try {

            $stmt = $pdo->prepare(

                'UPDATE tenants SET status = ?, license_type = ?, expires_at = ?, max_users = ?, company_name = ? WHERE id = ?'

            );

            $stmt->execute([

                $_POST['status'] ?? 'active',

                $licenseType,

                $expiresVal,

                (int) ($_POST['max_users'] ?? 15),

                trim($_POST['company_name'] ?? ''),

                $tenantId,

            ]);

            SubscriptionService::getInstance()->syncTenantToPlan($tenantId, $licenseType, $pdo);

            $message = 'Établissement mis à jour.';

        } catch (PDOException $e) {

            error_log('update_tenant: ' . $e->getMessage());

            if ((int) ($e->errorInfo[1] ?? 0) === 1205) {

                $message = 'La base de données est occupée. Réessayez dans quelques secondes.';

            } else {

                $message = 'Erreur lors de la mise à jour de l\'établissement.';

            }

            $messageType = 'danger';

        }

    } elseif (isset($_POST['extend_tenant'])) {

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);

        $years = max(1, (int) ($_POST['extend_years'] ?? 1));

        $pdo = getDB();

        $stmt = $pdo->prepare('SELECT expires_at, license_type FROM tenants WHERE id = ?');

        $stmt->execute([$tenantId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {

            $base = !empty($row['expires_at']) ? max(strtotime($row['expires_at']), time()) : time();

            $newExp = date('Y-m-d', strtotime('+' . $years . ' year', $base));

            $licenseType = SubscriptionPlan::normalizeSlug($row['license_type'] ?? SubscriptionPlan::ANNUAL);

            $pdo->prepare("UPDATE tenants SET expires_at = ?, status = 'active', license_type = ? WHERE id = ?")

                ->execute([$newExp, $licenseType, $tenantId]);

            $message = 'Licence prolongée de ' . $years . ' an(s) — expiration au ' . date('d/m/Y', strtotime($newExp)) . '.';

        }

    } elseif (isset($_POST['set_tenant_status'])) {

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);

        $status = $_POST['status'] ?? 'active';

        if (in_array($status, ['active', 'suspended', 'expired', 'cancelled'], true)) {

            getDB()->prepare('UPDATE tenants SET status = ? WHERE id = ?')->execute([$status, $tenantId]);

            $message = 'Statut de l\'établissement mis à jour.';

        }

    } elseif (isset($_POST['cancel_order'])) {

        $orderId = (int) ($_POST['order_id'] ?? 0);

        $pdo = getDB();

        $stmt = $pdo->prepare("SELECT payment_status FROM subscription_orders WHERE id = ?");

        $stmt->execute([$orderId]);

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {

            $message = 'Commande introuvable.';

            $messageType = 'danger';

        } elseif ($order['payment_status'] !== 'pending') {

            $message = 'Seules les commandes en attente peuvent être annulées.';

            $messageType = 'warning';

        } else {

            $pdo->prepare("UPDATE subscription_orders SET payment_status = 'cancelled' WHERE id = ?")

                ->execute([$orderId]);

            $message = 'Commande annulée.';

        }

    } elseif (isset($_POST['reset_tenant_password'])) {

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);

        $pdo = getDB();

        try {

            $stmt = $pdo->prepare(

                "SELECT id FROM utilisateurs WHERE tenant_id = ? AND role = 'admin' AND statut = 'actif' ORDER BY id ASC LIMIT 1"

            );

            $stmt->execute([$tenantId]);

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {

                $message = 'Aucun compte administrateur actif pour cet établissement.';

                $messageType = 'danger';

            } else {

                $newPassword = substr(bin2hex(random_bytes(4)), 0, 8);

                $hash = password_hash($newPassword, PASSWORD_DEFAULT);

                $pdo->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?')

                    ->execute([$hash, (int) $admin['id']]);

                $cols = $pdo->query(

                    "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()

                     AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'admin_login_password'"

                )->fetchColumn();

                if ($cols) {

                    $pdo->prepare('UPDATE tenants SET admin_login_password = ? WHERE id = ?')

                        ->execute([$newPassword, $tenantId]);

                }

                $message = 'Nouveau mot de passe généré : ' . $newPassword;

                $messageType = 'success';

            }

        } catch (Throwable $e) {

            error_log('reset_tenant_password: ' . $e->getMessage());

            $message = 'Impossible de réinitialiser le mot de passe.';

            $messageType = 'danger';

        }

    } elseif (isset($_POST['update_platform_branding'])) {
        try {
            $name = trim($_POST['platform_name'] ?? '');
            PlatformBranding::updateName($name);
            $message = 'Nom de la plateforme mis à jour.';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $messageType = 'danger';
        }
    } elseif (isset($_POST['upload_platform_logo'])) {
        if (!isset($_FILES['platform_logo']) || ($_FILES['platform_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $message = 'Veuillez sélectionner un fichier image.';
            $messageType = 'danger';
        } else {
            $result = PlatformBranding::uploadLogo($_FILES['platform_logo']);
            $message = $result['message'];
            $messageType = !empty($result['success']) ? 'success' : 'danger';
        }
    } elseif (isset($_POST['remove_platform_logo'])) {
        try {
            PlatformBranding::removeLogo();
            $message = 'Logo plateforme réinitialisé (logo par défaut).';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $messageType = 'danger';
        }
    } elseif (isset($_POST['delete_tenant'])) {

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);

        if ($tenantId <= 1) {

            $message = 'Impossible de supprimer l\'établissement principal de la plateforme.';

            $messageType = 'danger';

        } else {

            $pdo = getDB();

            try {

                $pdo->beginTransaction();

                $pdo->prepare('DELETE FROM subscription_orders WHERE tenant_id = ?')->execute([$tenantId]);

                $pdo->prepare('DELETE FROM utilisateurs WHERE tenant_id = ?')->execute([$tenantId]);

                $pdo->prepare('DELETE FROM tenants WHERE id = ?')->execute([$tenantId]);

                $pdo->commit();

                $message = 'Établissement supprimé définitivement.';

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {

                    $pdo->rollBack();

                }

                error_log('delete_tenant: ' . $e->getMessage());

                $message = 'Erreur lors de la suppression. Des données liées empêchent peut-être la suppression.';

                $messageType = 'danger';

            }

        }

    } elseif (isset($_POST['toggle_tenant_feature'])) {

        require_once __DIR__ . '/../includes/saas/PlatformTenantFeatures.php';

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $featureKey = trim((string) ($_POST['feature_key'] ?? ''));
        $enabled = !empty($_POST['enabled']);
        $auth = Auth::getInstance();
        $userId = (int) ($auth->getUtilisateur()['id'] ?? 0);

        $labels = PlatformTenantFeatures::featureLabels();
        if ($tenantId < 1 || !isset($labels[$featureKey])) {
            $message = 'Paramètres invalides.';
            $messageType = 'danger';
        } else {
            PlatformTenantFeatures::setEnabled($tenantId, $featureKey, $enabled, $userId ?: null);
            $message = $enabled
                ? 'Fonctionnalité activée pour l\'établissement.'
                : 'Fonctionnalité désactivée pour l\'établissement.';
        }

    } elseif (isset($_POST['toggle_all_tenant_feature'])) {

        require_once __DIR__ . '/../includes/saas/PlatformTenantFeatures.php';

        $featureKey = trim((string) ($_POST['feature_key'] ?? ''));
        $enabled = !empty($_POST['enabled']);
        $auth = Auth::getInstance();
        $userId = (int) ($auth->getUtilisateur()['id'] ?? 0);
        $labels = PlatformTenantFeatures::featureLabels();

        if (!isset($labels[$featureKey])) {
            $message = 'Fonctionnalité inconnue.';
            $messageType = 'danger';
        } else {
            $pdo = getDB();
            $tenantIds = $pdo->query('SELECT id FROM tenants ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tenantIds as $tid) {
                PlatformTenantFeatures::setEnabled((int) $tid, $featureKey, $enabled, $userId ?: null);
            }
            $message = $enabled
                ? 'Fonctionnalité activée pour tous les établissements.'
                : 'Fonctionnalité désactivée pour tous les établissements.';
        }

    }

    $redirectPage = null;

    if (
        isset($_POST['update_platform_branding'])
        || isset($_POST['upload_platform_logo'])
        || isset($_POST['remove_platform_logo'])
    ) {
        $redirectPage = 'branding.php';
    } elseif (isset($_POST['confirm_payment']) || isset($_POST['cancel_order'])) {

        $redirectPage = 'payments.php';

    } elseif (

        isset($_POST['update_tenant'])

        || isset($_POST['extend_tenant'])

        || isset($_POST['set_tenant_status'])

        || isset($_POST['reset_tenant_password'])

        || isset($_POST['delete_tenant'])

    ) {

        $redirectPage = 'tenants.php';

    } elseif (isset($_POST['toggle_tenant_feature']) || isset($_POST['toggle_all_tenant_feature'])) {

        $redirectPage = 'fonctionnalites.php';

    }

    if ($redirectPage !== null && $message !== '') {

        admin_platform_redirect_after_post($redirectPage, $message, $messageType);

    }

    return compact('message', 'messageType');

}

