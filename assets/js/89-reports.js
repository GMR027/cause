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

/**
 * Cauce · gráfica de patrimonio estilo bolsa: cruz y tooltip al recorrerla.
 */
(() => {
    document.querySelectorAll('[data-stock-chart]').forEach((wrap) => {
        let points = [];

        try {
            points = JSON.parse(wrap.getAttribute('data-points') || '[]');
        } catch (error) {
            points = [];
        }

        const svg = wrap.querySelector('svg');
        const cursor = wrap.querySelector('[data-stock-cursor]');
        const tooltip = wrap.querySelector('[data-stock-tooltip]');

        if (!svg || !cursor || !tooltip || points.length === 0) {
            return;
        }

        const width = parseFloat(wrap.getAttribute('data-width') || '720');
        const line = cursor.querySelector('line');
        const dot = cursor.querySelector('circle');
        const valueEl = tooltip.querySelector('[data-tooltip-value]');
        const changeEl = tooltip.querySelector('[data-tooltip-change]');
        const dateEl = tooltip.querySelector('[data-tooltip-date]');

        const show = (clientX) => {
            const rect = svg.getBoundingClientRect();

            if (rect.width === 0) {
                return;
            }

            const x = ((clientX - rect.left) / rect.width) * width;
            let nearest = points[0];

            points.forEach((point) => {
                if (Math.abs(point.x - x) < Math.abs(nearest.x - x)) {
                    nearest = point;
                }
            });

            line.setAttribute('x1', nearest.x);
            line.setAttribute('x2', nearest.x);
            dot.setAttribute('cx', nearest.x);
            dot.setAttribute('cy', nearest.y);
            cursor.hidden = false;
            cursor.classList.toggle('is-down', !nearest.up);

            valueEl.textContent = nearest.value;
            changeEl.textContent = (nearest.up && nearest.change.indexOf('-') === -1 ? '+' : '') + nearest.change;
            changeEl.classList.toggle('is-down', !nearest.up);
            dateEl.textContent = nearest.date;
            tooltip.hidden = false;

            const left = (nearest.x / width) * rect.width;
            const half = tooltip.offsetWidth / 2;
            tooltip.style.left = Math.min(rect.width - half, Math.max(half, left)) + 'px';
        };

        const hide = () => {
            cursor.hidden = true;
            tooltip.hidden = true;
        };

        svg.addEventListener('pointermove', (event) => show(event.clientX));
        svg.addEventListener('pointerdown', (event) => show(event.clientX));
        svg.addEventListener('pointerleave', hide);
        svg.addEventListener('touchmove', (event) => {
            if (event.touches.length > 0) {
                show(event.touches[0].clientX);
            }
        }, { passive: true });
        svg.addEventListener('touchend', hide);
    });
})();

/**
 * Cauce · suscripciones: el mismo formulario sirve para registrar y editar.
 */
(() => {
    const form = document.querySelector('[data-subscription-form]');

    if (!form) {
        return;
    }

    const title = document.getElementById('subscriptionFormTitle');
    const currency = document.querySelector('[data-subscription-currency]');
    const field = (name) => form.querySelector('[name="' + name + '"]');
    const set = (name, value) => {
        const input = field(name);

        if (input) {
            input.value = value === null || value === undefined ? '' : String(value);
        }
    };

    document.querySelectorAll('[data-subscription-new]').forEach((button) => {
        button.addEventListener('click', () => {
            form.reset();
            form.action = form.getAttribute('data-action-new');
            set('id', '');

            if (title) {
                title.textContent = title.getAttribute('data-title-new');
            }

            if (currency) {
                currency.hidden = false;
            }
        });
    });

    document.querySelectorAll('[data-subscription-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            let item = {};

            try {
                item = JSON.parse(button.getAttribute('data-item') || '{}');
            } catch (error) {
                return;
            }

            form.action = form.getAttribute('data-action-edit');
            set('id', item.id);
            set('nombre', item.name);
            set('proveedor', item.provider);
            set('monto', (Number(item.amount_centavos || 0) / 100).toFixed(2));
            set('recurrencia', item.frequency);
            set('proxima_ejecucion', item.next);
            set('parte_metodo', item.category);
            set('prueba_hasta', item.trial_until);
            set('recordatorio_dias', item.reminder_days);
            set('notas', item.notes);

            const variable = field('monto_variable');

            if (variable) {
                variable.checked = Boolean(item.variable);
            }

            // La moneda de un recurrente no cambia al editarlo.
            if (currency) {
                currency.hidden = true;
            }

            if (title) {
                title.textContent = title.getAttribute('data-title-edit');
            }
        });
    });
})();
