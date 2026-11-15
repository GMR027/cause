'use strict';

/**
 * Cauce · paneles de ahorro, inversión y donación.
 */
(() => {
    const value = (element, next) => {
        if (element) {
            element.value = next;
        }
    };

    const checked = (element, next) => {
        if (element) {
            element.checked = Boolean(next);
        }
    };

    document.querySelectorAll('[data-meta-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-meta-form]');

            if (!form) {
                return;
            }

            form.setAttribute('action', '/ahorro/metas/editar');
            value(form.querySelector('[name="id"]'), button.getAttribute('data-meta-id') || '');
            value(form.querySelector('[name="nombre"]'), button.getAttribute('data-meta-name') || '');
            value(form.querySelector('[name="tipo"]'), button.getAttribute('data-meta-type') || 'ahorro');
            value(form.querySelector('[name="objetivo"]'), button.getAttribute('data-meta-objective') || '');
            value(form.querySelector('[name="fecha_objetivo"]'), button.getAttribute('data-meta-date') || '');
            const months = button.getAttribute('data-meta-months') || '';
            value(form.querySelector('[name="plazo_meses"]'), months === '0' ? '' : months);
            checked(form.querySelector('[name="activa"]'), button.getAttribute('data-meta-active') === '1');
        });
    });

    document.querySelectorAll('[data-meta-new]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-meta-form]');

            if (!form) {
                return;
            }

            form.setAttribute('action', '/ahorro/metas');
            value(form.querySelector('[name="id"]'), '');
            value(form.querySelector('[name="nombre"]'), '');
            value(form.querySelector('[name="tipo"]'), 'ahorro');
            value(form.querySelector('[name="objetivo"]'), '');
            value(form.querySelector('[name="fecha_objetivo"]'), '');
            value(form.querySelector('[name="plazo_meses"]'), '');
            checked(form.querySelector('[name="activa"]'), true);
        });
    });

    document.querySelectorAll('[data-contribution-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-contribution-form]');

            if (!form) {
                return;
            }

            value(form.querySelector('[name="id"]'), button.getAttribute('data-id') || '');
            value(form.querySelector('[name="monto"]'), button.getAttribute('data-amount') || '');
            value(form.querySelector('[name="fecha"]'), button.getAttribute('data-date') || '');
            checked(form.querySelector('[name="ya_ahorrado"]'), button.getAttribute('data-initial') === '1');
        });
    });

    document.querySelectorAll('[data-withdrawal-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-withdrawal-form]');

            if (!form) {
                return;
            }

            value(form.querySelector('[name="id"]'), button.getAttribute('data-id') || '');
            value(form.querySelector('[name="monto"]'), button.getAttribute('data-amount') || '');
            value(form.querySelector('[name="fecha"]'), button.getAttribute('data-date') || '');
            value(form.querySelector('[name="motivo"]'), button.getAttribute('data-reason') || 'otro');
            value(form.querySelector('[name="motivo_texto"]'), button.getAttribute('data-text') || '');
        });
    });

    document.querySelectorAll('[data-investment-contribution-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-investment-contribution-form]');

            if (!form) {
                return;
            }

            value(form.querySelector('[name="id"]'), button.getAttribute('data-id') || '');
            value(form.querySelector('[name="monto"]'), button.getAttribute('data-amount') || '');
            value(form.querySelector('[name="fecha"]'), button.getAttribute('data-date') || '');
            checked(form.querySelector('[name="es_extra"]'), button.getAttribute('data-extra') === '1');
        });
    });

    document.querySelectorAll('[data-investment-withdrawal-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-investment-withdrawal-edit-form]');

            if (!form) {
                return;
            }

            value(form.querySelector('[name="id"]'), button.getAttribute('data-id') || '');
            value(form.querySelector('[name="monto"]'), button.getAttribute('data-amount') || '');
            value(form.querySelector('[name="fecha"]'), button.getAttribute('data-date') || '');
            value(form.querySelector('[name="motivo"]'), button.getAttribute('data-reason') || 'otro');
            value(form.querySelector('[name="motivo_texto"]'), button.getAttribute('data-text') || '');
        });
    });

    document.querySelectorAll('[data-donation-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-donation-form]');

            if (!form) {
                return;
            }

            value(form.querySelector('[name="id"]'), button.getAttribute('data-id') || '');
            value(form.querySelector('[name="monto"]'), button.getAttribute('data-amount') || '');
            value(form.querySelector('[name="fecha"]'), button.getAttribute('data-date') || '');
            value(form.querySelector('[name="causa"]'), button.getAttribute('data-cause') || '');
            checked(form.querySelector('[name="es_extra"]'), button.getAttribute('data-extra') === '1');
        });
    });

    document.querySelectorAll('[data-sharing-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-sharing-form]');

            if (!form) {
                return;
            }

            value(form.querySelector('[name="id"]'), button.getAttribute('data-id') || '');
            value(form.querySelector('[name="monto"]'), button.getAttribute('data-amount') || '');
            value(form.querySelector('[name="fecha"]'), button.getAttribute('data-date') || '');
            value(form.querySelector('[name="destinatario"]'), button.getAttribute('data-recipient') || '');
            checked(form.querySelector('[name="es_extra"]'), button.getAttribute('data-extra') === '1');
        });
    });

    const withdrawReason = document.querySelector('[data-investment-withdraw-reason]');
    const rebalanceBox = document.querySelector('[data-rebalance-target]');

    if (withdrawReason && rebalanceBox) {
        const syncRebalance = () => {
            rebalanceBox.hidden = withdrawReason.value !== 'rebalanceo';
        };

        withdrawReason.addEventListener('change', syncRebalance);
        syncRebalance();
    }

    document.querySelectorAll('[data-dismiss-warning]').forEach((button) => {
        button.addEventListener('click', () => {
            const warning = button.closest('[data-finance-warning]');

            if (warning) {
                warning.hidden = true;
            }
        });
    });

    const syncMetaOptions = (form) => {
        const account = form.querySelector('[data-account-select]');
        const meta = form.querySelector('[data-meta-select]');

        if (!account || !meta) {
            return;
        }

        const accountId = account.value;
        const hasEmpty = Array.from(meta.options).some((option) => option.value === '');
        let selectedVisible = false;

        Array.from(meta.options).forEach((option) => {
            if (option.value === '') {
                option.hidden = false;
                return;
            }

            const visible = option.getAttribute('data-account') === accountId;
            option.hidden = !visible;

            if (visible && option.selected) {
                selectedVisible = true;
            }
        });

        if (!selectedVisible) {
            if (hasEmpty) {
                meta.value = '';
            } else {
                const first = Array.from(meta.options).find((option) => !option.hidden && option.value !== '');
                meta.value = first ? first.value : '';
            }
        }
    };

    document.querySelectorAll('form').forEach((form) => {
        if (!form.querySelector('[data-account-select]') || !form.querySelector('[data-meta-select]')) {
            return;
        }

        const account = form.querySelector('[data-account-select]');
        account.addEventListener('change', () => syncMetaOptions(form));
        syncMetaOptions(form);
    });

    document.querySelectorAll('[data-account-new]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-account-form]');

            if (!form) {
                return;
            }

            form.setAttribute('action', '/ahorro/cuentas');
            value(form.querySelector('[name="id"]'), '');
            value(form.querySelector('[name="nombre"]'), '');
            value(form.querySelector('[name="tipo"]'), 'banco');
            value(form.querySelector('[name="institucion"]'), '');
            value(form.querySelector('[name="saldo_inicial"]'), '0');
            checked(form.querySelector('[name="activa"]'), true);
            checked(form.querySelector('[name="predeterminada"]'), false);

            const initial = form.querySelector('[data-account-initial-wrap]');
            const currency = form.querySelector('[data-account-currency-wrap]');
            const active = form.querySelector('[data-account-active-wrap]');

            if (initial) initial.hidden = false;
            if (currency) currency.hidden = false;
            if (active) active.hidden = true;
        });
    });

    document.querySelectorAll('[data-account-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-account-form]');

            if (!form) {
                return;
            }

            form.setAttribute('action', '/ahorro/cuentas/editar');
            value(form.querySelector('[name="id"]'), button.getAttribute('data-account-id') || '');
            value(form.querySelector('[name="nombre"]'), button.getAttribute('data-account-name') || '');
            value(form.querySelector('[name="tipo"]'), button.getAttribute('data-account-type') || 'otro');
            value(form.querySelector('[name="institucion"]'), button.getAttribute('data-account-institution') || '');
            checked(form.querySelector('[name="activa"]'), button.getAttribute('data-account-active') === '1');
            checked(form.querySelector('[name="predeterminada"]'), button.getAttribute('data-account-default') === '1');

            const initial = form.querySelector('[data-account-initial-wrap]');
            const currency = form.querySelector('[data-account-currency-wrap]');
            const active = form.querySelector('[data-account-active-wrap]');

            if (initial) initial.hidden = true;
            if (currency) currency.hidden = true;
            if (active) active.hidden = false;
        });
    });

    document.querySelectorAll('[data-meta-move]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('form[action="/ahorro/metas/mover"]');
            const label = document.querySelector('[data-move-meta-label]');

            if (!form) {
                return;
            }

            value(form.querySelector('[name="meta_id"]'), button.getAttribute('data-meta-id') || '');

            if (label) {
                label.textContent = button.getAttribute('data-meta-name') || '';
            }
        });
    });

    const metaAccountEdit = () => {
        const form = document.querySelector('[data-meta-form]');
        const account = form ? form.querySelector('[data-meta-account-select]') : null;

        return account;
    };

    document.querySelectorAll('[data-meta-new]').forEach((button) => {
        button.addEventListener('click', () => {
            const account = metaAccountEdit();

            if (account) {
                account.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-meta-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-meta-form]');
            const account = metaAccountEdit();

            if (form && account) {
                account.value = button.getAttribute('data-meta-account') || '';
                account.disabled = true;
            }
        });
    });

    const positionForm = document.querySelector('[data-position-form]');
    const positionManual = positionForm ? positionForm.querySelector('[data-position-manual-wrap]') : null;

    const syncPositionMode = () => {
        if (!positionForm || !positionManual) {
            return;
        }

        const mode = positionForm.querySelector('[name="modo_valor"]');
        positionManual.hidden = mode ? mode.value !== 'manual' : false;
    };

    if (positionForm) {
        const mode = positionForm.querySelector('[name="modo_valor"]');

        if (mode) {
            mode.addEventListener('change', syncPositionMode);
            syncPositionMode();
        }
    }

    document.querySelectorAll('[data-position-new]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!positionForm) {
                return;
            }

            positionForm.setAttribute('action', '/inversion/posiciones');
            value(positionForm.querySelector('[name="id"]'), '');
            value(positionForm.querySelector('[name="nombre"]'), '');
            value(positionForm.querySelector('[name="tipo"]'), 'accion');
            value(positionForm.querySelector('[name="mercado"]'), 'EUA');
            value(positionForm.querySelector('[name="ticker"]'), '');
            value(positionForm.querySelector('[name="simbolo_proveedor"]'), '');
            value(positionForm.querySelector('[name="cantidad"]'), '1');
            value(positionForm.querySelector('[name="costo_total"]'), '');
            value(positionForm.querySelector('[name="modo_valor"]'), 'manual');
            value(positionForm.querySelector('[name="valor_manual"]'), '');
            value(positionForm.querySelector('[name="fecha_valor"]'), '');
            syncPositionMode();
        });
    });

    document.querySelectorAll('[data-position-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!positionForm) {
                return;
            }

            positionForm.setAttribute('action', '/inversion/posiciones/editar');
            value(positionForm.querySelector('[name="id"]'), button.getAttribute('data-position-id') || '');
            value(positionForm.querySelector('[name="tipo"]'), button.getAttribute('data-position-type') || 'otro');
            value(positionForm.querySelector('[name="mercado"]'), button.getAttribute('data-position-market') || 'OTRO');
            value(positionForm.querySelector('[name="ticker"]'), button.getAttribute('data-position-ticker') || '');
            value(positionForm.querySelector('[name="simbolo_proveedor"]'), button.getAttribute('data-position-symbol') || '');
            value(positionForm.querySelector('[name="nombre"]'), button.getAttribute('data-position-name') || '');
            value(positionForm.querySelector('[name="moneda"]'), button.getAttribute('data-position-currency') || 'MXN');
            value(positionForm.querySelector('[name="cantidad"]'), button.getAttribute('data-position-quantity') || '1');
            value(positionForm.querySelector('[name="costo_total"]'), button.getAttribute('data-position-cost') || '');
            value(positionForm.querySelector('[name="modo_valor"]'), button.getAttribute('data-position-mode') || 'manual');
            value(positionForm.querySelector('[name="valor_manual"]'), button.getAttribute('data-position-value') || '');
            value(positionForm.querySelector('[name="fecha_valor"]'), button.getAttribute('data-position-date') || '');
            syncPositionMode();
        });
    });

    document.querySelectorAll('[data-position-value-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const form = document.querySelector('[data-position-value-form]');
            const label = document.querySelector('[data-position-value-label]');

            if (!form) {
                return;
            }

            value(form.querySelector('[name="id"]'), button.getAttribute('data-position-id') || '');
            value(form.querySelector('[name="valor"]'), button.getAttribute('data-position-value') || '');

            if (label) {
                label.textContent = button.getAttribute('data-position-name') || '';
            }
        });
    });
})();
