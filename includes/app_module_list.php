<?php
/**
 * Composants réutilisables pour les listes modules (KPIs, filtres, tableau, pagination).
 */

if (!function_exists('app_mod_stats')) {
    /**
     * @param list<array{value: string|int, label: string, icon?: string, mod?: string, id?: string}> $items
     */
    function app_mod_stats(array $items, string $extraClass = ''): void
    {
        $cls = trim('app-mod-stats mb-4 ' . $extraClass);
        echo '<div class="' . htmlspecialchars($cls) . '">';
        foreach ($items as $item) {
            $mod = !empty($item['mod']) ? ' app-mod-stat--' . $item['mod'] : '';
            $id = !empty($item['id']) ? ' id="' . htmlspecialchars($item['id']) . '"' : '';
            echo '<div class="app-mod-stat' . $mod . '">';
            echo '<div class="app-mod-stat-val"' . $id . '>' . htmlspecialchars((string) $item['value']) . '</div>';
            echo '<div class="app-mod-stat-label">';
            if (!empty($item['icon'])) {
                echo '<i class="fas ' . htmlspecialchars($item['icon']) . ' me-1 text-muted"></i>';
            }
            echo htmlspecialchars($item['label']);
            if (!empty($item['hint'])) {
                echo '<small class="d-block text-muted mt-1" style="font-size:0.75rem">' . htmlspecialchars($item['hint']) . '</small>';
            }
            echo '</div></div>';
        }
        echo '</div>';
    }
}

if (!function_exists('app_mod_badge')) {
    function app_mod_badge(string $statut, ?string $label = null): string
    {
        $key = preg_replace('/[^a-z0-9_]/', '_', strtolower($statut));
        $text = $label ?? ucfirst(str_replace('_', ' ', $statut));
        return '<span class="mod-badge mod-badge--' . htmlspecialchars($key) . '">' . htmlspecialchars($text) . '</span>';
    }
}

if (!function_exists('app_mod_action_tone')) {
    /** @param array<string, mixed> $item */
    function app_mod_action_tone(array $item): string
    {
        if (!empty($item['tone']) && is_string($item['tone'])) {
            return preg_replace('/[^a-z]/', '', $item['tone']) ?: 'neutral';
        }
        $class = (string) ($item['class'] ?? '');
        if (strpos($class, 'text-danger') !== false) {
            return 'danger';
        }
        if (strpos($class, 'text-success') !== false) {
            return 'success';
        }
        if (strpos($class, 'text-warning') !== false) {
            return 'warning';
        }
        $icon = (string) ($item['icon'] ?? '');
        if (preg_match('/fa-(trash|trash-alt|times|ban)/', $icon)) {
            return 'danger';
        }
        if (preg_match('/fa-(edit|pen)/', $icon)) {
            return 'warning';
        }
        if (preg_match('/fa-(check|undo|flag-checkered)/', $icon)) {
            return 'success';
        }
        if (preg_match('/fa-(eye|folder|file|print|reply|inbox)/', $icon)) {
            return 'primary';
        }
        return 'neutral';
    }
}

if (!function_exists('app_mod_item_attrs')) {
    /** @param array<string, scalar|null> $attrs */
    function app_mod_item_attrs(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $name => $value) {
            if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_:-]*$/i', $name)) {
                continue;
            }
            if ($value === null) {
                continue;
            }
            $html .= ' ' . htmlspecialchars($name) . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $html;
    }
}

