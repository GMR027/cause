'use strict';

/**
 * Cauce · lista de deseos: edición, compra e impacto.
 */
(() => {
    const page = document.querySelector('[data-wishlist-savings]');

    if (!page) {
        return;
    }

    const savingsTotal = Number(page.getAttribute('data-wishlist-savings') || 0);
    const currency = page.getAttribute('data-currency') || 'MXN';
    const locale = (window.CauceI18n && window.CauceI18n.locale) || 'es-MX';
    const moneyFormatter = new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
    });
    const formatMoney = (cents) => moneyFormatter.format(Number(cents || 0) / 100);
    const setValue = (id, value) => {
        const element = document.getElementById(id);

        if (element) {
            element.value = value;
        }
    };

    document.querySelectorAll('[data-wishlist-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            setValue('wishlist-edit-id', button.getAttribute('data-id') || '0');
            setValue('wishlist-edit-name', button.getAttribute('data-name') || '');
            setValue('wishlist-edit-amount', button.getAttribute('data-amount') || '');
            setValue('wishlist-edit-priority', button.getAttribute('data-priority') || '2');
            setValue('wishlist-edit-target', button.getAttribute('data-target-date') || '');
            setValue('wishlist-edit-cooling-date', button.getAttribute('data-cooling-date') || '');
            setValue('wishlist-edit-cooling', '0');
            setValue('wishlist-edit-note', button.getAttribute('data-note') || '');
            setValue('wishlist-edit-currency', button.getAttribute('data-currency') || 'MXN');
        });
    });

    const updateImpact = (element, amountValue) => {
        if (!element) {
            return;
        }

        const normalized = String(amountValue || '').replace(',', '.');
        const amount = Math.round(Number(normalized) * 100);

        if (!Number.isFinite(amount) || amount <= 0) {
            element.textContent = '';
            return;
        }

        const after = Math.max(0, savingsTotal - amount);
        const percent = savingsTotal > 0 ? Math.round((amount / savingsTotal) * 1000) / 10 : 0;
        const line = t('wishlist.impact.line', { amount: formatMoney(after) });
        const detail = t('wishlist.impact.percent', { percent });
        element.innerHTML = line + '<br><span class="text-secondary">' + detail + '</span>';
    };

    document.querySelectorAll('[data-wishlist-buy]').forEach((button) => {
        button.addEventListener('click', () => {
            const amountInput = document.getElementById('wishlist-buy-amount');
            const nameElement = document.querySelector('[data-wishlist-buy-name]');
            const warning = document.querySelector('[data-wishlist-cooling-warning]');
            const warningText = document.querySelector('[data-wishlist-cooling-text]');
            const skip = document.getElementById('wishlist-skip-cooling');
            const impact = document.querySelector('[data-wishlist-impact]');
            const amount = button.getAttribute('data-amount') || '';
            const coolingActive = button.getAttribute('data-cooling-active') === '1';
            const coolingLabel = button.getAttribute('data-cooling-label') || '';
            const savingsBox = document.querySelector('[data-wishlist-use-savings]');
            const savingsLabel = document.querySelector('[data-wishlist-saved-label]');
            const savingsInput = document.getElementById('wishlist-use-savings');
            const hasSavings = button.getAttribute('data-saved') === '1';

            if (savingsBox) {
                savingsBox.classList.toggle('d-none', !hasSavings);
            }

            if (savingsInput) {
                savingsInput.checked = hasSavings;
                savingsInput.disabled = !hasSavings;
            }

            if (savingsLabel) {
                savingsLabel.textContent = button.getAttribute('data-saved-label') || '';
            }

            setValue('wishlist-buy-id', button.getAttribute('data-id') || '0');
            setValue('wishlist-buy-amount', amount);

            if (nameElement) {
                nameElement.textContent = button.getAttribute('data-name') || '';
            }

            if (warning) {
                warning.classList.toggle('d-none', !coolingActive);
            }

            if (skip) {
                skip.checked = false;
            }

            if (warningText) {
                warningText.textContent = coolingActive
                    ? t('wishlist.buy.cooling_warning', { date: coolingLabel })
                    : '';
            }

            updateImpact(impact, amount);
        });
    });

    document.querySelectorAll('[data-wishlist-contribute]').forEach((button) => {
        button.addEventListener('click', () => {
            const nameElement = document.querySelector('[data-wishlist-contribute-name]');
            const firstElement = document.querySelector('[data-wishlist-contribute-first]');
            const currencyElement = document.querySelector('[data-wishlist-contribute-currency]');
            const suggestedButton = document.querySelector('[data-wishlist-contribute-suggested]');
            const linked = button.getAttribute('data-linked') === '1';
            const suggested = button.getAttribute('data-suggested') || '';

            setValue('wishlist-contribute-id', button.getAttribute('data-id') || '0');
            setValue('wishlist-contribute-amount', '');

            if (nameElement) {
                nameElement.textContent = button.getAttribute('data-name') || '';
            }

            if (firstElement) {
                firstElement.textContent = linked ? '' : (button.getAttribute('data-first-hint') || '');
                firstElement.classList.toggle('d-none', linked);
            }

            if (currencyElement) {
                currencyElement.textContent = button.getAttribute('data-currency') || currency;
            }

            if (suggestedButton) {
                suggestedButton.textContent = button.getAttribute('data-suggested-label') || '';
                suggestedButton.setAttribute('data-value', suggested);
                suggestedButton.classList.toggle('d-none', suggested === '');
            }
        });
    });

    document.querySelector('[data-wishlist-contribute-suggested]')?.addEventListener('click', (event) => {
        setValue('wishlist-contribute-amount', event.currentTarget.getAttribute('data-value') || '');
        document.getElementById('wishlist-contribute-amount')?.focus();
    });

    const buyAmount = document.getElementById('wishlist-buy-amount');

    buyAmount?.addEventListener('input', () => {
        updateImpact(document.querySelector('[data-wishlist-impact]'), buyAmount.value);
    });
})();
