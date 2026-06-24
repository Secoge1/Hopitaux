/**
 * Menus Actions — toggle, portal menu, suppression AJAX via #deleteModal.
 */
window.AppModActions = window.AppModActions || {};
(function () {
    'use strict';

    var activeEntry = null;
    var openGuardUntil = 0;
    var deleteGuard = { id: 0, at: 0 };

    function positionMenu(toggle, menu) {
        var rect = toggle.getBoundingClientRect();
        var menuWidth = menu.offsetWidth || 210;
        var menuHeight = menu.offsetHeight || 120;

        menu.style.position = 'fixed';
        menu.style.inset = 'auto';
        menu.style.margin = '0';
        menu.style.transform = 'none';
        menu.style.display = 'block';
        menu.style.visibility = 'visible';
        menu.style.opacity = '1';

        var top = rect.bottom + 4;
        var left = rect.right - menuWidth;
        if (left < 8) {
            left = 8;
        }
        if (left + menuWidth > window.innerWidth - 8) {
            left = Math.max(8, window.innerWidth - menuWidth - 8);
        }
        if (top + menuHeight > window.innerHeight - 8) {
            top = Math.max(8, rect.top - menuHeight - 4);
        }

        menu.style.top = top + 'px';
        menu.style.left = left + 'px';
        menu.style.zIndex = '9999';
    }

    function portalMenu(toggle, menu, dropdown) {
        if (!menu._modActionsHome) {
            menu._modActionsHome = dropdown;
        }
        if (menu.parentElement !== document.body) {
            document.body.appendChild(menu);
        }
        menu.classList.add('mod-actions-menu--portal');
        positionMenu(toggle, menu);
    }

    function restoreMenu(menu, dropdown) {
        if (!menu) {
            return;
        }
        var home = menu._modActionsHome || dropdown;
        if (home && menu.parentElement === document.body) {
            home.appendChild(menu);
        }
        menu.classList.remove('mod-actions-menu--portal');
        menu.style.position = '';
        menu.style.top = '';
        menu.style.left = '';
        menu.style.zIndex = '';
        menu.style.inset = '';
        menu.style.margin = '';
        menu.style.transform = '';
        menu.style.display = '';
        menu.style.visibility = '';
        menu.style.opacity = '';
    }

    function closeMenuEntry(entry) {
        if (!entry) {
            return;
        }
        entry.menu.classList.remove('show');
        entry.dropdown.classList.remove('show');
        entry.toggle.setAttribute('aria-expanded', 'false');
        restoreMenu(entry.menu, entry.dropdown);
        if (activeEntry === entry) {
            activeEntry = null;
        }
    }

    function closeAll() {
        if (activeEntry) {
            closeMenuEntry(activeEntry);
            return;
        }
        document.querySelectorAll('.dropdown.mod-actions .dropdown-menu.show').forEach(function (menu) {
            var dropdown = menu._modActionsHome || menu.closest('.dropdown.mod-actions');
            var toggle = dropdown ? dropdown.querySelector('.mod-actions-btn') : null;
            if (dropdown && toggle) {
                closeMenuEntry({ menu: menu, dropdown: dropdown, toggle: toggle });
            }
        });
    }

    function buildEntry(toggle) {
        if (!toggle) {
            return null;
        }
        var dropdown = toggle.closest('.dropdown.mod-actions');
        if (!dropdown) {
            return null;
        }
        var menu = dropdown.querySelector('.dropdown-menu.mod-actions-menu')
            || dropdown.querySelector('.dropdown-menu');
        if (!menu) {
            return null;
        }
        return { toggle: toggle, menu: menu, dropdown: dropdown };
    }

    function openMenuEntry(entry) {
        closeAll();
        entry.menu.classList.add('show');
        entry.dropdown.classList.add('show');
        entry.toggle.setAttribute('aria-expanded', 'true');
        activeEntry = entry;
        openGuardUntil = Date.now() + (window.matchMedia && window.matchMedia('(pointer: coarse)').matches ? 400 : 150);
        portalMenu(entry.toggle, entry.menu, entry.dropdown);
    }

    function stripBootstrapDropdown(toggle) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
            var inst = bootstrap.Dropdown.getInstance(toggle);
            if (inst) {
                inst.dispose();
            }
        }
        toggle.removeAttribute('data-bs-toggle');
        toggle.removeAttribute('data-bs-display');
        toggle.removeAttribute('data-bs-auto-close');
    }

    function prepareDropdown(dropdown) {
        if (!dropdown || dropdown.getAttribute('data-mod-actions-prepared') === '1') {
            return;
        }
        var toggle = dropdown.querySelector('.mod-actions-btn');
        if (!toggle) {
            toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
        }
        var menu = dropdown.querySelector('.dropdown-menu');
        if (!toggle || !menu) {
            return;
        }
        dropdown.setAttribute('data-mod-actions-prepared', '1');
        stripBootstrapDropdown(toggle);
        toggle.setAttribute('type', 'button');
        toggle.setAttribute('aria-haspopup', 'true');
        if (!toggle.hasAttribute('aria-expanded')) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    function scan(root) {
        (root || document).querySelectorAll('.dropdown.mod-actions').forEach(function (dropdown) {
            try {
                prepareDropdown(dropdown);
            } catch (err) {
                console.error('[AppModActions] Préparation impossible', err);
            }
        });
    }

    function toggle(btn, ev) {
        if (ev) {
            ev.preventDefault();
            ev.stopPropagation();
        }
        var entry = buildEntry(btn);
        if (!entry) {
            return false;
        }
        var isOpen = activeEntry === entry && entry.menu.classList.contains('show');
        if (isOpen) {
            closeMenuEntry(entry);
        } else {
            openMenuEntry(entry);
        }
        return false;
    }

    var toggleGuard = { btn: null, at: 0 };
    function toggleSafe(btn, ev) {
        if (!btn) {
            return false;
        }
        var now = Date.now();
        if (toggleGuard.btn === btn && now - toggleGuard.at < 450) {
            if (ev) {
                ev.preventDefault();
                ev.stopPropagation();
            }
            return false;
        }
        toggleGuard.btn = btn;
        toggleGuard.at = now;
        return toggle(btn, ev);
    }

    function getDeleteModal() {
        return document.getElementById('deleteModal');
    }

    function resolveDeleteNameTarget(modal) {
        return modal.querySelector('#patientNameToDelete, #medecinNameToDelete, [data-delete-name-target]');
    }

    function ensureDeleteModalInBody(modal) {
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    }

    function hideDeleteModal(modal) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var instance = bootstrap.Modal.getInstance(modal);
            if (instance) {
                instance.hide();
            }
        }
    }

    function updateStatsAfterDelete(stats, modal) {
        if (!stats) {
            return;
        }
        var map = {
            total: 'stat-total',
            actif: 'stat-actif',
            nouveaux_mois: 'stat-nouveaux',
            consultations_moyenne: 'stat-consult',
            conge: 'stat-conge',
            specialites: 'stat-specialites'
        };
        Object.keys(map).forEach(function (key) {
            if (stats[key] === undefined) {
                return;
            }
            var el = document.getElementById(map[key]);
            if (el) {
                el.textContent = stats[key];
            }
        });

        var totalEl = document.querySelector('.mod-list-count');
        if (!totalEl || stats.total === undefined) {
            return;
        }
        var match = totalEl.textContent.match(/(\d+)/g);
        var affiches = match ? Math.max(0, parseInt(match[0], 10) - 1) : 0;
        var label = modal.getAttribute('data-delete-entity-label') || 'élément(s)';
        totalEl.textContent = 'Affichage de ' + affiches + ' ' + label + ' sur ' + stats.total + ' au total';
    }

    function removeDeletedRow(id, rowKey, stats, modal) {
        var row = document.querySelector('tr[' + rowKey + '="' + id + '"]');
        if (!row) {
            location.reload();
            return;
        }
        row.style.transition = 'opacity 0.3s, transform 0.3s';
        row.style.opacity = '0';
        row.style.transform = 'translateX(-8px)';
        setTimeout(function () {
            row.remove();
            updateStatsAfterDelete(stats, modal);
            if (document.querySelectorAll('tr[' + rowKey + ']').length === 0) {
                location.reload();
            }
        }, 300);
    }

    function runDeleteRequest(modal, confirmBtn) {
        var id = parseInt(modal.getAttribute('data-pending-delete-id') || '0', 10);
        if (!id) {
            return;
        }
        var url = modal.getAttribute('data-delete-url') || 'ajax_supprimer.php';
        var rowKey = modal.getAttribute('data-delete-row-key') || 'data-patient-id';
        var defaultHtml = confirmBtn ? confirmBtn.innerHTML : '';

        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Suppression...';
        }

        var formData = new FormData();
        formData.append('id', id);

        fetch(url, {
            method: 'POST',
            body: formData,
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                hideDeleteModal(modal);
                if (data.success) {
                    removeDeletedRow(id, rowKey, data.stats, modal);
                } else {
                    alert('Erreur : ' + (data.message || data.error || 'Suppression échouée'));
                }
            })
            .catch(function (err) {
                alert('Erreur réseau : ' + err.message);
            })
            .finally(function () {
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = defaultHtml;
                }
                modal.removeAttribute('data-pending-delete-id');
            });
    }

    function bindDeleteModals() {
        var modal = getDeleteModal();
        var confirmBtn = document.getElementById('confirmDeleteBtn');
        if (!modal || !confirmBtn || confirmBtn.getAttribute('data-mod-delete-bound') === '1') {
            return;
        }
        confirmBtn.setAttribute('data-mod-delete-bound', '1');
        var defaultHtml = confirmBtn.innerHTML;

        confirmBtn.addEventListener('click', function () {
            runDeleteRequest(modal, confirmBtn);
        });

        modal.addEventListener('hidden.bs.modal', function () {
            modal.removeAttribute('data-pending-delete-id');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = defaultHtml;
        });
    }

    function openDeleteModal(id, name, trigger) {
        closeAll();
        var modal = getDeleteModal();
        if (!modal) {
            alert('Impossible d\'ouvrir la confirmation de suppression.');
            return false;
        }

        modal.setAttribute('data-pending-delete-id', String(id));

        if (trigger) {
            var triggerUrl = trigger.getAttribute('data-delete-url');
            var triggerRowKey = trigger.getAttribute('data-delete-row-key');
            if (triggerUrl) {
                modal.setAttribute('data-delete-url', triggerUrl);
            }
            if (triggerRowKey) {
                modal.setAttribute('data-delete-row-key', triggerRowKey);
            }
        }

        var nameEl = resolveDeleteNameTarget(modal);
        if (nameEl) {
            nameEl.textContent = name || '';
        }

        ensureDeleteModalInBody(modal);

        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            if (window.confirm('Confirmer la suppression de « ' + (name || '') + ' » ?')) {
                runDeleteRequest(modal, null);
            } else {
                modal.removeAttribute('data-pending-delete-id');
            }
            return false;
        }

        bootstrap.Modal.getOrCreateInstance(modal).show();
        return false;
    }

    function handleDeleteTrigger(trigger, ev) {
        if (ev) {
            ev.preventDefault();
            ev.stopPropagation();
        }

        var id = parseInt(trigger.getAttribute('data-delete-id') || '0', 10);
        if (!id) {
            return false;
        }

        var now = Date.now();
        if (deleteGuard.id === id && now - deleteGuard.at < 400) {
            return false;
        }
        deleteGuard.id = id;
        deleteGuard.at = now;

        var name = trigger.getAttribute('data-delete-name') || '';

        if (typeof window.confirmDelete === 'function' && window.confirmDelete !== openDeleteModal) {
            closeAll();
            window.confirmDelete(id, name);
            return false;
        }

        return openDeleteModal(id, name, trigger);
    }

    document.addEventListener('click', function (e) {
        var menu = e.target.closest('.mod-actions-menu');
        if (!menu) {
            return;
        }

        var deleteTrigger = e.target.closest('.js-mod-delete-trigger, [data-delete-id]');
        if (deleteTrigger && deleteTrigger.closest('.mod-actions-menu')) {
            handleDeleteTrigger(deleteTrigger, e);
            return;
        }

        var item = e.target.closest('a.dropdown-item, button.dropdown-item');
        if (item && !item.classList.contains('mod-actions-btn')) {
            setTimeout(closeAll, 0);
        }
    }, true);

    document.addEventListener('click', function (e) {
        if (e.target.closest('.mod-actions-menu')) {
            return;
        }
        if (e.target.closest('.mobile-global-nav')) {
            return;
        }
        var btn = e.target.closest('.dropdown.mod-actions .mod-actions-btn');
        if (!btn) {
            return;
        }
        if (btn.getAttribute('onclick')) {
            return;
        }
        toggleSafe(btn, e);
    });

    document.addEventListener('touchend', function (e) {
        if (window.matchMedia && !window.matchMedia('(pointer: coarse)').matches) {
            return;
        }
        var btn = e.target.closest('.dropdown.mod-actions .mod-actions-btn');
        if (!btn) {
            return;
        }
        toggleSafe(btn, e);
    }, { passive: false });

    document.addEventListener('click', function (e) {
        if (Date.now() < openGuardUntil) {
            return;
        }
        if (e.target.closest('.mod-actions-btn')) {
            return;
        }
        if (e.target.closest('.mod-actions-menu')) {
            return;
        }
        if (e.target.closest('.mobile-global-nav')) {
            return;
        }
        closeAll();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAll();
        }
    });

    window.addEventListener('resize', function () {
        if (!activeEntry || !activeEntry.menu.classList.contains('show')) {
            return;
        }
        positionMenu(activeEntry.toggle, activeEntry.menu);
    });

    window.addEventListener('scroll', function () {
        if (!activeEntry || !activeEntry.menu.classList.contains('show')) {
            return;
        }
        positionMenu(activeEntry.toggle, activeEntry.menu);
    }, true);

    function boot() {
        scan(document);
        bindDeleteModals();
    }

    window.AppModActions = {
        scan: scan,
        toggle: toggleSafe,
        prepareDropdown: prepareDropdown,
        closeAll: closeAll,
        bindDropdown: prepareDropdown,
        handleDeleteTrigger: handleDeleteTrigger,
        openDeleteModal: openDeleteModal,
        bindDeleteModals: bindDeleteModals
    };

    window.confirmDelete = window.confirmDelete || function (id, name) {
        openDeleteModal(id, name);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.addEventListener('load', boot);
})();
