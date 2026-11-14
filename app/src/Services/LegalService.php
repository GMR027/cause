<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Config\Database;
use Cauce\Helpers\Client;
use PDO;
use RuntimeException;

/**
 * Documentos legales versionados y aceptaciones.
 */
final class LegalService
{
    public const BLOCKING_TYPES = ['privacidad', 'terminos'];

    public const TYPES = ['privacidad', 'terminos', 'aviso'];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function current(): array
    {
        $statement = $this->db->query(
            "SELECT * FROM legal_documentos WHERE vigente = 1 ORDER BY tipo ASC, published_at DESC, id DESC"
        );
        $result = [];

        foreach ($statement->fetchAll() as $document) {
            $type = (string) $document['tipo'];

            if (!isset($result[$type])) {
                $result[$type] = $document;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentFor(string $type): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM legal_documentos WHERE tipo = ? AND vigente = 1
             ORDER BY published_at DESC, id DESC LIMIT 1'
        );
        $statement->execute([$type]);
        $document = $statement->fetch();

        return is_array($document) ? $document : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->db->query(
            'SELECT * FROM legal_documentos ORDER BY tipo ASC, published_at DESC, id DESC'
        )->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM legal_documentos WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $document = $statement->fetch();

        return is_array($document) ? $document : null;
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array<string, mixed>>
     */
    public function pending(array $user): array
    {
        $pending = [];
        $accepted = $this->acceptedVersions((int) $user['id']);

        foreach ($this->current() as $type => $document) {
            if (!in_array($type, self::BLOCKING_TYPES, true)) {
                continue;
            }

            $version = (string) $document['version'];

            if (!in_array($version, $accepted[$type] ?? [], true)) {
                $pending[] = $document;
            }
        }

        return $pending;
    }

    /**
     * @param array<string, mixed> $user
     * @param list<array<string, mixed>> $documents
     */
    public function accept(array $user, array $documents): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO legal_aceptaciones
                (user_id, tipo, version, accepted_at, ip, user_agent, created_at, updated_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP(), ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE accepted_at = VALUES(accepted_at), ip = VALUES(ip),
                user_agent = VALUES(user_agent), updated_at = UTC_TIMESTAMP()'
        );

        foreach ($documents as $document) {
            $statement->execute([
                (int) $user['id'],
                (string) $document['tipo'],
                (string) $document['version'],
                Client::ip(),
                Client::userAgent(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $admin
     */
    public function publish(
        array $admin,
        string $type,
        string $version,
        string $titleEs,
        string $titleEn,
        string $contentEs,
        string $contentEn
    ): int {
        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException('Tipo de documento inválido.');
        }

        $version = trim($version);

        if (preg_match('/^\d{1,3}(\.\d{1,3}){1,2}$/', $version) !== 1) {
            throw new RuntimeException('La versión debe verse como 1.0 o 1.1.2.');
        }

        if (trim($titleEs) === '' || trim($titleEn) === '' || trim($contentEs) === '' || trim($contentEn) === '') {
            throw new RuntimeException('Completa títulos y contenidos en ambos idiomas.');
        }

        $exists = $this->db->prepare(
            'SELECT id FROM legal_documentos WHERE tipo = ? AND version = ? LIMIT 1'
        );
        $exists->execute([$type, $version]);

        if ($exists->fetch() !== false) {
            throw new RuntimeException('Ya existe una versión de este documento con ese número.');
        }

        $this->db->beginTransaction();

        try {
            $this->db->prepare('UPDATE legal_documentos SET vigente = 0 WHERE tipo = ?')->execute([$type]);

            $statement = $this->db->prepare(
                'INSERT INTO legal_documentos
                    (tipo, version, titulo_es, titulo_en, contenido_es, contenido_en,
                     vigente, published_at, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP(), ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $statement->execute([
                $type,
                $version,
                mb_substr(trim($titleEs), 0, 160),
                mb_substr(trim($titleEn), 0, 160),
                trim($contentEs),
                trim($contentEn),
                (int) $admin['id'],
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();

            return $id;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($exception instanceof \PDOException && (string) $exception->getCode() === '23000') {
                throw new RuntimeException('Ya existe una versión de este documento con ese número.');
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function acceptedVersions(int $userId): array
    {
        $statement = $this->db->prepare(
            'SELECT tipo, version FROM legal_aceptaciones WHERE user_id = ?'
        );
        $statement->execute([$userId]);
        $result = [];

        foreach ($statement->fetchAll() as $row) {
            $result[(string) $row['tipo']][] = (string) $row['version'];
        }

        return $result;
    }

    public function acceptedCount(string $type, string $version): int
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM legal_aceptaciones WHERE tipo = ? AND version = ?'
        );
        $statement->execute([$type, $version]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed> $document
     */
    public static function title(array $document, string $lang): string
    {
        return (string) ($lang === 'en' ? ($document['titulo_en'] ?? '') : ($document['titulo_es'] ?? ''));
    }

    /**
     * @param array<string, mixed> $document
     */
    public static function content(array $document, string $lang): string
    {
        return (string) ($lang === 'en' ? ($document['contenido_en'] ?? '') : ($document['contenido_es'] ?? ''));
    }
}
