'use strict';

/**
 * Cauce · festejo al liquidar una deuda.
 *
 * Secuencia: la cadena tiembla y se rompe, salen chispas, el sello aparece
 * con rayos girando, cae el sello de «Pagada» con un golpe (la tarjeta
 * sacude y el teléfono vibra) y arrancan los fuegos artificiales con confeti.
 * Sin deudas restantes ('free') hay más cohetes, más tiempo y trofeo.
 * Espera a que terminen el logro de gamificación y la animación de dinero.
 */
(() => {
    const root = document.querySelector('[data-debt-paid]');

    if (!root) {
        return;
    }

    const free = root.getAttribute('data-debt-paid') === 'free';
    const card = root.querySelector('.debt-paid-card');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const STAMP_MS = 1250;
    let closed = false;
    let started = false;
    let lastFocus = null;
    let stopFireworks = () => {};

    const rand = (min, max) => min + Math.random() * (max - min);

    const primary = () => {
        const value = getComputedStyle(document.documentElement).getPropertyValue('--cauce-primary').trim();

        return /^#[0-9a-f]{6}$/i.test(value) ? value : '#157f6b';
    };

    // Fuegos artificiales y confeti en un mismo canvas.
    const fireworks = () => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        if (!ctx) {
            return () => {};
        }

        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        let width = window.innerWidth;
        let height = window.innerHeight;
        const palette = [primary(), '#f6c64f', '#ffd76a', '#e0457b', '#4fc3f7', '#8bd17c', '#ffffff', '#b388ff'];
        const DURATION = free ? 9000 : 5600;
        const LAUNCH_EVERY = free ? 230 : 380;
        const rockets = [];
        const sparks = [];
        const confetti = [];
        let raf = 0;
        let lastLaunch = 0;
        let running = true;

        const resize = () => {
            width = window.innerWidth;
            height = window.innerHeight;
            canvas.width = width * dpr;
            canvas.height = height * dpr;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        };

        canvas.className = 'debt-paid-canvas';
        canvas.setAttribute('aria-hidden', 'true');
        resize();
        // Detrás de la tarjeta y sobre el fondo oscuro: luce más y no tapa el texto.
        root.insertBefore(canvas, card);
        window.addEventListener('resize', resize);

        const launch = () => {
            rockets.push({
                x: rand(width * 0.12, width * 0.88),
                y: height + 10,
                vx: rand(-1.2, 1.2),
                vy: -rand(height / 70, height / 52),
                targetY: rand(height * 0.12, height * 0.42),
                color: palette[Math.floor(rand(0, palette.length))],
            });
        };

        const explode = (rocket) => {
            const count = Math.round(rand(46, free ? 90 : 70));
            const ring = Math.random() < 0.35;
            const second = palette[Math.floor(rand(0, palette.length))];

            for (let i = 0; i < count; i += 1) {
                const angle = (Math.PI * 2 * i) / count + rand(-0.05, 0.05);
                const speed = ring ? 4.2 : rand(1.2, 5.2);

                sparks.push({
                    x: rocket.x,
                    y: rocket.y,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed,
                    life: 1,
                    decay: rand(0.011, 0.02),
                    color: i % 3 === 0 ? second : rocket.color,
                    size: rand(1.4, 2.6),
                    twinkle: Math.random() < 0.3,
                });
            }
        };

        // Dos cañones de confeti desde las esquinas inferiores.
        const cannons = (amount) => {
            for (let i = 0; i < amount; i += 1) {
                const left = i % 2 === 0;

                confetti.push({
                    x: left ? -10 : width + 10,
                    y: height * rand(0.6, 0.85),
                    vx: left ? rand(4, 13) : rand(-13, -4),
                    vy: -rand(9, 17),
                    size: rand(6, 11),
                    color: palette[i % palette.length],
                    rot: rand(0, Math.PI * 2),
                    vr: rand(-0.25, 0.25),
                    wobble: rand(0, Math.PI * 2),
                    circle: i % 4 === 0,
                });
            }
        };

        const begin = performance.now();

        cannons(Math.min(free ? 260 : 170, Math.round(width / (free ? 3 : 4.5))));

        for (let i = 0; i < (free ? 5 : 3); i += 1) {
            launch();
        }

        const frame = (now) => {
            const elapsed = now - begin;

            // Borrado parcial: deja una estela breve sin ensuciar el fondo.
            ctx.globalCompositeOperation = 'destination-out';
            ctx.fillStyle = 'rgba(0, 0, 0, 0.28)';
            ctx.fillRect(0, 0, width, height);
            ctx.globalCompositeOperation = 'lighter';

            if (running && elapsed < DURATION - 1400 && now - lastLaunch > LAUNCH_EVERY) {
                lastLaunch = now;
                launch();

                if (free && Math.random() < 0.3) {
                    launch();
                }
            }

            for (let i = rockets.length - 1; i >= 0; i -= 1) {
                const r = rockets[i];

                r.x += r.vx;
                r.y += r.vy;
                r.vy *= 0.985;
                ctx.fillStyle = r.color;
                ctx.beginPath();
                ctx.arc(r.x, r.y, 2.2, 0, Math.PI * 2);
                ctx.fill();

                if (r.y <= r.targetY || r.vy > -1.5) {
                    explode(r);
                    rockets.splice(i, 1);
                }
            }

            for (let i = sparks.length - 1; i >= 0; i -= 1) {
                const s = sparks[i];

                s.vx *= 0.975;
                s.vy = s.vy * 0.975 + 0.05;
                s.x += s.vx;
                s.y += s.vy;
                s.life -= s.decay;

                if (s.life <= 0) {
                    sparks.splice(i, 1);
                    continue;
                }

                ctx.globalAlpha = s.twinkle ? s.life * (0.5 + Math.random() * 0.5) : s.life;
                ctx.fillStyle = s.color;
                ctx.beginPath();
                ctx.arc(s.x, s.y, s.size, 0, Math.PI * 2);
                ctx.fill();
            }

            ctx.globalCompositeOperation = 'source-over';
            ctx.globalAlpha = elapsed > DURATION - 900 ? Math.max(0, (DURATION - elapsed) / 900) : 1;

            for (let i = confetti.length - 1; i >= 0; i -= 1) {
                const p = confetti[i];

                p.vy = Math.min(p.vy + 0.22, 4.2);
                p.vx *= 0.985;
                p.wobble += 0.09;
                p.x += p.vx + Math.sin(p.wobble) * 0.7;
                p.y += p.vy;
                p.rot += p.vr;

                if (p.y > height + 30) {
                    confetti.splice(i, 1);
                    continue;
                }

                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.fillStyle = p.color;

                if (p.circle) {
                    ctx.beginPath();
                    ctx.arc(0, 0, p.size / 2.6, 0, Math.PI * 2);
                    ctx.fill();
                } else {
                    // El coseno simula el giro en 3D del papelito.
                    ctx.fillRect(-p.size / 2, -p.size / 4, p.size, (p.size / 2) * Math.abs(Math.cos(p.wobble)));
                }

                ctx.restore();
            }

            ctx.globalAlpha = 1;

            const busy = rockets.length > 0 || sparks.length > 0 || confetti.length > 0;

            if (elapsed < DURATION && (running || busy)) {
                raf = requestAnimationFrame(frame);
            } else {
                cleanup();
            }
        };

        const cleanup = () => {
            cancelAnimationFrame(raf);
            window.removeEventListener('resize', resize);
            canvas.remove();
        };

        raf = requestAnimationFrame(frame);

        // Al cerrar: no hay más cohetes y lo que queda se apaga rápido.
        return () => {
            running = false;
            canvas.classList.add('is-leaving');
            window.setTimeout(cleanup, 500);
        };
    };

    const close = () => {
        if (closed) {
            return;
        }

        closed = true;
        root.classList.add('is-leaving');
        document.removeEventListener('keydown', onKey);
        stopFireworks();
        window.setTimeout(() => root.remove(), reduced ? 0 : 450);

        if (lastFocus instanceof HTMLElement && document.body.contains(lastFocus)) {
            lastFocus.focus({ preventScroll: true });
        }
    };

    const onKey = (event) => {
        if (event.key === 'Escape') {
            close();
        }
    };

    const start = () => {
        if (started) {
            return;
        }

        started = true;
        lastFocus = document.activeElement;

        root.querySelectorAll('[data-debt-paid-close]').forEach((el) => {
            el.addEventListener('click', close);
        });
        document.addEventListener('keydown', onKey);

        requestAnimationFrame(() => {
            requestAnimationFrame(() => root.classList.add('is-visible'));
        });

        window.setTimeout(() => {
            const button = root.querySelector('.debt-paid-continue');

            if (button && !closed) {
                button.focus({ preventScroll: true });
            }
        }, reduced ? 50 : STAMP_MS + 300);

        if (reduced) {
            return;
        }

        // El golpe del sello: sacudida, vibración y arranca la fiesta.
        window.setTimeout(() => {
            if (closed) {
                return;
            }

            card.classList.add('is-stamped');
            stopFireworks = fireworks();

            if (navigator.vibrate) {
                navigator.vibrate(free ? [60, 40, 30, 40, 30, 80, 120] : [50, 50, 30, 50, 80]);
            }
        }, STAMP_MS);
    };

    // Primero el logro de gamificación y la animación de dinero, si las hay.
    const waits = [];
    const badge = document.getElementById('celebrationModal');

    if (badge) {
        waits.push(new Promise((resolve) => {
            badge.addEventListener('hidden.bs.modal', resolve, { once: true });
        }));
    }

    if (document.querySelector('[data-money-fx]')) {
        waits.push(new Promise((resolve) => {
            document.addEventListener('cauce:money-fx-done', resolve, { once: true });
            // Por si la animación de dinero no llega a mostrarse.
            window.setTimeout(resolve, 9000);
        }));
    }

    Promise.all(waits).then(start);
})();
