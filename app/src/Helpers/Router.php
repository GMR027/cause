<?php

declare(strict_types=1);

namespace Cauce\Helpers;

/**
 * Router mínimo con middleware y grupos de prefijo.
 *
 * Soporta rutas exactas por método HTTP y un 404/405 controlado. No incluye
 * parámetros dinámicos todavía.
 */
final class Router
{
    /** @var array<string, array<string, array{handler: callable|array{class-string, string}, middleware: list<class-string>}>> */
    private array $routes = [];

    private string $prefix = '';

    /** @var list<class-string> */
    private array $groupMiddleware = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * @param list<class-string> $middleware
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->prefix = $this->join($this->prefix, $prefix);
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $path = $this->normalize($path);

        $route = $this->routes[$method][$path] ?? null;

        if ($route === null) {
            $allowed = $this->allowedMethods($path);

            if ($allowed !== []) {
                Logger::warning('Método HTTP no permitido', ['method' => $method, 'path' => $path]);

                http_response_code(405);
                header('Allow: ' . implode(', ', $allowed));
                echo View::render('errors/405', ['title' => 'Método no permitido']);

                return;
            }

            Logger::warning('Ruta no encontrada', ['method' => $method, 'path' => $path]);

            http_response_code(404);
            echo View::render('errors/404', ['title' => 'Página no encontrada', 'path' => $path]);

            return;
        }

        foreach ($route['middleware'] as $middlewareClass) {
            /** @var object{handle: callable} $middleware */
            $middleware = new $middlewareClass();

            if ($middleware->handle() === false) {
                return;
            }
        }

        $handler = $route['handler'];

        if (is_array($handler)) {
            [$class, $action] = $handler;
            $controller = new $class();
            $controller->{$action}();

            return;
        }

        $handler();
    }

    /**
     * @param list<class-string> $middleware
     */
    private function add(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $fullPath = $this->normalize($this->join($this->prefix, $path));

        $this->routes[strtoupper($method)][$fullPath] = [
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedMethods(string $path): array
    {
        $allowed = [];

        foreach ($this->routes as $method => $routes) {
            if (isset($routes[$path])) {
                $allowed[] = $method;
            }
        }

        return $allowed;
    }

    private function join(string $prefix, string $path): string
    {
        $prefix = trim($prefix, '/');
        $path = trim($path, '/');

        if ($prefix === '') {
            return $path === '' ? '/' : $path;
        }

        return $path === '' ? $prefix : $prefix . '/' . $path;
    }

    private function normalize(string $path): string
    {
        $trimmed = trim($path, '/');

        return $trimmed === '' ? '/' : '/' . $trimmed;
    }
}
