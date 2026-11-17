<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Helpers\Client;
use Cauce\Helpers\Logger;
use Cauce\Helpers\Storage;
use Cauce\Repositories\AndroidAppRepository;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * App Android de Cauce (Tauri 2, carpeta android/ del repositorio).
 *
 * La app es una ventana hacia esta misma plataforma: su pantalla local consulta
 * /app/android/version.json, ofrece actualizar y abre el sitio pasando por
 * /app/android/entrar, que deja la cookie cauce_android con «versión|instalación».
 * Con esa cookie se registra el teléfono (dispositivos_android) y se avisa
 * cuando la versión instalada es vieja.
 *
 * Publicar es de línea de comandos (bin/publicar_android.php). El APK vive en
 * storage/android/ (volumen persistente) y se descarga sin sesión: el navegador
 * del sistema no comparte la sesión de la app y el APK no contiene datos.
 */
final class AndroidAppService
{
    public const COOKIE = 'cauce_android';
    public const MIME = 'application/vnd.android.package-archive';

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
    private const VERSION = '/^\d{1,3}\.\d{1,3}\.\d{1,3}$/';

    public function __construct(private readonly AndroidAppRepository $repo = new AndroidAppRepository())
    {
    }

    public static function directory(): string
    {
        return base_path('storage/android');
    }

