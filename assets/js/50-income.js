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

    // Acreditar: total de abonos opcionales.
    const creditForm = document.querySelector('[data-credit-form]');

    if (creditForm) {
        const totalEl = creditForm.querySelector('[data-payment-total]');
        const inputs = Array.from(creditForm.querySelectorAll('[data-payment-input]'));

        const updatePayments = () => {
            const cents = inputs.reduce((sum, input) => {
                const value = Math.round((parseFloat(input.value) || 0) * 100);

                return sum + (value > 0 ? value : 0);
            }, 0);

            if (totalEl) {
                totalEl.textContent = Cauce.formatMoney(cents);
            }
        };

        inputs.forEach((input) => input.addEventListener('input', updatePayments));
        updatePayments();
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

        const useMethod = extraForm.querySelector('[data-extra-use-method]');

        if (useMethod) {
            useMethod.addEventListener('click', () => {
                inputs.forEach((input) => {
                    input.value = input.getAttribute('data-method-percent') || '0';
                });
                updateTotal();
            });
        }

        updateTotal();
    }
    const methodForm = document.querySelector('[data-method-form]');

    if (methodForm) {
        const select = methodForm.querySelector('[data-method-select]');
        const total = methodForm.querySelector('[data-method-total]');
        const panels = Array.from(methodForm.querySelectorAll('[data-method-panel]'));

        const activePanel = () => panels.find((panel) => !panel.hidden) || null;

        const updateTotal = () => {
            const panel = activePanel();
            const value = panel
                ? Array.from(panel.querySelectorAll('[data-method-input]'))
                    .reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0)
                : 0;
            const rounded = Math.round(value * 100) / 100;

            if (total) {
                total.textContent = String(rounded);
                total.classList.toggle('text-danger', Math.abs(rounded - 100) > 0.01);
                total.classList.toggle('text-success', Math.abs(rounded - 100) <= 0.01);
            }
        };

        const showPanel = () => {
            const id = select ? select.value : '';

            panels.forEach((panel) => {
                const visible = panel.getAttribute('data-method-panel') === id;
                panel.hidden = !visible;
                panel.disabled = !visible;
            });

            updateTotal();
        };

        if (select) {
            select.addEventListener('change', showPanel);
        }

        methodForm.addEventListener('input', (event) => {
            if (event.target instanceof HTMLElement && event.target.hasAttribute('data-method-input')) {
                updateTotal();
            }
        });

        methodForm.querySelectorAll('[data-method-reset]').forEach((button) => {
            button.addEventListener('click', () => {
                const panel = button.closest('[data-method-panel]');

                if (panel) {
                    panel.querySelectorAll('[data-method-input]').forEach((input) => {
                        input.value = input.getAttribute('data-default') || '0';
                    });
                }

                updateTotal();
            });
        });

        showPanel();
    }

    const customForm = document.querySelector('[data-custom-method-form]');

    if (customForm) {
        const rows = customForm.querySelector('[data-custom-rows]');
        const template = customForm.querySelector('[data-custom-template]');
        const addButton = customForm.querySelector('[data-custom-add]');
        const total = customForm.querySelector('[data-custom-total]');
        const maxRows = parseInt(customForm.getAttribute('data-max-rows') || '10', 10);
        let nextIndex = rows ? rows.querySelectorAll('[data-custom-row]').length : 0;

        const rowCount = () => (rows ? rows.querySelectorAll('[data-custom-row]').length : 0);

        const refresh = () => {
            const value = Array.from(customForm.querySelectorAll('[data-custom-rows] [data-custom-percent]'))
                .reduce((sum, input) => sum + (parseInt(input.value, 10) || 0), 0);

            if (total) {
                total.textContent = String(value);
                total.classList.toggle('text-danger', value !== 100);
                total.classList.toggle('text-success', value === 100);
            }

            if (addButton) {
                addButton.disabled = rowCount() >= maxRows;
            }

            customForm.querySelectorAll('[data-custom-remove]').forEach((button) => {
                button.disabled = rowCount() <= 1;
            });
        };

        if (addButton && rows && template) {
            addButton.addEventListener('click', () => {
                if (rowCount() >= maxRows) {
                    return;
                }

                const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
                nextIndex += 1;
                rows.insertAdjacentHTML('beforeend', html);

                const last = rows.lastElementChild;
                const name = last ? last.querySelector('[data-custom-field="nombre"]') : null;

                if (name) {
                    name.focus();
                }

                refresh();
            });
        }

        customForm.addEventListener('click', (event) => {
            const button = event.target instanceof Element ? event.target.closest('[data-custom-remove]') : null;

            if (!button || rowCount() <= 1) {
                return;
            }

            const row = button.closest('[data-custom-row]');

            if (row) {
                row.remove();
                refresh();
            }
        });

        customForm.addEventListener('input', refresh);
        refresh();
    }
})();
