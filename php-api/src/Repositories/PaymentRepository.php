<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PaymentRepository extends BaseRepository
{
    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payment_attempts (
                id, user_id, user_email, plan_id, amount, currency,
                card_last4, card_brand, status, reason, metadata, created_at
             ) VALUES (
                :id, :user_id, :user_email, :plan_id, :amount, :currency,
                :card_last4, :card_brand, :status, :reason, :metadata, NOW()
             )'
        );

        $stmt->execute([
            ':id' => $data['id'],
            ':user_id' => $data['user_id'] ?? null,
            ':user_email' => $data['user_email'] ?? null,
            ':plan_id' => $data['plan_id'] ?? null,
            ':amount' => $data['amount'] ?? 0,
            ':currency' => $data['currency'] ?? 'MXN',
            ':card_last4' => $data['card_last4'] ?? '0000',
            ':card_brand' => $data['card_brand'] ?? 'SIMULATED',
            ':status' => $data['status'] ?? 'FAILED',
            ':reason' => $data['reason'] ?? null,
            ':metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        ]);

        $q = $this->pdo->prepare('SELECT * FROM payment_attempts WHERE id = :id LIMIT 1');
        $q->execute([':id' => $data['id']]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function countSuccessCurrentMonth(): int
    {
        $stmt = $this->pdo->query(
            'SELECT COUNT(*) FROM payment_attempts
             WHERE status = "SUCCESS"
               AND DATE_FORMAT(created_at, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m")'
        );

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function monthlyStats(int $months = 12): array
    {
        $months = max(1, min(36, $months));
        $sql = 'SELECT DATE_FORMAT(created_at, "%Y-%m") AS ym,
                       SUM(CASE WHEN status = "SUCCESS" THEN 1 ELSE 0 END) AS success_count,
                       SUM(CASE WHEN status = "FAILED" THEN 1 ELSE 0 END) AS failed_count
                FROM payment_attempts
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
                GROUP BY DATE_FORMAT(created_at, "%Y-%m")
                ORDER BY ym ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
