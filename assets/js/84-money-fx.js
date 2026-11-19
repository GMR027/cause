'use strict';

/**
 * Cauce · animación al mover dinero.
 *
 * save   → cerdito verde que recibe monedas.
 * invest → gráfica de bolsa que sube: velas que crecen, línea que se dibuja
 *          y flecha al alza.
 * out    → cerdito rojo que se agrieta y se rompe en dos.
 * wish   → caja de regalo con estrella que sale al abrirse: lista de deseos.
 * share  → corazón entre manos que late: lo que compartes con los demás.
 * income → banco que abre la puerta y escupe billetes hacia tu cartera.
 * plan   → hoja de plan que se escribe y recibe un sello de check al guardar.
 * debt   → tarjeta que entra y un candado que la cierra: deuda registrada.
 * loan   → cerdito que presta monedas a una tarjeta: préstamo de tu ahorro.
 * transfer → monedas que saltan de un frasco a otro: traspasos y movimientos.
 * new    → frasco nuevo con bandera: bolsa, meta, portafolio o inversión.
 * remind → calendario con campana que suena: recordatorios y recurrentes.
 * yield  → planta que crece y da monedas: rendimientos de inversión.
 *
 * El nivel (1-5) llega del servidor según el monto: más partículas, más
 * tamaño, más duración y, en los niveles altos, anillos, destellos y lluvia.
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

    const EXTRA = ['debt', 'loan', 'transfer', 'new', 'remind', 'yield'];

    if (!['save', 'invest', 'out', 'wish', 'share', 'income', 'plan', ...EXTRA].includes(kind)) {
        return;
    }

    const PALETTES = {
        save: { body: '#2fbf7f', light: '#8ff0bf', dark: '#137a4d', cheek: '#ff9fb0' },
        invest: { body: '#16a34a', light: '#86efac', dark: '#0f3d2a', cheek: '#ffb0c4' },
        out: { body: '#e5484d', light: '#ff9b9e', dark: '#9b1c22', cheek: '#ffd0d2' },
        wish: { body: '#8b5cf6', light: '#d8b4fe', dark: '#4c1d95', cheek: '#f9a8d4' },
        share: { body: '#ec4899', light: '#f9a8d4', dark: '#831843', cheek: '#ffe4ef' },
        income: { body: '#2563eb', light: '#93c5fd', dark: '#1e3a8a', cheek: '#fbbf24' },
        plan: { body: '#157f6b', light: '#8ee7d4', dark: '#0b4f43', cheek: '#f4b81f' },
        debt: { body: '#f97316', light: '#fdba74', dark: '#9a3412', cheek: '#fde68a' },
        loan: { body: '#d97706', light: '#fcd34d', dark: '#78350f', cheek: '#ffb0c4' },
        transfer: { body: '#0ea5e9', light: '#7dd3fc', dark: '#075985', cheek: '#f4b81f' },
        new: { body: '#10b981', light: '#6ee7b7', dark: '#065f46', cheek: '#f4b81f' },
        remind: { body: '#6366f1', light: '#a5b4fc', dark: '#3730a3', cheek: '#fbbf24' },
        yield: { body: '#65a30d', light: '#bef264', dark: '#3f6212', cheek: '#f4b81f' },
    };
    const palette = PALETTES[kind];
    const broken = kind === 'out';
    const isWish = kind === 'wish';
    const isShare = kind === 'share';
    const isGift = isWish || isShare;
    const isIncome = kind === 'income';
    const isPlan = kind === 'plan';
    const isExtra = EXTRA.includes(kind);
    const rand = (min, max) => min + Math.random() * (max - min);
    const pick = (list) => list[Math.floor(Math.random() * list.length)];

    // Duración total (ms) antes de desvanecerse.
    const duration = reduced ? 1800 : (broken ? 2600 : isGift || isIncome || isPlan || isExtra ? 2600 : 2300) + level * 450;

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

    // Caja de regalo con estrella: apuntar un deseo en la lista.
    const wishSvg = () => `<svg viewBox="0 0 240 200" class="money-fx-svg money-fx-wish" aria-hidden="true">
        <defs>
            <linearGradient id="moneyFxWishBox" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="${palette.light}"/>
                <stop offset="100%" stop-color="${palette.body}"/>
            </linearGradient>
            <linearGradient id="moneyFxWishLid" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="${palette.body}"/>
                <stop offset="100%" stop-color="${palette.dark}"/>
            </linearGradient>
        </defs>
        <g class="money-fx-wish-star">
            <path d="M120 24 L128.6 45.4 L151.6 47.1 L133.9 61.6 L140 83.6 L120 70.6 L100 83.6 L106.1 61.6 L88.4 47.1 L111.4 45.4 Z"
                  fill="#ffe58a" stroke="#f4b81f" stroke-width="3" stroke-linejoin="round"/>
            <circle cx="120" cy="52" r="5" fill="#fff7cc" opacity=".85"/>
        </g>
        <g class="money-fx-wish-box">
            <rect x="58" y="108" width="124" height="72" rx="12" fill="url(#moneyFxWishBox)"/>
            <rect x="58" y="108" width="124" height="72" rx="12" fill="none"
                  stroke="${palette.dark}" stroke-width="2.5" opacity=".5"/>
            <rect x="112" y="108" width="16" height="72" fill="${palette.light}" opacity=".9"/>
            <rect x="58" y="140" width="124" height="12" fill="${palette.dark}" opacity=".28"/>
        </g>
        <g class="money-fx-wish-lid">
            <rect x="50" y="88" width="140" height="28" rx="9" fill="url(#moneyFxWishLid)"/>
            <rect x="50" y="88" width="140" height="28" rx="9" fill="none"
                  stroke="${palette.light}" stroke-width="2" opacity=".45"/>
            <rect x="112" y="88" width="16" height="28" fill="${palette.light}" opacity=".9"/>
            <path d="M120 90 C 104 66 82 68 86 82 C 89 93 107 94 120 90 Z"
                  fill="${palette.body}" stroke="${palette.dark}" stroke-width="2.5" stroke-linejoin="round"/>
            <path d="M120 90 C 136 66 158 68 154 82 C 151 93 133 94 120 90 Z"
                  fill="${palette.body}" stroke="${palette.dark}" stroke-width="2.5" stroke-linejoin="round"/>
            <circle cx="120" cy="90" r="7" fill="${palette.light}" stroke="${palette.dark}" stroke-width="2"/>
        </g>
    </svg>`;

    // Corazón entre manos con dos personas al fondo: lo que compartes.
    const shareSvg = () => `<svg viewBox="0 0 240 200" class="money-fx-svg money-fx-share" aria-hidden="true">
        <defs>
            <radialGradient id="moneyFxShareHeart" cx="38%" cy="30%" r="85%">
                <stop offset="0%" stop-color="${palette.light}"/>
                <stop offset="55%" stop-color="${palette.body}"/>
                <stop offset="100%" stop-color="${palette.dark}"/>
            </radialGradient>
        </defs>
        <g class="money-fx-share-people">
            <circle cx="36" cy="70" r="9" fill="${palette.dark}" opacity=".5"/>
            <path d="M20 96 c 1 -13 8 -19 16 -19 c 8 0 15 6 16 19 Z" fill="${palette.dark}" opacity=".5"/>
            <circle cx="204" cy="70" r="9" fill="${palette.dark}" opacity=".5"/>
            <path d="M188 96 c 1 -13 8 -19 16 -19 c 8 0 15 6 16 19 Z" fill="${palette.dark}" opacity=".5"/>
        </g>
        <g class="money-fx-share-hands">
            <path d="M22 158 c 6 -17 28 -25 48 -18 l 17 6 c 10 4 8 18 -3 19 l -35 4 c -13 1 -23 -3 -27 -11 Z"
                  fill="${palette.light}" stroke="${palette.dark}" stroke-width="3" stroke-linejoin="round"/>
            <path d="M218 158 c -6 -17 -28 -25 -48 -18 l -17 6 c -10 4 -8 18 3 19 l 35 4 c 13 1 23 -3 27 -11 Z"
                  fill="${palette.light}" stroke="${palette.dark}" stroke-width="3" stroke-linejoin="round"/>
        </g>
        <g class="money-fx-share-heart">
            <path d="M120 164 C 54 120 42 84 66 62 C 86 44 110 52 120 74 C 130 52 154 44 174 62 C 198 84 186 120 120 164 Z"
                  fill="url(#moneyFxShareHeart)" stroke="${palette.dark}" stroke-width="3" stroke-linejoin="round"/>
            <path d="M88 76 c 10 -13 27 -9 32 4" fill="none" stroke="#fff" stroke-width="6"
                  stroke-linecap="round" opacity=".55"/>
        </g>
    </svg>`;

    // Banco que escupe billetes hacia la cartera: registrar un ingreso.
    const incomeSvg = () => {
        const count = [6, 9, 13, 18, 24][level - 1];
        let bills = '';

        for (let i = 0; i < count; i += 1) {
            const delay = (0.45 + (i * 1.5) / count + rand(0, 0.18)).toFixed(2);
            const arc = (-rand(8, 26)).toFixed(0);
            const spin = rand(-45, 45).toFixed(0);

            bills += `<g transform="translate(${(68 + rand(-4, 6)).toFixed(1)} ${(114 + rand(-4, 8)).toFixed(1)})">
                <g class="money-fx-income-bill" style="--delay:${delay}s; --arc:${arc}px; --spin:${spin}deg">
                    <rect width="18" height="10" rx="2" fill="#f4b81f" stroke="#b8860b" stroke-width="1.2"/>
                    <circle cx="9" cy="5" r="2.4" fill="#fff3c4" opacity=".9"/>
                </g>
            </g>`;
        }

        return `<svg viewBox="0 0 240 200" class="money-fx-svg money-fx-income" aria-hidden="true">
            <defs>
                <linearGradient id="moneyFxBankRoof" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="${palette.light}"/>
                    <stop offset="100%" stop-color="${palette.body}"/>
                </linearGradient>
                <linearGradient id="moneyFxWallet" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="${palette.body}"/>
                    <stop offset="100%" stop-color="${palette.dark}"/>
                </linearGradient>
            </defs>
            <g class="money-fx-bank">
                <path d="M12 84 L76 40 L140 84 Z" fill="url(#moneyFxBankRoof)"
                      stroke="${palette.dark}" stroke-width="3" stroke-linejoin="round"/>
                <path d="M24 82 L76 47 L128 82 Z" fill="${palette.body}" opacity=".35"/>
                <circle cx="76" cy="67" r="9" fill="${palette.cheek}"
                        stroke="${palette.dark}" stroke-width="2"/>
                <path d="M76 61 v12 M72.5 64.5 h7 M72.5 69.5 h7" fill="none"
                      stroke="${palette.dark}" stroke-width="1.8" stroke-linecap="round"/>
                <rect x="22" y="84" width="108" height="12" rx="3" fill="${palette.dark}"/>
                <rect x="32" y="96" width="10" height="46" rx="3" fill="${palette.light}"/>
                <rect x="48" y="96" width="10" height="46" rx="3" fill="${palette.light}"/>
                <rect x="94" y="96" width="10" height="46" rx="3" fill="${palette.light}"/>
                <rect x="110" y="96" width="10" height="46" rx="3" fill="${palette.light}"/>
                <path d="M64 142 v-26 a12 12 0 0 1 24 0 v26 Z" fill="${palette.dark}"/>
                <path class="money-fx-bank-door" d="M69 142 v-22 a7 7 0 0 1 14 0 v22 Z"
                      fill="${palette.light}" opacity=".3"/>
                <rect x="18" y="142" width="116" height="12" rx="3" fill="${palette.dark}"/>
                <rect x="12" y="154" width="128" height="9" rx="3" fill="${palette.body}"/>
                <rect x="12" y="164" width="128" height="9" rx="3" fill="${palette.dark}"/>
            </g>
            ${bills}
            <g class="money-fx-wallet">
                <rect x="158" y="100" width="62" height="30" rx="5" fill="${palette.cheek}"
                      opacity=".85" transform="rotate(-8 189 115)"/>
                <rect x="150" y="116" width="80" height="50" rx="12" fill="url(#moneyFxWallet)"
                      stroke="${palette.dark}" stroke-width="3"/>
                <path d="M150 132 c 20 -9 60 -9 80 0" fill="none"
                      stroke="${palette.light}" stroke-width="2" opacity=".45"/>
                <rect x="162" y="128" width="40" height="7" rx="3.5" fill="${palette.dark}" opacity=".8"/>
                <circle cx="216" cy="142" r="7" fill="${palette.cheek}"
                        stroke="${palette.dark}" stroke-width="2"/>
            </g>
        </svg>`;
    };

    // Hoja de plan: las filas se escriben, los checks se dibujan y un sello
    // de aprobado cae encima. Es la animación al guardar un planeador.
    const planSvg = () => {
        const widths = [72, 58, 66];
        let rows = '';

        widths.forEach((width, i) => {
            const y = 56 + i * 28;

            rows += `<g class="money-fx-plan-row" style="--i:${i}">
                <rect x="70" y="${y}" width="15" height="15" rx="4" fill="none"
                      stroke="${palette.body}" stroke-width="2.4"/>
                <path class="money-fx-plan-check" d="M73.5 ${y + 7.5} l4 4 l7.5 -8.5"
                      fill="none" stroke="${palette.body}" stroke-width="2.6"
                      stroke-linecap="round" stroke-linejoin="round"/>
                <rect class="money-fx-plan-line" x="94" y="${y + 4}" width="${width}" height="7" rx="3.5"
                      fill="${palette.dark}" opacity=".32"/>
            </g>`;
        });

        return `<svg viewBox="0 0 240 200" class="money-fx-svg money-fx-plan" aria-hidden="true">
            <defs>
                <linearGradient id="moneyFxPlanPaper" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#ffffff"/>
                    <stop offset="100%" stop-color="#d9ece7"/>
                </linearGradient>
            </defs>
            <g class="money-fx-plan-sheet">
                <rect x="52" y="16" width="136" height="170" rx="16" fill="url(#moneyFxPlanPaper)"
                      stroke="${palette.dark}" stroke-width="3"/>
                <rect x="70" y="34" width="64" height="8" rx="4" fill="${palette.dark}" opacity=".26"/>
                <rect x="94" y="4" width="52" height="20" rx="8" fill="${palette.body}"
                      stroke="${palette.dark}" stroke-width="2.5"/>
                <circle cx="120" cy="14" r="3.2" fill="${palette.light}"/>
                ${rows}
                <g class="money-fx-plan-stamp">
                    <circle cx="172" cy="152" r="27" fill="${palette.body}"
                            stroke="${palette.dark}" stroke-width="3"/>
                    <circle cx="172" cy="152" r="20.5" fill="none"
                            stroke="${palette.light}" stroke-width="2" opacity=".55"/>
                    <path d="M160 152 l8 8 l16 -18" fill="none" stroke="#fff" stroke-width="6"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </g>
            </g>
        </svg>`;
    };


    // Tarjeta de crédito que entra y un candado que cae y la cierra.
    const debtSvg = () => `<svg viewBox="0 0 240 200" class="money-fx-svg money-fx-debt" aria-hidden="true">
        <defs>
            <linearGradient id="moneyFxDebtCard" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="${palette.light}"/>
                <stop offset="55%" stop-color="${palette.body}"/>
                <stop offset="100%" stop-color="${palette.dark}"/>
            </linearGradient>
        </defs>
        <g class="money-fx-debt-card">
            <rect x="26" y="62" width="168" height="106" rx="14" fill="url(#moneyFxDebtCard)"
                  stroke="${palette.dark}" stroke-width="3"/>
            <rect x="26" y="84" width="168" height="18" fill="${palette.dark}" opacity=".55"/>
            <rect x="42" y="114" width="30" height="22" rx="5" fill="${palette.cheek}"
                  stroke="${palette.dark}" stroke-width="2"/>
            <path d="M42 125 h30 M57 114 v22" stroke="${palette.dark}" stroke-width="1.5" opacity=".5"/>
            <g fill="#fff" opacity=".8">
                <circle cx="88" cy="150" r="3"/><circle cx="97" cy="150" r="3"/><circle cx="106" cy="150" r="3"/>
                <circle cx="124" cy="150" r="3"/><circle cx="133" cy="150" r="3"/><circle cx="142" cy="150" r="3"/>
            </g>
            <path d="M40 74 c 30 -8 70 -8 100 0" fill="none" stroke="#fff" stroke-width="4"
                  stroke-linecap="round" opacity=".35"/>
        </g>
        <g class="money-fx-debt-lock">
            <path class="money-fx-debt-shackle" d="M172 98 v-18 a18 18 0 0 1 36 0 v18" fill="none"
                  stroke="${palette.dark}" stroke-width="7" stroke-linecap="round"/>
            <rect x="160" y="96" width="60" height="50" rx="10" fill="${palette.cheek}"
                  stroke="${palette.dark}" stroke-width="3"/>
            <circle cx="190" cy="116" r="6" fill="${palette.dark}"/>
            <rect x="187" y="118" width="6" height="14" rx="3" fill="${palette.dark}"/>
        </g>
    </svg>`;

    // Cerdito que presta monedas a una tarjeta: préstamo de tu propio ahorro.
    const loanSvg = () => {
        const count = [4, 6, 9, 13, 18][level - 1];
        let coins = '';

        for (let i = 0; i < count; i += 1) {
            const delay = (0.55 + (i * 1.4) / count + rand(0, 0.15)).toFixed(2);

            coins += `<g transform="translate(${(84 + rand(-4, 4)).toFixed(1)} ${(66 + rand(-3, 3)).toFixed(1)})">
                <g class="money-fx-loan-coin" style="--delay:${delay}s; --arc:${(-rand(30, 56)).toFixed(0)}px">
                    <circle r="8" fill="#f4b81f" stroke="#b8860b" stroke-width="2"/>
                    <path d="M0 -4 v8" stroke="#b8860b" stroke-width="2" stroke-linecap="round"/>
                </g>
            </g>`;
        }

        return `<svg viewBox="0 0 240 200" class="money-fx-svg money-fx-loan" aria-hidden="true">
            <g class="money-fx-loan-pig"><g transform="translate(-6 46) scale(.62)">${pigShape()}</g></g>
            <g class="money-fx-loan-card">
                <rect x="156" y="104" width="74" height="48" rx="9" fill="${palette.body}"
                      stroke="${palette.dark}" stroke-width="3"/>
                <rect x="156" y="114" width="74" height="9" fill="${palette.dark}" opacity=".5"/>
                <rect x="166" y="131" width="16" height="12" rx="3" fill="${palette.cheek}"/>
            </g>
            ${coins}
        </svg>`;
    };

    // Dos frascos: el de la izquierda baja y el de la derecha sube con las monedas.
    const transferSvg = () => {
        const count = [4, 6, 9, 13, 18][level - 1];
        let coins = '';

        for (let i = 0; i < count; i += 1) {
            const delay = (0.5 + (i * 1.4) / count + rand(0, 0.12)).toFixed(2);

            coins += `<g transform="translate(66 58)">
                <g class="money-fx-transfer-coin" style="--delay:${delay}s">
                    <circle r="8" fill="#f4b81f" stroke="#b8860b" stroke-width="2"/>
                    <path d="M0 -4 v8" stroke="#b8860b" stroke-width="2" stroke-linecap="round"/>
                </g>
            </g>`;
        }

        const jar = (x, cls) => `<g class="money-fx-jar ${cls}">
            <rect x="${x + 6}" y="62" width="58" height="10" rx="4" fill="${palette.dark}"/>
            <rect x="${x}" y="72" width="70" height="104" rx="18" fill="#ffffff" fill-opacity=".55"
                  stroke="${palette.dark}" stroke-width="3"/>
            <rect class="money-fx-jar-fill" x="${x + 5}" y="82" width="60" height="89" rx="13" fill="${palette.body}"/>
            <path d="M${x + 14} 90 v60" stroke="#fff" stroke-width="5" stroke-linecap="round" opacity=".45"/>
        </g>`;

        return `<svg viewBox="0 0 240 200" class="money-fx-svg money-fx-transfer" aria-hidden="true">
            ${jar(30, 'is-from')}
            ${jar(140, 'is-to')}
            <path class="money-fx-transfer-arrow" d="M100 34 q 20 -16 40 0" fill="none"
                  stroke="${palette.dark}" stroke-width="4" stroke-linecap="round"/>
            <path class="money-fx-transfer-arrow" d="M134 26 l7 8 l-10 3" fill="none"
                  stroke="${palette.dark}" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
            ${coins}
        </svg>`;
    };

    // Frasco nuevo que aparece y una bandera que se iza en la tapa.
    const newSvg = () => `<svg viewBox="0 0 240 200" class="money-fx-svg money-fx-new" aria-hidden="true">
        <g class="money-fx-new-jar">
            <rect x="72" y="70" width="96" height="14" rx="6" fill="${palette.dark}"/>
            <rect x="62" y="84" width="116" height="100" rx="24" fill="#ffffff" fill-opacity=".6"
                  stroke="${palette.dark}" stroke-width="3"/>
            <rect class="money-fx-new-fill" x="68" y="124" width="104" height="54" rx="18" fill="${palette.body}"/>
            <path d="M78 98 v58" stroke="#fff" stroke-width="6" stroke-linecap="round" opacity=".5"/>
            <circle cx="120" cy="150" r="14" fill="${palette.cheek}" stroke="${palette.dark}" stroke-width="2.5"/>
            <path d="M120 143 v14 M113 150 h14" stroke="${palette.dark}" stroke-width="3" stroke-linecap="round"/>
        </g>
        <g class="money-fx-new-flag">
            <rect x="117" y="10" width="5" height="62" rx="2.5" fill="${palette.dark}"/>
            <path class="money-fx-new-cloth" d="M122 14 C 140 8 152 22 172 14 L172 44 C 152 52 140 38 122 44 Z"
                  fill="${palette.cheek}" stroke="${palette.dark}" stroke-width="2.5" stroke-linejoin="round"/>
        </g>
    </svg>`;

    // Calendario con un día marcado y una campana que suena.
    const remindSvg = () => {
        let cells = '';

        for (let row = 0; row < 3; row += 1) {
            for (let col = 0; col < 5; col += 1) {
                const marked = row === 1 && col === 3;

                cells += `<rect x="${58 + col * 22}" y="${96 + row * 22}" width="16" height="16" rx="4"
                    fill="${marked ? palette.body : palette.dark}" opacity="${marked ? 1 : 0.18}"
                    class="${marked ? 'money-fx-remind-day' : ''}"/>`;
            }
        }

        return `<svg viewBox="0 0 240 200" class="money-fx-svg money-fx-remind" aria-hidden="true">
            <g class="money-fx-remind-cal">
                <rect x="44" y="50" width="136" height="134" rx="16" fill="#ffffff"
                      stroke="${palette.dark}" stroke-width="3"/>
                <rect x="44" y="50" width="136" height="34" rx="16" fill="${palette.body}"/>
                <rect x="44" y="70" width="136" height="14" fill="${palette.body}"/>
                <rect x="72" y="40" width="8" height="22" rx="4" fill="${palette.dark}"/>
                <rect x="144" y="40" width="8" height="22" rx="4" fill="${palette.dark}"/>
                ${cells}
                <path class="money-fx-remind-check" d="M128 127 l5 5 l9 -10" fill="none" stroke="#fff"
                      stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
            </g>
            <g class="money-fx-remind-bell">
                <path d="M170 74 c 0 -22 12 -34 28 -34 c 16 0 28 12 28 34 l 6 14 h -68 Z"
                      fill="${palette.cheek}" stroke="${palette.dark}" stroke-width="3" stroke-linejoin="round"/>
                <circle cx="198" cy="94" r="7" fill="${palette.dark}"/>
                <rect x="194" y="30" width="8" height="10" rx="3" fill="${palette.dark}"/>
            </g>
            <g class="money-fx-remind-waves" fill="none" stroke="${palette.body}" stroke-width="3.5" stroke-linecap="round">
                <path d="M160 46 q -8 10 0 22"/>
                <path d="M236 46 q 8 10 0 22"/>
            </g>
        </svg>`;
    };

    // Maceta de la que crece una planta que da monedas: rendimientos.
    const yieldSvg = () => {
        const count = [2, 3, 4, 5, 6][level - 1];
        const spots = [[120, 40], [88, 62], [152, 58], [100, 30], [142, 30], [120, 70]];
        let fruits = '';

        for (let i = 0; i < count; i += 1) {
            fruits += `<g transform="translate(${spots[i][0]} ${spots[i][1]})">
                <g class="money-fx-yield-fruit" style="--i:${i}">
                    <circle r="10" fill="#f4b81f" stroke="#b8860b" stroke-width="2.4"/>
                    <path d="M0 -5 v10" stroke="#b8860b" stroke-width="2.4" stroke-linecap="round"/>
                </g>
            </g>`;
        }

        return `<svg viewBox="0 0 240 200" class="money-fx-svg money-fx-yield" aria-hidden="true">
            <path class="money-fx-yield-stem" d="M120 150 C 118 120 124 100 120 60" fill="none"
                  stroke="${palette.dark}" stroke-width="6" stroke-linecap="round" pathLength="100"/>
            <path class="money-fx-yield-leaf" style="--i:0" d="M120 112 C 98 106 86 90 90 80 C 106 82 118 94 120 112 Z"
                  fill="${palette.body}" stroke="${palette.dark}" stroke-width="2.5"/>
            <path class="money-fx-yield-leaf" style="--i:1" d="M121 96 C 142 90 154 74 150 64 C 134 66 122 78 121 96 Z"
                  fill="${palette.light}" stroke="${palette.dark}" stroke-width="2.5"/>
            ${fruits}
            <g class="money-fx-yield-pot">
                <path d="M78 150 h84 l-10 40 h-64 Z" fill="#c2703d" stroke="#7c3f1d" stroke-width="3" stroke-linejoin="round"/>
                <rect x="72" y="142" width="96" height="14" rx="5" fill="#d98a52" stroke="#7c3f1d" stroke-width="3"/>
            </g>
        </svg>`;
    };

    const pigSvg = () => {
        const extra = { debt: debtSvg, loan: loanSvg, transfer: transferSvg, new: newSvg, remind: remindSvg, yield: yieldSvg };

        if (extra[kind]) {
            return extra[kind]();
        }

        if (isWish) {
            return wishSvg();
        }

        if (isShare) {
            return shareSvg();
        }

        if (isIncome) {
            return incomeSvg();
        }

        if (isPlan) {
            return planSvg();
        }

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
        // Monedas que caen en la ranura del cerdito.
        if (kind === 'save') {
            const coins = [4, 7, 12, 18, 28][level - 1];

            for (let i = 0; i < coins; i += 1) {
                particle('money-fx-coin', COIN, {
                    '--x': rand(-70, 70).toFixed(0) + 'px',
                    '--delay': (0.15 + i * (1.6 / coins) + rand(0, 0.12)).toFixed(2) + 's',
                    '--size': rand(1.4, 2.2 + level * 0.15).toFixed(2) + 'rem',
                    '--spin': pick([-1, 1]) * rand(180, 540).toFixed(0) + 'deg',
                });
            }
        }

        // En inversión, flechas y % que suben.
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

        // En deseos y compartir: estrellas, corazones y regalos que flotan.
        if (isGift) {
            const icons = isWish
                ? [
                    '<i class="bi bi-star-fill"></i>',
                    '<i class="bi bi-stars"></i>',
                    '<i class="bi bi-heart-fill"></i>',
                    '<i class="bi bi-gift-fill"></i>',
                ]
                : [
                    '<i class="bi bi-heart-fill"></i>',
                    '<i class="bi bi-heart"></i>',
                    '<i class="bi bi-people-fill"></i>',
                    '<i class="bi bi-gift-fill"></i>',
                ];
            const count = [6, 9, 14, 20, 28][level - 1];

            for (let i = 0; i < count; i += 1) {
                particle('money-fx-rise', pick(icons), {
                    '--x': rand(-140, 140).toFixed(0) + 'px',
                    '--delay': (0.35 + rand(0, 1.6)).toFixed(2) + 's',
                    '--size': rand(1.1, 1.9 + level * 0.12).toFixed(2) + 'rem',
                });
            }
        }

        // En un plan guardado: checks y destellos que suben al sellarse.
        if (isPlan) {
            const icons = [
                '<i class="bi bi-check-lg"></i>',
                '<i class="bi bi-check2-circle"></i>',
                '<i class="bi bi-stars"></i>',
            ];
            const count = [4, 6, 9, 13, 18][level - 1];

            for (let i = 0; i < count; i += 1) {
                particle('money-fx-rise', pick(icons), {
                    '--x': rand(-110, 110).toFixed(0) + 'px',
                    '--delay': (0.75 + rand(0, 1.4)).toFixed(2) + 's',
                    '--size': rand(1.1, 1.8 + level * 0.12).toFixed(2) + 'rem',
                });
            }
        }

        // Iconos que flotan, propios de cada animación nueva.
        if (isExtra) {
            const icons = {
                debt: ['<i class="bi bi-receipt"></i>', '<i class="bi bi-credit-card-fill"></i>', '<i class="bi bi-lock-fill"></i>'],
                loan: [COIN, '<i class="bi bi-arrow-right"></i>', '<i class="bi bi-piggy-bank-fill"></i>'],
                transfer: [COIN, '<i class="bi bi-arrow-left-right"></i>', '<i class="bi bi-arrow-right"></i>'],
                new: ['<i class="bi bi-stars"></i>', '<i class="bi bi-plus-circle-fill"></i>', '<i class="bi bi-flag-fill"></i>'],
                remind: ['<i class="bi bi-bell-fill"></i>', '<i class="bi bi-alarm"></i>', '<i class="bi bi-calendar-check"></i>'],
                yield: [COIN, '<i class="bi bi-flower1"></i>', '<i class="bi bi-arrow-up"></i>', '<i class="bi bi-stars"></i>'],
            }[kind];
            const count = [4, 6, 9, 13, 18][level - 1];

            for (let i = 0; i < count; i += 1) {
                particle('money-fx-rise', pick(icons), {
                    '--x': rand(-130, 130).toFixed(0) + 'px',
                    '--delay': (0.6 + rand(0, 1.5)).toFixed(2) + 's',
                    '--size': rand(1.1, 1.8 + level * 0.12).toFixed(2) + 'rem',
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
            const rain = isWish
                ? '<i class="bi bi-star-fill"></i>'
                : isShare
                    ? '<i class="bi bi-heart-fill"></i>'
                    : isPlan
                        ? '<i class="bi bi-check2-circle"></i>'
                        : kind === 'yield' || kind === 'transfer' || kind === 'loan'
                            ? COIN
                            : '<i class="bi bi-cash-stack"></i>';

            for (let i = 0; i < 16; i += 1) {
                particle('money-fx-bill', rain, {
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
        window.setTimeout(() => {
            overlay.remove();
            // Otros festejos (deuda liquidada) esperan a que termine este.
            document.dispatchEvent(new CustomEvent('cauce:money-fx-done'));
        }, 420);

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
