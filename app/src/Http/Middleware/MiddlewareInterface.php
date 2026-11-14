<?php

declare(strict_types=1);

namespace Cauce\Http\Middleware;

interface MiddlewareInterface
{
    /**
     * Devuelve false para detener la petición (por ejemplo, tras redirigir).
     */
    public function handle(): bool;
}
