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

    const HEADING = 'h1, h2, h3, h4, h5, h6, legend, .form-label';

    // Etiqueta «real»: visible y que no sea una tarjeta de opción que
    // envuelve el campo con más contenido (option-card, swatches…).
    const usableLabel = (label, field) => {
        if (!label || label.classList.contains('visually-hidden') || label.closest('.visually-hidden')) {
            return null;
        }

        if (label.contains(field) && !label.classList.contains('form-check-label')) {
            const text = labelText(label);

            // Una etiqueta que envuelve el campo solo sirve si es texto corto.
            if (text === '' || label.querySelector('.option-body, .option-title, span span, img, i.bi')) {
                return null;
            }
        }

        return label;
    };

    // Título de un grupo de opciones: el legend del fieldset o el encabezado
    // que va justo antes del contenedor de todas las opciones.
    const groupHeading = (field) => {
        const fieldset = field.closest('fieldset');
        const legend = fieldset ? fieldset.querySelector(':scope > legend') : null;

        if (legend) {
            return legend;
        }

        const form = field.form || document;
        const name = field.getAttribute('name');
        const all = Array.from(form.querySelectorAll('[name="' + CSS.escape(name) + '"]'));
        let box = field.parentElement;

        while (box && box !== form && !all.every((item) => box.contains(item))) {
            box = box.parentElement;
        }

        // Si todas las opciones caben en una sola etiqueta (un checkbox suelto), vale esa.
        if (all.length === 1) {
            const own = usableLabel(findLabel(field), field);

            if (own) {
                return own;
            }
        }

        for (let node = box; node && node !== form; node = node.parentElement) {
            let previous = node.previousElementSibling;

            while (previous && previous.matches('p, .form-text, .small')) {
                previous = previous.previousElementSibling;
            }

            if (previous && previous.matches(HEADING)) {
                return previous;
            }

            if (previous) {
                break;
            }
        }

        return null;
    };

    const attachButton = (field, label) => {
        if (!label || label.querySelector('.field-help')) {
            return;
        }

        const button = makeButton(helpTextFor(field, label));

        const type = (field.getAttribute('type') || '').toLowerCase();

        if (label.contains(field) && type !== 'checkbox' && type !== 'radio') {
            // Etiqueta que envuelve el campo: el «?» va junto al texto, no tras el campo.
            field.insertAdjacentElement('beforebegin', button);
            return;
        }

        label.appendChild(button);
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

                attachButton(field, groupHeading(field));

                return;
            }

            // Sin etiqueta visible no se agrega: un «?» suelto se encima al
            // contenido de tablas, filtros y grupos.
            attachButton(field, usableLabel(findLabel(field), field));
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
