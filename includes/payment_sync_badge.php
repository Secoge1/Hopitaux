<?php
/**
 * Avis « Nouveau » sync Paiements / Finances — bandeau global + badge carte.
 * Affiché une fois par utilisateur et par activation (localStorage + empreinte enabled_at).
 */

if (!function_exists('app_payment_sync_notice_context')) {
    /**
     * @return array{user_id: int, stamp: string, show: bool}|null
     */
    function app_payment_sync_notice_context(): ?array
    {
        if (!function_exists('payment_finance_sync_enabled') || !payment_finance_sync_enabled()) {
            return null;
        }

        require_once __DIR__ . '/saas/PlatformTenantFeatures.php';
        $stamp = PlatformTenantFeatures::getEnabledStamp(PlatformTenantFeatures::PAYMENT_FINANCE_SYNC);
        if ($stamp === null) {
            return null;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId < 1) {
            return null;
        }

        return [
            'user_id' => $userId,
            'stamp' => $stamp,
            'show' => true,
        ];
    }
}

if (!function_exists('app_payment_sync_notice_modules')) {
    /** @return list<string> */
    function app_payment_sync_notice_modules(): array
    {
        return ['paiements', 'consultations', 'laboratoire', 'finances'];
    }
}

if (!function_exists('app_payment_sync_notice_assets_once')) {
    function app_payment_sync_notice_assets_once(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        echo <<<'HTML'
<style>
.payment-sync-feature-block { position: relative; padding-top: 0.65rem; margin-bottom: 1rem; }
.payment-sync-global-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    margin-bottom: 1rem;
    border-radius: 10px;
    border: 1px solid rgba(25, 135, 84, 0.35);
    background: linear-gradient(135deg, rgba(25, 135, 84, 0.12) 0%, rgba(32, 201, 151, 0.08) 100%);
    box-shadow: 0 4px 16px rgba(25, 135, 84, 0.12);
}
.payment-sync-global-banner__icon {
    flex-shrink: 0;
    width: 2.25rem;
    height: 2.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    color: #fff;
    background: linear-gradient(135deg, #198754 0%, #20c997 100%);
}
.payment-sync-global-banner__body { flex: 1; min-width: 0; }
.payment-sync-global-banner__title {
    font-weight: 700;
    color: #146c43;
    margin-bottom: 0.25rem;
}
.payment-sync-global-banner__text {
    margin: 0;
    color: #1f5132;
    font-size: 0.92rem;
}
.payment-sync-global-banner__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.65rem;
}
.payment-sync-global-banner__close {
    flex-shrink: 0;
    border: 0;
    background: transparent;
    color: #146c43;
    opacity: 0.75;
    padding: 0.15rem 0.35rem;
}
.payment-sync-global-banner__close:hover { opacity: 1; }
.payment-sync-new-badge {
    position: absolute;
    top: -12px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 5;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.75rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: #fff;
    background: linear-gradient(135deg, #198754 0%, #20c997 100%);
    border-radius: 999px;
    box-shadow: 0 4px 14px rgba(25, 135, 84, 0.35);
    white-space: nowrap;
    transition: opacity 0.6s ease, transform 0.6s ease;
    pointer-events: none;
    animation: paymentSyncBadgePulse 1.5s ease-in-out infinite;
}
.payment-sync-new-badge.is-hidden,
.payment-sync-global-banner.is-hidden {
    opacity: 0;
    transform: translateY(-6px);
}
.payment-sync-new-badge.is-hidden { transform: translateX(-50%) translateY(-6px); }
@keyframes paymentSyncBadgePulse {
    0%, 100% { box-shadow: 0 4px 14px rgba(25, 135, 84, 0.35); }
    50% { box-shadow: 0 6px 20px rgba(32, 201, 151, 0.55); }
}
</style>
<script>
(function () {
    if (window.__paymentSyncNoticeInit) return;
    window.__paymentSyncNoticeInit = true;

    function noticeKey(uid) {
        return 'hopitaux_payment_sync_notice_' + uid;
    }

    function shouldShow(el) {
        var uid = el.getAttribute('data-user-id') || '0';
        var stamp = el.getAttribute('data-feature-stamp') || '';
        var seen = localStorage.getItem(noticeKey(uid));
        return !seen || seen !== stamp;
    }

    function markSeen(uid, stamp) {
        try {
            localStorage.setItem(noticeKey(uid), stamp);
        } catch (e) {}
    }

    function hideNotice(el, uid, stamp) {
        el.classList.add('is-hidden');
        markSeen(uid, stamp);
        window.setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 700);
    }

    function initNotices() {
        var nodes = document.querySelectorAll('.payment-sync-notice');
        if (!nodes.length) return;

        nodes.forEach(function (el) {
            if (!shouldShow(el)) {
                el.remove();
                return;
            }

            var uid = el.getAttribute('data-user-id') || '0';
            var stamp = el.getAttribute('data-feature-stamp') || '';

            var closeBtn = el.querySelector('[data-payment-sync-dismiss]');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    document.querySelectorAll('.payment-sync-notice').forEach(function (n) {
                        hideNotice(n, n.getAttribute('data-user-id') || uid, n.getAttribute('data-feature-stamp') || stamp);
                    });
                });
            }

            window.setTimeout(function () {
                if (el.parentNode) hideNotice(el, uid, stamp);
            }, 10000);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNotices);
    } else {
        initNotices();
    }
})();
</script>
HTML;
    }
}

