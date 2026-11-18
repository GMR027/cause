'use strict';

/**
 * Cauce · botón de ojo en cada contraseña para ver lo que se escribe.
 *
 * Se añade a todo input[type="password"] (salvo data-no-reveal). Al enviar
 * el formulario la contraseña vuelve a ocultarse para que el navegador no la
 * guarde como texto en el historial de autocompletado.
 */
(() => {
    const label = (shown) => t(shown ? 'common.actions.hide_password' : 'common.actions.show_password');

    const enhance = (input) => {
        if (input.hasAttribute('data-no-reveal') || input.closest('.password-field')) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle';
        button.setAttribute('aria-label', label(false));
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-controls', input.id || '');
        button.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';

        const group = input.closest('.input-group');

        if (group) {
            // Dentro de un input-group el botón es un elemento más del grupo.
            button.className = 'btn btn-outline-secondary password-toggle-group';
            input.insertAdjacentElement('afterend', button);
        } else {
            const wrap = document.createElement('div');
            wrap.className = 'password-field';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);
            wrap.appendChild(button);
        }

        const set = (shown) => {
            input.type = shown ? 'text' : 'password';
            button.setAttribute('aria-pressed', shown ? 'true' : 'false');
            button.setAttribute('aria-label', label(shown));
            button.innerHTML = `<i class="bi ${shown ? 'bi-eye-slash' : 'bi-eye'}" aria-hidden="true"></i>`;
        };

        button.addEventListener('click', () => {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            set(input.type === 'password');
            input.focus({ preventScroll: true });

            try {
                input.setSelectionRange(start, end);
            } catch (error) {
                // Algunos tipos no admiten selección; no importa.
            }
        });

        if (input.form) {
            input.form.addEventListener('submit', () => set(false));
        }
    };

    document.querySelectorAll('input[type="password"]').forEach(enhance);
})();

/**
 * Cauce · login: cambiar entre contraseña y PIN. El campo oculto se
 * desactiva para que no se envíe ni sea obligatorio.
 */
(() => {
    const group = document.querySelector('[data-login-method]');

    if (!group) {
        return;
    }

    const form = group.closest('form');
    const sync = () => {
        const checked = group.querySelector('input[name="metodo"]:checked');
        const method = checked ? checked.value : 'password';

        form.querySelectorAll('[data-login-panel]').forEach((panel) => {
            const active = panel.getAttribute('data-login-panel') === method;
            panel.hidden = !active;
            panel.querySelectorAll('input').forEach((input) => {
                input.disabled = !active;
                input.required = active;
            });
        });
    };

    group.querySelectorAll('input[name="metodo"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            sync();
            const panel = form.querySelector(`[data-login-panel="${radio.value}"] input`);

            if (panel) {
                panel.focus();
            }
        });
    });
    sync();
})();
