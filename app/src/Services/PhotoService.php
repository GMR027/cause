<?php

declare(strict_types=1);

namespace Cauce\Services;

use RuntimeException;

/**
 * Guardado y compresión de fotos de gastos.
 *
 * Las imágenes quedan fuera de public/ y se sirven por una ruta autenticada.
 */
final class PhotoService
{
    private const MAX_BYTES = 1048576;
    private const MAX_DIMENSION = 1600;

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
     */
    public function store(array $file, int $userId): ?string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo subir la foto.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');

        if ($tmp === '' || !is_file($tmp)) {
            throw new RuntimeException('La foto no es válida.');
        }

        if ((int) ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new RuntimeException('La foto supera el tamaño máximo de 10 MB.');
        }

        $info = @getimagesize($tmp);

        if ($info === false) {
            throw new RuntimeException('El archivo no es una imagen.');
        }

        $mime = (string) ($info['mime'] ?? '');
        $allowed = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Formato de imagen no soportado.');
        }

        if (!function_exists('imagecreatefromjpeg')) {
            throw new RuntimeException('La extensión GD no está disponible.');
        }

        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($tmp),
            'image/png' => imagecreatefrompng($tmp),
            'image/webp' => imagecreatefromwebp($tmp),
            default => false,
        };

        if ($image === false) {
            throw new RuntimeException('No se pudo procesar la imagen.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_DIMENSION / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $directory = base_path('storage/uploads/gastos/' . $userId);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            imagedestroy($image);
            imagedestroy($canvas);

            throw new RuntimeException('No se pudo crear la carpeta de fotos.');
        }

        $filename = bin2hex(random_bytes(16)) . '.jpg';
        $absolute = $directory . '/' . $filename;

        foreach ([80, 70, 60, 50] as $quality) {
            imagejpeg($canvas, $absolute, $quality);

            if (is_file($absolute) && filesize($absolute) <= self::MAX_BYTES) {
                break;
            }
        }

        if (!is_file($absolute) || filesize($absolute) > self::MAX_BYTES) {
            $smallerWidth = max(1, (int) round($newWidth * 0.75));
            $smallerHeight = max(1, (int) round($newHeight * 0.75));
            $smaller = imagecreatetruecolor($smallerWidth, $smallerHeight);
            imagefill($smaller, 0, 0, $white);
            imagecopyresampled($smaller, $canvas, 0, 0, 0, 0, $smallerWidth, $smallerHeight, $newWidth, $newHeight);
            imagejpeg($smaller, $absolute, 60);
            imagedestroy($smaller);
        }

        imagedestroy($image);
        imagedestroy($canvas);

        return $userId . '/' . $filename;
    }

    public function path(?string $relative): ?string
    {
        if ($relative === null || $relative === '') {
            return null;
        }

        $absolute = base_path('storage/uploads/gastos/' . ltrim($relative, '/'));

        return is_file($absolute) ? $absolute : null;
    }

    public function delete(?string $relative): void
    {
        $absolute = $this->path($relative);

        if ($absolute !== null) {
            @unlink($absolute);
        }
    }
}
