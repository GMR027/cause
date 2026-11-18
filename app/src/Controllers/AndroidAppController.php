<?php

declare(strict_types=1);

namespace Cauce\Controllers;

use Cauce\Helpers\Client;
use Cauce\Helpers\View;
use Cauce\Services\AndroidAppService;
use Cauce\Services\AuthService;

/**
 * Rutas públicas que usa la app Android (/app/android/*).
 */
final class AndroidAppController
{
    /**
     * GET /.well-known/assetlinks.json — asocia el dominio con la app para que
     * Android permita usar las llaves de acceso (huella) del sitio dentro de ella.
     */
    public function assetLinks(): void
    {
        $hex = strtolower(preg_replace('/[^0-9a-f]/i', '', (string) config('security.android.cert_sha256', '')) ?? '');
        $fingerprint = strtoupper(implode(':', str_split($hex, 2)));

        header('Cache-Control: public, max-age=3600');
        json_response([[
            'relation' => ['delegate_permission/common.get_login_creds'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => (string) config('security.android.package', ''),
                'sha256_cert_fingerprints' => $hex !== '' ? [$fingerprint] : [],
            ],
        ]]);
    }

    /** GET /app/android/version.json — la pantalla local de la app (otro origen) lo consulta al abrir. */
    public function version(): void
    {
        $scheme = Client::isHttps() ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-cache');
        json_response((new AndroidAppService())->publicInfo($scheme . '://' . $host));
    }

    /** GET /app/android/descargar — el APK vigente, sin sesión (lo baja el navegador del sistema). */
    public function download(): void
    {
        $service = new AndroidAppService();
        $latest = $service->latest();
        $path = $latest !== null ? $service->apkPath($latest) : null;

        if ($latest === null || $path === null) {
            http_response_code(404);
            echo View::render('errors/404', ['title' => __('common.errors.404.title')]);

            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . AndroidAppService::MIME);
        header('Content-Disposition: attachment; filename="' . basename((string) $latest['archivo']) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }

    /** GET /app/android/entrar?v=1.2.3&id=<uuid> — primera página que abre la app. */
    public function enter(): void
    {
        AndroidAppService::remember((string) ($_GET['v'] ?? ''), (string) ($_GET['id'] ?? ''));

        redirect(AuthService::check() ? '/inicio' : '/login');
    }
}
