<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function success(string $message, ?array $data = null, int $statusCode = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data ?? new \stdClass(),
        ], $statusCode);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function error(string $message, int $statusCode, array $details = []): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $details,
        ], $statusCode);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public static function paginated(string $message, array $items, int $page, int $pageSize, int $total): void
    {
        self::success($message, [
            'items' => $items,
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ]);
    }
}
