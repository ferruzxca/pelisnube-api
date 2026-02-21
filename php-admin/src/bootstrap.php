<?php

declare(strict_types=1);

use Admin\Env;

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/ApiClient.php';
require_once __DIR__ . '/helpers.php';

$envPath = dirname(__DIR__) . '/.env';
if (!is_file($envPath)) {
    $envPath = dirname(__DIR__) . '/.env.example';
}

Env::load($envPath);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
