<?php

declare(strict_types=1);

use App\Core\Env;

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once __DIR__ . '/src/Core/functions.php';

$envFile = __DIR__ . '/.env';
if (!is_file($envFile)) {
    $envFile = __DIR__ . '/.env.example';
}

Env::load($envFile);

date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'America/Mexico_City'));

$debug = Env::bool('APP_DEBUG', false);
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    ini_set('display_errors', '0');
}
