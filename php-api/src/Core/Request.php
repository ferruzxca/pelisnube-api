<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private string $method;
    private string $path;
    /** @var array<string, string> */
    private array $query;
    /** @var array<string, mixed> */
    private array $body;
    /** @var array<string, string> */
    private array $headers;
    /** @var array<string, string> */
    private array $routeParams = [];
    /** @var array<string, mixed>|null */
    private ?array $user = null;
    private string $lang;

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $query
     * @param array<string, mixed> $body
     */
    private function __construct(string $method, string $path, array $headers, array $query, array $body)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->headers = $headers;
        $this->query = $query;
        $this->body = $body;
        $this->lang = I18n::resolveLanguage($headers['accept-language'] ?? null);
    }

    public static function fromGlobals(): self
    {
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $parsed = parse_url($uri);
        $path = $parsed['path'] ?? '/';

        // Fallback path selector for hosts where rewrite rules are not available.
        // Example: /index.php?route=/api/v1/health
        $routeOverride = $_GET['route'] ?? $_GET['r'] ?? null;
        if (is_string($routeOverride) && $routeOverride !== '') {
            $path = str_starts_with($routeOverride, '/')
                ? $routeOverride
                : '/' . $routeOverride;
        }

        $rawHeaders = function_exists('getallheaders') ? getallheaders() : [];
        $headers = [];
        foreach ($rawHeaders as $name => $value) {
            $headers[strtolower((string) $name)] = (string) $value;
        }

        $query = [];
        foreach ($_GET as $key => $value) {
            $query[(string) $key] = is_array($value) ? '' : (string) $value;
        }

        $body = [];
        $contentType = strtolower((string) ($headers['content-type'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $rawBody = file_get_contents('php://input') ?: '';
            if ($rawBody !== '') {
                $decoded = json_decode($rawBody, true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }
        } else {
            foreach ($_POST as $key => $value) {
                $body[(string) $key] = $value;
            }
        }

        return new self($method, $path, $headers, $query, $body);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, string>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->body;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function queryParam(string $key, ?string $default = null): ?string
    {
        return $this->query[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization');
        if (!$header) {
            return null;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    /**
     * @param array<string, string> $params
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function routeParam(string $name): ?string
    {
        return $this->routeParams[$name] ?? null;
    }

    /**
     * @param array<string, mixed> $user
     */
    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        return $this->user;
    }

    public function lang(): string
    {
        return $this->lang;
    }

    public function ip(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            return trim($parts[0]);
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    }
}
