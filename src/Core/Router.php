<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Einfacher Router: Muster wie "/admin/gutscheine/{id}". Parameter werden als Strings übergeben.
 */
final class Router
{
    /** @var list<array{string, string, callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    public function dispatch(Request $request): void
    {
        $allowed = false;
        foreach ($this->routes as [$method, $pattern, $handler]) {
            $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[A-Za-z0-9_-]+)', $pattern) . '$#';
            if (!preg_match($regex, $request->path, $matches)) {
                continue;
            }
            $allowed = true;
            if ($method !== $request->method) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler($request, ...array_values($params));
            return;
        }
        if ($allowed) {
            Response::error(405, 'Diese Aktion ist hier nicht erlaubt.');
        }
        Response::error(404, 'Diese Seite gibt es nicht.');
    }
}
