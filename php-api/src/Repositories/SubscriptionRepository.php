<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SubscriptionRepository extends BaseRepository
{
    /**
     * @return array<string,mixed>|null
     */
    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscriptions WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByUserId(string $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscriptions WHERE user_id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO subscriptions (
                id, user_id, plan_id, status, started_at, renewal_at, ended_at,
                is_active, created_at, updated_at
            ) VALUES (
                :id, :user_id, :plan_id, :status, :started_at, :renewal_at, :ended_at,
                :is_active, NOW(), NOW()
            )'
        );

        $stmt->execute([
            ':id' => $data['id'],
            ':user_id' => $data['user_id'],
            ':plan_id' => $data['plan_id'],
            ':status' => $data['status'] ?? 'ACTIVE',
            ':started_at' => $data['started_at'] ?? now_utc(),
            ':renewal_at' => $data['renewal_at'],
            ':ended_at' => $data['ended_at'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
        ]);

        return $this->findById((string) $data['id']) ?? [];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(string $id, array $data): ?array
    {
        $allowed = ['plan_id', 'status', 'started_at', 'renewal_at', 'ended_at', 'is_active'];
        $set = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $set[] = $field . ' = :' . $field;
            $params[':' . $field] = $data[$field];
        }

        if ($set === []) {
            return $this->findById($id);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE subscriptions SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute($params);

        return $this->findById($id);
    }

    public function inactivate(string $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE subscriptions SET is_active = 0, deactivated_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function userSubscriptionDetail(string $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, p.code, p.name AS plan_name, p.price_monthly, p.currency, p.quality, p.screens
             FROM subscriptions s
             INNER JOIN subscription_plans p ON p.id = s.plan_id
             WHERE s.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->normalizeSubscriptionWithPlan($row);
    }

    /**
     * @return array{items: array<int, array<string,mixed>>, total:int}
     */
    public function paginatedWithDetails(int $page, int $pageSize, ?string $status = null): array
    {
        $pagination = $this->pagination($page, $pageSize);
        $where = ['1=1'];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[] = 's.status = :status';
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM subscriptions s WHERE ' . $whereSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $sql = 'SELECT s.*, u.name AS user_name, u.email AS user_email, u.role AS user_role,
                       p.code AS plan_code, p.name AS plan_name, p.price_monthly, p.currency
                FROM subscriptions s
                INNER JOIN users u ON u.id = s.user_id
                INNER JOIN subscription_plans p ON p.id = s.plan_id
                WHERE ' . $whereSql . '
                ORDER BY s.updated_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => $row['id'],
                'status' => $row['status'],
                'isActive' => (int) $row['is_active'] === 1,
                'startedAt' => $row['started_at'],
                'renewalAt' => $row['renewal_at'],
                'endedAt' => $row['ended_at'],
                'user' => [
                    'id' => $row['user_id'],
                    'name' => $row['user_name'],
                    'email' => $row['user_email'],
                    'role' => $row['user_role'],
                ],
                'plan' => [
                    'id' => $row['plan_id'],
                    'code' => $row['plan_code'],
                    'name' => $row['plan_name'],
                    'priceMonthly' => (float) $row['price_monthly'],
                    'currency' => $row['currency'],
                ],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at'],
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    public function countActive(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM subscriptions WHERE status = "ACTIVE" AND is_active = 1');
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function statusCounts(): array
    {
        $stmt = $this->pdo->query('SELECT status, COUNT(*) AS total FROM subscriptions GROUP BY status');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function dueForRenewal(): array
    {
        $stmt = $this->pdo->query(
            'SELECT s.*, u.is_active AS user_active, u.status AS user_status, p.is_active AS plan_active
             FROM subscriptions s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN subscription_plans p ON p.id = s.plan_id
             WHERE s.status = "ACTIVE" AND s.is_active = 1 AND s.renewal_at <= NOW()'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeSubscriptionWithPlan(array $row): array
    {
        return [
            'id' => $row['id'],
            'status' => $row['status'],
            'startedAt' => $row['started_at'],
            'renewalAt' => $row['renewal_at'],
            'endedAt' => $row['ended_at'],
            'isActive' => (int) $row['is_active'] === 1,
            'plan' => [
                'id' => $row['plan_id'],
                'code' => $row['code'],
                'name' => $row['plan_name'],
                'priceMonthly' => (float) $row['price_monthly'],
                'currency' => $row['currency'],
                'quality' => $row['quality'],
                'screens' => (int) $row['screens'],
            ],
        ];
    }
}
