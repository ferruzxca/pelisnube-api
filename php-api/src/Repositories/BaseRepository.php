<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

abstract class BaseRepository
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{limit:int,offset:int,page:int,pageSize:int}
     */
    protected function pagination(int $page, int $pageSize): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $offset = ($page - 1) * $pageSize;

        return [
            'limit' => $pageSize,
            'offset' => $offset,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }
}
