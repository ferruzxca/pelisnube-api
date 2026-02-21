# php-admin

Panel de administracion HTML/PHP que consume `php-api`.

Entrada web: `index.php` (misma carpeta).

## Tecnologias

- PHP 8.2
- Bootstrap 5
- Chart.js

## Flujo

1. Login admin contra `POST /api/v1/auth/login`
2. Guarda JWT en sesion PHP
3. Consume dashboard + CRUD via API

## Config

Crear `.env` desde `.env.example`:

- `API_BASE_URL`
- `DEFAULT_LANG`

`API_BASE_URL` puede ser cualquiera de estas formas:

- `https://tudominio.com/api/v1`
- `https://tudominio.com/index.php?route=/api/v1`
