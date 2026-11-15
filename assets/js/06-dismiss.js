'use strict';

/**
 * Cauce · avisos que se pueden cerrar con una X.
 *
 * Cualquier elemento con data-dismissible recibe un botón X. Si además tiene
 * data-dismiss-key, se recuerda durante la sesión para no volver a mostrarlo
 * al navegar (vuelve a aparecer en una sesión nueva si sigue aplicando).
 */
(() => {
    const STORAGE_KEY = 'cauce_dismissed';
    const label = document.documentElement.getAttribute('data-close-label') || 'Cerrar';

    const read = () => {
        try {
            return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
        } catch (error) {
            return [];
        }
    };

    const remember = (key) => {
        try {
            const list = read();

            if (!list.includes(key)) {
                list.push(key);
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(list.slice(-200)));
            }
        } catch (error) {
            // Sin sessionStorage solo se oculta en esta página.
        }
    };

    const dismissed = read();

    document.querySelectorAll('[data-dismissible]').forEach((element) => {
        const key = element.getAttribute('data-dismiss-key');

        if (key && dismissed.includes(key)) {
            element.remove();
            return;
        }

        if (element.querySelector(':scope > .dismiss-x')) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn-close dismiss-x';
        button.setAttribute('aria-label', label);
        button.title = label;
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (key) {
                remember(key);
            }

            element.classList.add('is-dismissing');
            window.setTimeout(() => {
                const parent = element.parentElement;
                element.remove();

                // Si un contenedor de avisos queda vacío, también se quita.
                if (parent && parent.hasAttribute('data-dismiss-empty') && parent.querySelector('[data-dismissible]') === null) {
                    parent.remove();
                }
            }, 200);
        });

        element.classList.add('is-dismissible');
        element.appendChild(button);
    });
})();
