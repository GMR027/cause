'use strict';

/**
 * Cauce · bloqueo por inactividad.
 *
 * Si la página se queda abierta sin que el usuario la toque durante
 * body[data-idle-lock] minutos (15 por defecto), se va a /desbloquear, que
 * bloquea la sesión en el servidor. La última actividad se comparte entre
 * pestañas con localStorage; al volver a la app (Android en segundo plano,
 * pestaña oculta) se revisa en cuanto la página vuelve a verse.
 */
(() => {
    const minutes = Number(document.body?.getAttribute('data-idle-lock') || 0);

    if (!(minutes > 0)) {
        return;
    }

    const limit = minutes * 60 * 1000;
    const key = 'cauce.ultimaActividad';
    let last = Date.now();
    let locking = false;

    const read = () => {
        try {
            const stored = Number(window.localStorage.getItem(key) || 0);

            return stored > last ? stored : last;
        } catch (error) {
            return last;
        }
    };

    const write = () => {
        try {
            window.localStorage.setItem(key, String(last));
        } catch (error) {
            // Sin almacenamiento: solo cuenta esta pestaña.
        }
    };

    const lock = () => {
        if (locking) {
            return;
        }

        locking = true;
        const back = window.location.pathname + window.location.search;
        window.location.replace('/desbloquear?volver=' + encodeURIComponent(back));
    };

    const check = () => {
        if (Date.now() - read() >= limit) {
            lock();
        }
    };

    let lastWrite = 0;

    const touch = () => {
        if (locking) {
            return;
        }

        // Si ya pasó el tiempo (p. ej. el teléfono estuvo dormido), primero se bloquea.
        if (Date.now() - read() >= limit) {
            lock();

            return;
        }

        last = Date.now();

        if (last - lastWrite > 5000) {
            lastWrite = last;
            write();
        }
    };

    ['pointerdown', 'keydown', 'wheel', 'touchstart', 'scroll'].forEach((type) => {
        window.addEventListener(type, touch, { passive: true, capture: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            check();
        }
    });
    window.addEventListener('focus', check);
    window.addEventListener('pageshow', check);

    write();
    window.setInterval(check, 20000);
})();
