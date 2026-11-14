'use strict';

/**
 * Cauce · movimientos entre categorías.
 *
 * Muestra el disponible del origen y advierte si origen y destino coinciden.
 */
(() => {
    const form = document.querySelector('[data-move-form]');

    if (!form) {
        return;
    }

    const origin = form.querySelector('[data-move-origin]');
    const destination = form.querySelector('select[name="destino"]');
    const hint = form.querySelector('[data-move-available]');
    const warning = form.querySelector('[data-move-warning]');

    const sync = () => {
        const option = origin ? origin.options[origin.selectedIndex] : null;
        const available = option ? Number(option.getAttribute('data-available') || 0) : 0;

        if (hint) {
            const amount = window.Cauce && window.Cauce.isDiscreto()
                ? '••••'
                : (window.Cauce ? window.Cauce.formatMoney(available) : String(available));
            hint.textContent = t('moves.form.available', { amount });
        }

        const same = origin && destination && origin.value !== '' && origin.value === destination.value;

        if (warning) {
            warning.classList.toggle('d-none', !same);
        }
    };

    origin?.addEventListener('change', sync);
    destination?.addEventListener('change', sync);
    sync();

    if (window.MutationObserver) {
        new MutationObserver(sync).observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-discreto'],
        });
    }
})();
