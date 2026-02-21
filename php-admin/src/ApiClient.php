<?php

declare(strict_types=1);

namespace Admin;

final class ApiClient
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $baseUrl = trim($baseUrl);
        $this->baseUrl = $baseUrl !== '' ? $baseUrl : 'http://localhost:8080/api/v1';
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    public function request(string $method, string $path, ?array $body = null, ?string $token = null, string $lang = 'es'): array
    {
        $urls = $this->candidateUrls($path);
        $lastError = null;

        foreach ($urls as $url) {
            $attempt = $this->rawRequest($method, $url, $body, $token, $lang);
            if (($attempt['transportError'] ?? false) === true) {
                $lastError = $attempt;
                continue;
            }

            $raw = (string) ($attempt['raw'] ?? '');
            $httpCode = (int) ($attempt['httpCode'] ?? 0);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $decoded['statusCode'] = $httpCode;
                $decoded['resolvedUrl'] = $url;
                return $decoded;
            }

            $lastError = [
                'success' => false,
                'message' => 'Invalid API response (HTTP ' . $httpCode . '). Verify API_BASE_URL and HTTPS.',
                'statusCode' => $httpCode,
                'data' => [],
                'errors' => [
                    'url' => $url,
                    'raw_snippet' => trim(substr($raw, 0, 220)),
                    'attempted_urls' => $urls,
                ],
            ];
        }

        if (is_array($lastError)) {
            return $lastError;
        }

        return [
            'success' => false,
            'message' => 'HTTP error: request could not be completed.',
            'statusCode' => 0,
            'data' => [],
            'errors' => [
                'attempted_urls' => $urls,
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function rawRequest(string $method, string $url, ?array $body, ?string $token, string $lang): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'transportError' => true,
                'success' => false,
                'message' => 'HTTP error: curl_init failed.',
                'statusCode' => 0,
                'data' => [],
                'errors' => ['url' => $url],
            ];
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Accept-Language: ' . $lang,
        ];

        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return [
                'transportError' => true,
                'success' => false,
                'message' => 'HTTP error: ' . $err,
                'statusCode' => 0,
                'data' => [],
                'errors' => [
                    'url' => $url,
                ],
            ];
        }

        return [
            'transportError' => false,
            'raw' => $raw,
            'httpCode' => $httpCode,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function candidateUrls(string $path): array
    {
        $path = ltrim($path, '/');
        $base = rtrim($this->baseUrl, '/');
        $urls = [];

        $parts = parse_url($base);
        if (is_array($parts)) {
            $query = [];
            if (isset($parts['query'])) {
                parse_str((string) $parts['query'], $query);
            }
            $routeKey = array_key_exists('route', $query) ? 'route' : (array_key_exists('r', $query) ? 'r' : null);
            if ($routeKey !== null) {
                $route = trim((string) ($query[$routeKey] ?? ''));
                $route = $route === '' ? '/api/v1' : '/' . ltrim($route, '/');
                if (!preg_match('#/api/v[0-9]+#', $route)) {
                    $route = rtrim($route, '/') . '/api/v1';
                }
                $query[$routeKey] = rtrim($route, '/') . '/' . $path;
                $this->addUrl($urls, $this->buildUrl($parts, $query));

                $alt = $query;
                if ($routeKey === 'route') {
                    unset($alt['route']);
                    $alt['r'] = $query[$routeKey];
                } else {
                    unset($alt['r']);
                    $alt['route'] = $query[$routeKey];
                }
                $this->addUrl($urls, $this->buildUrl($parts, $alt));

                $baseNoQuery = $this->stripQueryAndFragment($base);
                $root = (string) preg_replace('#/index\.php$#', '', $baseNoQuery);
                $root = rtrim($root, '/');
                if ($root !== '' && preg_match('#/api/v[0-9]+$#', $root) !== 1) {
                    $this->addUrl($urls, $root . '/api/v1/' . $path);
                }

                return $urls;
            }
        }

        $baseNoQuery = rtrim($this->stripQueryAndFragment($base), '/');
        $hasApiVersion = preg_match('#/api/v[0-9]+(?:$|/)#', $baseNoQuery) === 1;
        $endsWithIndex = str_ends_with($baseNoQuery, '/index.php');

        if ($hasApiVersion) {
            $this->addUrl($urls, $baseNoQuery . '/' . $path);
        } else {
            $this->addUrl($urls, $baseNoQuery . '/api/v1/' . $path);
        }

        $root = $baseNoQuery;
        if ($endsWithIndex) {
            $root = substr($baseNoQuery, 0, -strlen('/index.php'));
        }
        $root = rtrim($root, '/');
        if ($root !== '') {
            $routeValue = '/api/v1/' . $path;
            $this->addUrl($urls, $root . '/index.php?route=' . rawurlencode($routeValue));
            $this->addUrl($urls, $root . '/index.php?r=' . rawurlencode($routeValue));
        }

        if (!$hasApiVersion) {
            $this->addUrl($urls, $baseNoQuery . '/' . $path);
        }

        return $urls;
    }

    private function stripQueryAndFragment(string $url): string
    {
        $main = explode('#', $url, 2)[0];
        return explode('?', $main, 2)[0];
    }

    /**
     * @param array<string,mixed> $parts
     * @param array<string,mixed> $query
     */
    private function buildUrl(array $parts, array $query): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = (string) ($parts['user'] ?? '');
        $pass = (string) ($parts['pass'] ?? '');
        $auth = $user !== '' ? $user . ($pass !== '' ? ':' . $pass : '') . '@' : '';
        $path = (string) ($parts['path'] ?? '');
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $scheme . $auth . $host . $port . $path . ($queryString !== '' ? '?' . $queryString : '');
    }

    /**
     * @param array<int,string> $urls
     */
    private function addUrl(array &$urls, string $url): void
    {
        $url = trim($url);
        if ($url === '' || in_array($url, $urls, true)) {
            return;
        }
        $urls[] = $url;
    }
}
