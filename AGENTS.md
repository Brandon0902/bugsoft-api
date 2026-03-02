
# AGENTS.md — Bug&Soft API (Laravel 12)

## Proyecto
- Backend API para “Bug&Soft” (Agenda y Gestión de Citas para Consultorios Dentales).
- Framework: Laravel 12.
- Base de datos: MySQL (no SQLite).
- Timezone del proyecto: America/Mexico_City (UTC-06 aprox). Preferir guardar fechas en UTC en DB si se puede; si no, ser consistente y documentar.

## Arquitectura
- Este repo es SOLO backend (no frontend).
- Todas las rutas deben vivir en `routes/api.php` y responder JSON.
- Prefijo API: `/api/...`
- No usar Blade, Inertia ni vistas.

## Autenticación
- Usar Laravel Sanctum con tokens (Bearer token).
- Endpoints mínimos:
  - `POST /api/auth/login`
  - `POST /api/auth/logout` (requiere auth)
  - `GET /api/auth/me` (requiere auth)
- Si se implementa registro, será SOLO para `client` o se deja preparado para admin (definir en la tarea).

## Roles
- Roles en `users.role` (string):
  - `admin`
  - `receptionist`
  - `dentist`
  - `client`
- Aplicar control de acceso con `auth:sanctum` + middleware por rol (ej. `role:admin,receptionist`).

## Agenda / Citas
- Tabla `appointments` con `status`:
  - `scheduled`
  - `confirmed`
  - `completed`
  - `canceled`
  - `no_show`
- Reglas de negocio (choques de horarios, `end_at > start_at`) se validan en la app (FormRequest/Service), no con triggers.

## Estándar de respuestas JSON (consistente)
Usar SIEMPRE este formato (tanto éxito como error):

### Éxito (200/201)
```json
{
  "success": true,
  "message": "OK",
  "data": {}
}