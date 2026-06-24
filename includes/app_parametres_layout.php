<?php
/**
 * Layout partagé — section Paramètres (parametres/*)
 */

if (!function_exists('app_parametres_require_admin')) {
    function app_parametres_require_admin(): void
    {
        app_parametres_require_user();
        $auth = Auth::getInstance();
        if (!$auth->estAdmin()) {
            header('Location: ' . app_url('parametres/guide_utilisateurs.php'));
            exit;
        }
    }

    /** Utilisateur connecté avec tenant (guide accessible à tous les rôles). */
    function app_parametres_require_user(): void
    {
        $auth = Auth::getInstance();
        $auth->requireAuth();

        if (!function_exists('saas_is_platform_admin')) {
            require_once __DIR__ . '/saas/saas_helpers.php';
        }
        if (saas_is_platform_admin() && !$auth->getTenantId()) {
            header('Location: ' . app_url('admin_platform/index.php'));
            exit;
        }
        if (!$auth->getTenantId() && !saas_is_platform_admin()) {
            header('Location: ' . app_url('index.php?error=no_tenant'));
            exit;
        }
    }

    function app_parametres_sections(): array
    {
        return [
            ['key' => 'guide',        'href' => 'parametres/guide_utilisateurs.php',           'icon' => 'fa-book',              'label' => 'Guide utilisateur', 'all_roles' => true],
            ['key' => 'general',      'href' => 'parametres/index.php',                          'icon' => 'fa-sliders-h',         'label' => 'Général'],
            ['key' => 'utilisateurs', 'href' => 'parametres/utilisateurs.php',                   'icon' => 'fa-users',             'label' => 'Utilisateurs'],
            ['key' => 'droits',       'href' => 'parametres/droits_acces.php',                 'icon' => 'fa-shield-alt',        'label' => 'Droits d\'accès'],
            ['key' => 'tarifs',       'href' => 'parametres/tarifs.php',                         'icon' => 'fa-tags',              'label' => 'Tarifs'],
            ['key' => 'soins',        'href' => 'parametres/soins.php',                          'icon' => 'fa-hand-holding-medical','label' => 'Soins'],
            ['key' => 'tarifs_labo',  'href' => 'parametres/tarifs_laboratoire.php',           'icon' => 'fa-flask',             'label' => 'Tarifs labo'],
            ['key' => 'sauvegardes',  'href' => 'parametres/sauvegardes.php',                    'icon' => 'fa-database',          'label' => 'Sauvegardes'],
            ['key' => 'import_donnees', 'href' => 'parametres/import_donnees.php',               'icon' => 'fa-file-import',       'label' => 'Import de données'],
            ['key' => 'journaux',     'href' => 'parametres/journaux.php',                       'icon' => 'fa-clipboard-list',    'label' => 'Journaux'],
            ['key' => 'ia',           'href' => 'parametres/ia.php',                             'icon' => 'fa-robot',             'label' => 'IA (statut)'],
        ];
    }

    /** Sections visibles selon le rôle (non-admin : guide uniquement). */
    function app_parametres_sections_for_user(): array
    {
        $auth = Auth::getInstance();
        $sections = app_parametres_sections();
        if ($auth->estAdmin()) {
            return $sections;
        }
        return array_values(array_filter($sections, static function (array $sec): bool {
            return !empty($sec['all_roles']);
        }));
    }

    function app_parametres_shell_start(string $active, string $title, string $subtitle = '', string $actionsHtml = ''): void
    {
        global $notifications, $unreadCount;
        ?>
<div class="param-layout">
    <aside class="param-nav" aria-label="Navigation paramètres">
        <div class="param-nav-head">
            <i class="fas fa-cog"></i>
            <span>Paramètres</span>
        </div>
        <nav class="param-nav-list">
            <?php foreach (app_parametres_sections_for_user() as $sec): ?>
            <a href="<?= htmlspecialchars(app_url($sec['href'])) ?>"
               class="param-nav-link<?= $active === $sec['key'] ? ' active' : '' ?>">
                <i class="fas <?= htmlspecialchars($sec['icon']) ?>"></i>
                <?= htmlspecialchars($sec['label']) ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <a href="<?= htmlspecialchars(app_url('index.php')) ?>" class="param-nav-back">
            <i class="fas fa-arrow-left"></i> Retour à l'accueil
        </a>
    </aside>
    <div class="param-main">
        <header class="param-page-head">
            <div>
                <h1><?= htmlspecialchars($title) ?></h1>
                <?php if ($subtitle !== ''): ?>
                <p><?= htmlspecialchars($subtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="param-page-actions">
                <?php if ($actionsHtml !== ''): echo $actionsHtml; endif; ?>
                <button type="button" class="param-icon-btn" data-bs-toggle="collapse" data-bs-target="#notificationsPanel" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if (!empty($unreadCount)): ?>
                    <span class="param-notif-dot"><?= (int) $unreadCount ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </header>
        <?php
        if (function_exists('app_render_notifications_panel')) {
            app_render_notifications_panel($notifications ?? [], (int) ($unreadCount ?? 0));
        }
        ?>
        <div class="param-body">
        <?php
    }

    function app_parametres_shell_end(): void
    {
        ?>
        </div>
    </div>
</div>
        <?php
    }

    function app_parametres_alert(string $message, string $type = 'success'): void
    {
        if ($message === '') {
            return;
        }
        $type = in_array($type, ['success', 'danger', 'warning', 'info'], true) ? $type : 'info';
        ?>
<div class="alert alert-<?= $type ?> alert-dismissible fade show param-alert" role="alert">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
</div>
        <?php
    }
}
