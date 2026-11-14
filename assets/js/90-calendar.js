'use strict';

/**
 * Cauce · calendario: pulsación larga para acciones rápidas.
 */
(() => {
    const cells = document.querySelectorAll('[data-cal-day]');
    const sheet = document.getElementById('calendarQuick');

    if (cells.length === 0 || !sheet) {
        return;
    }

    const title = sheet.querySelector('#calendarQuickTitle');
    const label = sheet.querySelector('[data-quick-label]');
    const links = sheet.querySelectorAll('[data-quick-link]');
    let timer = null;
    let longPressed = false;
    let activeDate = '';

    const open = (date, dateLabel) => {
        activeDate = date;

        if (title) {
            title.textContent = dateLabel;
        }

        if (label) {
            label.textContent = dateLabel;
        }

        links.forEach((link) => {
            const target = link.getAttribute('data-quick-link');

            if (target === 'expense') {
                link.setAttribute('href', '/gastos/nuevo?fecha=' + encodeURIComponent(date));
            } else if (target === 'day') {
                link.setAttribute('href', '/calendario?fecha=' + encodeURIComponent(date));
            }
        });

        if (window.bootstrap && window.bootstrap.Offcanvas) {
            window.bootstrap.Offcanvas.getOrCreateInstance(sheet).show();
        }
    };

    const clear = () => {
        if (timer !== null) {
            window.clearTimeout(timer);
            timer = null;
        }
    };

    cells.forEach((cell) => {
        cell.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            longPressed = false;
            clear();
            timer = window.setTimeout(() => {
                longPressed = true;
                open(
                    cell.getAttribute('data-cal-date') || activeDate,
                    cell.getAttribute('data-cal-label') || ''
                );
            }, 550);
        });

        cell.addEventListener('pointerup', clear);
        cell.addEventListener('pointerleave', clear);
        cell.addEventListener('pointercancel', clear);
        cell.addEventListener('contextmenu', (event) => event.preventDefault());

        cell.addEventListener('click', (event) => {
            if (longPressed) {
                event.preventDefault();
                longPressed = false;
            }
        });
    });
})();
