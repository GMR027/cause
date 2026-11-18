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

/**
 * Abre la hoja indicada en el hash (p. ej. /ahorro#savingsContribute) usando
 * su propio botón, para que corra el precargado de cada panel. Si el botón no
 * existe (p. ej. sin portafolios), el panel muestra su estado vacío.
 */
(() => {
    const id = window.location.hash.slice(1);

    if (!/^[A-Za-z][\w-]*$/.test(id) || !document.getElementById(id)?.classList.contains('offcanvas')) {
        return;
    }

    // Tras cargar todo app.js, para que los listeners de cada panel ya existan.
    window.setTimeout(() => {
        const trigger = document.querySelector(`[data-bs-toggle="offcanvas"][data-bs-target="#${id}"]`);

        if (trigger) {
            trigger.click();
        }

        history.replaceState(null, '', window.location.pathname + window.location.search);
    }, 0);
})();
