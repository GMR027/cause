'use strict';

/**
 * Cauce · llaves de acceso (huella, rostro o bloqueo de pantalla).
 *
 * form[data-passkey="get"]: entrar o desbloquear. form[data-passkey="create"]:
 * vincular el dispositivo en Ajustes → Seguridad. Se piden las opciones por
 * fetch, el navegador pide la huella y la respuesta se envía en el mismo
 * formulario (con su _token), que el servidor verifica y redirige.
 */
(() => {
    const forms = document.querySelectorAll('form[data-passkey]');

    if (forms.length === 0) {
        return;
    }

    const supported = typeof window.PublicKeyCredential === 'function'
        && !!navigator.credentials
        && typeof navigator.credentials.get === 'function';

    const toBuffer = (value) => {
        const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
        const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
        const binary = atob(padded);
        const bytes = new Uint8Array(binary.length);

        for (let i = 0; i < binary.length; i += 1) {
            bytes[i] = binary.charCodeAt(i);
        }

        return bytes.buffer;
    };

    const toText = (buffer) => {
        if (!buffer) {
            return '';
        }

        const bytes = new Uint8Array(buffer);
        let binary = '';

        bytes.forEach((byte) => {
            binary += String.fromCharCode(byte);
        });

        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    };

    const deviceName = () => {
        const agent = navigator.userAgent || '';

        if (/iPhone/i.test(agent)) { return 'iPhone'; }
        if (/iPad/i.test(agent)) { return 'iPad'; }
        if (/Android/i.test(agent)) { return 'Android'; }
        if (/Macintosh/i.test(agent)) { return 'Mac'; }
        if (/Windows/i.test(agent)) { return 'Windows'; }
        if (/Linux/i.test(agent)) { return 'Linux'; }

        return '';
    };

    const set = (form, name, value) => {
        const input = form.querySelector(`[name="${name}"]`);

        if (input) {
            input.value = value;
        }
    };

    forms.forEach((form) => {
        const mode = form.getAttribute('data-passkey');
        const button = form.querySelector('[data-passkey-start]');
        const error = form.querySelector('[data-passkey-error]');
        const original = button ? button.innerHTML : '';

        const showError = (message) => {
            if (error) {
                error.textContent = message;
                error.hidden = message === '';
            }
        };

        const busy = (on) => {
            if (!button) {
                return;
            }

            button.disabled = on;
            button.innerHTML = on
                ? `<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>${t('auth.passkeys.busy')}`
                : original;
        };

        if (!supported) {
            if (mode === 'create' && button) {
                button.disabled = true;
                showError(t('auth.passkeys.unsupported'));
            }

            return;
        }

        if (mode === 'get') {
            form.hidden = false;
        }

        if (mode === 'create' && typeof PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable === 'function') {
            PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().then((available) => {
                if (!available && button) {
                    button.disabled = true;
                    showError(t('auth.passkeys.no_platform'));
                }
            }).catch(() => {});
        }

        const options = async () => {
            const body = new FormData();
            body.append('_token', form.querySelector('[name="_token"]').value);

            const response = await fetch(form.getAttribute('data-passkey-options'), {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const data = response.ok ? await response.json() : null;

            if (!data || !data.ok) {
                throw new Error('options');
            }

            return data.publicKey;
        };

        const run = async () => {
            showError('');

            if (mode === 'create') {
                const password = form.querySelector('[name="current_password"]');

                if (password && !password.reportValidity()) {
                    return;
                }
            }

            busy(true);

            try {
                const publicKey = await options();
                publicKey.challenge = toBuffer(publicKey.challenge);
                (publicKey.allowCredentials || publicKey.excludeCredentials || []).forEach((item) => {
                    item.id = toBuffer(item.id);
                });

                if (mode === 'create') {
                    publicKey.user.id = toBuffer(publicKey.user.id);
                    const credential = await navigator.credentials.create({ publicKey });
                    const response = credential.response;

                    if (typeof response.getPublicKey !== 'function' || typeof response.getAuthenticatorData !== 'function') {
                        throw Object.assign(new Error('unsupported'), { name: 'Unsupported' });
                    }

                    set(form, 'credential_id', toText(credential.rawId));
                    set(form, 'client_data', toText(response.clientDataJSON));
                    set(form, 'authenticator_data', toText(response.getAuthenticatorData()));
                    set(form, 'public_key', toText(response.getPublicKey()));
                    set(form, 'algorithm', String(response.getPublicKeyAlgorithm()));

                    const name = form.querySelector('[name="nombre"]');

                    if (name && name.value.trim() === '') {
                        name.value = deviceName();
                    }
                } else {
                    const credential = await navigator.credentials.get({ publicKey });
                    const response = credential.response;

                    set(form, 'credential_id', toText(credential.rawId));
                    set(form, 'client_data', toText(response.clientDataJSON));
                    set(form, 'authenticator_data', toText(response.authenticatorData));
                    set(form, 'signature', toText(response.signature));
                    set(form, 'user_handle', toText(response.userHandle));
                }

                form.submit();
            } catch (exception) {
                busy(false);

                const name = exception && exception.name;

                if (name === 'NotAllowedError' || name === 'AbortError') {
                    showError(t('auth.passkeys.cancelled'));
                } else if (name === 'InvalidStateError') {
                    showError(t('auth.passkeys.errors.duplicate'));
                } else if (name === 'Unsupported' || name === 'NotSupportedError' || name === 'SecurityError') {
                    showError(t('auth.passkeys.unsupported'));
                } else {
                    showError(t('auth.passkeys.errors.generic'));
                }
            }
        };

        if (button) {
            button.addEventListener('click', run);
        }

        // Enter en un campo no envía el formulario sin la respuesta del dispositivo.
        form.addEventListener('submit', (event) => {
            const credential = form.querySelector('[name="credential_id"]');

            if (!credential || credential.value === '') {
                event.preventDefault();
                run();
            }
        });
    });
})();
