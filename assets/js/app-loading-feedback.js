(function () {
    'use strict';

    var LOADING_BODY_CLASS = 'app-loading-active';
    var LOADING_EL_CLASS = 'is-loading';

    function ensureBar() {
        if (document.getElementById('appLoadingBar')) {
            return;
        }
        var bar = document.createElement('div');
        bar.id = 'appLoadingBar';
        bar.className = 'app-loading-bar';
        bar.setAttribute('aria-hidden', 'true');
        bar.innerHTML = '<span class="app-loading-bar__track"><span class="app-loading-bar__fill"></span></span>';
        document.body.appendChild(bar);
    }

    function setIconLoading(el) {
        var icon = el.querySelector('i.fas, i.far, i.fab');
        if (!icon || icon.dataset.loadingSaved) {
            return;
        }
        icon.dataset.loadingSaved = icon.className;
        icon.className = 'fas fa-spinner fa-spin';
    }

    function showLoading(el) {
        if (!el || el.classList.contains(LOADING_EL_CLASS)) {
            return;
        }
        ensureBar();
        document.body.classList.add(LOADING_BODY_CLASS);
        el.classList.add(LOADING_EL_CLASS);
        el.setAttribute('aria-busy', 'true');

        if (el.tagName === 'BUTTON' || el.tagName === 'INPUT') {
            if (!el.dataset.loadingSavedDisabled) {
                el.dataset.loadingSavedDisabled = el.disabled ? '1' : '0';
            }
            setTimeout(function() {
                el.disabled = true;
            }, 0);
        }

        setIconLoading(el);

        if ((el.tagName === 'BUTTON' || el.tagName === 'INPUT') && !el.querySelector('i')) {
            if (!el.dataset.loadingSavedHtml) {
                el.dataset.loadingSavedHtml = el.innerHTML;
            }
            var label = el.getAttribute('data-loading-label') || 'Chargement…';
            el.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + label;
        }
    }

    function hideLoading() {
        document.body.classList.remove(LOADING_BODY_CLASS);
        document.querySelectorAll('.' + LOADING_EL_CLASS).forEach(function (el) {
            el.classList.remove(LOADING_EL_CLASS);
            el.removeAttribute('aria-busy');
            if (el.dataset.loadingSavedDisabled === '0') {
                el.disabled = false;
            }
            delete el.dataset.loadingSavedDisabled;

            var icon = el.querySelector('i[data-loading-saved]');
            if (icon) {
                icon.className = icon.dataset.loadingSaved;
                delete icon.dataset.loadingSaved;
            }
            if (el.dataset.loadingSavedHtml) {
                el.innerHTML = el.dataset.loadingSavedHtml;
                delete el.dataset.loadingSavedHtml;
            }
        });
    }

    function hasModifier(e) {
        return !!(e.metaKey || e.ctrlKey || e.shiftKey || e.altKey);
    }

    function isBootstrapToggle(el) {
        return el.hasAttribute('data-bs-toggle')
            || el.hasAttribute('data-bs-dismiss')
            || el.getAttribute('data-bs-toggle')
            || el.closest('[data-bs-toggle="collapse"], [data-bs-toggle="dropdown"], [data-bs-toggle="modal"]');
    }

    function isInternalLink(el) {
        if (!el || el.tagName !== 'A') {
            return false;
        }
        var href = el.getAttribute('href');
        if (!href || href === '#' || href.indexOf('javascript:') === 0) {
            return false;
        }
        if (el.target === '_blank' || el.hasAttribute('download')) {
            return false;
        }
        if (el.getAttribute('role') === 'button') {
            return false;
        }
        if (isBootstrapToggle(el)) {
            return false;
        }
        if (el.classList.contains('mod-actions-btn') || el.closest('.mod-actions-menu')) {
            return false;
        }
        try {
            var url = new URL(el.href, window.location.href);
            return url.origin === window.location.origin;
        } catch (err) {
            return false;
        }
    }

    function shouldSkipClick(el) {
        if (!el) {
            return true;
        }
        if (el.classList.contains('mobile-header-menu') || el.id === 'mobileHeaderMenu') {
            return true;
        }
        if (el.id === 'mobileHeaderBell' || el.closest('#mobileHeaderBell')) {
            return true;
        }
        if (el.closest('.app-sidebar-overlay')) {
            return true;
        }
        if (isBootstrapToggle(el)) {
            return true;
        }
        if (el.dataset.noLoading === '1') {
            return true;
        }
        return false;
    }

    function handleClick(e) {
        if (hasModifier(e)) {
            return;
        }

        var submitEl = e.target.closest('button[type="submit"], input[type="submit"]');
        if (submitEl && submitEl.form && !shouldSkipClick(submitEl)) {
            showLoading(submitEl);
            return;
        }

        var link = e.target.closest('a[href]');
        if (link && isInternalLink(link) && !shouldSkipClick(link)) {
            showLoading(link);
            return;
        }

        var btn = e.target.closest('[data-loading="1"], .js-show-loading');
        if (btn && !shouldSkipClick(btn)) {
            showLoading(btn);
        }
    }

    window.appShowLoading = showLoading;
    window.appHideLoading = hideLoading;

    document.addEventListener('click', handleClick, true);
    window.addEventListener('pageshow', hideLoading);
    window.addEventListener('load', hideLoading);
})();
