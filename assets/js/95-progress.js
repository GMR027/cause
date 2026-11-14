'use strict';

/**
 * Cauce · celebración de logros.
 *
 * Muestra el modal una vez y marca el hito como celebrado al cerrarlo.
 */
(() => {
    const modal = document.getElementById('celebrationModal');

    if (!modal) {
        return;
    }

    const id = modal.getAttribute('data-celebration-id') || '0';
    const url = modal.getAttribute('data-celebration-url') || '/logros/celebracion';
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let marked = false;

    const markCelebrated = () => {
        if (marked || id === '0') {
            return;
        }

        marked = true;

        const body = new URLSearchParams({
            _token: token,
            id,
        });

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'fetch',
            },
            body,
        }).catch(() => {
            // Si falla, el hito se volverá a mostrar en la próxima visita.
        });
    };

    modal.addEventListener('hidden.bs.modal', markCelebrated);

    const show = () => {
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modal, {
                backdrop: 'static',
                keyboard: true,
            }).show();
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', show);
    } else {
        show();
    }
})();
