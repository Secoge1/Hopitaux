(function () {
    'use strict';

    var BOTTOM_NAV_H = 60;
    var TOP_BAR_H = 50;

    function isMobile() {
        return window.innerWidth < 768;
    }

    function isAppShell() {
        return document.body.classList.contains('app-shell');
    }

    function isMobileLayoutMode() {
        return document.body.classList.contains('body-mobile-mode');
    }

    function isConsultationPage() {
        return window.location.pathname.indexOf('consultations/') !== -1;
    }

    function getBasePath() {
        var base = document.body.getAttribute('data-base-path');
        if (base === null || base === undefined) {
            return '';
        }
        return String(base).replace(/\/$/, '');
    }

    function appUrl(path) {
        var base = getBasePath();
        var p = String(path || '').replace(/^\//, '');
        if (!base) {
            return '/' + p;
        }
        return base + '/' + p;
    }

    function pathActive(segment) {
        return window.location.pathname.indexOf(segment) !== -1;
    }

    function isHomePage() {
        var path = window.location.pathname;
        if (path.indexOf('dashboard') !== -1) return false;
        if (path.indexOf('/patients') !== -1) return false;
        if (path.indexOf('/rendez-vous') !== -1) return false;
        return path.indexOf('/index.php') !== -1 || /\/$/.test(path);
    }

    function toggleSidebar() {
        document.body.classList.toggle('app-sidebar-open');
    }

    function showSidebar() {
        document.body.classList.add('app-sidebar-open');
    }

    function hideSidebar() {
        document.body.classList.remove('app-sidebar-open');
    }

    function shouldUseBottomNav() {
        if (!isMobile()) return false;
        if (isMobileLayoutMode()) return false;
        if (isConsultationPage()) return false;
        return isAppShell();
    }

    function clearMobileChrome() {
        var bottomNav = document.getElementById('mobile-bottom-nav');
        var topBar = document.getElementById('mobile-top-bar');
        if (bottomNav) bottomNav.remove();
        if (topBar) topBar.remove();
        document.body.style.paddingTop = '';
        document.body.style.paddingBottom = '';
        document.body.classList.remove('has-mobile-bottom-nav');
    }

    function applyContentInsets() {
        if (isMobileLayoutMode()) {
            document.body.style.paddingTop = '';
            document.body.style.paddingBottom = '';
            return;
        }

        if (!isMobile() || !isAppShell()) {
            document.body.style.paddingTop = '';
            document.body.style.paddingBottom = '';
            return;
        }

        document.body.style.paddingTop = '';
        if (shouldUseBottomNav()) {
            document.body.style.paddingBottom = BOTTOM_NAV_H + 'px';
            document.body.classList.add('has-mobile-bottom-nav');
        } else {
            document.body.style.paddingBottom = '';
            document.body.classList.remove('has-mobile-bottom-nav');
        }
    }

    function createBottomNavigation() {
        if (!shouldUseBottomNav()) return;

        if (document.getElementById('mobile-bottom-nav')) {
            return;
        }

        var bottomNav = document.createElement('nav');
        bottomNav.id = 'mobile-bottom-nav';
        bottomNav.className = 'mobile-bottom-nav';
        bottomNav.setAttribute('aria-label', 'Navigation principale');

        var navItems = [];
        var serverNav = window.__APP_MOBILE_NAV;
        if (Array.isArray(serverNav) && serverNav.length) {
            serverNav.forEach(function (item) {
                var active = false;
                if (item.active === 'home') {
                    active = isHomePage();
                } else if (item.active) {
                    active = pathActive(item.active);
                }
                navItems.push({
                    icon: item.icon,
                    text: item.text,
                    url: appUrl(item.url),
                    active: active
                });
            });
        } else {
            navItems.push(
                { icon: 'fas fa-home', text: 'Accueil', url: appUrl('index.php'), active: isHomePage() },
                { icon: 'fas fa-user-injured', text: 'Patients', url: appUrl('patients/'), active: pathActive('/patients/') },
                { icon: 'fas fa-stethoscope', text: 'Consult.', url: appUrl('consultations/'), active: pathActive('/consultations/') },
                { icon: 'fas fa-flask', text: 'Labo', url: appUrl('laboratoire/'), active: pathActive('/laboratoire/') }
            );
        }
        navItems.push({ icon: 'fas fa-bars', text: 'Menu', action: 'toggle-sidebar' });

        navItems.forEach(function (item) {
            var navItem = document.createElement('a');
            navItem.className = 'mobile-bottom-nav-item' + (item.active ? ' active' : '');
            navItem.href = item.url || '#';

            if (item.action === 'toggle-sidebar') {
                navItem.addEventListener('click', function (e) {
                    e.preventDefault();
                    toggleSidebar();
                });
            }

            navItem.innerHTML = '<i class="' + item.icon + '"></i><span>' + item.text + '</span>';
            bottomNav.appendChild(navItem);
        });

        document.body.appendChild(bottomNav);
    }

    function bindMobileMenuButtons() {
        var headerMenu = document.getElementById('mobileHeaderMenu');
        if (headerMenu && !headerMenu.dataset.bound) {
            headerMenu.dataset.bound = '1';
            headerMenu.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof window.__mobileToggleSidebar === 'function') {
                    window.__mobileToggleSidebar(e);
                } else {
                    toggleSidebar();
                }
            });
        }

        var menuBtn = document.getElementById('mobileNavMenu');
        if (menuBtn && !menuBtn.dataset.bound) {
            menuBtn.dataset.bound = '1';
            menuBtn.addEventListener('click', function (e) {
                e.preventDefault();
                toggleSidebar();
            });
        }

        var notifBtn = document.getElementById('mobile-notifications-btn');
        if (notifBtn && !notifBtn.dataset.bound) {
            notifBtn.dataset.bound = '1';
            notifBtn.addEventListener('click', function () {
                var panel = document.getElementById('notificationsPanel');
                if (panel && typeof bootstrap !== 'undefined') {
                    bootstrap.Collapse.getOrCreateInstance(panel).toggle();
                }
            });
        }
    }

    var touchStartX = 0;
    var touchStartY = 0;

    function handleTouchStart(e) {
        if (!isMobile() || !isAppShell()) return;
        touchStartX = e.changedTouches[0].screenX;
        touchStartY = e.changedTouches[0].screenY;
    }

    function handleTouchEnd(e) {
        if (!isMobile() || !isAppShell()) return;
        var touchEndX = e.changedTouches[0].screenX;
        var touchEndY = e.changedTouches[0].screenY;
        var deltaX = touchEndX - touchStartX;
        var deltaY = touchEndY - touchStartY;
        var minSwipeDistance = 50;

        if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > minSwipeDistance) {
            if (deltaX > 0 && touchStartX < 40) {
                showSidebar();
            } else if (deltaX < 0) {
                hideSidebar();
            }
        }
    }

    function init() {
        bindMobileMenuButtons();

        if (!isMobile()) {
            clearMobileChrome();
            return;
        }

        if (isMobileLayoutMode()) {
            clearMobileChrome();
            applyContentInsets();
            return;
        }

        if (isAppShell()) {
            createBottomNavigation();
            applyContentInsets();
            return;
        }

        clearMobileChrome();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('touchstart', handleTouchStart, { passive: true });
    document.addEventListener('touchend', handleTouchEnd, { passive: true });

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(init, 150);
    });

    window.appShellMobile = {
        toggleSidebar: toggleSidebar,
        showSidebar: showSidebar,
        hideSidebar: hideSidebar,
        isMobile: isMobile
    };

    window.autoResponsive = window.appShellMobile;

    window.wptouchInspired = {
        createBottomNavigation: createBottomNavigation,
        isMobile: isMobile
    };
})();
