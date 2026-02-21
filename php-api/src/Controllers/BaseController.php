<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;

abstract class BaseController
{
    /**
     * @return array{page:int,pageSize:int}
     */
    protected function pagination(Request $request): array
    {
        $page = (int) ($request->queryParam('page', '1') ?? '1');
        $pageSize = (int) ($request->queryParam('pageSize', '20') ?? '20');

        return [
            'page' => max(1, $page),
            'pageSize' => max(1, min(100, $pageSize)),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    protected function requireUser(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            throw new HttpException(401, 'No autorizado.');
        }

        return $user;
    }
}
