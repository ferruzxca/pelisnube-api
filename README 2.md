# PelisNube API

Backend NestJS para la plataforma de peliculas y series.

## Stack
- NestJS + TypeScript
- Prisma + MySQL
- JWT en cookie httpOnly
- SMTP (Hostinger) con Nodemailer

## Configuracion local
1. Instala dependencias:
   - `npm install`
2. Crea tu entorno:
   - `cp .env.example .env`
3. Genera cliente Prisma:
   - `npm run prisma:generate`
4. Aplica migraciones:
   - `npm run prisma:migrate`
5. Carga contenido inicial:
   - `npm run prisma:seed`
6. Inicia en desarrollo:
   - `npm run start:dev`

API base local: `http://localhost:3000/api/v1`

## Scripts importantes
- `npm run build`
- `npm run start:prod`
- `npm run prisma:generate`
- `npm run prisma:migrate`
- `npm run prisma:deploy`
- `npm run prisma:seed`

## Deploy en Hostinger
- Preset: `NestJS` (Node 20)
- Build command: `npm run build`
- Start command: `npm run start:prod`
- Variables: las de `.env.example`
- Dominio recomendado: `api.tudominio.com`