if (!function_exists('app_payment_sync_notice_attrs')) {
    function app_payment_sync_notice_attrs(array $ctx): string
    {
        return ' data-user-id="' . (int) $ctx['user_id'] . '" data-feature-stamp="'
            . htmlspecialchars($ctx['stamp'], ENT_QUOTES, 'UTF-8') . '"';
    }
}

if (!function_exists('app_payment_sync_global_banner')) {
    function app_payment_sync_global_banner(string $activeModule = ''): void
    {
        if ($activeModule !== '' && !in_array($activeModule, app_payment_sync_notice_modules(), true)) {
            return;
        }

        $ctx = app_payment_sync_notice_context();
        if ($ctx === null) {
            return;
        }

        app_payment_sync_notice_assets_once();

        $attrs = app_payment_sync_notice_attrs($ctx);
        $consultUrl = function_exists('app_url') ? app_url('consultations/index.php') : 'consultations/index.php';
        $paiementsUrl = function_exists('app_url') ? app_url('paiements/index.php') : 'paiements/index.php';

        echo '<div class="payment-sync-notice"' . $attrs . ' role="status" aria-live="polite">';
        echo '<div class="payment-sync-global-banner">';
        echo '<span class="payment-sync-global-banner__icon"><i class="fas fa-star"></i></span>';
        echo '<div class="payment-sync-global-banner__body">';
        echo '<div class="payment-sync-global-banner__title">Nouveau — synchronisation Paiements &amp; Comptabilité</div>';
        echo '<p class="payment-sync-global-banner__text">Générez un paiement depuis une consultation ou une analyse labo. '
            . 'Au statut «&nbsp;Payé&nbsp;», l\'écriture comptable est créée automatiquement. '
            . 'Les encaissements validés sont verrouillés (annulation = contre-passation).</p>';
        echo '<div class="payment-sync-global-banner__actions">';
        echo '<a href="' . htmlspecialchars($consultUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-success btn-sm">';
        echo '<i class="fas fa-stethoscope me-1"></i>Consultations</a>';
        echo '<a href="' . htmlspecialchars($paiementsUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-outline-success btn-sm">';
        echo '<i class="fas fa-credit-card me-1"></i>Paiements</a>';
        echo '</div></div>';
        echo '<button type="button" class="payment-sync-global-banner__close" data-payment-sync-dismiss aria-label="Fermer">';
        echo '<i class="fas fa-times"></i></button>';
        echo '</div></div>';
    }
}

if (!function_exists('app_payment_sync_new_badge')) {
    function app_payment_sync_new_badge(string $label = 'Nouveau — sync. paiements & comptabilité'): void
    {
        $ctx = app_payment_sync_notice_context();
        if ($ctx === null) {
            return;
        }

        app_payment_sync_notice_assets_once();

        echo '<span class="payment-sync-new-badge payment-sync-notice"' . app_payment_sync_notice_attrs($ctx)
            . ' role="status" aria-live="polite">'
            . '<i class="fas fa-star"></i> '
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
}
