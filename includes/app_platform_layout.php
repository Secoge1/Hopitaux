<?php
/**
 * Layout partagé — administration plateforme SaaS (vendeur SeSanté).
 */

if (!function_exists('platform_name')) {
    require_once __DIR__ . '/platform_brand.php';
}

if (!function_exists('app_platform_require_admin')) {
    function app_platform_require_admin(): void
    {
        require_once __DIR__ . '/saas/saas_helpers.php';
        saas_require_platform_admin();
    }

    /** Contexte léger — évite getDashboardStats() sur chaque requête admin plateforme. */
    function app_prepare_platform_context(): array
    {
        $auth = Auth::getInstance();
        $auth->requireAuth();
        $utilisateur = $auth->getUtilisateur();
        $notifications = getUserNotifications($utilisateur['id']);
        $unreadCount = getUnreadNotificationCount($utilisateur['id']);

        return app_bind_layout_context([
            'auth' => $auth,
            'utilisateur' => $utilisateur,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'stats' => [],
            'messagesNonLus' => 0,
        ]);
    }

    function app_platform_sections(): array
    {
        return [
            ['key' => 'dashboard', 'href' => 'admin_platform/index.php',    'icon' => 'fa-chart-pie',       'label' => 'Tableau de bord'],
            ['key' => 'branding',  'href' => 'admin_platform/branding.php', 'icon' => 'fa-palette',        'label' => 'Marque & logo'],
            ['key' => 'ia',        'href' => 'admin_platform/ia.php',       'icon' => 'fa-robot',          'label' => 'IA Mistral'],
            ['key' => 'tenants',   'href' => 'admin_platform/tenants.php',  'icon' => 'fa-building',       'label' => 'Établissements'],
            ['key' => 'features',  'href' => 'admin_platform/fonctionnalites.php', 'icon' => 'fa-toggle-on', 'label' => 'Fonctionnalités'],
            ['key' => 'payments',  'href' => 'admin_platform/payments.php',  'icon' => 'fa-money-bill-wave', 'label' => 'Paiements'],
            ['key' => 'billing',   'href' => 'admin_platform/facturation.php', 'icon' => 'fa-file-invoice',   'label' => 'Facturation'],
        ];
    }

    function app_platform_nav_counts(): array
    {
        try {
            $pdo = getDB();
            $pending = (int) $pdo->query(
                "SELECT COUNT(*) FROM subscription_orders WHERE payment_status = 'pending'"
            )->fetchColumn();
            $tenants = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
            $invoices = 0;
            if ($pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_invoices'")->fetchColumn()) {
                $invoices = (int) $pdo->query(
                    "SELECT COUNT(*) FROM subscription_invoices WHERE status = 'issued'"
                )->fetchColumn();
            }
            return ['pending' => $pending, 'tenants' => $tenants, 'invoices' => $invoices];
        } catch (Throwable $e) {
            return ['pending' => 0, 'tenants' => 0];
        }
    }

    /**
     * @return array{username: string, email: string, password: string, has_password: bool}
     */
    function app_platform_tenant_credentials(array $t): array
    {
        $username = trim((string) ($t['admin_username'] ?? ''));
        if ($username === '') {
            $username = trim((string) ($t['nom_utilisateur'] ?? ''));
        }
        $email = trim((string) ($t['admin_email'] ?? $t['email'] ?? ''));
        $password = trim((string) ($t['admin_password_stored'] ?? ''));
        if ($password === '' && !empty($t['is_demo'])) {
            $password = 'demo123';
        }

        return [
            'username' => $username !== '' ? $username : '—',
            'email' => $email,
            'password' => $password,
            'has_password' => $password !== '',
        ];
    }

    function app_platform_credential_cell(array $credentials, string $type = 'username'): string
    {
        if ($type === 'username') {
            $value = $credentials['username'];
            return '<code class="platform-credential">' . htmlspecialchars($value) . '</code>';
        }

        if (!$credentials['has_password']) {
            return '<span class="text-muted small">Non enregistré</span>';
        }

        $masked = str_repeat('•', min(8, max(6, strlen($credentials['password']))));
        return '<span class="platform-credential-secret" data-secret="' . htmlspecialchars($credentials['password']) . '">'
            . '<code class="platform-credential platform-credential--masked">' . $masked . '</code>'
            . '<button type="button" class="btn btn-link btn-sm platform-credential-toggle" title="Afficher / masquer">'
            . '<i class="fas fa-eye"></i></button></span>';
    }

    function app_platform_status_badge(string $status): string
    {
        $map = [
            'active' => 'success',
            'expired' => 'warning',
            'suspended' => 'danger',
            'cancelled' => 'secondary',
        ];
        $cls = $map[$status] ?? 'secondary';
        return '<span class="platform-status platform-status--' . htmlspecialchars($status) . ' badge bg-' . $cls . '">'
            . htmlspecialchars($status) . '</span>';
    }

    function app_platform_shell_start(string $active, string $title, string $subtitle = '', string $actionsHtml = ''): void
    {
        global $notifications, $unreadCount;
        $navCounts = app_platform_nav_counts();

        ?>
<div class="platform-layout">
    <aside class="platform-nav" aria-label="Navigation administration plateforme">
        <div class="platform-nav-head">
            <img src="<?= htmlspecialchars(app_url(platform_logo_path())) ?>" alt="<?= htmlspecialchars(platform_name()) ?>" class="platform-nav-logo" height="28">
            <span>Admin <?= htmlspecialchars(platform_name()) ?></span>
        </div>
        <nav class="platform-nav-list">
            <?php foreach (app_platform_sections() as $sec): ?>
            <a href="<?= htmlspecialchars(app_url($sec['href'])) ?>"
               class="platform-nav-link<?= $active === $sec['key'] ? ' active' : '' ?>">
                <i class="fas <?= htmlspecialchars($sec['icon']) ?>"></i>
                <?= htmlspecialchars($sec['label']) ?>
                <?php if ($sec['key'] === 'payments' && $navCounts['pending'] > 0): ?>
                <span class="platform-nav-badge"><?= (int) $navCounts['pending'] ?></span>
                <?php elseif ($sec['key'] === 'tenants' && $navCounts['tenants'] > 0): ?>
                <span class="platform-nav-badge platform-nav-badge--muted"><?= (int) $navCounts['tenants'] ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <a href="<?= htmlspecialchars(app_url('tarifs.php')) ?>" class="platform-nav-extra" target="_blank" rel="noopener">
            <i class="fas fa-external-link-alt"></i> Page tarifs publique
        </a>
        <a href="<?= htmlspecialchars(app_url('index.php')) ?>" class="platform-nav-back">
            <i class="fas fa-arrow-left"></i> Espace établissement
        </a>
    </aside>
    <div class="platform-main">
        <header class="platform-page-head">
            <div>
                <p class="platform-page-eyebrow">Administration plateforme · <?= htmlspecialchars(platform_company()) ?></p>
                <h1><?= htmlspecialchars($title) ?></h1>
                <?php if ($subtitle !== ''): ?>
                <p><?= htmlspecialchars($subtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="platform-page-actions">
                <?php if ($actionsHtml !== ''): echo $actionsHtml; endif; ?>
                <button type="button" class="platform-icon-btn" data-bs-toggle="collapse" data-bs-target="#notificationsPanel" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if (!empty($unreadCount)): ?>
                    <span class="platform-notif-dot"><?= (int) $unreadCount ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </header>
        <?php
        if (function_exists('app_render_notifications_panel')) {
            app_render_notifications_panel($notifications ?? [], (int) ($unreadCount ?? 0));
        }
        ?>
        <div class="platform-body">
        <?php
    }

    function app_platform_shell_end(): void
    {
        ?>
        </div>
    </div>
</div>
        <?php
    }

    function app_platform_alert(string $message, string $type = 'success'): void
    {
        if ($message === '') {
            return;
        }
        $type = in_array($type, ['success', 'danger', 'warning', 'info'], true) ? $type : 'info';
        ?>
<div class="alert alert-<?= $type ?> alert-dismissible fade show platform-alert" role="alert">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
</div>
        <?php
    }
}
