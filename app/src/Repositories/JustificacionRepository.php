<?php

declare(strict_types=1);

namespace Cauce\Repositories;

use Cauce\Config\Database;
use PDO;

final class JustificacionRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function create(int $asignacionId, string $motivo, ?string $texto): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO justificaciones
                (asignacion_id, motivo_catalogo, texto, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$asignacionId, $motivo, $texto]);

        return (int) $this->db->lastInsertId();
    }
}
