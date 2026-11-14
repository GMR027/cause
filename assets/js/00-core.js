'use strict';

/**
 * Cauce · núcleo de UI.
 *
 * Expone t(), formateadores localizados, aplicación de tema y modo discreto.
 * El resto de archivos asume que este se carga primero (00-*).
 */
(() => {
    const config = window.CauceI18n || {
        lang: 'es',
        locale: 'es-MX',
        currency: 'MXN',
        translations: {},
        fallbackTranslations: {},
    };

    const lookup = (dictionary, key) => {
        if (!dictionary || typeof key !== 'string') {
            return null;
        }

        let value = dictionary;

        for (const segment of key.split('.')) {
            if (value === null || typeof value !== 'object' || !(segment in value)) {
                return null;
            }

            value = value[segment];
        }

        return typeof value === 'string' ? value : null;
    };

    const interpolate = (text, params) => {
        if (!params || typeof params !== 'object') {
            return text;
        }

        return text.replace(/:([a-zA-Z_][a-zA-Z0-9_]*)/g, (match, name) => {
            return Object.prototype.hasOwnProperty.call(params, name) ? String(params[name]) : match;
        });
    };

    window.t = (key, params = {}) => {
        const translated = lookup(config.translations, key);
        const fallback = lookup(config.fallbackTranslations, key);

        if (translated !== null) {
            return interpolate(translated, params);
        }

        if (fallback !== null) {
            return interpolate(fallback, params);
        }

        return key;
    };

    const jsLocale = typeof config.locale === 'string' && config.locale !== ''
        ? config.locale.replace('_', '-')
        : 'es-MX';
    const moneyFormatter = new Intl.NumberFormat(jsLocale, {
        style: 'currency',
        currency: config.currency || 'MXN',
    });

    const dateFormatter = new Intl.DateTimeFormat(jsLocale, { dateStyle: 'medium' });
    const dateTimeFormatter = new Intl.DateTimeFormat(jsLocale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

    const toDate = (value) => {
        if (value instanceof Date) {
            return value;
        }

        if (typeof value !== 'string') {
            return null;
        }

        const normalized = /Z$|[+-]\d\d:\d\d$/.test(value) ? value : value.replace(' ', 'T') + 'Z';
        const date = new Date(normalized);

        return Number.isNaN(date.getTime()) ? null : date;
    };

    const STORAGE = {
        theme: 'cauce_theme',
        sidebar: 'cauce_sidebar_collapsed',
        discreto: 'cauce_discreto',
    };

    const DEFAULT_THEME = {
        modo: 'auto',
        color: '#157f6b',
        enfoque: 'mixto',
        fondo: 'ninguno',
        textura: 'dots',
    };

    const MODES = ['claro', 'oscuro', 'auto'];
    const FOCUS = ['iconos', 'botones', 'mixto'];
    const BACKGROUNDS = ['ninguno', 'radial', 'textura', 'radial_textura'];
    const TEXTURES = ['dots', 'grid', 'waves', 'finanzas', 'simbolos'];

    const hexToRgb = (hex) => {
        if (!/^#[0-9a-f]{6}$/i.test(hex || '')) {
            return [21, 127, 107];
        }

        return [
            parseInt(hex.substr(1, 2), 16),
            parseInt(hex.substr(3, 2), 16),
            parseInt(hex.substr(5, 2), 16),
        ];
    };

    const shade = (hex, factor) => {
        return (
            '#' +
            hexToRgb(hex)
                .map((value) => Math.round(value * factor).toString(16).padStart(2, '0'))
                .join('')
        );
    };

    const luminance = (hex) => {
        const linear = (channel) => {
            const value = channel / 255;

            return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
        };

        const [r, g, b] = hexToRgb(hex);

        return 0.2126 * linear(r) + 0.7152 * linear(g) + 0.0722 * linear(b);
    };

    const contrastRatio = (a, b) => {
        const la = luminance(a);
        const lb = luminance(b);

        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    };

    const normalizeTheme = (theme) => {
        const source = theme && typeof theme === 'object' ? theme : {};

        return {
            modo: MODES.includes(source.modo) ? source.modo : DEFAULT_THEME.modo,
            color: /^#[0-9a-f]{6}$/i.test(source.color || '') ? source.color : DEFAULT_THEME.color,
            enfoque: FOCUS.includes(source.enfoque) ? source.enfoque : DEFAULT_THEME.enfoque,
            fondo: BACKGROUNDS.includes(source.fondo) ? source.fondo : DEFAULT_THEME.fondo,
            textura: TEXTURES.includes(source.textura) ? source.textura : DEFAULT_THEME.textura,
        };
    };

    const prefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

    const resolveBootstrapTheme = (modo) => {
        return modo === 'oscuro' || (modo === 'auto' && prefersDark()) ? 'dark' : 'light';
    };

    const applyVars = (element, color) => {
        const [r, g, b] = hexToRgb(color);

        element.style.setProperty('--cauce-primary', color);
        element.style.setProperty('--cauce-primary-rgb', r + ', ' + g + ', ' + b);
        element.style.setProperty('--cauce-primary-hover', shade(color, 0.88));
        element.style.setProperty('--cauce-primary-active', shade(color, 0.78));
        element.style.setProperty('--cauce-primary-soft', 'rgba(' + r + ', ' + g + ', ' + b + ', 0.12)');

        if (window.CauceDoodles) {
            window.CauceDoodles.apply(element, color);
        }
    };

    let currentTheme = null;

    const applyTheme = (theme, options = {}) => {
        const value = normalizeTheme(theme);
        const target = options.target || document.documentElement;

        target.setAttribute('data-cauce-modo', value.modo);
        target.setAttribute('data-enfoque', value.enfoque);
        target.setAttribute('data-fondo', value.fondo);
        target.setAttribute('data-textura', value.textura);
        target.setAttribute('data-bs-theme', resolveBootstrapTheme(value.modo));
        applyVars(target, value.color);

        if (target === document.documentElement) {
            currentTheme = value;

            if (options.persist) {
                try {
                    localStorage.setItem(STORAGE.theme, JSON.stringify(value));
                } catch (error) {
                    // localStorage puede estar bloqueado; el servidor conserva el tema.
                }
            }
        }

        return value;
    };

    const readStoredTheme = () => {
        try {
            const raw = localStorage.getItem(STORAGE.theme);

            return raw ? normalizeTheme(JSON.parse(raw)) : null;
        } catch (error) {
            return null;
        }
    };

    const isDiscreto = () => document.documentElement.getAttribute('data-discreto') === '1';

    const setDiscreto = (enabled) => {
        const root = document.documentElement;

        if (enabled) {
            root.setAttribute('data-discreto', '1');
        } else {
            root.removeAttribute('data-discreto');
        }

        try {
            sessionStorage.setItem(STORAGE.discreto, enabled ? '1' : '0');
        } catch (error) {
            // Sin sessionStorage el estado solo dura la página actual.
        }

        document.querySelectorAll('[data-discreto-icon]').forEach((icon) => {
            icon.className = enabled ? 'bi bi-eye-slash' : 'bi bi-eye';
        });

        document.querySelectorAll('[data-action="toggle-discreto"]').forEach((button) => {
            button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        });
    };

    const toggleDiscreto = () => setDiscreto(!isDiscreto());

    window.Cauce = {
        lang: config.lang,
        locale: jsLocale,
        currency: config.currency,
        storage: STORAGE,
        defaults: DEFAULT_THEME,
        formatMoney: (cents) => moneyFormatter.format(Number(cents || 0) / 100),
        formatDate: (value) => {
            const date = toDate(value);

            return date === null ? '—' : dateFormatter.format(date);
        },
        formatDateTime: (value) => {
            const date = toDate(value);

            return date === null ? '—' : dateTimeFormatter.format(date);
        },
        normalizeTheme,
        applyTheme,
        readStoredTheme,
        contrastRatio,
        hexToRgb,
        shade,
        isDiscreto,
        setDiscreto,
        toggleDiscreto,
    };

    const initialTheme = window.CauceServerTheme || readStoredTheme();

    if (initialTheme) {
        applyTheme(initialTheme, { persist: true });
    }

    setDiscreto(isDiscreto());

    const media = window.matchMedia('(prefers-color-scheme: dark)');

    const onSchemeChange = () => {
        if (currentTheme !== null && currentTheme.modo === 'auto') {
            applyTheme(currentTheme);
        }
    };

    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', onSchemeChange);
    }
})();
