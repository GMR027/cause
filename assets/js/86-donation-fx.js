'use strict';

/**
 * Cauce · festejo al donar.
 *
 * Agradecimiento: tarjeta con latido y corazones/monedas que flotan; se va sola.
 * Récord: diálogo con trofeo y lluvia de confeti en los colores del tema.
 */
(() => {
    const root = document.querySelector('[data-donation-fx]');

    if (!root) {
        return;
    }

    const kind = root.getAttribute('data-donation-fx');
    const card = root.querySelector('.donation-fx-card');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const THANKS_MS = 6000;
    let closed = false;
    let timer = null;
    let lastFocus = null;

    const rand = (min, max) => min + Math.random() * (max - min);

    const primary = () => {
        const value = getComputedStyle(document.documentElement).getPropertyValue('--cauce-primary').trim();

        return /^#[0-9a-f]{6}$/i.test(value) ? value : '#157f6b';
    };

    // Corazones y monedas que salen de la tarjeta hacia arriba.
    const floatParticles = (count) => {
        if (reduced || !card) {
            return;
        }

        const box = card.getBoundingClientRect();
        const icons = [
            ['bi-heart-fill', '#e0457b'],
            ['bi-heart-fill', '#ff7aa2'],
            ['bi-coin', '#e3a512'],
            ['bi-star-fill', '#f6c64f'],
            ['bi-heart-fill', primary()],
        ];

        for (let i = 0; i < count; i += 1) {
            const [icon, color] = icons[i % icons.length];
            const el = document.createElement('i');

            el.className = 'bi ' + icon + ' donation-fx-particle';
            el.setAttribute('aria-hidden', 'true');
            el.style.left = rand(box.left + 16, box.right - 16) + 'px';
            el.style.top = rand(box.top, box.top + box.height / 2) + 'px';
            el.style.setProperty('--color', color);
            el.style.setProperty('--size', rand(0.9, 1.7).toFixed(2) + 'rem');
            el.style.setProperty('--dx', rand(-70, 70).toFixed(0) + 'px');
            el.style.setProperty('--dy', rand(-260, -120).toFixed(0) + 'px');
            el.style.setProperty('--rot', rand(-40, 40).toFixed(0) + 'deg');
            el.style.setProperty('--dur', rand(1.8, 3).toFixed(2) + 's');
            el.style.setProperty('--delay', rand(0.2, 1.4).toFixed(2) + 's');
            el.addEventListener('animationend', () => el.remove());
            document.body.appendChild(el);
        }
    };

    // Confeti en canvas: cae desde arriba con giro y vaivén.
    const confetti = () => {
        if (reduced) {
            return;
        }

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        if (!ctx) {
            return;
        }

        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const width = window.innerWidth;
        const height = window.innerHeight;
        const colors = [primary(), '#f6c64f', '#e0457b', '#4fc3f7', '#8bd17c', '#ffffff'];
        const pieces = [];
        const total = Math.min(220, Math.round(width / 5));

        canvas.className = 'donation-fx-canvas';
        canvas.setAttribute('aria-hidden', 'true');
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        ctx.scale(dpr, dpr);
        document.body.appendChild(canvas);

        for (let i = 0; i < total; i += 1) {
            // Dos cañones desde las esquinas superiores más una lluvia central.
            const side = i % 3;
            const fromLeft = side === 0;
            const rain = side === 2;

            pieces.push({
                x: rain ? rand(0, width) : (fromLeft ? -10 : width + 10),
                y: rain ? rand(-height * 0.6, -20) : rand(height * 0.15, height * 0.45),
                vx: rain ? rand(-1, 1) : (fromLeft ? rand(4, 11) : rand(-11, -4)),
                vy: rain ? rand(1, 3) : rand(-12, -5),
                size: rand(6, 11),
                color: colors[i % colors.length],
                rot: rand(0, Math.PI * 2),
                vr: rand(-0.2, 0.2),
                wobble: rand(0, Math.PI * 2),
                shape: i % 4 === 0 ? 'circle' : 'rect',
            });
        }

        const started = performance.now();
        const DURATION = 4200;

        const frame = (now) => {
            const elapsed = now - started;

            ctx.clearRect(0, 0, width, height);
            ctx.globalAlpha = elapsed > DURATION - 900 ? Math.max(0, (DURATION - elapsed) / 900) : 1;

            pieces.forEach((p) => {
                p.vy += 0.18;
                p.vx *= 0.985;
                p.vy = Math.min(p.vy, 4.5);
                p.wobble += 0.08;
                p.x += p.vx + Math.sin(p.wobble) * 0.6;
                p.y += p.vy;
                p.rot += p.vr;

                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.fillStyle = p.color;

                if (p.shape === 'circle') {
                    ctx.beginPath();
                    ctx.arc(0, 0, p.size / 2.6, 0, Math.PI * 2);
                    ctx.fill();
                } else {
                    // El coseno simula el giro en 3D de un papelito.
                    ctx.fillRect(-p.size / 2, -p.size / 4, p.size, (p.size / 2) * Math.abs(Math.cos(p.wobble)));
                }

                ctx.restore();
            });

            if (elapsed < DURATION) {
                requestAnimationFrame(frame);
            } else {
                canvas.remove();
            }
        };

        requestAnimationFrame(frame);
    };

    const close = () => {
        if (closed) {
            return;
        }

        closed = true;
        window.clearTimeout(timer);
        root.classList.add('is-leaving');
        document.removeEventListener('keydown', onKey);

        window.setTimeout(() => root.remove(), reduced ? 0 : 450);

        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    };

    const onKey = (event) => {
        if (event.key === 'Escape') {
            close();
        }
    };

    const start = () => {
        root.style.setProperty('--donation-fx-duration', THANKS_MS + 'ms');

        requestAnimationFrame(() => {
            requestAnimationFrame(() => root.classList.add('is-visible'));
        });

        root.querySelectorAll('[data-donation-fx-close]').forEach((el) => {
            el.addEventListener('click', close);
        });
        document.addEventListener('keydown', onKey);

        if (kind === 'record') {
            lastFocus = document.activeElement;
            window.setTimeout(() => {
                const button = root.querySelector('.btn-primary[data-donation-fx-close]');

                if (button && !closed) {
                    button.focus({ preventScroll: true });
                }
            }, 300);
            confetti();
            window.setTimeout(() => floatParticles(14), 250);

            if (navigator.vibrate && !reduced) {
                navigator.vibrate([25, 60, 25, 60, 40]);
            }

            return;
        }

        floatParticles(12);

        // Se va sola, salvo mientras el puntero esté encima.
        let remaining = THANKS_MS;
        let since = performance.now();
        const schedule = () => {
            since = performance.now();
            timer = window.setTimeout(close, remaining);
        };

        card.addEventListener('mouseenter', () => {
            window.clearTimeout(timer);
            remaining -= performance.now() - since;
        });
        card.addEventListener('mouseleave', schedule);
        schedule();
    };

    // Si también hay un logro de gamificación en pantalla, festejamos después.
    const badge = document.getElementById('celebrationModal');

    if (badge) {
        badge.addEventListener('hidden.bs.modal', start, { once: true });
    } else {
        start();
    }
})();
