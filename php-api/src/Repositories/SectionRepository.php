<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SectionRepository extends BaseRepository
{
    /**
     * @return array<string,mixed>|null
     */
    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sections WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByKey(string $sectionKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sections WHERE section_key = :section_key LIMIT 1');
        $stmt->execute([':section_key' => $sectionKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sections (id, section_key, name, description, sort_order, is_home_visible, is_active, created_at, updated_at)
             VALUES (:id, :section_key, :name, :description, :sort_order, :is_home_visible, :is_active, NOW(), NOW())'
        );

        $stmt->execute([
            ':id' => $data['id'],
            ':section_key' => $data['section_key'],
            ':name' => $data['name'],
            ':description' => $data['description'],
            ':sort_order' => (int) ($data['sort_order'] ?? 0),
            ':is_home_visible' => (int) ($data['is_home_visible'] ?? 1),
            ':is_active' => (int) ($data['is_active'] ?? 1),
        ]);

        return $this->findById((string) $data['id']) ?? [];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(string $id, array $data): ?array
    {
        $allowed = ['section_key', 'name', 'description', 'sort_order', 'is_home_visible', 'is_active'];
        $params = [':id' => $id];
        $set = [];

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

        $stmt = $this->pdo->prepare('UPDATE sections SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = :id');
        $stmt->execute($params);

        return $this->findById($id);
    }

    public function inactivate(string $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE sections SET is_active = 0, deactivated_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * @param array<int, string> $contentIds
     */
    public function syncContents(string $sectionId, array $contentIds): void
    {
        $disable = $this->pdo->prepare('UPDATE content_sections SET is_active = 0, updated_at = NOW() WHERE section_id = :section_id');
        $disable->execute([':section_id' => $sectionId]);

        $unique = array_values(array_unique(array_filter($contentIds)));
        foreach ($unique as $idx => $contentId) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO content_sections (content_id, section_id, sort_order, is_active, created_at, updated_at)
                 VALUES (:content_id, :section_id, :sort_order, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), is_active = 1, updated_at = NOW()'
            );
            $stmt->execute([
                ':content_id' => $contentId,
                ':section_id' => $sectionId,
                ':sort_order' => $idx,
            ]);
        }
    }

    /**
     * @return array{items: array<int, array<string,mixed>>, total:int}
     */
    public function listAdmin(int $page, int $pageSize, ?string $search = null): array
    {
        $pagination = $this->pagination($page, $pageSize);
        $where = ['1=1'];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where[] = '(name LIKE :search OR section_key LIKE :search OR description LIKE :search)';
            $params[':search'] = '%' . trim($search) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM sections WHERE ' . $whereSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $sql = 'SELECT * FROM sections WHERE ' . $whereSql . ' ORDER BY sort_order ASC, name ASC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['items' => array_map([$this, 'normalizeSection'], $items), 'total' => $total];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function homeSections(): array
    {
        $sql = 'SELECT * FROM sections WHERE is_home_visible = 1 AND is_active = 1 ORDER BY sort_order ASC, name ASC';
        $stmt = $this->pdo->query($sql);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($sections === []) {
            return [];
        }

        $result = [];

        foreach ($sections as $section) {
            $itemsStmt = $this->pdo->prepare(
                'SELECT c.* , cs.sort_order AS section_sort_order
                 FROM content_sections cs
                 INNER JOIN contents c ON c.id = cs.content_id
                 WHERE cs.section_id = :section_id AND cs.is_active = 1 AND c.is_active = 1
                 ORDER BY cs.sort_order ASC'
            );
            $itemsStmt->execute([':section_id' => $section['id']]);
            $contents = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $mappedItems = [];
            foreach ($contents as $content) {
                $mappedItems[] = [
                    'id' => $content['id'],
                    'title' => $content['title'],
                    'slug' => $content['slug'],
                    'type' => $content['type'],
                    'synopsis' => $content['synopsis'],
                    'year' => (int) $content['year'],
                    'duration' => (int) $content['duration'],
                    'rating' => (float) $content['rating'],
                    'trailerWatchUrl' => $content['trailer_watch_url'],
                    'trailerEmbedUrl' => $content['trailer_embed_url'],
                    'posterUrl' => $content['poster_url'],
                    'bannerUrl' => $content['banner_url'],
                    'sortOrder' => (int) $content['section_sort_order'],
                ];
            }

            $normalized = $this->normalizeSection($section);
            $normalized['items'] = $mappedItems;
            $result[] = $normalized;
        }

        return $result;
    }

    public function countActive(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM sections WHERE is_active = 1');
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function normalizeSection(array $row): array
    {
        return [
            'id' => $row['id'],
            'key' => $row['section_key'],
            'name' => $row['name'],
            'description' => $row['description'],
            'sortOrder' => (int) $row['sort_order'],
            'isHomeVisible' => (int) $row['is_home_visible'] === 1,
            'isActive' => (int) $row['is_active'] === 1,
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }
}
