'use strict';

/**
 * Cauce · bienvenida al iniciar sesión: el disco de la caja fuerte marca la
 * combinación y se abre. Dura 3 s; un toque o Escape la cierran antes.
 * Al terminar avisa con el evento `cauce:login-fx-done`.
 */
(() => {
    const root = document.querySelector('[data-login-fx]');

    if (!root) {
        return;
    }

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const timers = [];
    let done = false;

    const finish = (fast) => {
        if (done) {
            return;
        }

        done = true;
        timers.forEach((timer) => window.clearTimeout(timer));
        document.removeEventListener('keydown', onKey);

        const remove = () => {
            root.remove();
            document.dispatchEvent(new CustomEvent('cauce:login-fx-done'));
        };

        if (fast) {
            root.classList.add('is-leaving');
            window.setTimeout(remove, 250);
        } else {
            remove();
        }
    };

    const onKey = (event) => {
        if (event.key === 'Escape') {
            finish(true);
        }
    };

    root.addEventListener('click', () => finish(true));
    document.addEventListener('keydown', onKey);

    // El «clic» de la cerradura: vibración corta en el teléfono.
    if (!reduced && navigator.vibrate) {
        timers.push(window.setTimeout(() => navigator.vibrate([18, 40, 30]), 2000));
    }

    timers.push(window.setTimeout(() => finish(reduced), reduced ? 1200 : 3000));
})();
