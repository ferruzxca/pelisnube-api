# php-api

API REST en PHP 8.2 para PelisNube.

Entrada web: `index.php` (misma carpeta).

## Base URL

`/api/v1`

## Endpoints principales

- Salud: `GET /health`
- Catalogo: `GET /catalog`, `GET /catalog/{slug}`
- Auth: login, signup con pago simulado, OTP reset
- Favoritos y suscripcion (rol `USER`)
- Dashboard + CRUD admin (roles `ADMIN|SUPER_ADMIN`)

## Comportamientos clave

- JWT Bearer HS256
- Bilingue por `Accept-Language`
- Soft delete / inactivacion
- Auditoria de mutaciones
- Pago simulado por ultimo digito de tarjeta
- OTP por correo SMTP (`SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`)

## Cron de renovaciones

```bash
php scripts/cron-renew-subscriptions.php
```
