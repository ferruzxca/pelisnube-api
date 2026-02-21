<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;

final class HealthController
{
    public function __invoke(Request $request): void
    {
        Response::success(I18n::t('ok', $request->lang()), [
            'status' => 'ok',
            'service' => 'pelisnube-php-api',
            'timestamp' => gmdate('c'),
        ]);
    }
}
