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
    const registerExpense = document.getElementById('debt-register-expense');
    const categoryWrap = document.querySelector('[data-debt-category-wrap]');

    document.querySelectorAll('[data-debt-pay]').forEach((button) => {
        button.addEventListener('click', () => {
            const balance = Number(button.getAttribute('data-balance') || 0);

            setValue(payDebtId, button.getAttribute('data-id'));
            setValue(payAmount, '');

            if (payName) {
                payName.textContent = button.getAttribute('data-name') || '';
            }

            if (payBalance) {
                payBalance.textContent = t('debts.fields.balance') + ': ' + Cauce.formatMoney(Math.round(balance * 100));
            }

            if (registerExpense) {
                registerExpense.checked = false;
            }

            if (categoryWrap) {
                categoryWrap.hidden = true;
            }
        });
    });

    if (registerExpense && categoryWrap) {
        const syncCategory = () => {
            categoryWrap.hidden = !registerExpense.checked;
        };

        registerExpense.addEventListener('change', syncCategory);
        syncCategory();
    }
})();
