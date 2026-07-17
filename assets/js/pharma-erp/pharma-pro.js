(function () {
    'use strict';

    const shell = document.querySelector('.pharma-pro-shell');
    const menuBtn = document.getElementById('pharmaProMenuBtn');
    const sidebar = document.getElementById('pharmaProSidebar');
    const themeBtn = document.getElementById('pharmaProThemeToggle');

    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    if (themeBtn && shell) {
        themeBtn.addEventListener('click', function () {
            const current = shell.getAttribute('data-theme') || 'light';
            const next = current === 'light' ? 'dark' : 'light';
            shell.setAttribute('data-theme', next);
            document.cookie = 'pharma_pro_theme=' + next + ';path=/;max-age=31536000;SameSite=Lax';
            const icon = themeBtn.querySelector('i');
            if (icon) {
                icon.className = next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        });
        const icon = themeBtn.querySelector('i');
        if (icon && shell.getAttribute('data-theme') === 'dark') {
            icon.className = 'fas fa-sun';
        }
    }

    window.PharmaPro = window.PharmaPro || {};

    PharmaPro.formatMoney = function (n) {
        return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' FCFA';
    };

    PharmaPro.toast = function (title, icon) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title,
                icon: icon || 'success',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
            });
        }
    };
})();
