'use strict';

/**
 * Cauce · layout: sidebar, drawer, modo discreto.
 */
(() => {
    const root = document.documentElement;
    const desktop = window.matchMedia('(min-width: 1200px)');

    try {
        if (localStorage.getItem(Cauce.storage.sidebar) === '1') {
            root.classList.add('sidebar-collapsed');
        }
    } catch (error) {
        // Sin localStorage se usa el estado expandido.
    }

    const toggleSidebar = () => {
        if (desktop.matches) {
            const collapsed = root.classList.toggle('sidebar-collapsed');

            try {
                localStorage.setItem(Cauce.storage.sidebar, collapsed ? '1' : '0');
            } catch (error) {
                // Sin persistencia: solo cambia en esta página.
            }

            return;
        }

        const drawer = document.getElementById('appDrawer');

        if (drawer && window.bootstrap) {
            window.bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
        }
    };

    document.querySelectorAll('[data-action="sidebar-toggle"]').forEach((button) => {
        button.addEventListener('click', toggleSidebar);
    });

    document.querySelectorAll('[data-action="toggle-discreto"]').forEach((button) => {
        button.addEventListener('click', () => Cauce.toggleDiscreto());
    });

    Cauce.setDiscreto(Cauce.isDiscreto());
})();
