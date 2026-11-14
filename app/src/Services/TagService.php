<?php

declare(strict_types=1);

namespace Cauce\Services;

use Cauce\Repositories\PlantillaRepository;
use Cauce\Repositories\TagRepository;
use InvalidArgumentException;

/**
 * Normalización y sincronización de tags.
 */
final class TagService
{
    public function normalize(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

            if (is_string($transliterated) && $transliterated !== '') {
                $name = $transliterated;
            }
        }

        $name = mb_strtolower($name, 'UTF-8');
        $name = (string) preg_replace('/[^\p{L}\p{N}\s_-]+/u', '', $name);
        $name = (string) preg_replace('/\s+/', ' ', $name);

        return mb_substr(trim($name), 0, 80);
    }

    /**
     * @return list<string>
     */
    public function parseInput(mixed $raw): array
    {
        if (is_array($raw)) {
            $pieces = $raw;
        } elseif (is_string($raw)) {
            $pieces = preg_split('/,/', $raw) ?: [];
        } else {
            return [];
        }

        $tags = [];

        foreach ($pieces as $piece) {
            $normalized = $this->normalize((string) $piece);

            if ($normalized !== '' && !in_array($normalized, $tags, true)) {
                $tags[] = $normalized;
            }

            if (count($tags) >= 5) {
                break;
            }
        }

        return $tags;
    }

    /**
     * @return list<string>
     */
    public function syncGasto(int $gastoId, int $userId, mixed $raw): array
    {
        $tags = $this->parseInput($raw);
        $repo = new TagRepository();
        $repo->detachAll($gastoId);

        foreach ($tags as $tag) {
            $repo->attach($gastoId, $repo->findOrCreate($userId, $tag));
        }

        return $tags;
    }

    public function create(int $userId, string $name): int
    {
        $normalized = $this->normalize($name);

        if ($normalized === '') {
            throw new InvalidArgumentException('El tag no puede estar vacío.');
        }

        return (new TagRepository())->findOrCreate($userId, $normalized);
    }

    public function rename(int $id, int $userId, string $newName): void
    {
        $normalized = $this->normalize($newName);

        if ($normalized === '') {
            throw new InvalidArgumentException('El tag no puede estar vacío.');
        }

        $repo = new TagRepository();
        $tag = $repo->findForUser($id, $userId);

        if ($tag === null) {
            throw new InvalidArgumentException('El tag no existe.');
        }

        if ($tag['nombre_normalizado'] === $normalized) {
            return;
        }

        foreach ($repo->allForUser($userId) as $existing) {
            if ($existing['nombre_normalizado'] === $normalized && (int) $existing['id'] !== $id) {
                $this->merge($id, (int) $existing['id'], $userId);
                return;
            }
        }

        $repo->rename($id, $userId, $normalized);
        $this->replaceInTemplates($userId, $id, $id);
    }

    public function merge(int $sourceId, int $targetId, int $userId): void
    {
        if ($sourceId <= 0 || $targetId <= 0) {
            throw new InvalidArgumentException('El tag no existe.');
        }

        $repo = new TagRepository();
        $source = $repo->findForUser($sourceId, $userId);
        $target = $repo->findForUser($targetId, $userId);

        if ($source === null || $target === null) {
            throw new InvalidArgumentException('El tag no existe.');
        }

        if ($sourceId === $targetId) {
            return;
        }

        $repo->merge($sourceId, $targetId, $userId);
        $this->replaceInTemplates($userId, $sourceId, $targetId);
    }

    public function delete(int $id, int $userId): void
    {
        $repo = new TagRepository();

        if ($repo->findForUser($id, $userId) === null) {
            throw new InvalidArgumentException('El tag no existe.');
        }

        $repo->delete($id, $userId);
        $this->replaceInTemplates($userId, $id, null);
    }

    /**
     * Reemplaza un tag por otro (o lo elimina) en las plantillas.
     */
    private function replaceInTemplates(int $userId, int $sourceId, ?int $targetId): void
    {
        $templates = new PlantillaRepository();

        foreach ($templates->tagPayloadForUser($userId) as $template) {
            $raw = $template['tags_json'];
            $ids = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $ids = is_array($ids) ? array_map('intval', $ids) : [];
            $changed = false;
            $next = [];

            foreach ($ids as $id) {
                if ($id === $sourceId) {
                    $changed = true;

                    if ($targetId !== null && !in_array($targetId, $next, true)) {
                        $next[] = $targetId;
                    }

                    continue;
                }

                if (!in_array($id, $next, true)) {
                    $next[] = $id;
                }
            }

            if ($changed) {
                $templates->updateTagsJson((int) $template['id'], $next);
            }
        }
    }
}
