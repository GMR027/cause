'use strict';

/**
 * Cauce · onboarding: simulador en vivo, autosave y permiso de notificaciones.
 */
(() => {
    const form = document.querySelector('[data-simulator]');

    if (form) {
        const amountInput = form.querySelector('[data-sim-amount]');
        const metodoInput = form.querySelector('[data-sim-metodo]');
        const panes = Array.from(form.querySelectorAll('[data-sim-pane]'));
        const token = form.querySelector('input[name="_token"]');
        let timer = null;

        const amountCents = () => {
            const value = parseFloat((amountInput ? amountInput.value : '0').replace(',', '.'));

            return Number.isFinite(value) ? Math.max(0, Math.round(value * 100)) : 0;
        };

        const recalc = () => {
            const cents = amountCents();

            panes.forEach((pane) => {
                let percentages = {};

                try {
                    percentages = JSON.parse(pane.getAttribute('data-porcentajes') || '{}');
                } catch (error) {
                    percentages = {};
                }

                const entries = Object.entries(percentages);

                if (entries.length === 0) {
                    return;
                }

                const split = {};
                let total = 0;
                let maxKey = null;
                let maxPercentage = -1;

                entries.forEach(([name, percentage]) => {
                    const value = Math.floor((cents * Number(percentage)) / 100);
                    split[name] = value;
                    total += value;

                    if (Number(percentage) > maxPercentage) {
                        maxPercentage = Number(percentage);
                        maxKey = name;
                    }
                });

                if (maxKey !== null) {
                    split[maxKey] += cents - total;
                }

                pane.querySelectorAll('[data-cat]').forEach((cell) => {
                    const name = cell.getAttribute('data-cat');
                    const valueEl = cell.querySelector('.money-value');

                    if (valueEl && Object.prototype.hasOwnProperty.call(split, name)) {
                        valueEl.textContent = Cauce.formatMoney(split[name]);
                    }
                });
            });
        };

        const autosave = () => {
            if (!token) {
                return;
            }

            const data = new FormData();
            data.append('_token', token.value);
            data.append('monto_centavos', String(amountCents()));

            if (metodoInput) {
                data.append('metodo_preview', metodoInput.value);
            }

            fetch('/api/onboarding/estado', {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).catch(() => {
                // El autosave es best-effort; el formulario principal sigue funcionando.
            });
        };

        if (amountInput) {
            amountInput.addEventListener('input', () => {
                recalc();
                window.clearTimeout(timer);
                timer = window.setTimeout(autosave, 500);
            });
        }

        form.querySelectorAll('[data-sim-tab]').forEach((tab) => {
            tab.addEventListener('shown.bs.tab', () => {
                if (metodoInput) {
                    metodoInput.value = tab.getAttribute('data-sim-tab') || '';
                }

                autosave();
            });
        });

        recalc();
    }

    const base64ToBytes = (value) => {
        const padding = '='.repeat((4 - (value.length % 4)) % 4);
        const normalized = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(normalized);
        const bytes = new Uint8Array(raw.length);

        for (let i = 0; i < raw.length; i++) {
            bytes[i] = raw.charCodeAt(i);
        }

        return bytes;
    };

    const csrf = document.querySelector('meta[name="csrf-token"]');
    const token = csrf ? csrf.getAttribute('content') || '' : '';

    document.querySelectorAll('[data-action="request-notifications"]').forEach((button) => {
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            const notifyForm = button.closest('[data-notify-form]');
            const status = document.querySelector('[data-notify-status]');
            const pushEnabled = button.getAttribute('data-push-enabled') === '1';
            const publicKey = button.getAttribute('data-push-key') || '';
            let statusKey = 'status_active';

            button.disabled = true;

            try {
                if ('Notification' in window && pushEnabled && publicKey !== '') {
                    const permission = await Notification.requestPermission();

                    if (permission !== 'granted') {
                        statusKey = 'status_denied';
                    } else if ('serviceWorker' in navigator && 'PushManager' in window) {
                        const registration = await navigator.serviceWorker.register('/sw.js');
                        let subscription = await registration.pushManager.getSubscription();

                        if (!subscription) {
                            subscription = await registration.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: base64ToBytes(publicKey),
                            });
                        }

                        const json = subscription.toJSON();
                        const keys = json.keys || {};
                        const response = await fetch('/notificaciones/push', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'fetch',
                            },
                            body: new URLSearchParams({
                                _token: token,
                                endpoint: subscription.endpoint,
                                p256dh: keys.p256dh || '',
                                auth: keys.auth || '',
                            }),
                        });

                        statusKey = response.ok ? 'status_push' : 'status_active';
                    } else {
                        statusKey = 'status_unsupported';
                    }
                } else if (pushEnabled) {
                    statusKey = 'status_unsupported';
                }
            } catch (error) {
                statusKey = 'status_active';
            }

            if (status) {
                status.textContent = t('onboarding.notificaciones.' + statusKey);
            }

            if (notifyForm) {
                window.setTimeout(() => {
                    // form.submit() no incluye el botón que originó el envío,
                    // así que «respuesta» llegaría vacío y el servidor mostraría
                    // «Ocurrió un error». requestSubmit sí conserva name/value.
                    button.disabled = false;

                    if (typeof notifyForm.requestSubmit === 'function') {
                        notifyForm.requestSubmit(button);
                        return;
                    }

                    const respuesta = document.createElement('input');
                    respuesta.type = 'hidden';
                    respuesta.name = 'respuesta';
                    respuesta.value = 'activadas';
                    notifyForm.appendChild(respuesta);
                    notifyForm.submit();
                }, 900);
            } else {
                button.disabled = false;
            }
        });
    });
})();
