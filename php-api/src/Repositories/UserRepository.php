<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (id, name, email, password_hash, role, status, is_active, preferred_lang, must_change_password, created_at, updated_at)
             VALUES (:id, :name, :email, :password_hash, :role, :status, :is_active, :preferred_lang, :must_change_password, NOW(), NOW())'
        );

        $stmt->execute([
            ':id' => $data['id'],
            ':name' => trim((string) $data['name']),
            ':email' => strtolower(trim((string) $data['email'])),
            ':password_hash' => (string) $data['password_hash'],
            ':role' => $data['role'] ?? 'USER',
            ':status' => $data['status'] ?? 'ACTIVE',
            ':is_active' => $data['is_active'] ?? 1,
            ':preferred_lang' => $data['preferred_lang'] ?? 'es',
            ':must_change_password' => $data['must_change_password'] ?? 0,
        ]);

        return $this->findById((string) $data['id']) ?? [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): ?array
    {
        $allowed = ['name', 'role', 'status', 'is_active', 'preferred_lang', 'must_change_password'];
        $setParts = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $setParts[] = $field . ' = :' . $field;
            $params[':' . $field] = $data[$field];
        }

        if ($setParts === []) {
            return $this->findById($id);
        }

        $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ', updated_at = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->findById($id);
    }

    public function updatePassword(string $id, string $passwordHash, bool $mustChangePassword = false): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash, must_change_password = :must_change_password, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':password_hash' => $passwordHash,
            ':must_change_password' => $mustChangePassword ? 1 : 0,
        ]);
    }

    public function inactivate(string $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET is_active = 0, status = "INACTIVE", deactivated_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return array{items: array<int, array<string,mixed>>, total: int}
     */
    public function paginated(int $page, int $pageSize, ?string $search = null, ?string $role = null, ?string $status = null): array
    {
        $pagination = $this->pagination($page, $pageSize);

        $where = ['1=1'];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where[] = '(name LIKE :search OR email LIKE :search)';
            $params[':search'] = '%' . trim($search) . '%';
        }

        if ($role !== null && $role !== '') {
            $where[] = 'role = :role';
            $params[':role'] = $role;
        }

        if ($status !== null && $status !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = 'SELECT COUNT(*) AS total FROM users WHERE ' . $whereSql;
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $sql = 'SELECT id, name, email, role, status, is_active, preferred_lang, must_change_password, created_at, updated_at
                FROM users
                WHERE ' . $whereSql . '
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array<string, int>
     */
    public function countByRoleActive(): array
    {
        $stmt = $this->pdo->query(
            'SELECT role, COUNT(*) AS total FROM users WHERE is_active = 1 GROUP BY role'
        );

        $result = [
            'SUPER_ADMIN' => 0,
            'ADMIN' => 0,
            'USER' => 0,
        ];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['role']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function monthlyUsers(int $months = 12): array
    {
        $months = max(1, min(36, $months));
        $sql = 'SELECT DATE_FORMAT(created_at, "%Y-%m") AS ym, COUNT(*) AS total
                FROM users
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
                GROUP BY DATE_FORMAT(created_at, "%Y-%m")
                ORDER BY ym ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':months', $months, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
