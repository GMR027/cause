'use strict';

/**
 * Cauce · lecciones contextuales.
 *
 * Marca una lección como vista la primera vez que se abre. No hay push ni
 * notificaciones: solo desaparece el chip de contenido nuevo.
 */
(() => {
    const panels = document.querySelectorAll('[data-lesson-panel]');

    if (panels.length === 0) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]');
    const token = csrf ? csrf.getAttribute('content') || '' : '';

    panels.forEach((panel) => {
        panel.addEventListener('shown.bs.offcanvas', () => {
            if (panel.getAttribute('data-seen') === '1') {
                return;
            }

            const lessonId = panel.getAttribute('data-lesson-id') || '';
            const code = panel.getAttribute('data-lesson-code') || '';

            if (!lessonId || !code) {
                return;
            }

            panel.setAttribute('data-seen', '1');

            fetch('/lecciones/vista', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'fetch',
                },
                body: new URLSearchParams({ _token: token, codigo: code }).toString(),
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('lesson');
                    }

                    const badge = document.querySelector('[data-lesson-new="' + lessonId + '"]');

                    if (badge) {
                        badge.remove();
                    }
                })
                .catch(() => {
                    panel.removeAttribute('data-seen');
                });
        });
    });
})();
