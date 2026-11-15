'use strict';

/**
 * Cauce · editor de temas con previsualización.
 */
(() => {
    const form = document.querySelector('[data-theme-form]');

    if (!form) {
        return;
    }

    const preview = document.querySelector('[data-theme-preview]');
    const contrastEl = document.querySelector('[data-theme-contrast]');
    const custom = form.querySelector('[data-theme-custom]');
    const textureWrap = document.querySelector('[data-theme-texture-wrap]');
    const textureSelect = form.querySelector('[name="textura"]');
    const opacityInput = form.querySelector('[name="opacidad"]');
    const opacityValue = form.querySelector('[data-opacity-value]');

    const checked = (name) => {
        const input = form.querySelector('input[name="' + name + '"]:checked');

        return input ? input.value : null;
    };

    const readTheme = () => {
        let color = checked('color') || Cauce.defaults.color;

        if (color === 'custom') {
            color = custom ? custom.value : Cauce.defaults.color;
        }

        return {
            modo: checked('modo') || 'auto',
            color,
            enfoque: checked('enfoque') || 'mixto',
            fondo: checked('fondo') || 'ninguno',
            textura: textureSelect ? textureSelect.value : 'dots',
            opacidad: opacityInput ? parseInt(opacityInput.value, 10) : 30,
        };
    };

    const updateContrast = (theme) => {
        if (!contrastEl) {
            return;
        }

        const ratio = Cauce.contrastRatio(theme.color, '#ffffff');
        const ok = ratio >= 4.5;

        contrastEl.textContent = t(ok ? 'settings.temas.contrast_ok' : 'settings.temas.contrast_bad', {
            ratio: ratio.toFixed(2),
        });
        contrastEl.classList.toggle('text-danger', !ok);
        contrastEl.classList.toggle('text-success', ok);
    };

    const update = () => {
        const theme = readTheme();

        if (preview) {
            Cauce.applyTheme(theme, { target: preview });
        }

        updateContrast(theme);

        if (textureWrap) {
            textureWrap.classList.toggle(
                'd-none',
                !['textura', 'radial_textura'].includes(theme.fondo)
            );
        }
    };

    form.addEventListener('change', (event) => {
        const target = event.target;

        if (target.matches('input[name="color"]') && target.value !== 'custom' && custom) {
            custom.value = target.value;
        }

        if (target === custom) {
            const customRadio = form.querySelector('input[name="color"][value="custom"]');

            if (customRadio) {
                customRadio.checked = true;
            }
        }

        update();
    });

    if (opacityInput) {
        opacityInput.addEventListener('input', () => {
            if (opacityValue) {
                opacityValue.textContent = opacityInput.value + ' %';
            }

            update();
        });
    }

    if (custom) {
        custom.addEventListener('input', () => {
            const customRadio = form.querySelector('input[name="color"][value="custom"]');

            if (customRadio) {
                customRadio.checked = true;
            }

            update();
        });
    }

    form.addEventListener('submit', (event) => {
        if (event.submitter && event.submitter.name === 'reset') {
            return;
        }

        const theme = readTheme();

        if (Cauce.contrastRatio(theme.color, '#ffffff') < 4.5) {
            event.preventDefault();
            updateContrast(theme);
            return;
        }

        Cauce.applyTheme(theme, { persist: true });
    });

    update();
})();
