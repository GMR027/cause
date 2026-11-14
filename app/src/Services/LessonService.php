<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Helpers\I18n;
use Cauce\Repositories\LessonRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lecciones contextuales neutrales y su administración.
 */
final class LessonService
{
    public const SECTIONS = [
        'ahorro',
        'inversion',
        'donacion',
        'deudas',
        'patrimonio',
        'fondo_emergencia',
    ];

    public const MAX_WORDS = 200;

    private LessonRepository $repo;

    public function __construct(?LessonRepository $repo = null)
    {
        $this->repo = $repo ?? new LessonRepository();
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    public function forSection(array $user, string $section): ?array
    {
        $row = $this->repo->publishedForSection($section);

        if ($row === null) {
            return null;
        }

        return array_merge($row, [
            'vista' => $this->repo->isSeen((int) $user['id'], (int) $row['id']),
            'titulo' => $this->title($row),
            'contenido' => $this->content($row),
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    public function markSeenByCode(array $user, string $code): bool
    {
        $row = $this->repo->findByCode($code);

        if ($row === null || (int) $row['publicada'] !== 1) {
            return false;
        }

        $this->repo->markSeen((int) $user['id'], (int) $row['id']);

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminAll(): array
    {
        return array_map(function (array $row): array {
            return array_merge($row, [
                'titulo' => $this->title($row),
                'contenido' => $this->content($row),
            ]);
        }, $this->repo->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data, ?int $id = null): int
    {
        $normalized = $this->normalize($data, $id);

        if ($id === null) {
            if ($this->repo->codeExists($normalized['codigo'])) {
                throw new RuntimeException('Ya existe una lección con ese código.');
            }

            return $this->repo->create($normalized);
        }

        if ($this->repo->find($id) === null) {
            throw new RuntimeException('La lección no existe.');
        }

        $this->repo->update($id, $normalized);

        return $id;
    }

    public function setPublished(int $id, bool $published): void
    {
        if ($this->repo->find($id) === null) {
            throw new RuntimeException('La lección no existe.');
        }

        $this->repo->setPublished($id, $published);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function title(array $row): string
    {
        $spanish = trim((string) ($row['titulo_es'] ?? ''));
        $english = trim((string) ($row['titulo_en'] ?? ''));

        if (I18n::current() === 'en' && $english !== '') {
            return $english;
        }

        return $spanish;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function content(array $row): string
    {
        $spanish = trim((string) ($row['contenido_es'] ?? ''));
        $english = trim((string) ($row['contenido_en'] ?? ''));

        if (I18n::current() === 'en' && $english !== '') {
            return $english;
        }

        return $spanish;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?int $id): array
    {
        $code = mb_strtolower(trim((string) ($data['codigo'] ?? '')));
        $section = trim((string) ($data['seccion'] ?? ''));
        $titleEs = trim((string) ($data['titulo_es'] ?? ''));
        $titleEn = trim((string) ($data['titulo_en'] ?? ''));
        $contentEs = trim((string) ($data['contenido_es'] ?? ''));
        $contentEn = trim((string) ($data['contenido_en'] ?? ''));
        $order = (int) ($data['orden'] ?? 0);

        if ($id === null && preg_match('/^[a-z0-9_]{3,40}$/', $code) !== 1) {
            throw new InvalidArgumentException('El código debe tener entre 3 y 40 caracteres: letras minúsculas, números o guion bajo.');
        }

        if (!in_array($section, self::SECTIONS, true)) {
            throw new InvalidArgumentException('La sección no es válida.');
        }

        if ($titleEs === '' || mb_strlen($titleEs) > 160) {
            throw new InvalidArgumentException('El título en español es obligatorio y admite hasta 160 caracteres.');
        }

        if ($titleEn !== '' && mb_strlen($titleEn) > 160) {
            throw new InvalidArgumentException('El título en inglés admite hasta 160 caracteres.');
        }

        if ($contentEs === '') {
            throw new InvalidArgumentException('El contenido en español es obligatorio.');
        }

        $this->assertWordLimit($contentEs, 'español');

        if ($contentEn !== '') {
            $this->assertWordLimit($contentEn, 'inglés');
        }

        return [
            'codigo' => $code,
            'seccion' => $section,
            'titulo_es' => $titleEs,
            'titulo_en' => $titleEn !== '' ? $titleEn : null,
            'contenido_es' => $contentEs,
            'contenido_en' => $contentEn !== '' ? $contentEn : null,
            'publicada' => !empty($data['publicada']),
            'orden' => max(0, min(255, $order)),
        ];
    }

    private function assertWordLimit(string $content, string $language): void
    {
        $words = preg_split('/\s+/u', trim($content), -1, PREG_SPLIT_NO_EMPTY);
        $count = is_array($words) ? count($words) : 0;

        if ($count > self::MAX_WORDS) {
            throw new InvalidArgumentException(
                'El contenido en ' . $language . ' supera las ' . self::MAX_WORDS . ' palabras.'
            );
        }
    }
}
