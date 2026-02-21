<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PlanRepository extends BaseRepository
{
    /**
     * @return array<string,mixed>|null
     */
    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_plans WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_plans WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO subscription_plans (
                id, code, name, price_monthly, currency, quality, screens,
                is_active, created_at, updated_at
            ) VALUES (
                :id, :code, :name, :price_monthly, :currency, :quality, :screens,
                :is_active, NOW(), NOW()
            )'
        );

        $stmt->execute([
            ':id' => $data['id'],
            ':code' => strtoupper((string) $data['code']),
            ':name' => $data['name'],
            ':price_monthly' => $data['price_monthly'],
            ':currency' => $data['currency'],
            ':quality' => $data['quality'],
            ':screens' => (int) $data['screens'],
            ':is_active' => (int) ($data['is_active'] ?? 1),
        ]);

        return $this->findById((string) $data['id']) ?? [];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(string $id, array $data): ?array
    {
        $allowed = ['code', 'name', 'price_monthly', 'currency', 'quality', 'screens', 'is_active'];
        $params = [':id' => $id];
        $set = [];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $set[] = $field . ' = :' . $field;
            $value = $data[$field];
            if ($field === 'code') {
                $value = strtoupper((string) $value);
            }
            $params[':' . $field] = $value;
        }

        if ($set === []) {
            return $this->findById($id);
        }

        $sql = 'UPDATE subscription_plans SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->findById($id);
    }

    public function inactivate(string $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE subscription_plans SET is_active = 0, deactivated_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return array{items: array<int, array<string,mixed>>, total:int}
     */
    public function paginated(int $page, int $pageSize, ?string $search = null): array
    {
        $pagination = $this->pagination($page, $pageSize);
        $where = ['1=1'];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where[] = '(name LIKE :search OR code LIKE :search OR quality LIKE :search)';
            $params[':search'] = '%' . trim($search) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM subscription_plans WHERE ' . $whereSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $sql = 'SELECT * FROM subscription_plans WHERE ' . $whereSql . ' ORDER BY price_monthly ASC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['items' => array_map([$this, 'normalizePlan'], $items), 'total' => $total];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function normalizePlan(array $row): array
    {
        return [
            'id' => $row['id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'priceMonthly' => (float) $row['price_monthly'],
            'currency' => $row['currency'],
            'quality' => $row['quality'],
            'screens' => (int) $row['screens'],
            'isActive' => (int) $row['is_active'] === 1,
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }
}
