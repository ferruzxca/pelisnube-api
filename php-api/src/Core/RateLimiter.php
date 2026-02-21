<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class RateLimiter
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{allowed: bool, retryAfter: int}
     */
    public function hit(string $actionKey, string $subject, int $limit, int $windowSeconds): array
    {
        $now = time();
        $windowStart = $now - $windowSeconds;

        $stmt = $this->pdo->prepare(
            'SELECT id, attempt_count, UNIX_TIMESTAMP(window_start) AS window_start_ts
             FROM rate_limits WHERE action_key = :action_key AND subject = :subject LIMIT 1'
        );
        $stmt->execute([
            ':action_key' => $actionKey,
            ':subject' => $subject,
        ]);
        $row = $stmt->fetch();

        if (!$row) {
            $insert = $this->pdo->prepare(
                'INSERT INTO rate_limits (action_key, subject, attempt_count, window_start, updated_at)
                 VALUES (:action_key, :subject, 1, NOW(), NOW())'
            );
            $insert->execute([
                ':action_key' => $actionKey,
                ':subject' => $subject,
            ]);

            return ['allowed' => true, 'retryAfter' => 0];
        }

        $count = (int) $row['attempt_count'];
        $startTs = (int) $row['window_start_ts'];

        if ($startTs < $windowStart) {
            $reset = $this->pdo->prepare(
                'UPDATE rate_limits
                 SET attempt_count = 1, window_start = NOW(), updated_at = NOW()
                 WHERE id = :id'
            );
            $reset->execute([':id' => $row['id']]);
            return ['allowed' => true, 'retryAfter' => 0];
        }

        if ($count >= $limit) {
            $retryAfter = max(1, ($startTs + $windowSeconds) - $now);
            return ['allowed' => false, 'retryAfter' => $retryAfter];
        }

        $update = $this->pdo->prepare(
            'UPDATE rate_limits SET attempt_count = attempt_count + 1, updated_at = NOW() WHERE id = :id'
        );
        $update->execute([':id' => $row['id']]);

        return ['allowed' => true, 'retryAfter' => 0];
    }
}
