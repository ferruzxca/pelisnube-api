<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class FavoriteRepository extends BaseRepository
{
    /**
     * @return array{items: array<int, array<string,mixed>>, total:int}
     */
    public function listByUser(string $userId, int $page, int $pageSize): array
    {
        $pagination = $this->pagination($page, $pageSize);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = :user_id AND is_active = 1');
        $countStmt->execute([':user_id' => $userId]);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $sql = 'SELECT f.id AS favorite_id, f.created_at AS favorite_created_at,
                       c.id, c.title, c.slug, c.type, c.synopsis, c.year, c.duration, c.rating,
                       c.trailer_watch_url, c.trailer_embed_url, c.poster_url, c.banner_url
                FROM favorites f
                INNER JOIN contents c ON c.id = f.content_id
                WHERE f.user_id = :user_id AND f.is_active = 1 AND c.is_active = 1
                ORDER BY f.updated_at DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'favoriteId' => $row['favorite_id'],
                'createdAt' => $row['favorite_created_at'],
                'content' => [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'slug' => $row['slug'],
                    'type' => $row['type'],
                    'synopsis' => $row['synopsis'],
                    'year' => (int) $row['year'],
                    'duration' => (int) $row['duration'],
                    'rating' => (float) $row['rating'],
                    'trailerWatchUrl' => $row['trailer_watch_url'],
                    'trailerEmbedUrl' => $row['trailer_embed_url'],
                    'posterUrl' => $row['poster_url'],
                    'bannerUrl' => $row['banner_url'],
                ],
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array<string,mixed>
     */
    public function activate(string $userId, string $contentId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO favorites (user_id, content_id, is_active, created_at, updated_at)
             VALUES (:user_id, :content_id, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':content_id' => $contentId,
        ]);

        $q = $this->pdo->prepare('SELECT * FROM favorites WHERE user_id = :user_id AND content_id = :content_id LIMIT 1');
        $q->execute([
            ':user_id' => $userId,
            ':content_id' => $contentId,
        ]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function inactivate(string $userId, string $contentId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE favorites SET is_active = 0, updated_at = NOW() WHERE user_id = :user_id AND content_id = :content_id'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':content_id' => $contentId,
        ]);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function topContentFavorites(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        $sql = 'SELECT c.id, c.title, c.slug, COUNT(*) AS total
                FROM favorites f
                INNER JOIN contents c ON c.id = f.content_id
                WHERE f.is_active = 1 AND c.is_active = 1
                GROUP BY c.id, c.title, c.slug
                ORDER BY total DESC, c.title ASC
                LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
