<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Helpers\Client;
use Cauce\Helpers\Storage;
use PDO;
use RuntimeException;

/**
 * Estados de cuenta en PDF.
 *
 * Los archivos viven fuera de public/ y solo se sirven por un endpoint
 * autenticado. No se extrae información de los PDFs.
 */
final class StatementService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public static function consentVersion(): string
    {
        return (string) config('security.statements.consent_version', '2026-11-13');
    }

    /**
     * @param array<string, mixed> $user
     */
    public function hasConsent(array $user): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM estados_cuenta_consentimientos WHERE user_id = ? AND version = ? LIMIT 1'
        );
        $statement->execute([(int) $user['id'], self::consentVersion()]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function acceptConsent(array $user): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO estados_cuenta_consentimientos
                (user_id, version, accepted_at, ip, user_agent, created_at, updated_at)
             VALUES (?, ?, UTC_TIMESTAMP(), ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE accepted_at = VALUES(accepted_at), ip = VALUES(ip),
                user_agent = VALUES(user_agent), updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            (int) $user['id'],
            self::consentVersion(),
            Client::ip(),
            Client::userAgent(),
        ]);
    }

    public function reauthValid(): bool
    {
        $at = isset($_SESSION['_statement_reauth_at']) ? (int) $_SESSION['_statement_reauth_at'] : 0;
        $minutes = max(1, (int) config('security.statements.reauth_minutes', 15));

        return $at > 0 && time() - $at <= $minutes * 60;
    }

    public function markReauthenticated(): void
    {
        $_SESSION['_statement_reauth_at'] = time();
    }

    /**
     * @param array<string, mixed> $user
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
     */
    public function store(array $user, array $file, string $type = 'banco'): int
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('No se recibió ningún archivo.');
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo subir el archivo.');
        }

        $maxBytes = (int) config('security.statements.max_bytes', 10485760);

        if ($this->count((int) $user['id']) >= (int) config('security.statements.max_files', 240)) {
            throw new RuntimeException('Alcanzaste el límite de 240 estados de cuenta.');
        }

        $original = trim((string) ($file['name'] ?? ''));

        if (preg_match('/\.pdf$/i', $original) !== 1) {
            throw new RuntimeException('El archivo debe tener extensión .pdf.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');

        if ($tmp === '' || !is_file($tmp)) {
            throw new RuntimeException('El archivo no es válido.');
        }

        $size = @filesize($tmp);

        if ($size === false || $size <= 0) {
            throw new RuntimeException('El archivo está vacío.');
        }

        if ($size > $maxBytes) {
            throw new RuntimeException('El archivo supera el tamaño máximo de 10 MB.');
        }

        $mime = '';

        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($tmp);
            $mime = is_string($detected) ? $detected : '';
        }

        if ($mime !== 'application/pdf') {
            throw new RuntimeException('El archivo no es un PDF válido.');
        }

        $handle = fopen($tmp, 'rb');
        $magic = $handle !== false ? (string) fread($handle, 5) : '';

        if ($handle !== false) {
            fclose($handle);
        }

        if ($magic !== '%PDF-') {
            throw new RuntimeException('El archivo no es un PDF válido.');
        }

        $userId = (int) $user['id'];
        $directory = $this->directory($userId);
        Storage::ensureDirectory($directory);

        $filename = bin2hex(random_bytes(16)) . '.pdf';
        $absolute = $directory . '/' . $filename;

        if (!move_uploaded_file($tmp, $absolute)) {
            throw new RuntimeException('No se pudo guardar el archivo.');
        }

        $checksum = hash_file('sha256', $absolute);

        $statement = $this->db->prepare(
            'INSERT INTO estados_cuenta
                (user_id, nombre_original, ruta_archivo, tamano_bytes, mime, checksum, tipo, fecha_subida, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );

        try {
            $statement->execute([
                $userId,
                $this->cleanName($original),
                $userId . '/' . $filename,
                $size,
                $mime,
                $checksum === false ? null : $checksum,
                in_array($type, ['banco', 'inversion'], true) ? $type : 'banco',
            ]);
        } catch (\Throwable $exception) {
            @unlink($absolute);

            throw $exception;
        }

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function all(array $user): array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM estados_cuenta WHERE user_id = ? ORDER BY fecha_subida DESC, id DESC'
        );
        $statement->execute([(int) $user['id']]);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    public function find(array $user, int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM estados_cuenta WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $statement->execute([$id, (int) $user['id']]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function delete(array $user, int $id): void
    {
        $row = $this->find($user, $id);

        if ($row === null) {
            return;
        }

        $absolute = $this->path($row);

        if ($absolute !== null) {
            @unlink($absolute);
        }

        $statement = $this->db->prepare('DELETE FROM estados_cuenta WHERE id = ? AND user_id = ?');
        $statement->execute([$id, (int) $user['id']]);
    }

    /**
     * @param array<string, mixed> $user
     * @return array{count: int, bytes: int}
     */
    public function usage(array $user): array
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) AS count, COALESCE(SUM(tamano_bytes), 0) AS bytes
             FROM estados_cuenta WHERE user_id = ?'
        );
        $statement->execute([(int) $user['id']]);
        $row = $statement->fetch();

        return [
            'count' => (int) ($row['count'] ?? 0),
            'bytes' => (int) ($row['bytes'] ?? 0),
        ];
    }

    public function count(int $userId): int
    {
        $statement = $this->db->prepare('SELECT COUNT(*) FROM estados_cuenta WHERE user_id = ?');
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     */
    public function path(array $row): ?string
    {
        $relative = trim((string) ($row['ruta_archivo'] ?? ''));

        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $absolute = base_path('storage/estados_cuenta/' . ltrim($relative, '/'));

        return is_file($absolute) ? $absolute : null;
    }

    public function directory(int $userId): string
    {
        return base_path('storage/estados_cuenta/' . $userId);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function purge(array $user): void
    {
        Storage::deleteDirectory($this->directory((int) $user['id']));
    }

    private function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;

        return mb_substr($name !== '' ? $name : 'estado-cuenta.pdf', 0, 255);
    }
}
