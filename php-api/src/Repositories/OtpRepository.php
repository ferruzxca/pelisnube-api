<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class OtpRepository extends BaseRepository
{
    public function invalidateOpenOtps(string $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE password_otps SET is_used = 1, verified_at = NOW()
             WHERE user_id = :user_id AND is_used = 0'
        );
        $stmt->execute([':user_id' => $userId]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_otps (
                id, user_id, code_hash, expires_at, attempts, max_attempts,
                is_used, created_at
             ) VALUES (
                :id, :user_id, :code_hash, :expires_at, 0, :max_attempts, 0, NOW()
             )'
        );
        $stmt->execute([
            ':id' => $data['id'],
            ':user_id' => $data['user_id'],
            ':code_hash' => $data['code_hash'],
            ':expires_at' => $data['expires_at'],
            ':max_attempts' => $data['max_attempts'] ?? 5,
        ]);

        $q = $this->pdo->prepare('SELECT * FROM password_otps WHERE id = :id LIMIT 1');
        $q->execute([':id' => $data['id']]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function latestOpenOtp(string $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM password_otps
             WHERE user_id = :user_id AND is_used = 0
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function incrementAttempts(string $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE password_otps SET attempts = attempts + 1 WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function markUsed(string $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE password_otps SET is_used = 1, verified_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }
}
