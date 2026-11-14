<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\CoverRepository;
use Cauce\Helpers\Storage;
use RuntimeException;

/**
 * Portadas pequeñas para las tarjetas de deseos, ahorro, deudas e inversión.
 *
 * La imagen se recorta al centro en un cuadrado de 200 px y se guarda como
 * JPEG fuera de public/; se sirve por una ruta autenticada.
 */
final class CoverService
{
    public const SIZE = 200;
    private const MAX_UPLOAD_BYTES = 8 * 1024 * 1024;
    // GD usa ~5 bytes por píxel y memory_limit es 256M en la imagen.
    private const MAX_PIXELS = 25_000_000;

    /**
     * Tipo público => [tabla, página a la que se regresa].
     */
    public const TARGETS = [
        'deseo' => ['table' => 'lista_deseos', 'return' => '/deseos'],
        'meta' => ['table' => 'metas_ahorro', 'return' => '/ahorro'],
        'bolsa' => ['table' => 'cuentas_ahorro', 'return' => '/ahorro'],
        'deuda' => ['table' => 'deudas', 'return' => '/deudas'],
        'posicion' => ['table' => 'inversiones_posiciones', 'return' => '/inversion'],
    ];

    private CoverRepository $repo;

    public function __construct(?CoverRepository $repo = null)
    {
        $this->repo = $repo ?? new CoverRepository();
    }

    public static function validTarget(string $target): bool
    {
        return isset(self::TARGETS[$target]);
    }

    public static function returnPath(string $target): string
    {
        return self::TARGETS[$target]['return'] ?? '/inicio';
    }

    /**
     * Guarda la portada y reemplaza la anterior.
     *
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
     */
    public function upload(int $userId, string $target, int $id, array $file): void
    {
        $table = $this->table($target);
        $current = $this->repo->currentPath($table, $id, $userId);

        if ($current === false) {
            throw new RuntimeException('covers.errors.not_found');
        }

        $relative = $this->store($file, $userId);
        $this->repo->setPath($table, $id, $userId, $relative);
        $this->deleteFile($current);
    }

    public function remove(int $userId, string $target, int $id): void
    {
        $table = $this->table($target);
        $current = $this->repo->currentPath($table, $id, $userId);

        if ($current === false) {
            throw new RuntimeException('covers.errors.not_found');
        }

        $this->repo->setPath($table, $id, $userId, null);
        $this->deleteFile($current);
    }

    /**
     * Ruta absoluta de la portada de un registro del usuario, o null.
     */
    public function pathFor(int $userId, string $target, int $id): ?string
    {
        if (!self::validTarget($target)) {
            return null;
        }

        $current = $this->repo->currentPath($this->table($target), $id, $userId);

        return is_string($current) ? $this->path($current) : null;
    }

    /**
     * Borra el archivo de un registro que se va a eliminar.
     */
    public function forget(int $userId, string $target, int $id): void
    {
        if (!self::validTarget($target)) {
            return;
        }

        $current = $this->repo->currentPath($this->table($target), $id, $userId);

        if (is_string($current)) {
            $this->deleteFile($current);
        }
    }

    public static function userDirectory(int $userId): string
    {
        return base_path('storage/uploads/portadas/' . $userId);
    }

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
     */
    private function store(array $file, int $userId): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('covers.errors.missing');
        }

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('covers.errors.too_big');
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('covers.errors.upload');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');

        if ($tmp === '' || !is_file($tmp)) {
            throw new RuntimeException('covers.errors.upload');
        }

        if ((int) ($file['size'] ?? filesize($tmp)) > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('covers.errors.too_big');
        }

        $info = @getimagesize($tmp);

        if ($info === false) {
            throw new RuntimeException('covers.errors.not_image');
        }

        [$width, $height] = [(int) $info[0], (int) $info[1]];
        $mime = (string) ($info['mime'] ?? '');

        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            throw new RuntimeException('covers.errors.format');
        }

        if ($width <= 0 || $height <= 0 || $width * $height > self::MAX_PIXELS) {
            throw new RuntimeException('covers.errors.too_big');
        }

        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('covers.errors.upload');
        }

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png' => @imagecreatefrompng($tmp),
            'image/webp' => @imagecreatefromwebp($tmp),
            'image/gif' => @imagecreatefromgif($tmp),
        };

        if ($image === false) {
            throw new RuntimeException('covers.errors.not_image');
        }

        // Respeta la orientación EXIF de las fotos tomadas con el celular.
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmp);
            $rotation = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };

            if ($rotation !== 0) {
                $rotated = imagerotate($image, $rotation, 0);

                if ($rotated !== false) {
                    imagedestroy($image);
                    $image = $rotated;
                    $width = imagesx($image);
                    $height = imagesy($image);
                }
            }
        }

        $side = min($width, $height);
        $srcX = (int) floor(($width - $side) / 2);
        $srcY = (int) floor(($height - $side) / 2);
        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $image, 0, 0, $srcX, $srcY, self::SIZE, self::SIZE, $side, $side);
        imagedestroy($image);

        $directory = self::userDirectory($userId);
        Storage::ensureDirectory($directory);
        $filename = bin2hex(random_bytes(16)) . '.jpg';

        if (!imagejpeg($canvas, $directory . '/' . $filename, 82)) {
            imagedestroy($canvas);

            throw new RuntimeException('covers.errors.upload');
        }

        imagedestroy($canvas);

        return $userId . '/' . $filename;
    }

    private function path(string $relative): ?string
    {
        if (preg_match('#^\d+/[a-f0-9]{32}\.jpg$#', $relative) !== 1) {
            return null;
        }

        $absolute = base_path('storage/uploads/portadas/' . $relative);

        return is_file($absolute) ? $absolute : null;
    }

    private function deleteFile(?string $relative): void
    {
        if ($relative === null || $relative === '') {
            return;
        }

        $absolute = $this->path($relative);

        if ($absolute !== null) {
            @unlink($absolute);
        }
    }

    private function table(string $target): string
    {
        if (!self::validTarget($target)) {
            throw new RuntimeException('covers.errors.not_found');
        }

        return self::TARGETS[$target]['table'];
    }
}
