<?php
/**
 * Composants boutons d'action — admin plateforme.
 */

if (!function_exists('app_platform_act')) {
    /**
     * @param array<string, string> $attrs
     */
    function app_platform_act(string $variant, string $icon, string $label, array $attrs = [], bool $withText = false): string
    {
        $class = 'platform-act platform-act--' . $variant;
        if ($withText) {
            $class .= ' platform-act--text';
        }
        $attrStr = '';
        foreach ($attrs as $k => $v) {
            $attrStr .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars($v) . '"';
        }
        $text = $withText
            ? '<span class="platform-act-label">' . htmlspecialchars($label) . '</span>'
            : '';

        return '<button type="button" class="' . $class . '" title="' . htmlspecialchars($label) . '"' . $attrStr . '>'
            . '<i class="fas ' . htmlspecialchars($icon) . '"></i>' . $text . '</button>';
    }

    /**
     * @param array<string, string|int> $hidden
     */
    function app_platform_act_submit(
        string $name,
        string $variant,
        string $icon,
        string $label,
        array $hidden = [],
        string $confirm = '',
        bool $withText = false
    ): string {
        $class = 'platform-act platform-act--' . $variant;
        if ($withText) {
            $class .= ' platform-act--text';
        }
        $onsubmit = '';
        if ($confirm !== '') {
            $onsubmit = ' onsubmit=\'return confirm(' . json_encode($confirm, JSON_UNESCAPED_UNICODE) . ');\'';
        }
        $html = '<form method="post" class="platform-act-form"' . $onsubmit . '>';
        foreach ($hidden as $k => $v) {
            $html .= '<input type="hidden" name="' . htmlspecialchars((string) $k) . '" value="' . htmlspecialchars((string) $v) . '">';
        }
        $text = $withText
            ? '<span class="platform-act-label">' . htmlspecialchars($label) . '</span>'
            : '';
        $html .= '<button type="submit" name="' . htmlspecialchars($name) . '" value="1" class="' . $class . '"'
            . ' title="' . htmlspecialchars($label) . '">'
            . '<i class="fas ' . htmlspecialchars($icon) . '"></i>' . $text . '</button></form>';
        return $html;
    }

    /**
     * @param array<string, string> $attrs
     */
    function app_platform_act_link(
        string $href,
        string $variant,
        string $icon,
        string $label,
        array $attrs = [],
        bool $withText = false
    ): string {
        $class = 'platform-act platform-act--' . $variant;
        if ($withText) {
            $class .= ' platform-act--text';
        }
        $defaults = ['href' => $href, 'class' => $class, 'title' => $label];
        $merged = array_merge($defaults, $attrs);
        $attrStr = '';
        foreach ($merged as $k => $v) {
            $attrStr .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars($v) . '"';
        }
        $text = $withText
            ? '<span class="platform-act-label">' . htmlspecialchars($label) . '</span>'
            : '';

        return '<a' . $attrStr . '><i class="fas ' . htmlspecialchars($icon) . '"></i>' . $text . '</a>';
    }

    function app_platform_tenant_actions(array $t, bool $compact = false): string
    {
        $id = (int) $t['id'];
        $name = $t['company_name'] ?? '';
        $canDelete = $id > 1;

        $html = '<div class="platform-action-btns" role="group" aria-label="Actions établissement">';

        $html .= app_platform_act('primary', 'fa-edit', 'Modifier', [
            'data-bs-toggle' => 'modal',
            'data-bs-target' => '#edit-' . $id,
        ]);

        if (($t['license_type'] ?? '') !== 'lifetime') {
            $html .= app_platform_act_submit('extend_tenant', 'success', 'fa-calendar-plus', 'Prolonger 1 an', [
                'tenant_id' => $id,
                'extend_years' => 1,
            ], 'Prolonger la licence d\'un an ?');
        }

        if (($t['status'] ?? '') === 'active') {
            $html .= app_platform_act_submit('set_tenant_status', 'warning', 'fa-pause', 'Suspendre', [
                'tenant_id' => $id,
                'status' => 'suspended',
            ], 'Suspendre « ' . $name . ' » ?');
        } else {
            $html .= app_platform_act_submit('set_tenant_status', 'success', 'fa-play', 'Réactiver', [
                'tenant_id' => $id,
                'status' => 'active',
            ], 'Réactiver « ' . $name . ' » ?');
        }

        if (!$compact) {
            $html .= app_platform_act_link(
                app_url('admin_platform/tenants.php?edit=' . $id),
                'neutral',
                'fa-external-link-alt',
                'Ouvrir la fiche',
                []
            );
        }

        if ($canDelete) {
            $html .= app_platform_act_submit('delete_tenant', 'danger', 'fa-trash', 'Supprimer', [
                'tenant_id' => $id,
            ], 'Supprimer définitivement « ' . $name . ' » ? Cette action est irréversible.');
        }

        $html .= '</div>';
        return $html;
    }

    function app_platform_payment_actions(array $o, bool $compact = false): string
    {
        $orderId = (int) $o['id'];
        $ref = $o['ref_command'] ?? '';
        $tenantId = (int) ($o['tenant_id'] ?? 0);

        $html = '<div class="platform-action-btns" role="group" aria-label="Actions paiement">';

        $html .= app_platform_act_submit(
            'confirm_payment',
            'success',
            'fa-check',
            'Confirmer',
            ['order_id' => $orderId],
            'Confirmer le paiement et activer la licence ?',
            !$compact
        );

        $html .= app_platform_act_link(
            saas_payment_instructions_url($ref),
            'neutral',
            'fa-file-invoice',
            'Instructions client',
            ['target' => '_blank', 'rel' => 'noopener']
        );

        if ($tenantId > 0) {
            $html .= app_platform_act_link(
                app_url('admin_platform/tenants.php?edit=' . $tenantId),
                'primary',
                'fa-building',
                'Gérer l\'établissement',
                []
            );
        }

        $html .= app_platform_act_submit(
            'cancel_order',
            'danger',
            'fa-times',
            'Annuler',
            ['order_id' => $orderId],
            'Annuler cette commande en attente ?',
            !$compact
        );

        $html .= '</div>';
        return $html;
    }
}
