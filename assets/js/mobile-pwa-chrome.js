(function () {
    'use strict';

    function isMobileLayout() {
        return document.body && document.body.classList.contains('body-mobile-mode');
    }

    function basePath() {
        var base = document.body.getAttribute('data-base-path') || '';
        return String(base).replace(/\/$/, '');
    }

    function withMobile(url) {
        if (!url || url.indexOf('mobile=1') !== -1) {
            return url;
        }
        return url + (url.indexOf('?') !== -1 ? '&' : '?') + 'mobile=1';
    }

    function appLink(path) {
        var p = String(path || '').replace(/^\//, '');
        var base = basePath();
        return withMobile(base ? base + '/' + p : '/' + p);
    }

    function pathActive(slug) {
        var path = window.location.pathname;
        var search = window.location.search;
        var uri = path + search;
        if (slug === 'home') {
            return (path.indexOf('index.php') !== -1 || /\/$/.test(path))
                && path.indexOf('dashboard') === -1
                && path.indexOf('/patients') === -1
                && path.indexOf('/rendez-vous') === -1
                && path.indexOf('/dossiers') === -1
                && path.indexOf('/medecins') === -1
                && path.indexOf('/personnel') === -1;
        }
        if (slug === 'agenda') {
            return uri.indexOf('rendez-vous') !== -1;
        }
        if (slug === 'profil') {
            return path.indexOf('/medecins/voir.php') !== -1
                || path.indexOf('/personnel') !== -1
                || (path.indexOf('/medecins') !== -1 && path.indexOf('voir.php') === -1);
        }
        return uri.indexOf(slug) !== -1;
    }

    function defaultNavItems() {
        return [
            { icon: 'fa-home', text: 'Accueil', url: 'index.php', active: 'home' },
            { icon: 'fa-users', text: 'Patients', url: 'patients/', active: 'patients' },
            { icon: 'fa-calendar', text: 'Agenda', url: 'rendez-vous/', active: 'agenda' },
            { icon: 'fa-folder', text: 'Dossiers', url: 'dossiers/', active: 'dossiers' },
            { icon: 'fa-user', text: 'Profil', url: 'index.php', active: 'profil' }
        ];
    }

    function buildBottomNav() {
        var items = Array.isArray(window.__APP_MOBILE_NAV) && window.__APP_MOBILE_NAV.length
            ? window.__APP_MOBILE_NAV
            : defaultNavItems();

        var nav = document.createElement('nav');
        nav.className = 'mobile-global-nav';
        nav.setAttribute('aria-label', 'Navigation mobile');
        nav.id = 'mobileGlobalNav';

        items.forEach(function (item) {
            var a = document.createElement('a');
            a.className = 'nav-item' + (pathActive(item.active) ? ' active' : '');
            a.href = appLink(item.url);
            var icon = String(item.icon || 'fa-circle').replace(/^fas\s+/, '');
            a.innerHTML = '<i class="fas ' + icon + '"></i><span class="nav-label">' + (item.text || '') + '</span>';
            nav.appendChild(a);
        });

        return nav;
    }

    function pageTitle() {
        var path = window.location.pathname;
        var map = [
            ['patients', 'Patients'],
            ['rendez-vous', 'Agenda'],
            ['dossiers', 'Dossiers'],
            ['consultations', 'Consultations'],
            ['laboratoire', 'Laboratoire'],
            ['dashboard', 'Dashboard'],
            ['medecins', 'Profil'],
            ['personnel', 'Profil']
        ];
        for (var i = 0; i < map.length; i++) {
            if (path.indexOf('/' + map[i][0]) !== -1) {
                return map[i][1];
            }
        }
        return 'Accueil';
    }

    function buildHeader() {
        var header = document.createElement('header');
        header.className = 'mobile-global-header';
        header.innerHTML =
            '<button type="button" class="mobile-header-btn mobile-header-menu" id="mobileHeaderMenu" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="appSidebar"'
            + ' onclick="window.__mobileToggleSidebar&&window.__mobileToggleSidebar(event)">'
            + '<i class="fas fa-bars"></i></button>'
            + '<h1 class="mobile-header-title">' + pageTitle() + '</h1>'
            + '<button type="button" class="mobile-header-btn mobile-header-bell" id="mobileHeaderBell"'
            + ' data-bs-toggle="collapse" data-bs-target="#notificationsPanel" aria-expanded="false" aria-label="Notifications">'
            + '<i class="fas fa-bell"></i></button>';
        return header;
    }

    function toggleSidebar(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        var open = !document.body.classList.contains('app-sidebar-open');
        document.body.classList.toggle('app-sidebar-open', open);
        document.body.classList.toggle('mobile-sidebar-open', open);
        var menu = document.getElementById('mobileHeaderMenu');
        if (menu) {
            menu.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function closeSidebar(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        document.body.classList.remove('app-sidebar-open', 'mobile-sidebar-open');
        var menu = document.getElementById('mobileHeaderMenu');
        if (menu) {
            menu.setAttribute('aria-expanded', 'false');
        }
    }

    window.__mobileToggleSidebar = toggleSidebar;
    window.__mobileCloseSidebar = closeSidebar;

    function bindChrome() {
        var menu = document.getElementById('mobileHeaderMenu');
        if (menu && !menu.dataset.bound) {
            menu.dataset.bound = '1';
            menu.setAttribute('aria-expanded', 'false');
            menu.setAttribute('aria-controls', 'appSidebar');
            menu.addEventListener('click', toggleSidebar);
            menu.addEventListener('touchend', function (e) {
                e.preventDefault();
                toggleSidebar(e);
            }, { passive: false });
        }

        var overlay = document.getElementById('appSidebarOverlay');
        if (overlay && !overlay.dataset.bound) {
            overlay.dataset.bound = '1';
            overlay.addEventListener('click', closeSidebar);
            overlay.addEventListener('touchend', function (e) {
                e.preventDefault();
                closeSidebar(e);
            }, { passive: false });
        }
    }

    function repositionSidebar() {
        var sidebar = document.getElementById('appSidebar');
        var overlay = document.getElementById('appSidebarOverlay');
        var nav = document.querySelector('.mobile-global-nav');
        if (overlay && sidebar) {
            document.body.appendChild(overlay);
            document.body.appendChild(sidebar);
        }
        if (nav && nav.parentNode !== document.body) {
            document.body.appendChild(nav);
        }
    }

    function bindMenuDelegation() {
        if (document.body.dataset.mobileMenuDelegation) {
            return;
        }
        document.body.dataset.mobileMenuDelegation = '1';
        document.addEventListener('click', function (e) {
            if (!isMobileLayout()) {
                return;
            }
            var btn = e.target.closest('#mobileHeaderMenu, .mobile-header-menu');
            if (!btn) {
                return;
            }
            toggleSidebar(e);
        }, true);
    }

    function ensureChrome() {
        if (!isMobileLayout()) {
            return;
        }

        if (!document.querySelector('.mobile-global-header')) {
            document.body.insertBefore(buildHeader(), document.body.firstChild);
        }

        if (!document.querySelector('.mobile-global-nav')) {
            document.body.appendChild(buildBottomNav());
        }

        bindChrome();
        bindMenuDelegation();
        repositionSidebar();
    }

    function setupNotificationsPanel() {
        if (!isMobileLayout()) {
            return;
        }
        var panel = document.getElementById('notificationsPanel');
        if (panel && panel.parentElement !== document.body) {
            document.body.appendChild(panel);
        }
        var bell = document.getElementById('mobileHeaderBell');
        if (bell && !bell.dataset.notifBound) {
            bell.dataset.notifBound = '1';
            bell.addEventListener('click', function () {
                if (typeof window.appRefreshNotificationsPanel === 'function') {
                    window.appRefreshNotificationsPanel();
                }
            });
        }
    }

    function initMobileChrome() {
        ensureChrome();
        bindMenuDelegation();
        repositionSidebar();
        bindChrome();
        setupNotificationsPanel();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileChrome);
    } else {
        initMobileChrome();
    }

    window.addEventListener('load', function () {
        bindChrome();
        repositionSidebar();
        setupNotificationsPanel();
    });
})();
