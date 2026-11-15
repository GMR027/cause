'use strict';

/**
 * Cauce · animación del cerdito al mover dinero.
 *
 * save   → cerdito verde que recibe monedas.
 * invest → gráfica de bolsa que sube: velas que crecen, línea que se dibuja
 *          y flecha al alza.
 * out    → cerdito rojo que se agrieta y se rompe en dos.
 *
 * El nivel (1-5) llega del servidor según el monto: más partículas, más
 * tamaño, más duración y, en los niveles altos, anillos, destellos y billetes.
 * Se muestra centrado en la pantalla y se cierra solo, con clic o con Esc.
 */
(() => {
    const source = document.querySelector('[data-money-fx]');

    if (!source) {
        return;
    }

    const kind = source.getAttribute('data-money-fx');
    const level = Math.max(1, Math.min(5, Number(source.getAttribute('data-level')) || 1));
    const label = source.getAttribute('data-label') || '';
    const message = source.getAttribute('data-message') || '';
    const closeLabel = source.getAttribute('data-close') || 'Close';
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!['save', 'invest', 'out'].includes(kind)) {
        return;
    }

    const PALETTES = {
        save: { body: '#2fbf7f', light: '#8ff0bf', dark: '#137a4d', cheek: '#ff9fb0' },
        invest: { body: '#16a34a', light: '#86efac', dark: '#0f3d2a', cheek: '#ffb0c4' },
        out: { body: '#e5484d', light: '#ff9b9e', dark: '#9b1c22', cheek: '#ffd0d2' },
    };
    const palette = PALETTES[kind];
    const broken = kind === 'out';
    const rand = (min, max) => min + Math.random() * (max - min);
    const pick = (list) => list[Math.floor(Math.random() * list.length)];

    // Duración total (ms) antes de desvanecerse.
    const duration = reduced ? 1800 : (broken ? 2600 : 2300) + level * 450;

    // Grieta irregular que parte al cerdito en dos.
    const CRACK = '128,20 120,48 134,70 118,94 136,118 122,142 132,166 126,200';
    const crackPoints = CRACK.split(' ');
    const leftClip = '0,0 128,0 ' + CRACK + ' 0,200';
    const rightClip = '128,0 240,0 240,200 ' + crackPoints.slice().reverse().join(' ');

    const pigShape = () => `
        <path d="M206 98 c 14 -10 30 -4 28 9 c -2 11 -16 11 -16 2 c 0 -7 10 -10 18 -4"
              fill="none" stroke="${palette.dark}" stroke-width="6" stroke-linecap="round"/>
        <rect x="86" y="146" width="26" height="40" rx="11" fill="${palette.dark}"/>
        <rect x="164" y="146" width="26" height="40" rx="11" fill="${palette.dark}"/>
        <path d="M78 66 C 72 44 78 26 88 18 C 102 24 118 40 124 58 Z" fill="${palette.body}"
              stroke="${palette.light}" stroke-width="4" stroke-linejoin="round"/>
        <ellipse cx="136" cy="110" rx="84" ry="64" fill="${palette.body}"/>
        <rect x="34" y="92" width="38" height="42" rx="16" fill="${palette.body}"/>
        <rect x="34" y="92" width="38" height="42" rx="16" fill="none" stroke="${palette.dark}" stroke-width="2.5" opacity=".45"/>
        <ellipse cx="47" cy="113" rx="4" ry="7" fill="${palette.dark}" opacity=".7"/>
        <ellipse cx="60" cy="113" rx="4" ry="7" fill="${palette.dark}" opacity=".7"/>
        <rect x="70" y="150" width="28" height="42" rx="12" fill="${palette.body}"/>
        <rect x="140" y="150" width="28" height="42" rx="12" fill="${palette.body}"/>
        <circle cx="92" cy="84" r="7" fill="#123"/>
        <circle cx="94.5" cy="81.5" r="2.4" fill="#fff"/>
        <ellipse cx="88" cy="112" rx="12" ry="7" fill="${palette.cheek}" opacity=".6"/>
        <rect x="114" y="46" width="52" height="10" rx="5" fill="${palette.dark}" opacity=".75"/>
        <path d="M168 66 C 196 74 214 92 220 110" fill="none" stroke="#fff" stroke-width="5"
              stroke-linecap="round" opacity=".5"/>`;

    // Gráfica de acciones al alza: más velas y más subida con el nivel.
    const chartSvg = () => {
        const candles = [6, 7, 8, 10, 12][level - 1];
        const left = 22;
        const right = 218;
        const bottom = 170;
        const top = 30;
        const step = (right - left) / candles;
        const points = [];
        let value = 0.18;
        let body = '';

        for (let i = 0; i < candles; i += 1) {
            const open = value;
            // Tendencia al alza con algún retroceso pequeño, como en la bolsa.
            const close = Math.min(0.95, Math.max(0.08, open + (i % 3 === 2 ? -0.05 : rand(0.06, 0.14))));
            const high = Math.max(open, close) + rand(0.02, 0.06);
            const low = Math.min(open, close) - rand(0.02, 0.05);
            const y = (v) => (bottom - v * (bottom - top)).toFixed(1);
            const x = left + step * i + step / 2;
            const up = close >= open;
            const width = Math.max(6, step * 0.5);

            body += `<g class="money-fx-candle ${up ? 'is-up' : 'is-down'}" style="--i:${i}">
                <line x1="${x.toFixed(1)}" y1="${y(high)}" x2="${x.toFixed(1)}" y2="${y(low)}"/>
                <rect x="${(x - width / 2).toFixed(1)}" y="${y(Math.max(open, close))}" width="${width.toFixed(1)}"
                      height="${Math.max(3, (Math.abs(close - open) * (bottom - top))).toFixed(1)}" rx="1.5"/>
            </g>`;
            points.push(`${x.toFixed(1)},${y(close)}`);
            value = close;
        }

        const last = points[points.length - 1].split(',');
        const area = `M${points[0].split(',')[0]},${bottom} L${points.join(' L')} L${last[0]},${bottom} Z`;

        return `<svg viewBox="0 0 240 200" class="money-fx-svg money-fx-chart" aria-hidden="true">
            <defs>
                <linearGradient id="moneyFxArea" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="${palette.light}" stop-opacity=".55"/>
                    <stop offset="100%" stop-color="${palette.light}" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <rect x="4" y="8" width="232" height="184" rx="18" class="money-fx-chart-panel"/>
            <g class="money-fx-chart-grid">
                <line x1="${left}" y1="${top}" x2="${right}" y2="${top}"/>
                <line x1="${left}" y1="${(top + bottom) / 2}" x2="${right}" y2="${(top + bottom) / 2}"/>
                <line x1="${left}" y1="${bottom}" x2="${right}" y2="${bottom}"/>
            </g>
            <path d="${area}" class="money-fx-chart-area" fill="url(#moneyFxArea)"/>
            ${body}
            <polyline points="${points.join(' ')}" class="money-fx-chart-line" pathLength="100"/>
            <g class="money-fx-chart-arrow" transform="translate(${last[0]} ${last[1]})">
                <circle r="9"/>
                <path d="M-4 3 L0 -3 L4 3" />
            </g>
        </svg>`;
    };

    const pigSvg = () => {
        if (kind === 'invest') {
            return chartSvg();
        }

        if (!broken) {
            return `<svg viewBox="0 0 240 200" class="money-fx-svg" aria-hidden="true">
                <g class="money-fx-whole">${pigShape()}</g>
            </svg>`;
        }

        return `<svg viewBox="0 0 240 200" class="money-fx-svg" aria-hidden="true">
            <defs>
                <clipPath id="moneyFxLeft"><polygon points="${leftClip}"/></clipPath>
                <clipPath id="moneyFxRight"><polygon points="${rightClip}"/></clipPath>
            </defs>
            <g class="money-fx-half money-fx-half-left"><g clip-path="url(#moneyFxLeft)">${pigShape()}</g></g>
            <g class="money-fx-half money-fx-half-right"><g clip-path="url(#moneyFxRight)">${pigShape()}</g></g>
            <polyline class="money-fx-crack" points="${CRACK}" fill="none" stroke="#3b0a0d"
                      stroke-width="4" stroke-linejoin="round" stroke-linecap="round"/>
        </svg>`;
    };

    const overlay = document.createElement('div');
    overlay.className = `money-fx money-fx--${kind} money-fx--l${level}${reduced ? ' is-reduced' : ''}`;
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.style.setProperty('--fx-color', palette.body);
    overlay.style.setProperty('--fx-light', palette.light);
    overlay.style.setProperty('--fx-dark', palette.dark);
    overlay.style.setProperty('--fx-level', String(level));
    overlay.style.setProperty('--fx-duration', duration + 'ms');
    overlay.innerHTML = `
        <div class="money-fx-backdrop"></div>
        <div class="money-fx-stage">
            <div class="money-fx-rings" aria-hidden="true"><span></span><span></span><span></span></div>
            <div class="money-fx-glow" aria-hidden="true"></div>
            <div class="money-fx-pig">${pigSvg()}</div>
            <div class="money-fx-label">
                <strong></strong>
                <span></span>
            </div>
        </div>
        <button type="button" class="visually-hidden-focusable money-fx-close"></button>`;
    overlay.querySelector('.money-fx-label strong').textContent = label;
    overlay.querySelector('.money-fx-label span').textContent = message;
    overlay.querySelector('.money-fx-close').textContent = closeLabel;

    const pigBox = overlay.querySelector('.money-fx-pig');
    const particle = (className, html, vars, parent = pigBox) => {
        const el = document.createElement('span');

        el.className = 'money-fx-particle ' + className;
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = html;
        Object.entries(vars).forEach(([key, value]) => el.style.setProperty(key, value));
        parent.appendChild(el);

        return el;
    };

    const COIN = '<i class="bi bi-coin"></i>';

    if (!reduced && !broken) {
        // Monedas que caen en la ranura; en inversión, flechas y % que suben.
        const coins = kind === 'invest' ? 0 : [4, 7, 12, 18, 28][level - 1];

        for (let i = 0; i < coins; i += 1) {
            particle('money-fx-coin', COIN, {
                '--x': rand(-70, 70).toFixed(0) + 'px',
                '--delay': (0.15 + i * (1.6 / coins) + rand(0, 0.12)).toFixed(2) + 's',
                '--size': rand(1.4, 2.2 + level * 0.15).toFixed(2) + 'rem',
                '--spin': pick([-1, 1]) * rand(180, 540).toFixed(0) + 'deg',
            });
        }

        if (kind === 'invest') {
            const arrows = [3, 5, 7, 10, 14][level - 1];

            for (let i = 0; i < arrows; i += 1) {
                particle('money-fx-rise', pick([
                    '<i class="bi bi-arrow-up-right"></i>',
                    '<i class="bi bi-graph-up-arrow"></i>',
                    '<b>%</b>',
                    '<i class="bi bi-caret-up-fill"></i>',
                ]), {
                    '--x': rand(-150, 150).toFixed(0) + 'px',
                    '--delay': rand(0.4, 1.8).toFixed(2) + 's',
                    '--size': rand(1.2, 2).toFixed(2) + 'rem',
                });
            }
        }

        if (level >= 3) {
            const sparks = [0, 0, 8, 14, 22][level - 1];

            for (let i = 0; i < sparks; i += 1) {
                const angle = (Math.PI * 2 * i) / sparks + rand(-0.2, 0.2);
                const distance = rand(110, 150 + level * 25);

                particle('money-fx-spark', '<i class="bi bi-stars"></i>', {
                    '--dx': (Math.cos(angle) * distance).toFixed(0) + 'px',
                    '--dy': (Math.sin(angle) * distance).toFixed(0) + 'px',
                    '--delay': rand(1.1, 1.6).toFixed(2) + 's',
                    '--size': rand(1, 1.8).toFixed(2) + 'rem',
                });
            }
        }

        if (level >= 5) {
            for (let i = 0; i < 16; i += 1) {
                particle('money-fx-bill', '<i class="bi bi-cash-stack"></i>', {
                    '--x': rand(-46, 46).toFixed(0) + 'vw',
                    '--delay': rand(0.3, 2.2).toFixed(2) + 's',
                    '--size': rand(1.6, 2.6).toFixed(2) + 'rem',
                    '--spin': rand(-60, 60).toFixed(0) + 'deg',
                }, overlay);
            }
        }
    }

    if (!reduced && broken) {
        // Fragmentos y monedas que salen disparados al romperse.
        const shards = [6, 10, 16, 24, 34][level - 1];

        for (let i = 0; i < shards; i += 1) {
            const angle = rand(-Math.PI, 0.25);
            const distance = rand(90, 140 + level * 30);
            const coin = i % 3 === 0;

            particle(coin ? 'money-fx-shard is-coin' : 'money-fx-shard', coin ? COIN : '', {
                '--dx': (Math.cos(angle) * distance).toFixed(0) + 'px',
                '--dy': (Math.sin(angle) * distance * 0.8).toFixed(0) + 'px',
                '--fall': rand(120, 260).toFixed(0) + 'px',
                '--rot': rand(-540, 540).toFixed(0) + 'deg',
                '--delay': rand(0.95, 1.1).toFixed(2) + 's',
                '--size': rand(0.5, 0.9 + level * 0.12).toFixed(2) + 'rem',
            });
        }
    }

    let closed = false;
    const lastFocus = document.activeElement;

    const close = () => {
        if (closed) {
            return;
        }

        closed = true;
        overlay.classList.add('is-leaving');
        document.removeEventListener('keydown', onKey);
        window.setTimeout(() => overlay.remove(), 420);

        if (lastFocus instanceof HTMLElement && document.body.contains(lastFocus)) {
            lastFocus.focus({ preventScroll: true });
        }
    };

    const onKey = (event) => {
        if (event.key === 'Escape') {
            close();
        }
    };

    overlay.addEventListener('click', close);
    document.addEventListener('keydown', onKey);
    document.body.appendChild(overlay);
    window.setTimeout(close, duration);
})();
