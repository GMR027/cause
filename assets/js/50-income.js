'use strict';

/**
 * Cauce · flujo de ingreso.
 */
(() => {
    const createForm = document.querySelector('[data-income-form]');

    if (createForm) {
        const note = createForm.querySelector('[data-extra-note]');

        const updateNote = () => {
            const type = createForm.querySelector('[data-income-type]:checked');

            if (note) {
                note.hidden = !type || type.value !== 'extra';
            }
        };

        createForm.querySelectorAll('[data-income-type]').forEach((input) => {
            input.addEventListener('change', updateNote);
        });

        updateNote();
    }

    const allocationForm = document.querySelector('[data-allocation-form]');

    if (allocationForm) {
        const summary = allocationForm.querySelector('[data-allocation-summary]');
        const cards = Array.from(allocationForm.querySelectorAll('[data-allocation]'));

        const updateSummary = () => {
            const counts = {
                acreditado: 0,
                justificado: 0,
                no_controlado: 0,
            };

            cards.forEach((card) => {
                const checked = card.querySelector('input[type="radio"]:checked');

                if (checked) {
                    counts[checked.value] = (counts[checked.value] || 0) + 1;
                }
            });

            if (summary) {
                const total = counts.acreditado + counts.justificado + counts.no_controlado;

                summary.textContent = total === 0
                    ? t('income.acreditar.summary_empty')
                    : t('income.acreditar.summary_text', {
                        acreditado: counts.acreditado,
                        justificado: counts.justificado,
                        no_controlado: counts.no_controlado,
                    });
            }
        };

        cards.forEach((card) => {
            const justify = card.querySelector('[data-justify]');
            const reason = card.querySelector('[data-justify-reason]');

            const sync = () => {
                const checked = card.querySelector('input[type="radio"]:checked');
                const isJustified = checked && checked.value === 'justificado';

                if (justify) {
                    justify.classList.toggle('d-none', !isJustified);
                }

                if (reason) {
                    reason.required = Boolean(isJustified);
                }
            };

            card.querySelectorAll('input[type="radio"]').forEach((input) => {
                input.addEventListener('change', () => {
                    sync();
                    updateSummary();
                });
            });

            sync();
        });

        allocationForm.addEventListener('submit', (event) => {
            const missing = cards.some((card) => !card.querySelector('input[type="radio"]:checked'));

            if (missing) {
                event.preventDefault();
                updateSummary();
            }
        });

        updateSummary();
    }

    const extraForm = document.querySelector('[data-extra-form]');

    if (extraForm) {
        const total = extraForm.querySelector('[data-extra-total]');
        const inputs = Array.from(extraForm.querySelectorAll('[data-extra-input]'));

        const updateTotal = () => {
            const value = inputs.reduce((sum, input) => sum + (parseInt(input.value, 10) || 0), 0);

            if (total) {
                total.textContent = String(value);
                total.classList.toggle('text-danger', value !== 100);
                total.classList.toggle('text-success', value === 100);
            }
        };

        inputs.forEach((input) => input.addEventListener('input', updateTotal));
        updateTotal();
    }
})();
