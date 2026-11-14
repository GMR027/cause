<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

/**
 * Persistencia de lecciones contextuales y de su estado de lectura.
 */
final class LessonRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM lecciones ORDER BY seccion ASC, orden ASC, id ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function publishedForSection(string $section): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM lecciones
             WHERE seccion = ? AND publicada = 1
             ORDER BY orden ASC, id ASC LIMIT 1'
        );
        $stmt->execute([$section]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM lecciones WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM lecciones WHERE codigo = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function isSeen(int $userId, int $lessonId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM lecciones_vistas WHERE user_id = ? AND leccion_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $lessonId]);

        return $stmt->fetchColumn() !== false;
    }

    public function markSeen(int $userId, int $lessonId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO lecciones_vistas (user_id, leccion_id, vista_at)
             VALUES (?, ?, UTC_TIMESTAMP())'
        );
        $stmt->execute([$userId, $lessonId]);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO lecciones
                (codigo, seccion, titulo_es, titulo_en, contenido_es, contenido_en,
                 publicada, orden, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['codigo'],
            $data['seccion'],
            $data['titulo_es'],
            $data['titulo_en'],
            $data['contenido_es'],
            $data['contenido_en'],
            $data['publicada'] ? 1 : 0,
            $data['orden'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE lecciones SET
                seccion = ?, titulo_es = ?, titulo_en = ?, contenido_es = ?,
                contenido_en = ?, publicada = ?, orden = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['seccion'],
            $data['titulo_es'],
            $data['titulo_en'],
            $data['contenido_es'],
            $data['contenido_en'],
            $data['publicada'] ? 1 : 0,
            $data['orden'],
            $id,
        ]);
    }

    public function setPublished(int $id, bool $published): void
    {
        $stmt = $this->db->prepare(
            'UPDATE lecciones SET publicada = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$published ? 1 : 0, $id]);
    }

    public function codeExists(string $code, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM lecciones WHERE codigo = ? AND id <> ? LIMIT 1'
        );
        $stmt->execute([$code, $excludeId]);

        return $stmt->fetchColumn() !== false;
    }
}
