<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuditRepository extends BaseRepository
{
    /**
     * @param array<string,mixed> $data
     */
    public function log(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (
                actor_user_id, actor_role, target_type, target_id, action,
                before_state, after_state, ip_address, user_agent, created_at
             ) VALUES (
                :actor_user_id, :actor_role, :target_type, :target_id, :action,
                :before_state, :after_state, :ip_address, :user_agent, NOW()
             )'
        );

        $stmt->execute([
            ':actor_user_id' => $data['actor_user_id'] ?? null,
            ':actor_role' => $data['actor_role'] ?? null,
            ':target_type' => $data['target_type'] ?? '',
            ':target_id' => $data['target_id'] ?? null,
            ':action' => $data['action'] ?? '',
            ':before_state' => isset($data['before_state']) ? json_encode($data['before_state']) : null,
            ':after_state' => isset($data['after_state']) ? json_encode($data['after_state']) : null,
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
        ]);
    }

    /**
     * @return array{items: array<int, array<string,mixed>>, total:int}
     */
    public function paginated(
        int $page,
        int $pageSize,
        ?string $targetType = null,
        ?string $action = null,
        ?string $actorUserId = null,
        ?string $from = null,
        ?string $to = null
    ): array {
        $pagination = $this->pagination($page, $pageSize);

        $where = ['1=1'];
        $params = [];

        if ($targetType !== null && trim($targetType) !== '') {
            $where[] = 'target_type = :target_type';
            $params[':target_type'] = $targetType;
        }

        if ($action !== null && trim($action) !== '') {
            $where[] = 'action = :action';
            $params[':action'] = $action;
        }

        if ($actorUserId !== null && trim($actorUserId) !== '') {
            $where[] = 'actor_user_id = :actor_user_id';
            $params[':actor_user_id'] = $actorUserId;
        }

        if ($from !== null && trim($from) !== '') {
            $where[] = 'created_at >= :from_date';
            $params[':from_date'] = $from;
        }

        if ($to !== null && trim($to) !== '') {
            $where[] = 'created_at <= :to_date';
            $params[':to_date'] = $to;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE ' . $whereSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $sql = 'SELECT * FROM audit_logs
                WHERE ' . $whereSql . '
                ORDER BY id DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = array_map(static function (array $row): array {
            $row['before_state'] = $row['before_state'] ? json_decode((string) $row['before_state'], true) : null;
            $row['after_state'] = $row['after_state'] ? json_decode((string) $row['after_state'], true) : null;
            return $row;
        }, $rows);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    public function addHistory(string $table, string $entityId, string $action, array $snapshot, ?string $actorUserId = null): void
    {
        $allowed = ['user_history', 'content_history', 'section_history', 'plan_history', 'subscription_history'];
        if (!in_array($table, $allowed, true)) {
            return;
        }

        $entityFieldMap = [
            'user_history' => 'user_id',
            'content_history' => 'content_id',
            'section_history' => 'section_id',
            'plan_history' => 'plan_id',
            'subscription_history' => 'subscription_id',
        ];

        $entityField = $entityFieldMap[$table];

        $sql = sprintf(
            'INSERT INTO %s (%s, action, snapshot, actor_user_id, created_at)
             VALUES (:entity_id, :action, :snapshot, :actor_user_id, NOW())',
            $table,
            $entityField
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':entity_id' => $entityId,
            ':action' => $action,
            ':snapshot' => json_encode($snapshot),
            ':actor_user_id' => $actorUserId,
        ]);
    }
}
