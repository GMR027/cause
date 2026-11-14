<?php

declare(strict_types=1);

namespace Cauce\Tests\Unit;

use Cauce\Services\Navigation;
use PHPUnit\Framework\TestCase;

final class NavigationTest extends TestCase
{
    public function test_only_the_most_specific_route_is_active(): void
    {
        $active = $this->activeRoutes('/ajustes/seguridad');

        self::assertSame(['/ajustes/seguridad'], $active);
    }

    public function test_parent_stays_active_for_child_without_its_own_entry(): void
    {
        $active = $this->activeRoutes('/ajustes/perfil');

        self::assertSame(['/ajustes'], $active);
    }

    public function test_admin_root_is_exact_and_does_not_override_children(): void
    {
        self::assertSame(['/admin'], $this->activeRoutes('/admin'));
        self::assertSame(['/admin/usuarios'], $this->activeRoutes('/admin/usuarios'));
    }

    public function test_every_navigation_route_has_exactly_one_active_item(): void
    {
        foreach ($this->routeList() as $route) {
            self::assertSame([$route], $this->activeRoutes($route), 'Ruta: ' . $route);
        }
    }

    /**
     * @return list<string>
     */
    private function activeRoutes(string $path): array
    {
        $active = [];

        foreach (Navigation::groups(['rol' => 'admin'], $path) as $group) {
            foreach ($group['items'] as $item) {
                if (!empty($item['active'])) {
                    $active[] = (string) $item['route'];
                }
            }
        }

        return $active;
    }

    /**
     * @return list<string>
     */
    private function routeList(): array
    {
        $routes = [];

        foreach (Navigation::groups(['rol' => 'admin'], '/inicio') as $group) {
            foreach ($group['items'] as $item) {
                $routes[] = (string) $item['route'];
            }
        }

        return $routes;
    }
}
