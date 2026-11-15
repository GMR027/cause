'use strict';

/**
 * Cauce · portadas de tarjetas: envía el formulario al elegir la imagen.
 */
(() => {
    const MAX_BYTES = 8 * 1024 * 1024;

    document.addEventListener('change', (event) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || !input.matches('[data-cover-input]')) {
            return;
        }

        const file = input.files && input.files[0];
        const form = input.form;

        if (!file || !form) {
            return;
        }

        if (file.size > MAX_BYTES) {
            const message = input.getAttribute('data-cover-too-big') || '';

            if (window.Cauce && typeof window.Cauce.alert === 'function') {
                window.Cauce.alert(message);
            } else {
                window.alert(message);
            }
            input.value = '';
            return;
        }

        form.classList.add('is-uploading');
        form.submit();
    });
})();
