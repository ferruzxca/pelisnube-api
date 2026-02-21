<?php

declare(strict_types=1);

use Admin\Env;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function selected(string $current, string $expected): string
{
    return $current === $expected ? 'selected' : '';
}

function checked(bool $value): string
{
    return $value ? 'checked' : '';
}

function app_lang(): string
{
    $lang = $_SESSION['lang'] ?? Env::get('DEFAULT_LANG', 'es');
    $lang = strtolower((string) $lang);
    return in_array($lang, ['es', 'en'], true) ? $lang : 'es';
}

function t_admin(string $key): string
{
    $lang = app_lang();

    $messages = [
        'es' => [
            'dashboard' => 'Dashboard',
            'contents' => 'Contenidos',
            'sections' => 'Secciones',
            'users' => 'Usuarios',
            'plans' => 'Planes',
            'subscriptions' => 'Suscripciones',
            'audit' => 'Auditoria',
            'api_routes' => 'Rutas API',
            'logout' => 'Cerrar sesion',
            'login' => 'Iniciar sesion',
            'email' => 'Correo',
            'password' => 'Contrasena',
            'save' => 'Guardar',
        ],
        'en' => [
            'dashboard' => 'Dashboard',
            'contents' => 'Contents',
            'sections' => 'Sections',
            'users' => 'Users',
            'plans' => 'Plans',
            'subscriptions' => 'Subscriptions',
            'audit' => 'Audit',
            'api_routes' => 'API Routes',
            'logout' => 'Logout',
            'login' => 'Login',
            'email' => 'Email',
            'password' => 'Password',
            'save' => 'Save',
        ],
    ];

    return $messages[$lang][$key] ?? $key;
}
