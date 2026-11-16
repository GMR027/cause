/**
 * Cauce · Planeador de gastos
 *
 * Agrega y quita conceptos por sección y mantiene el total planeado y el
 * restante a la vista. El servidor siempre revalida los montos.
 */
(function () {
    'use strict';

    const parseAmount = (raw) => {
        let value = String(raw || '').replace(/[^\d.,-]/g, '').trim();

        if (value === '') {
            return 0;
        }

        if (value.includes(',') && value.includes('.')) {
            value = value.lastIndexOf(',') > value.lastIndexOf('.')
                ? value.replace(/\./g, '').replace(',', '.')
                : value.replace(/,/g, '');
        } else if (value.includes(',')) {
            const parts = value.split(',');

            value = parts.length === 2 && parts[1].length === 3
                ? parts[0] + parts[1]
                : value.replace(',', '.');
        }

        const number = Number.parseFloat(value);

        return Number.isFinite(number) ? number : 0;
    };

    // Mismo formato que Locale::formatMoney(): "$1,500.00 MXN".
    const formatAmount = (value, currency) => {
        const code = currency || 'MXN';
        let amount;

        try {
            amount = new Intl.NumberFormat('es-MX', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(value);
        } catch (error) {
            amount = value.toFixed(2);
        }

        return '$' + amount + ' ' + code;
    };

    // Da formato de miles y dos decimales al escribir en los campos de monto.
    const formatMoneyInput = (input, pad = false) => {
        if (!input || input.value.trim() === '') {
            return;
        }

        const caret = input.selectionStart ?? input.value.length;
        const digitsBefore = (input.value.slice(0, caret).match(/\d/g) || []).length;
        const dotBefore = input.value.slice(0, caret).includes('.');

        let raw = input.value.replace(/[^\d.,]/g, '').trim();

        if (raw === '') {
            input.value = '';
            return;
        }

        if (raw.includes(',') && raw.includes('.')) {
            raw = raw.lastIndexOf(',') > raw.lastIndexOf('.')
                ? raw.replace(/\./g, '').replace(',', '.')
                : raw.replace(/,/g, '');
        } else if (raw.includes(',')) {
            const parts = raw.split(',');

            raw = parts.length === 2 && parts[1].length === 3
                ? parts[0] + parts[1]
                : raw.replace(',', '.');
        }

        const firstDot = raw.indexOf('.');
        let integer = firstDot === -1 ? raw : raw.slice(0, firstDot);
        let decimals = firstDot === -1 ? '' : raw.slice(firstDot + 1).replace(/\./g, '');

        integer = integer.replace(/^0+(?=\d)/, '');

        if (firstDot !== -1 && integer === '') {
            integer = '0';
        }

        decimals = decimals.slice(0, 2);

        if (pad) {
            decimals = decimals.padEnd(2, '0');
        }

        const formattedInteger = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        let formatted = formattedInteger;

        if (firstDot !== -1 || pad) {
            formatted += '.' + decimals;
        }

        input.value = formatted;

        if (document.activeElement === input) {
            let position = 0;
            let seen = 0;

            while (position < input.value.length && seen < digitsBefore) {
                if (/\d/.test(input.value[position])) {
                    seen += 1;
                }

                position += 1;
            }

            if (dotBefore && input.value[position] === '.') {
                position += 1;
            }

            input.setSelectionRange(position, position);
        }
    };

    document.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.matches('[data-money-input]')) {
            formatMoneyInput(event.target);
        }
    });

    document.addEventListener('focusout', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.matches('[data-money-input]')) {
            formatMoneyInput(event.target, true);
        }
    });

    const sectionAssignedCents = (section) =>
        Math.round(parseAmount(section.getAttribute('data-assigned')) * 100);

    const sectionCommittedCents = (section) =>
        Math.round(parseAmount(section.getAttribute('data-committed')) * 100);

    const updateSection = (section) => {
        if (!section) {
            return;
        }

        const currency = section.getAttribute('data-currency') || 'MXN';
        const assigned = sectionAssignedCents(section);
        const committed = sectionCommittedCents(section);
        const available = assigned - committed;
        let planned = 0;

        section.querySelectorAll('[data-planner-amount]').forEach((input) => {
            planned += Math.round(parseAmount(input.value) * 100);
        });

        const remaining = available - planned;
        const plannedEl = section.querySelector('[data-planner-planned]');
        const remainingEl = section.querySelector('[data-planner-remaining]');

        if (plannedEl) {
            plannedEl.textContent = formatAmount(planned / 100, currency);
        }

        if (remainingEl) {
            remainingEl.textContent = formatAmount(remaining / 100, currency);
            remainingEl.classList.toggle('is-negative', remaining < 0);
        }

        section.classList.toggle('is-over', available < 0 || remaining < 0);
    };

    const refreshAvailable = (section, currency) => {
        const availableEl = section.querySelector('.planner-section-available');

        if (!availableEl) {
            return;
        }

        const available = sectionAssignedCents(section) - sectionCommittedCents(section);
        availableEl.classList.toggle('is-negative', available < 0);

        const valueEl = availableEl.querySelector('.money-value');

        if (valueEl) {
            valueEl.textContent = formatAmount(available / 100, currency);
        }
    };

    const applyCurrency = (currency) => {
        document.querySelectorAll('[data-planner-section]').forEach((section) => {
            section.setAttribute('data-currency', currency);
            section.querySelectorAll('.planner-amount-field .input-group-text').forEach((prefix) => {
                prefix.textContent = currency;
            });
        });
    };

    /**
     * Reparte el monto esperado entre las secciones según su porcentaje, con el
     * mismo criterio que RepartoService: redondeo hacia abajo y el residuo al
     * porcentaje mayor. Así la tarjeta reacciona al instante sin recargar.
     */
    const redistributeIncome = () => {
        const form = document.querySelector('[data-planner-form]');

        if (!form) {
            return;
        }

        const amountInput = form.querySelector('input[name="monto"]');
        const currencySelect = form.querySelector('select[name="moneda"]');
        const currency = String(currencySelect ? currencySelect.value : '').toUpperCase() || 'MXN';
        const sections = Array.from(document.querySelectorAll('[data-planner-section]'));
        const entries = sections
            .map((section) => ({
                section,
                percent: Number.parseFloat(section.getAttribute('data-percent') || '0'),
            }))
            .filter((entry) => Number.isFinite(entry.percent) && entry.percent > 0);

        if (entries.length === 0) {
            return;
        }

        const totalPercent = entries.reduce((total, entry) => total + entry.percent, 0);

        // Si el método no reparte 100 %, el servidor rechaza el cálculo:
        // dejamos los montos tal como llegaron para no mostrar un reparto falso.
        if (Math.abs(totalPercent - 100) > 0.01) {
            return;
        }

        const incomeCents = Math.round(parseAmount(amountInput ? amountInput.value : '') * 100);
        const amounts = new Map();
        let mainEntry = entries[0];

        entries.forEach((entry) => {
            amounts.set(
                entry.section,
                incomeCents > 0 ? Math.floor((incomeCents * entry.percent) / 100) : 0
            );

            if (entry.percent > mainEntry.percent) {
                mainEntry = entry;
            }
        });

        if (incomeCents > 0) {
            const assignedTotal = entries.reduce(
                (total, entry) => total + (amounts.get(entry.section) || 0),
                0
            );
            const remainder = incomeCents - assignedTotal;

            if (remainder !== 0) {
                amounts.set(mainEntry.section, (amounts.get(mainEntry.section) || 0) + remainder);
            }
        }

        applyCurrency(currency);

        sections.forEach((section) => {
            if (amounts.has(section)) {
                section.setAttribute('data-assigned', ((amounts.get(section) || 0) / 100).toFixed(2));
            }

            refreshAvailable(section, currency);
            updateSection(section);
        });
    };

    let nextIndex = 10000;

    document.addEventListener('click', (event) => {
        const add = event.target.closest('[data-planner-add]');

        if (add) {
            event.preventDefault();
            const section = add.closest('[data-planner-editor]');
            const template = section ? section.querySelector('template[data-planner-row-template]') : null;
            const rows = section ? section.querySelector('[data-planner-rows]') : null;

            if (template && rows) {
                rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(nextIndex++)));
                updateSection(section.closest('[data-planner-section]'));
            }

            return;
        }

        const remove = event.target.closest('[data-planner-remove]');

        if (remove) {
            event.preventDefault();
            const row = remove.closest('.planner-editor-row');
            const section = remove.closest('[data-planner-editor]');

            if (row) {
                row.remove();
            }

            updateSection(section ? section.closest('[data-planner-section]') : null);
        }
    });

    document.addEventListener('input', (event) => {
        if (!event.target.matches('[data-planner-amount]')) {
            return;
        }

        updateSection(event.target.closest('[data-planner-section]'));
    });

    document.querySelectorAll('[data-planner-section]').forEach(updateSection);

    const plannerForm = document.querySelector('[data-planner-form]');

    if (plannerForm) {
        const amountInput = plannerForm.querySelector('input[name="monto"]');
        const currencySelect = plannerForm.querySelector('select[name="moneda"]');

        if (amountInput) {
            amountInput.addEventListener('input', redistributeIncome);
            amountInput.addEventListener('change', redistributeIncome);

            if (amountInput.value.trim() !== '') {
                redistributeIncome();
            }
        }

        if (currencySelect) {
            currencySelect.addEventListener('change', redistributeIncome);
        }
    }
})();
