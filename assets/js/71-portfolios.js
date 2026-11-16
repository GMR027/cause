'use strict';

/**
 * Cauce · portafolios de inversión (/inversion).
 */
(() => {
    const set = (element, next) => {
        if (element) {
            element.value = next;
        }
    };

    // Portafolio → instrumento: muestra solo los instrumentos del portafolio elegido.
    const syncPositions = (portfolioSelect) => {
        const key = portfolioSelect.getAttribute('data-portfolio-select');
        const form = portfolioSelect.form;
        const positionSelect = form ? form.querySelector(`[data-position-select="${key}"]`) : null;

        if (!positionSelect) {
            return;
        }

        let firstVisible = null;

        Array.from(positionSelect.options).forEach((option) => {
            const owner = option.getAttribute('data-portfolio');
            const visible = owner === null || owner === portfolioSelect.value;
            option.hidden = !visible;
            option.disabled = !visible;

            if (visible && firstVisible === null) {
                firstVisible = option;
            }
        });

        const selected = positionSelect.selectedOptions[0];

        if ((!selected || selected.disabled) && firstVisible) {
            positionSelect.value = firstVisible.value;
        }
    };

    document.querySelectorAll('[data-portfolio-select]').forEach((select) => {
        select.addEventListener('change', () => syncPositions(select));
        syncPositions(select);
    });

    // Botones que abren una hoja con portafolio/instrumento ya elegidos.
    document.querySelectorAll('[data-invest-prefill]').forEach((button) => {
        button.addEventListener('click', () => {
            const sheet = document.querySelector(button.getAttribute('data-bs-target') || '');
            const form = sheet ? sheet.querySelector('form') : null;

            if (!form) {
                return;
            }

            const portfolio = form.querySelector('[data-portfolio-select]');
            const position = form.querySelector('[data-position-select]');

            if (portfolio && button.hasAttribute('data-portfolio')) {
                portfolio.value = button.getAttribute('data-portfolio');
            }

            if (portfolio) {
                syncPositions(portfolio);
            }

            if (position) {
                set(position, button.getAttribute('data-position') || '0');

                if (position.selectedIndex < 0) {
                    position.selectedIndex = 0;
                }
            }
        });
    });

    // Bajas y comisiones siempre restan: no se elige destino.
    const yieldType = document.querySelector('[data-yield-type]');
    const yieldDestination = document.querySelector('[data-yield-destination]');

    if (yieldType && yieldDestination) {
        const syncDestination = () => {
            const isLoss = ['baja', 'comision'].includes(yieldType.value);
            yieldDestination.hidden = isLoss;
            yieldDestination.querySelectorAll('input').forEach((input) => {
                input.disabled = isLoss;
            });
        };

        yieldType.addEventListener('change', syncDestination);
        syncDestination();
    }

    document.querySelectorAll('[data-yield-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-yield-edit-form]');

            if (!form) {
                return;
            }

            const paid = button.getAttribute('data-paid') === '1';
            set(form.querySelector('[name="id"]'), button.getAttribute('data-id') || '');
            set(form.querySelector('[name="tipo"]'), button.getAttribute('data-type') || 'interes');
            set(form.querySelector('[name="monto"]'), button.getAttribute('data-amount') || '');
            set(form.querySelector('[name="moneda"]'), button.getAttribute('data-currency') || '');
            set(form.querySelector('[name="fecha"]'), button.getAttribute('data-date') || '');
            set(form.querySelector('[name="nota"]'), button.getAttribute('data-note') || '');

            const amount = form.querySelector('[name="monto"]');
            const type = form.querySelector('[name="tipo"]');
            const note = form.querySelector('[data-yield-paid-note]');

            if (amount) {
                amount.readOnly = paid;
            }

            if (type) {
                const loss = ['baja', 'comision'].includes(type.value);
                Array.from(type.options).forEach((option) => {
                    const optionLoss = ['baja', 'comision'].includes(option.value);
                    option.disabled = paid ? option.value !== type.value : optionLoss !== loss;
                });
            }

            if (note) {
                note.hidden = !paid;
            }
        });
    });

    // Portafolio: nuevo o editar.
    const portfolioForm = document.querySelector('[data-portfolio-form]');
    const portfolioLabel = document.getElementById('investmentPortfolioLabel');
    const portfolioMode = (edit) => {
        if (portfolioLabel) {
            portfolioLabel.textContent = portfolioLabel.getAttribute(edit ? 'data-label-edit' : 'data-label-new') || '';
        }
    };

    document.querySelectorAll('[data-portfolio-new]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!portfolioForm) {
                return;
            }

            portfolioForm.setAttribute('action', '/inversion/portafolios');
            set(portfolioForm.querySelector('[name="id"]'), '');
            set(portfolioForm.querySelector('[name="nombre"]'), '');
            set(portfolioForm.querySelector('[name="clase"]'), 'sofipo');
            set(portfolioForm.querySelector('[name="institucion"]'), '');
            portfolioMode(false);
        });
    });

    document.querySelectorAll('[data-portfolio-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!portfolioForm) {
                return;
            }

            portfolioForm.setAttribute('action', '/inversion/portafolios/editar');
            set(portfolioForm.querySelector('[name="id"]'), button.getAttribute('data-id') || '');
            set(portfolioForm.querySelector('[name="nombre"]'), button.getAttribute('data-name') || '');
            set(portfolioForm.querySelector('[name="clase"]'), button.getAttribute('data-class') || 'otro');
            set(portfolioForm.querySelector('[name="institucion"]'), button.getAttribute('data-institution') || '');
            portfolioMode(true);
        });
    });

    document.querySelectorAll('[data-position-move]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-move-form]');
            const label = document.querySelector('[data-move-label]');

            if (!form) {
                return;
            }

            set(form.querySelector('[name="id"]'), button.getAttribute('data-position-id') || '');
            const select = form.querySelector('[name="portafolio_id"]');
            const current = button.getAttribute('data-portfolio');

            if (select) {
                Array.from(select.options).forEach((option) => {
                    option.disabled = option.value === current;
                });
                const next = Array.from(select.options).find((option) => !option.disabled);
                select.value = next ? next.value : '';
            }

            if (label) {
                label.textContent = button.getAttribute('data-position-name') || '';
            }
        });
    });

    // Filtro del historial por portafolio.
    const filter = document.querySelector('[data-history-filter]');

    if (filter) {
        filter.addEventListener('change', () => {
            document.querySelectorAll('[data-history-portfolio]').forEach((row) => {
                row.hidden = filter.value !== '' && row.getAttribute('data-history-portfolio') !== filter.value;
            });
        });
    }
})();
