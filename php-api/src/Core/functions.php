<?php

declare(strict_types=1);

if (!function_exists('uuidv4')) {
    function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('now_utc')) {
    function now_utc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = trim(mb_strtolower($text, 'UTF-8'));
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
        $text = trim($text, '-');
        return $text !== '' ? $text : 'item';
    }
}

if (!function_exists('extract_youtube_video_id')) {
    function extract_youtube_video_id(string $url): ?string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        if ($host === 'youtu.be') {
            $path = trim((string) ($parts['path'] ?? ''), '/');
            return $path !== '' ? $path : null;
        }

        if (str_contains($host, 'youtube.com')) {
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $queryParams);
                if (!empty($queryParams['v'])) {
                    return (string) $queryParams['v'];
                }
            }
            $path = trim((string) ($parts['path'] ?? ''), '/');
            if (str_starts_with($path, 'embed/')) {
                return substr($path, 6) ?: null;
            }
        }

        return null;
    }
}

if (!function_exists('youtube_embed_url')) {
    function youtube_embed_url(string $watchUrl): ?string
    {
        $videoId = extract_youtube_video_id($watchUrl);
        if (!$videoId) {
            return null;
        }

        return 'https://www.youtube.com/embed/' . rawurlencode($videoId);
    }
}
