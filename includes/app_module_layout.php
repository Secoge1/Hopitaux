<?php
/**
 * Helpers layout pour les pages modules métier (shell SeSanté).
 */

require_once __DIR__ . '/app_layout.php';
require_once __DIR__ . '/module_guard.php';
require_once __DIR__ . '/app_module_list.php';
require_once __DIR__ . '/staff_scope.php';
require_once __DIR__ . '/payment_sync_badge.php';

if (!function_exists('app_module_context')) {
    /**
     * Auth module + contexte layout (sidebar, notifications, stats).
     */
    function app_module_context(string $moduleKey): array
    {
        module_require_roles($moduleKey);
        StaffScope::flashIfUnlinked();
        $ctx = app_prepare_context();
        foreach ($ctx as $key => $value) {
            $GLOBALS[$key] = $value;
        }
        return $ctx;
    }

    /**
     * Début de page module : head + sidebar + en-tête.
     *
     * @param array{
     *   active?: string,
     *   title: string,
     *   subtitle?: string,
     *   icon?: string,
     *   extra_css?: list<string>,
     *   body_class?: string,
     *   show_refresh?: bool,
     *   skip_page_header?: bool,
     *   skip_notifications?: bool
     * } $opts
     */
    function app_module_page_start(array $opts): void
    {
        global $auth, $utilisateur, $notifications, $unreadCount, $stats, $messagesNonLus;

        $extraCss = $opts['extra_css'] ?? [];
        $bodyClass = trim('app-module-page ' . ($opts['body_class'] ?? ''));

        app_head($opts['title'], $extraCss, $bodyClass);
        app_layout_start([
            'active'             => $opts['active'] ?? '',
            'icon'               => $opts['icon'] ?? 'fa-layer-group',
            'title'              => $opts['title'],
            'subtitle'           => $opts['subtitle'] ?? '',
            'show_refresh'       => !empty($opts['show_refresh']),
            'skip_page_header'   => !empty($opts['skip_page_header']),
            'skip_notifications' => !empty($opts['skip_notifications']),
        ]);

        echo '<div class="app-module-body">';
        app_payment_sync_global_banner($opts['active'] ?? '');
    }

    /**
     * Barre d'actions sous l'en-tête (boutons Ajouter, etc.).
     *
     * @param list<array{href: string, label: string, icon?: string, class?: string}> $actions
     */
    function app_module_toolbar(array $actions): void
    {
        if (empty($actions)) {
            return;
        }
        ?>
<div class="app-module-toolbar d-flex flex-wrap gap-2 mb-4">
    <?php foreach ($actions as $action):
        $btnClass = $action['class'] ?? 'btn-primary';
        $icon = $action['icon'] ?? 'fa-plus';
        $target = !empty($action['target']) ? ' target="' . htmlspecialchars($action['target']) . '"' : '';
        if (!empty($action['target'])) {
            $target .= ' rel="noopener noreferrer"';
        }
        ?>
    <a href="<?= htmlspecialchars($action['href']) ?>" class="btn <?= htmlspecialchars($btnClass) ?> btn-sm"<?= $target ?>>
        <i class="fas <?= htmlspecialchars($icon) ?> me-1"></i><?= htmlspecialchars($action['label']) ?>
    </a>
    <?php endforeach; ?>
</div>
        <?php
    }

    function app_module_flash(): void
    {
        $flash = displayFlashMessages();
        if ($flash !== '') {
            echo $flash;
        }
    }

    /**
     * Toolbar standard sous-page : retour liste + actions optionnelles.
     *
     * @param list<array{href: string, label: string, icon?: string, class?: string}> $extra
     */
    function app_module_back_toolbar(string $listHref, string $listLabel = 'Retour à la liste', array $extra = []): void
    {
        $actions = array_merge([
            ['href' => $listHref, 'label' => $listLabel, 'icon' => 'fa-arrow-left', 'class' => 'btn-outline-secondary'],
        ], $extra);
        app_module_toolbar($actions);
    }

    function app_module_page_end(array $opts = []): void
    {
        echo '</div><!-- .app-module-body -->';
        app_layout_end(array_merge(['stats_mode' => 'refresh'], $opts));
    }
}