if (!function_exists('app_mod_actions_dropdown')) {
    /**
     * Menu d'actions unifié (listes modules).
     *
     * @param list<array<string, mixed>> $items
     * @param array{label?: string, align?: string} $opts
     */
    function app_mod_actions_dropdown(array $items, array $opts = []): void
    {
        if (empty($items)) {
            return;
        }
        $menuLabel = $opts['label'] ?? 'Actions';
        $align = ($opts['align'] ?? 'end') === 'start' ? 'dropdown-menu-start' : 'dropdown-menu-end';
        ?>
<div class="dropdown mod-actions">
    <button type="button"
            class="btn mod-actions-btn dropdown-toggle"
            aria-expanded="false"
            aria-haspopup="true"
            onclick="return AppModActions.toggle(this, event)"
            aria-label="<?= htmlspecialchars($menuLabel) ?>"
            title="<?= htmlspecialchars($menuLabel) ?>">
        <i class="fas fa-ellipsis-h mod-actions-btn-icon" aria-hidden="true"></i>
        <span class="mod-actions-btn-text"><?= htmlspecialchars($menuLabel) ?></span>
    </button>
    <ul class="dropdown-menu mod-actions-menu shadow <?= $align ?>">
        <li class="dropdown-header mod-actions-header"><?= htmlspecialchars($menuLabel) ?></li>
        <?php foreach ($items as $item):
            if (!empty($item['divider'])): ?>
        <li><hr class="dropdown-divider mod-actions-divider"></li>
            <?php continue; endif;

            $itemClass = trim($item['class'] ?? '');
            $icon = $item['icon'] ?? 'fa-circle';
            $label = $item['label'] ?? '';
            $tone = app_mod_action_tone($item);
            $itemClasses = trim('mod-actions-item mod-actions-item--' . $tone . ' ' . $itemClass);
            $iconHtml = '<span class="mod-actions-icon"><i class="fas ' . htmlspecialchars($icon) . '" aria-hidden="true"></i></span>';
            $labelHtml = '<span class="mod-actions-text">' . htmlspecialchars($label) . '</span>';
            $extraAttrs = !empty($item['attrs']) && is_array($item['attrs']) ? app_mod_item_attrs($item['attrs']) : '';

            if (!empty($item['form'])):
                $f = $item['form'];
                ?>
        <li class="mod-actions-li">
            <form method="<?= htmlspecialchars($f['method'] ?? 'POST') ?>" action="<?= htmlspecialchars($f['action']) ?>" class="mod-actions-form">
                <?php foreach ($f['fields'] ?? [] as $name => $value): ?>
                <input type="hidden" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars((string) $value) ?>">
                <?php endforeach; ?>
                <button type="button" class="dropdown-item <?= htmlspecialchars($itemClasses) ?>"<?= $extraAttrs ?>
                        <?php if (!empty($f['confirm'])): ?> data-confirm="<?= htmlspecialchars($f['confirm']) ?>"<?php endif; ?>
                        onclick="appModSubmitActionForm(this); return false;">
                    <?= $iconHtml . $labelHtml ?>
                </button>
            </form>
        </li>
            <?php elseif (!empty($item['button'])):
                $isDeleteTrigger = strpos($itemClass, 'js-mod-delete-trigger') !== false
                    || (!empty($item['attrs']['data-delete-id']));
                $btnOnclick = '';
                if (!empty($item['onclick'])) {
                    $btnOnclick = htmlspecialchars($item['onclick'], ENT_COMPAT, 'UTF-8');
                } elseif ($isDeleteTrigger) {
                    $btnOnclick = 'return AppModActions.handleDeleteTrigger(this, event)';
                }
                ?>
        <li class="mod-actions-li">
            <button type="button" class="dropdown-item <?= htmlspecialchars($itemClasses) ?>"<?= $extraAttrs ?>
                    <?php if ($btnOnclick !== ''): ?> onclick="<?= $btnOnclick ?>"<?php endif; ?>>
                <?= $iconHtml . $labelHtml ?>
            </button>
        </li>
            <?php else:
                $href = $item['href'] ?? '#';
                $target = !empty($item['target']) ? ' target="' . htmlspecialchars($item['target']) . '"' : '';
                $onclick = !empty($item['onclick']) ? ' onclick="' . htmlspecialchars($item['onclick'], ENT_COMPAT, 'UTF-8') . '"' : '';
                ?>
        <li class="mod-actions-li">
            <a class="dropdown-item <?= htmlspecialchars($itemClasses) ?>" href="<?= htmlspecialchars($href) ?>"<?= $target ?><?= $extraAttrs ?><?= $onclick ?>>
                <?= $iconHtml . $labelHtml ?>
            </a>
        </li>
            <?php endif;
        endforeach; ?>
    </ul>
</div>
        <?php
    }
}

if (!function_exists('app_mod_pagination')) {
    function app_mod_pagination(int $page, int $totalPages, array $params = [], string $ariaLabel = 'Pagination'): void
    {
        if ($totalPages <= 1) {
            return;
        }
        $query = http_build_query(array_filter($params, static fn($v) => $v !== null && $v !== ''));
        $suffix = $query !== '' ? '&' . $query : '';
        ?>
<nav aria-label="<?= htmlspecialchars($ariaLabel) ?>" class="mt-4 app-mod-pagination">
    <ul class="pagination justify-content-center mb-0">
        <?php if ($page > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= $page - 1 ?><?= $suffix ?>"><i class="fas fa-chevron-left"></i></a>
        </li>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?><?= $suffix ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= $page + 1 ?><?= $suffix ?>"><i class="fas fa-chevron-right"></i></a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
        <?php
    }
}

if (!function_exists('app_mod_list_count')) {
    function app_mod_list_count(int $shown, int $total, string $entityLabel, string $extraClass = 'mod-list-count'): void
    {
        echo '<p class="text-center text-muted small mt-3 mb-0 ' . htmlspecialchars($extraClass) . '">';
        echo 'Affichage de ' . $shown . ' ' . htmlspecialchars($entityLabel) . ' sur ' . (int) $total . ' au total';
        echo '</p>';
    }
}

if (!function_exists('app_mod_filter_active')) {
    function app_mod_filter_active(int $count, string $summary): void
    {
        ?>
<div class="alert alert-light border mt-3 mb-0 py-2 small">
    <i class="fas fa-filter me-1 text-primary"></i>
    <strong><?= (int) $count ?></strong> résultat(s) · <?= $summary ?>
</div>
        <?php
    }
}