    /** Código de versión de Android tal como lo calcula Tauri. */
    public static function versionCode(string $version): int
    {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $m)) {
            return 0;
        }

        return (int) $m[1] * 1000000 + (int) $m[2] * 1000 + (int) $m[3];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        return $this->repo->latest();
    }

    /**
     * Lo que la app consulta para saber si hay actualización (público).
     *
     * @return array<string, mixed>
     */
    public function publicInfo(string $baseUrl): array
    {
        $latest = $this->repo->latest();

        if ($latest === null) {
            return ['disponible' => false];
        }

        return [
            'disponible' => true,
            'version' => (string) $latest['version'],
            'version_code' => (int) $latest['version_code'],
            'tamano' => (int) $latest['tamano'],
            'sha256' => (string) $latest['sha256'],
            'notas' => (string) ($latest['notas'] ?? ''),
            'publicada' => gmdate('c', (int) strtotime((string) $latest['publicado_en'] . ' UTC')),
            'url' => rtrim($baseUrl, '/') . '/app/android/descargar',
        ];
    }

    /**
     * Ruta absoluta del APK vigente, o null si no hay o no pasa la verificación.
     *
     * @param array<string, mixed> $version
     */
    public function apkPath(array $version): ?string
    {
        $base = realpath(self::directory());
        $path = realpath(self::directory() . '/' . basename((string) $version['archivo']));

        if ($base === false || $path === false || !is_file($path) || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (!hash_equals((string) $version['sha256'], (string) hash_file('sha256', $path))) {
            Logger::error('APK Android con SHA-256 distinto; no se entrega', ['version' => $version['version']]);

            return null;
        }

        return $path;
    }

    /**
     * Copia un APK firmado a storage/android/ y lo registra.
     *
     * @return array<string, mixed> la versión registrada
     */
    public function publish(string $apk, string $version, string $notas): array
    {
        if (!preg_match(self::VERSION, $version)) {
            throw new RuntimeException('La versión debe tener el formato 1.2.3.');
        }

        if (!is_file($apk) || filesize($apk) < 1024) {
            throw new RuntimeException('El archivo no existe o está vacío.');
        }

        $zip = new ZipArchive();

        if ((string) file_get_contents($apk, false, null, 0, 4) !== "PK\x03\x04" || $zip->open($apk) !== true) {
            throw new RuntimeException('El archivo no es un APK (no es un ZIP válido).');
        }

        $hasManifest = $zip->locateName('AndroidManifest.xml') !== false;
        $zip->close();

        if (!$hasManifest) {
            throw new RuntimeException('El archivo no es un APK (falta AndroidManifest.xml).');
        }

        if (!self::hasV2Signature($apk)) {
            throw new RuntimeException('El APK no está firmado: compílalo con android/compilar.sh.');
        }

        $code = self::versionCode($version);
        $latest = $this->repo->latest();

        if ($latest !== null && (int) $latest['version_code'] >= $code) {
            throw new RuntimeException(sprintf(
                'La versión %s no es mayor que la publicada %s: Android no la aceptaría como actualización.',
                $version,
                (string) $latest['version']
            ));
        }

        Storage::ensureDirectory(self::directory());
        $name = sprintf('cauce-android_%s.apk', $version);
        $target = self::directory() . '/' . $name;

        if (!copy($apk, $target)) {
            throw new RuntimeException('No fue posible copiar el APK a storage/android/.');
        }

        @chmod($target, 0644);
        $sha = (string) hash_file('sha256', $target);
        $notas = trim($notas);
        $this->repo->create($version, $code, $name, (int) filesize($target), $sha, $notas !== '' ? mb_substr($notas, 0, 500) : null);

        return (array) $this->repo->latest();
    }

    /** Firma v2/v3: bloque «APK Sig Block 42» justo antes del directorio central del ZIP. */
    private static function hasV2Signature(string $path): bool
    {
        $size = (int) filesize($path);
        $read = min($size, 70000);
        $tail = (string) file_get_contents($path, false, null, $size - $read, $read);
        $pos = strrpos($tail, "PK\x05\x06");

        if ($pos === false) {
            return false;
        }

        $central = unpack('V', substr($tail, $pos + 16, 4))[1] ?? 0;

        return $central >= 24
            && (string) file_get_contents($path, false, null, $central - 16, 16) === 'APK Sig Block 42';
    }

    /** /app/android/entrar: recuerda la versión instalada en una cookie de un año. */
    public static function remember(string $version, string $instalacion): bool
    {
        $instalacion = strtolower($instalacion);

        if (!preg_match(self::VERSION, $version) || !preg_match(self::UUID, $instalacion)) {
            return false;
        }

        $value = $version . '|' . $instalacion;
        setcookie(self::COOKIE, $value, [
            'expires' => time() + 365 * 86400,
            'path' => '/',
            'secure' => Client::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $value;
        unset($_SESSION['_android_seen']);

        return true;
    }

    /**
     * @return array{version: string, instalacion: string}|null si la petición viene de la app
     */
    public static function fromApp(): ?array
    {
        $value = $_COOKIE[self::COOKIE] ?? '';

        if (!is_string($value) || !preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3})\|([0-9a-f-]{36})$/', $value, $m)) {
            return null;
        }

        return ['version' => $m[1], 'instalacion' => $m[2]];
    }

    /**
     * Anota el teléfono (una vez por sesión o al cambiar de versión).
     */
    public function recordVisit(?int $userId): void
    {
        $app = self::fromApp();

        if ($app === null) {
            return;
        }

        $mark = $app['version'] . '|' . $app['instalacion'] . '|' . (int) $userId;

        if (($_SESSION['_android_seen'] ?? '') === $mark) {
            return;
        }

        try {
            $device = self::deviceFromUserAgent((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            $this->repo->touchDevice($app['instalacion'], $app['version'], $userId, $device['modelo'], $device['android']);
            $_SESSION['_android_seen'] = $mark;
        } catch (Throwable $exception) {
            Logger::warning('No se registró el dispositivo Android', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * @return array{modelo: ?string, android: ?string}
     */
    public static function deviceFromUserAgent(string $agent): array
    {
        $modelo = null;
        $android = null;

        if (preg_match('/Android\s+([\d.]+)(?:;\s*([^;)]+?))?(?:\s+Build\/[^;)]*)?[;)]/', $agent, $m)) {
            $android = $m[1];
            $candidate = trim($m[2] ?? '');
            $modelo = $candidate !== '' && strtoupper($candidate) !== 'K' ? mb_substr($candidate, 0, 80) : null;
        }

        return ['modelo' => $modelo, 'android' => $android];
    }

    public static function isOutdated(string $installed, ?array $latest): bool
    {
        return $latest !== null && version_compare($installed, (string) $latest['version'], '<');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function devices(): array
    {
        return $this->repo->devices();
    }
}
