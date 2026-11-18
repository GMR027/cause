'use strict';

/**
 * Cauce · deudas: edición, pago y recordatorios.
 */
(() => {
    const form = document.querySelector('[data-debt-form]');

    if (!form) {
        return;
    }

    const idInput = form.querySelector('[name="id"]');
    const title = document.getElementById('debtFormTitle');
    const nameInput = form.querySelector('[name="nombre"]');
    const totalInput = form.querySelector('[name="monto_total"]');
    const balanceInput = form.querySelector('[name="saldo_actual"]');
    const rateInput = form.querySelector('[name="tasa_interes"]');
    const minimumInput = form.querySelector('[name="pago_minimo"]');
    const limitInput = form.querySelector('[name="fecha_limite"]');
    const currencyInput = form.querySelector('[name="moneda"]');

    const setValue = (input, value) => {
        if (input) {
            input.value = value || '';
        }
    };

    const setEditMode = (editing) => {
        form.setAttribute('action', editing ? '/deudas/actualizar' : '/deudas');
        if (title) {
            title.textContent = editing ? t('common.actions.edit') : t('debts.create');
        }
    };

    document.querySelectorAll('[data-debt-reset]').forEach((button) => {
        button.addEventListener('click', () => {
            form.reset();
            setValue(idInput, '');
            setEditMode(false);
        });
    });

    document.querySelectorAll('[data-debt-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            setValue(idInput, button.getAttribute('data-id'));
            setValue(nameInput, button.getAttribute('data-name'));
            setValue(totalInput, button.getAttribute('data-total'));
            setValue(balanceInput, button.getAttribute('data-balance'));
            setValue(rateInput, button.getAttribute('data-rate'));
            setValue(minimumInput, button.getAttribute('data-minimum'));
            setValue(limitInput, button.getAttribute('data-limit'));
            setValue(currencyInput, button.getAttribute('data-currency'));
            setEditMode(true);
        });
    });

    const payName = document.querySelector('[data-debt-pay-name]');
    const payBalance = document.querySelector('[data-debt-pay-balance]');
    const payAmount = document.getElementById('debt-pay-amount');
    const payDebtId = document.querySelector('#debtPayment [name="deuda_id"]');
    const origins = document.querySelectorAll('[data-debt-origin]');
    const bagSelect = document.querySelector('[data-debt-bag]');
    const cardSelect = document.querySelector('[data-debt-card]');

    // Muestra lo que pide cada origen y desactiva el resto para no enviarlo.
    const syncOrigin = () => {
        const current = Array.from(origins).find((radio) => radio.checked);
        const value = current ? current.value : 'ingreso';

        document.querySelectorAll('[data-debt-origin-panel]').forEach((panel) => {
            const visible = panel.getAttribute('data-debt-origin-panel') === value;
            panel.hidden = !visible;
            panel.querySelectorAll('select, input').forEach((field) => {
                field.disabled = !visible;
            });
        });
    };

    // Solo bolsas y tarjetas en la moneda de la deuda; una deuda no se paga a sí misma.
    const filterOptions = (select, currency, skipId) => {
        if (!select) {
            return;
        }

        let first = null;

        Array.from(select.options).forEach((option) => {
            const optionCurrency = option.getAttribute('data-currency');
            const usable = (optionCurrency === null || optionCurrency === currency) && option.value !== skipId;
            option.hidden = !usable;
            option.disabled = !usable;

            if (usable && first === null) {
                first = option;
            }
        });

        if (first) {
            select.value = first.value;
        }
    };

    origins.forEach((radio) => radio.addEventListener('change', syncOrigin));

    document.querySelectorAll('[data-debt-pay]').forEach((button) => {
        button.addEventListener('click', () => {
            const balance = Number(button.getAttribute('data-balance') || 0);
            const currency = button.getAttribute('data-currency') || '';

            setValue(payDebtId, button.getAttribute('data-id'));
            setValue(payAmount, '');

            if (payName) {
                payName.textContent = button.getAttribute('data-name') || '';
            }

            if (payBalance) {
                payBalance.textContent = t('debts.fields.balance') + ': ' + Cauce.formatMoney(Math.round(balance * 100));
            }

            origins.forEach((radio) => {
                radio.checked = radio.value === 'ingreso';
            });
            filterOptions(bagSelect, currency, null);
            filterOptions(cardSelect, currency, button.getAttribute('data-id'));
            syncOrigin();
        });
    });

    syncOrigin();
})();
