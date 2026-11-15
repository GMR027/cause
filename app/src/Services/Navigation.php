<?php

declare(strict_types=1);

namespace Cauce\Services;

/**
 * Construye la navegación lateral y del dock desde app/config/navigation.php.
 */
final class Navigation
{
    /**
     * @param array<string, mixed>|null $user
     * @return list<array<string, mixed>>
     */
    public static function groups(?array $user, string $currentPath): array
    {
        $groups = config('navigation.groups', []);

        if (!is_array($groups)) {
            return [];
        }

        $isAdmin = $user !== null && ($user['rol'] ?? 'user') === 'admin';
        $result = [];
        $routes = [];

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            if (!empty($group['admin']) && !$isAdmin) {
                continue;
            }

            $items = [];

            foreach ((array) ($group['items'] ?? []) as $item) {
                if (!is_array($item) || !isset($item['route'])) {
                    continue;
                }

                if (!empty($item['desktop_only']) && \Cauce\Helpers\Client::isMobile()) {
                    continue;
                }

                if (!empty($item['requires_category']) && !self::hasCategoryType($user, (string) $item['requires_category'])) {
                    continue;
                }

                $item['active'] = false;
                $items[] = $item;
                $routes[] = (string) $item['route'];
            }

            if ($items === []) {
                continue;
            }

            $group['items'] = $items;
            $result[] = $group;
        }

        // Solo la ruta más específica queda activa. Así /ajustes/tags no marca
        // también /ajustes, pero /ajustes/perfil (sin entrada propia) sí marca
        // Ajustes.
        $activeRoute = self::activeRoute($routes, $currentPath);

        foreach ($result as &$group) {
            foreach ($group['items'] as &$item) {
                $item['active'] = $activeRoute !== null
                    && (string) $item['route'] === $activeRoute;
            }
        }
        unset($group, $item);

        return $result;
    }

    /**
     * @param list<string> $routes
     */
    public static function activeRoute(array $routes, string $currentPath): ?string
    {
        $active = null;
        $longest = -1;

        foreach (array_unique($routes) as $route) {
            $route = (string) $route;

            if (!self::isActive($route, $currentPath)) {
                continue;
            }

            $length = strlen(rtrim($route, '/'));

            if ($length > $longest || ($length === $longest && $route === $currentPath)) {
                $longest = $length;
                $active = $route;
            }
        }

        return $active;
    }

    public static function isActive(string $route, string $currentPath): bool
    {
        if ($route === '/admin') {
            return $currentPath === '/admin';
        }

        return $currentPath === $route
            || str_starts_with($currentPath, rtrim($route, '/') . '/');
    }

    /**
     * ¿El método activo del usuario tiene una categoría de ese tipo con %?
     * Se usa para mostrar secciones que solo aplican a ciertos métodos.
     *
     * @param array<string, mixed>|null $user
     */
    public static function hasCategoryType(?array $user, string $type): bool
    {
        if ($user === null || !isset($user['id'])) {
            return false;
        }

        static $cache = [];
        $key = (int) $user['id'] . ':' . $type;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $data = (new MethodService())->activeData($user);
        } catch (\Throwable) {
            return $cache[$key] = false;
        }

        if ($data === null) {
            return $cache[$key] = false;
        }

        foreach ($data['method']['categorias'] as $category) {
            if ((string) ($category['tipo'] ?? '') === $type
                && (float) ($data['percentages'][(string) $category['nombre']] ?? 0) > 0.001) {
                return $cache[$key] = true;
            }
        }

        return $cache[$key] = false;
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function isAdmin(array $user): bool
    {
        return ($user['rol'] ?? 'user') === 'admin';
    }
}
