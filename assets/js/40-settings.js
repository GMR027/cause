'use strict';

/**
 * Cauce · ajustes: copiar enlace, QR y comprobación de actualizaciones.
 */
(() => {
    const copyText = async (value) => {
        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(value);

                return true;
            } catch (error) {
                // El navegador puede negar el permiso (pestaña sin foco, iframe,
                // permisos del sitio). En ese caso se usa el fallback clásico.
            }
        }

        const area = document.createElement('textarea');
        area.value = value;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();

        let ok = false;

        try {
            ok = document.execCommand('copy');
        } catch (error) {
            ok = false;
        } finally {
            area.remove();
        }

        return ok;
    };

    document.querySelectorAll('[data-action="copy-link"]').forEach((button) => {
        button.addEventListener('click', async () => {
            const ok = await copyText(button.getAttribute('data-copy-value') || '');
            const feedback = document.querySelector('[data-copy-feedback]');

            if (feedback && ok) {
                feedback.hidden = false;
                window.setTimeout(() => {
                    feedback.hidden = true;
                }, 2200);
            }
        });
    });

    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = document.querySelector(button.getAttribute('data-copy-target') || '');

            if (!target) {
                return;
            }

            const ok = await copyText((target.textContent || '').trim());

            if (!ok) {
                return;
            }

            const original = button.textContent;

            button.textContent = t('common.actions.copied');
            window.setTimeout(() => {
                button.textContent = original;
            }, 1800);
        });
    });

    const qrBox = document.getElementById('shareQr');
    const qrError = document.querySelector('[data-qr-error]');

    const showQrError = () => {
        if (qrBox) {
            qrBox.innerHTML = '<i class="bi bi-qr-code text-secondary" aria-hidden="true"></i>';
        }

        if (qrError) {
            qrError.hidden = false;
        }
    };

    if (qrBox) {
        if (typeof window.qrcode !== 'function') {
            showQrError();
        } else {
            try {
                const qr = window.qrcode(0, 'M');
                qr.addData(qrBox.getAttribute('data-qr-url') || window.location.origin);
                qr.make();

                qrBox.innerHTML = qr.createSvgTag(8, 2);
            } catch (error) {
                showQrError();
            }
        }
    }

    document.querySelectorAll('[data-action="check-updates"]').forEach((button) => {
        button.addEventListener('click', async () => {
            const status = document.querySelector('[data-update-status]');

            if (status) {
                status.textContent = t('settings.android.checking');
            }

            try {
                const response = await fetch(button.getAttribute('data-endpoint') || '/api/app/version', {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await response.json();

                if (status) {
                    status.textContent = data.disponible
                        ? t('settings.android.up_to_date')
                        : t('settings.android.soon');
                }
            } catch (error) {
                if (status) {
                    status.textContent = t('settings.android.error');
                }
            }
        });
    });
})();
