<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ContentRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contents WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contents WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO contents (
                id, title, slug, type, synopsis, year, duration, rating,
                trailer_watch_url, trailer_embed_url, poster_url, banner_url,
                is_active, created_at, updated_at
            ) VALUES (
                :id, :title, :slug, :type, :synopsis, :year, :duration, :rating,
                :trailer_watch_url, :trailer_embed_url, :poster_url, :banner_url,
                :is_active, NOW(), NOW()
            )'
        );

        $stmt->execute([
            ':id' => $data['id'],
            ':title' => $data['title'],
            ':slug' => $data['slug'],
            ':type' => $data['type'],
            ':synopsis' => $data['synopsis'],
            ':year' => (int) $data['year'],
            ':duration' => (int) $data['duration'],
            ':rating' => (float) $data['rating'],
            ':trailer_watch_url' => $data['trailer_watch_url'],
            ':trailer_embed_url' => $data['trailer_embed_url'],
            ':poster_url' => $data['poster_url'],
            ':banner_url' => $data['banner_url'],
            ':is_active' => $data['is_active'] ?? 1,
        ]);

        return $this->findById((string) $data['id']) ?? [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): ?array
    {
        $allowed = [
            'title', 'slug', 'type', 'synopsis', 'year', 'duration', 'rating',
            'trailer_watch_url', 'trailer_embed_url', 'poster_url', 'banner_url', 'is_active'
        ];

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

        $stmt = $this->pdo->prepare(
            'UPDATE contents SET ' . implode(', ', $setParts) . ', updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute($params);

        return $this->findById($id);
    }

    public function inactivate(string $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE contents SET is_active = 0, deactivated_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * @param array<int, string> $sectionIds
     */
    public function syncSections(string $contentId, array $sectionIds): void
    {
        $delete = $this->pdo->prepare('UPDATE content_sections SET is_active = 0, updated_at = NOW() WHERE content_id = :content_id');
        $delete->execute([':content_id' => $contentId]);

        $unique = array_values(array_unique(array_filter($sectionIds)));
        foreach ($unique as $index => $sectionId) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO content_sections (content_id, section_id, sort_order, is_active, created_at, updated_at)
                 VALUES (:content_id, :section_id, :sort_order, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), is_active = 1, updated_at = NOW()'
            );
            $stmt->execute([
                ':content_id' => $contentId,
                ':section_id' => $sectionId,
                ':sort_order' => $index,
            ]);
        }
    }

    /**
     * @return array{items: array<int, array<string,mixed>>, total:int}
     */
    public function listCatalog(int $page, int $pageSize, ?string $type, ?string $search, ?string $sectionKey): array
    {
        $pagination = $this->pagination($page, $pageSize);

        $where = ['c.is_active = 1'];
        $params = [];

        if ($type !== null && in_array($type, ['MOVIE', 'SERIES'], true)) {
            $where[] = 'c.type = :type';
            $params[':type'] = $type;
        }

        if ($search !== null && trim($search) !== '') {
            $where[] = '(c.title LIKE :search OR c.synopsis LIKE :search)';
            $params[':search'] = '%' . trim($search) . '%';
        }

        if ($sectionKey !== null && trim($sectionKey) !== '') {
            $where[] = 'EXISTS (
                SELECT 1 FROM content_sections cs
                INNER JOIN sections s ON s.id = cs.section_id
                WHERE cs.content_id = c.id
                  AND cs.is_active = 1
                  AND s.section_key = :section_key
                  AND s.is_active = 1
            )';
            $params[':section_key'] = trim($sectionKey);
        }

        $whereSql = implode(' AND ', $where);

        $countSql = 'SELECT COUNT(*) FROM contents c WHERE ' . $whereSql;
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $sql = 'SELECT c.* FROM contents c
                WHERE ' . $whereSql . '
                ORDER BY c.year DESC, c.title ASC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($items === []) {
            return ['items' => [], 'total' => $total];
        }

        $contentIds = array_map(static fn(array $item): string => (string) $item['id'], $items);
        $sectionsMap = $this->sectionsMap($contentIds);

        $mapped = [];
        foreach ($items as $item) {
            $mapped[] = $this->normalizeContent($item, $sectionsMap[$item['id']] ?? []);
        }

        return ['items' => $mapped, 'total' => $total];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function catalogDetailBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contents WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $content = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$content) {
            return null;
        }

        $sectionsMap = $this->sectionsMap([$content['id']]);
        return $this->normalizeContent($content, $sectionsMap[$content['id']] ?? []);
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
            $where[] = '(title LIKE :search OR synopsis LIKE :search OR slug LIKE :search)';
            $params[':search'] = '%' . trim($search) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM contents WHERE ' . $whereSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $sql = 'SELECT * FROM contents WHERE ' . $whereSql . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_map(static fn(array $row): string => (string) $row['id'], $items);
        $sectionsMap = $ids !== [] ? $this->sectionsMap($ids) : [];

        $mapped = [];
        foreach ($items as $item) {
            $mapped[] = $this->normalizeContent($item, $sectionsMap[$item['id']] ?? []);
        }

        return ['items' => $mapped, 'total' => $total];
    }

    public function countActive(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM contents WHERE is_active = 1');
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @param array<int, string> $contentIds
     * @return array<string, array<int, array<string,mixed>>>
     */
    private function sectionsMap(array $contentIds): array
    {
        if ($contentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($contentIds), '?'));
        $sql = 'SELECT cs.content_id, cs.sort_order, s.id, s.section_key, s.name
                FROM content_sections cs
                INNER JOIN sections s ON s.id = cs.section_id
                WHERE cs.content_id IN (' . $placeholders . ')
                  AND cs.is_active = 1
                  AND s.is_active = 1
                ORDER BY cs.sort_order ASC';

        $stmt = $this->pdo->prepare($sql);
        foreach ($contentIds as $idx => $id) {
            $stmt->bindValue($idx + 1, $id);
        }
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $contentId = (string) $row['content_id'];
            $map[$contentId] ??= [];
            $map[$contentId][] = [
                'id' => $row['id'],
                'key' => $row['section_key'],
                'name' => $row['name'],
                'sortOrder' => (int) $row['sort_order'],
            ];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $content
     * @param array<int, array<string, mixed>> $sections
     * @return array<string, mixed>
     */
    public function normalizeContent(array $content, array $sections = []): array
    {
        return [
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
            'isActive' => (int) $content['is_active'] === 1,
            'createdAt' => $content['created_at'],
            'updatedAt' => $content['updated_at'],
            'sections' => $sections,
        ];
    }
}
