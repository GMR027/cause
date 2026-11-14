'use strict';

/**
 * Cauce · formulario de gasto.
 */
(() => {
    const form = document.querySelector('[data-expense-form]');

    if (!form) {
        return;
    }

    const available = (() => {
        try {
            return JSON.parse(form.getAttribute('data-available') || '{}');
        } catch (error) {
            return {};
        }
    })();

    const part = form.querySelector('[data-expense-part]');
    const amount = form.querySelector('[data-expense-amount]');
    const availableEl = form.querySelector('[data-expense-available]');
    const excess = form.querySelector('[data-expense-excess]');
    const excessText = form.querySelector('[data-expense-excess-text]');
    const subcategoryWrap = form.querySelector('[data-subcategory-wrap]');
    const subcategorySelect = form.querySelector('[data-expense-subcategory]');
    const sharedToggle = form.querySelector('[data-shared-toggle]');
    const sharedFields = form.querySelector('[data-shared-fields]');
    const templateId = form.querySelector('[data-template-id]');
    const tagsInput = form.querySelector('[name="tags"]');

    const amountCents = () => {
        const value = parseFloat((amount ? amount.value : '0').replace(',', '.'));

        return Number.isFinite(value) ? Math.round(value * 100) : 0;
    };

    const selectedPart = () => {
        if (!part) {
            return null;
        }

        return part.options[part.selectedIndex] || null;
    };

    const availableForPart = () => {
        const option = selectedPart();

        if (!option) {
            return 0;
        }

        return parseInt(option.getAttribute('data-disponible') || '0', 10) || 0;
    };

    const updateSubcategories = () => {
        const option = selectedPart();
        const controlada = option !== null && option.getAttribute('data-tipo') === 'controlada';

        if (subcategoryWrap) {
            subcategoryWrap.hidden = !controlada;
        }

        if (!subcategorySelect) {
            return;
        }

        if (!controlada) {
            subcategorySelect.value = '';
            return;
        }

        const partName = part ? part.value : '';
        let matches = 0;

        Array.from(subcategorySelect.options).forEach((optionElement) => {
            if (optionElement.value === '') {
                return;
            }

            const optionPart = optionElement.getAttribute('data-part') || '';
            const show = optionPart === '' || optionPart === partName;

            optionElement.hidden = !show;

            if (show) {
                matches++;
            }
        });

        if (matches === 0) {
            Array.from(subcategorySelect.options).forEach((optionElement) => {
                optionElement.hidden = false;
            });
        }

        const visible = Array.from(subcategorySelect.options)
            .filter((optionElement) => optionElement.value !== '' && !optionElement.hidden)
            .sort((a, b) => {
                return (parseInt(b.getAttribute('data-usos') || '0', 10) || 0)
                    - (parseInt(a.getAttribute('data-usos') || '0', 10) || 0);
            });

        visible.forEach((optionElement) => {
            subcategorySelect.appendChild(optionElement);
        });
    };

    const updateExcess = () => {
        const availableCents = availableForPart();
        const value = amountCents();

        if (availableEl) {
            availableEl.textContent = Cauce.formatMoney(availableCents);
        }

        if (!excess) {
            return;
        }

        const isExcess = value > availableCents;
        excess.hidden = !isExcess;

        if (isExcess && excessText) {
            excessText.textContent = t('expenses.form.excess_breakdown', {
                available: Cauce.formatMoney(availableCents),
                excess: Cauce.formatMoney(value - availableCents),
            });
        }
    };

    const debtPicker = form.querySelector('[data-expense-debt]');
    const debtSelect = form.querySelector('[name="deuda_id"]');
    const newDebtWrap = form.querySelector('[data-expense-new-debt]');
    const newDebtName = form.querySelector('[name="nueva_deuda_nombre"]');
    const sourceRadios = form.querySelectorAll('[name="fuente_exceso"]');

    const updateDebtPicker = () => {
        if (!debtPicker) {
            return;
        }

        const source = form.querySelector('[name="fuente_exceso"]:checked');
        const isDebt = source !== null && source.value === 'deuda';
        debtPicker.hidden = !isDebt;

        if (!isDebt) {
            return;
        }

        const isNew = !debtSelect || debtSelect.value === '0';

        if (newDebtWrap) {
            newDebtWrap.hidden = !isNew;
        }

        if (newDebtName) {
            newDebtName.required = isNew;
        }
    };

    sourceRadios.forEach((radio) => {
        radio.addEventListener('change', updateDebtPicker);
    });

    if (debtSelect) {
        debtSelect.addEventListener('change', updateDebtPicker);
    }

    const update = () => {
        updateSubcategories();
        updateExcess();
        updateDebtPicker();
    };

    if (part) {
        part.addEventListener('change', update);
    }

    if (amount) {
        amount.addEventListener('input', updateExcess);
    }

    if (sharedToggle && sharedFields) {
        const syncShared = () => {
            sharedFields.hidden = !sharedToggle.checked;
        };

        sharedToggle.addEventListener('change', syncShared);
        syncShared();
    }

    // Las plantillas viven junto al formulario (fuera de <form>), así que se
    // buscan en el contenedor para que el clic sí las aplique.
    const templateScope = form.parentElement || form;

    templateScope.querySelectorAll('[data-template]').forEach((chip) => {
        chip.addEventListener('click', () => {
            if (amount) {
                amount.value = chip.getAttribute('data-template-amount') || '';
            }

            if (part) {
                part.value = chip.getAttribute('data-template-part') || part.value;
            }

            const currency = form.querySelector('[name="moneda"]');

            if (currency) {
                currency.value = chip.getAttribute('data-template-currency') || currency.value;
            }

            if (subcategorySelect) {
                const subcategory = chip.getAttribute('data-template-subcategory') || '0';
                subcategorySelect.value = subcategory !== '0' ? subcategory : '';
            }

            if (tagsInput) {
                tagsInput.value = chip.getAttribute('data-template-tags') || '';
            }

            if (templateId) {
                templateId.value = chip.getAttribute('data-template-id') || '';
            }

            update();
        });
    });

    if (tagsInput) {
        tagsInput.addEventListener('blur', () => {
            tagsInput.value = tagsInput.value
                .split(',')
                .map((tag) => tag.trim().toLowerCase())
                .filter((tag, index, list) => tag !== '' && list.indexOf(tag) === index)
                .slice(0, 5)
                .join(', ');
        });
    }

    // Mantiene el mapa disponible para futuras extensiones de la UI.
    form.CauceAvailable = available;

    update();
})();
