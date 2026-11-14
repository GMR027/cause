'use strict';

/**
 * Cauce · ayuda contextual.
 *
 * Agrega un globo de ayuda junto a cada campo de formulario. El texto se toma
 * de `common.help.fields.<name>` y, si no existe, se usa un mensaje genérico con la
 * etiqueta del campo. Se puede sobrescribir con `data-help="..."`.
 */
(() => {
    const SKIP_TYPES = new Set(['hidden', 'submit', 'button', 'reset', 'image']);
    const SKIP_NAMES = new Set(['_token', 'id', 'csrf_token']);

    const baseName = (name) => String(name || '').split('[')[0].replace(/\[\]$/, '');

    const labelText = (label) => {
        if (!label) {
            return '';
        }

        const clone = label.cloneNode(true);
        clone.querySelectorAll('.field-help').forEach((node) => node.remove());
        const text = (clone.textContent || '').replace(/\s+/g, ' ').trim();

        return text.length > 80 ? text.slice(0, 77) + '…' : text;
    };

    const helpTextFor = (field, label) => {
        const custom = (field.getAttribute('data-help') || '').trim();

        if (custom !== '') {
            return custom;
        }

        const key = 'common.help.fields.' + baseName(field.getAttribute('name'));
        const translated = t(key);

        if (translated && translated !== key) {
            return translated;
        }

        const labelValue = labelText(label)
            || field.getAttribute('aria-label')
            || field.getAttribute('placeholder')
            || baseName(field.getAttribute('name'));

        return t('common.help.generic', { field: labelValue });
    };

    const makeButton = (text) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'field-help';
        button.setAttribute('aria-label', t('common.help.label') + ': ' + text);
        button.setAttribute('data-bs-toggle', 'tooltip');
        button.setAttribute('data-bs-placement', 'top');
        button.setAttribute('data-bs-container', 'body');
        button.setAttribute('title', text);
        button.innerHTML = '<i class="bi bi-question-circle" aria-hidden="true"></i>';
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            // Safari/iOS no enfoca botones al tocarlos: forzamos el foco para
            // que el tooltip (trigger focus) también aparezca en móvil.
            button.focus();
        });

        return button;
    };

    const findLabel = (field) => {
        if (field.id) {
            const byFor = document.querySelector('label[for="' + CSS.escape(field.id) + '"]');

            if (byFor) {
                return byFor;
            }
        }

        return field.closest('label');
    };

    const groupLabel = (field) => {
        const group = field.closest('fieldset');

        if (group) {
            const legend = group.querySelector('legend');

            if (legend) {
                return legend;
            }
        }

        return findLabel(field);
    };

    const attachButton = (field, label) => {
        const parent = field.closest('.input-group');
        const button = makeButton(helpTextFor(field, label));

        if (label) {
            if (!label.querySelector('.field-help')) {
                label.appendChild(button);
            }

            return;
        }

        if (parent) {
            parent.insertAdjacentElement('afterend', button);
            return;
        }

        field.insertAdjacentElement('afterend', button);
    };

    const init = () => {
        if (typeof window.t !== 'function' || !window.bootstrap) {
            return;
        }

        document.querySelectorAll('input[name], select[name], textarea[name]').forEach((field) => {
            const type = (field.getAttribute('type') || '').toLowerCase();
            const name = field.getAttribute('name') || '';

            if (SKIP_TYPES.has(type) || SKIP_NAMES.has(name)) {
                return;
            }

            if (field.disabled || field.getAttribute('data-no-help') !== null) {
                return;
            }

            if (field.closest('[data-no-help]')) {
                return;
            }

            const isChoice = type === 'radio' || type === 'checkbox';

            if (isChoice) {
                const form = field.closest('form') || document;

                if (!form.__cauceHelpGroups) {
                    form.__cauceHelpGroups = new Set();
                }

                if (form.__cauceHelpGroups.has(name)) {
                    return;
                }

                form.__cauceHelpGroups.add(name);

                const label = groupLabel(field);

                if (label) {
                    attachButton(field, label);
                }

                return;
            }

            attachButton(field, findLabel(field));
        });

        document.querySelectorAll('.field-help').forEach((element) => {
            new bootstrap.Tooltip(element, {
                container: 'body',
                placement: 'top',
                trigger: 'hover focus',
                customClass: 'field-help-tooltip',
                delay: { show: 150, hide: 100 },
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
