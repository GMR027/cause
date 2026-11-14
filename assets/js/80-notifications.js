'use strict';

/**
 * Cauce · notificaciones in-app y Web Push.
 */
(() => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const post = (url, data) => {
        const body = new URLSearchParams(Object.assign({ _token: token }, data));

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'fetch',
            },
            body,
        });
    };

    // Marca la notificación como leída antes de navegar al detalle.
    document.querySelectorAll('[data-notification-read]').forEach((link) => {
        link.addEventListener('click', async (event) => {
            if (link.getAttribute('data-read-done') === '1') {
                return;
            }

            event.preventDefault();
            const id = link.getAttribute('data-notification-read') || '';

            try {
                await post('/notificaciones/leer', { id });
            } catch (error) {
                // La navegación no depende del marcado.
            }

            link.setAttribute('data-read-done', '1');
            window.location.href = link.getAttribute('href') || '/notificaciones';
        });
    });

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

    const button = document.querySelector('[data-push-subscribe]');

    if (!button) {
        return;
    }

    const status = document.querySelector('[data-push-status]');
    const setStatus = (key, isError = false) => {
        if (!status) {
            return;
        }

        status.textContent = t(key);
        status.classList.toggle('text-danger', isError);
        status.classList.toggle('text-secondary', !isError);
    };

    button.addEventListener('click', async () => {
        const publicKey = button.getAttribute('data-public-key') || '';

        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            setStatus('notifications.settings.push_unsupported', true);
            return;
        }

        if (publicKey === '') {
            setStatus('notifications.settings.push_error', true);
            return;
        }

        button.disabled = true;

        try {
            const registration = await navigator.serviceWorker.register('/sw.js');
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                setStatus('notifications.settings.push_denied', true);
                return;
            }

            let subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: base64ToBytes(publicKey),
                });
            }

            const json = subscription.toJSON();
            const keys = json.keys || {};
            const response = await post('/notificaciones/push', {
                endpoint: subscription.endpoint,
                p256dh: keys.p256dh || '',
                auth: keys.auth || '',
            });

            if (!response.ok) {
                throw new Error('subscription');
            }

            setStatus('notifications.settings.push_ok');
        } catch (error) {
            setStatus('notifications.settings.push_error', true);
        } finally {
            button.disabled = false;
        }
    });
})();
