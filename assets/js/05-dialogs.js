'use strict';

/**
 * Cauce · diálogos estéticos en lugar de confirm()/alert() nativos.
 *
 * - Formularios con data-confirm="Mensaje" piden confirmación antes de
 *   enviarse. data-confirm-danger pinta el botón en rojo y
 *   data-confirm-title cambia el título.
 * - Cauce.confirm(mensaje, opciones) y Cauce.alert(mensaje) devuelven una
 *   promesa para usarlos desde otros scripts.
 */
(() => {
    const root = document.querySelector('[data-app-dialog]');
    const strings = document.querySelector('[data-dialog-strings]');

    const fallbackConfirm = (message) => Promise.resolve(window.confirm(message));
    const fallbackAlert = (message) => {
        window.alert(message);

        return Promise.resolve(true);
    };

    if (!root || !window.bootstrap || !window.bootstrap.Offcanvas) {
        window.Cauce = Object.assign(window.Cauce || {}, { confirm: fallbackConfirm, alert: fallbackAlert });

        return;
    }

    const sheet = window.bootstrap.Offcanvas.getOrCreateInstance(root);
    const title = root.querySelector('[data-dialog-title]');
    const message = root.querySelector('[data-dialog-message]');
    const icon = root.querySelector('[data-dialog-icon]');
    const ok = root.querySelector('[data-dialog-ok]');
    const cancel = root.querySelector('[data-dialog-cancel]');
    let resolver = null;

    const finish = (value) => {
        const resolve = resolver;
        resolver = null;
        sheet.hide();

        if (resolve) {
            resolve(value);
        }
    };

    const open = (text, options = {}) => {
        if (resolver) {
            resolver(false);
        }

        const kind = options.kind || 'confirm';
        const danger = Boolean(options.danger);

        title.textContent = options.title
            || (strings ? strings.getAttribute(kind === 'alert'
                ? 'data-notice-title'
                : (danger ? 'data-danger-title' : 'data-confirm-title')) : '')
            || '';
        message.textContent = text || '';
        ok.textContent = options.okLabel
            || ok.getAttribute(kind === 'alert' ? 'data-label-ok' : (danger ? 'data-label-danger' : 'data-label-confirm'));
        ok.classList.toggle('btn-danger', danger);
        ok.classList.toggle('btn-primary', !danger);
        cancel.hidden = kind === 'alert';
        icon.className = 'app-dialog-icon mb-3' + (danger ? ' is-danger' : (kind === 'alert' ? ' is-info' : ''));
        icon.innerHTML = '<i class="bi ' + (danger ? 'bi-exclamation-triangle' : (kind === 'alert' ? 'bi-info-lg' : 'bi-question-lg')) + '"></i>';

        return new Promise((resolve) => {
            resolver = resolve;
            sheet.show();
        });
    };

    ok.addEventListener('click', () => finish(true));
    cancel.addEventListener('click', () => finish(false));
    root.addEventListener('shown.bs.offcanvas', () => ok.focus());
    root.addEventListener('hidden.bs.offcanvas', () => {
        if (resolver) {
            finish(false);
        }
    });
    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            finish(false);
        }
    });

    window.Cauce = Object.assign(window.Cauce || {}, {
        confirm: (text, options = {}) => open(text, Object.assign({}, options, { kind: 'confirm' })),
        alert: (text, options = {}) => open(text, Object.assign({}, options, { kind: 'alert' })),
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
            return;
        }

        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;

            return;
        }

        event.preventDefault();
        const submitter = event.submitter || null;

        open(form.getAttribute('data-confirm'), {
            danger: form.hasAttribute('data-confirm-danger'),
            title: form.getAttribute('data-confirm-title') || '',
        }).then((accepted) => {
            if (!accepted) {
                return;
            }

            form.dataset.confirmed = '1';

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitter && form.contains(submitter) ? submitter : undefined);
            } else {
                form.submit();
            }
        });
    }, true);
})();
