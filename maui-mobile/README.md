# PelisNubeMobile (.NET 10 MAUI)

App móvil independiente para Android/iOS/MacCatalyst que consume la API de PelisNube.

## Ubicación

- Proyecto: `maui-mobile/PelisNubeMobile`

## Funcionalidades

- Login (`POST /api/v1/auth/login`)
- Signup con pago simulado (`POST /api/v1/auth/signup-with-payment`)
- Recuperación de contraseña por OTP correo:
  - `POST /api/v1/auth/password/otp/request`
  - `POST /api/v1/auth/password/otp/verify`
  - `POST /api/v1/auth/password/reset`
- Home con:
  - carrusel de destacados
  - secciones con tarjetas de contenido
- Detalle con trailer en reproductor WebView (YouTube embed/watch)
- Perfil con datos de usuario y suscripción

## Base URL API

La app prueba automáticamente estas variantes:

1. `https://peliapi.ferruzca.pro/api/v1`
2. `https://peliapi.ferruzca.pro/index.php?route=/api/v1`
3. `https://peliapi.ferruzca.pro/index.php?r=/api/v1`

Se define en `Config/AppConfig.cs`.

## Ejecutar en Android (Mac)

1. Verifica dispositivo:

```bash
adb devices
```

2. Ejecuta en dispositivo conectado:

```bash
cd maui-mobile/PelisNubeMobile
dotnet build -t:Run -f net10.0-android
```

## Compilar APK

```bash
cd maui-mobile/PelisNubeMobile
dotnet publish -f net10.0-android -c Release
```

El APK queda en `bin/Release/net10.0-android/publish/`.
