<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array<string, mixed>> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, array $middlewares = []): void
    {
        $method = strtoupper($method);
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $params = $this->match($route['pattern'], $request->path());
            if ($params === null) {
                continue;
            }

            $request->setRouteParams($params);

            foreach ($route['middlewares'] as $middleware) {
                $middleware($request);
            }

            ($route['handler'])($request);
            return;
        }

        throw new HttpException(404, I18n::t('not_found', $request->lang()));
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $pattern, string $path): ?array
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts = explode('/', trim($path, '/'));

        if (count($patternParts) !== count($pathParts)) {
            return null;
        }

        $params = [];

        foreach ($patternParts as $index => $part) {
            $pathPart = $pathParts[$index] ?? '';
            if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $part, $matches)) {
                $params[$matches[1]] = urldecode($pathPart);
                continue;
            }

            if ($part !== $pathPart) {
                return null;
            }
        }

        return $params;
    }
}
