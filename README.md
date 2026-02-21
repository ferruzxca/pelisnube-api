# PelisNube PHP 8.2 Migration

Migracion completa desde Node/Nest a una solucion separada en:

- `php-api/` (`api.tudominio.com`)
- `php-admin/` (`admin.tudominio.com`)

Ambos desacoplados. `php-admin` y la app `.NET MAUI` consumen solo la API por `Bearer JWT`.

## Estructura

- `php-api/index.php`: front controller API (`/api/v1`)
- `php-api/database/schema.sql`: esquema completo MySQL
- `php-api/database/seed.sql`: seed curado con 12 titulos reales + trailers + imagenes por titulo
- `php-api/scripts/cron-renew-subscriptions.php`: renovacion diaria
- `php-admin/index.php`: panel admin HTML/PHP + Bootstrap + Chart.js

## Variables de entorno

### API (`php-api/.env`)

Copia de `php-api/.env.example`.

Valores clave:

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `JWT_SECRET`, `JWT_TTL_MINUTES`, `JWT_RESET_TTL_MINUTES`
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE`
- `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`, `SMTP_TIMEOUT_SECONDS`
- `APP_CURRENCY` (default `MXN`)
- `DEFAULT_LANG` (`es` / `en`)
- `CORS_ORIGINS` (ej. `https://admin.tudominio.com,https://app.tudominio.com`)

### Admin (`php-admin/.env`)

Copia de `php-admin/.env.example`.

Valores clave:

- `API_BASE_URL=https://api.tudominio.com/api/v1`
- `DEFAULT_LANG=es`

## Instalacion local rapida

1. Importa `php-api/database/schema.sql` en MySQL.
2. Importa `php-api/database/seed.sql`.
3. Copia `.env.example` a `.env` en `php-api` y `php-admin`.
4. Inicia servidores locales:

```bash
php -S localhost:8080 -t php-api
php -S localhost:8081 -t php-admin
```

5. Abre `http://localhost:8081`.

Credenciales seed:

- `superadmin@pelisnube.local` / `Admin12345!`
- `admin@pelisnube.local` / `Admin12345!`

## Reglas de negocio implementadas

- `signup-with-payment`: pago simulado primero, cuenta despues.
- Simulador de pago:
  - ultimo digito de tarjeta par => `SUCCESS`
  - impar => `FAILED`
- Catalogo:
  - listado publico
  - detalle autenticado
- OTP reset solo para rol `USER`.
- Soft delete / inactivacion en recursos administrativos.
- Auditoria de mutaciones en `audit_logs` + tablas historicas.

## Contrato principal API

Base path: `/api/v1`

Publico:

- `GET /health`
- `GET /catalog`
- `POST /auth/signup-with-payment`
- `POST /auth/login`
- `POST /auth/password/otp/request`
- `POST /auth/password/otp/verify`
- `POST /auth/password/reset`

Auth:

- `GET /catalog/{slug}`
- `GET /sections/home`
- `GET /auth/me`
- Favoritos y suscripcion para `USER`
- Dashboard + CRUD admin para `ADMIN|SUPER_ADMIN`

## Despliegue Hostinger (sin GitHub)

### Modo simple recomendado (sin tocar document root)

1. Respaldar DB actual desde phpMyAdmin.
2. Subir ZIP del proyecto por File Manager/FTP y extraer.
3. En `public_html` deja esta estructura:
   - `public_html/index.php` + archivos de `php-api/*`
   - `public_html/admin/*` con archivos de `php-admin/*`
4. Crear:
   - `public_html/.env` (copia de `php-api/.env.example`)
   - `public_html/admin/.env` (copia de `php-admin/.env.example`)
5. En `public_html/admin/.env` define:
   - `API_BASE_URL=https://tudominio.com/api/v1`
   - Tambien acepta: `https://tudominio.com/index.php?route=/api/v1`
6. Importar SQL en la DB:
   - `php-api/database/schema.sql`
   - `php-api/database/seed.sql`
7. Verificar permisos:
   - carpetas `755`
   - archivos `644`
8. Configurar cron diario:

```bash
/usr/bin/php /home/USER/public_html/scripts/cron-renew-subscriptions.php
```

9. Smoke tests:

```bash
curl https://tudominio.com/api/v1/health
```

Prueba OTP (debe devolver `success: true`):

```bash
curl -X POST https://tudominio.com/api/v1/auth/password/otp/request \
  -H "Content-Type: application/json" \
  -d '{"email":"tu_usuario_user@tudominio.com"}'
```

Si el hosting no aplica `.htaccess` y devuelve 404, usa temporalmente:

```bash
curl "https://tudominio.com/index.php?route=/api/v1/health"
```

- Probar login admin en `https://tudominio.com/admin`.
- Probar dashboard y CRUD.
- Opcional: ejecutar `php-api/scripts/smoke.sh` (requiere `jq`).

### Compatibilidad de rutas en `php-admin`

El cliente HTTP del panel (`php-admin/src/ApiClient.php`) intenta automaticamente estas variantes:

- `https://tudominio.com/api/v1/...`
- `https://tudominio.com/index.php?route=/api/v1/...`
- `https://tudominio.com/index.php?r=/api/v1/...`

Con esto, el panel sigue funcionando aunque cambie la forma de rewrite en Hostinger.

## Notas para MAUI

Consumir con:

- `Authorization: Bearer <token>`
- `Accept-Language: es` o `en`
- Respuestas de listas con formato paginado:

```json
{
  "success": true,
  "message": "...",
  "data": {
    "items": [],
    "page": 1,
    "pageSize": 20,
    "total": 0
  }
}
```

## App MAUI incluida

Se agrego un proyecto movil independiente en:

- `maui-mobile/PelisNubeMobile`

Incluye:

- Login / signup con pago simulado
- Recuperacion de contrasena por OTP correo
- Home con carrusel y secciones
- Detalle con reproductor WebView para trailer
- Perfil con datos de usuario y suscripcion
