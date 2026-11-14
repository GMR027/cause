'use strict';

/**
 * Cauce · reportes: ahorro potencial de suscripciones.
 */
(() => {
    const checkboxes = document.querySelectorAll('[data-subscription]');

    if (checkboxes.length === 0) {
        return;
    }

    const totalEl = document.querySelector('[data-subscription-total]');
    const messageEl = document.querySelector('[data-subscription-message]');

    const update = () => {
        let total = 0;
        let selected = 0;

        checkboxes.forEach((checkbox) => {
            if (checkbox.checked) {
                total += parseInt(checkbox.value || '0', 10) || 0;
                selected++;
            }
        });

        if (totalEl) {
            totalEl.textContent = selected > 0 ? Cauce.formatMoney(total) : '—';
        }

        if (messageEl) {
            messageEl.textContent = selected > 0
                ? t('reports.subscriptions.savings_selected', { amount: Cauce.formatMoney(total) })
                : t('reports.subscriptions.savings_none');
        }
    };

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', update);
    });

    update();
})();
