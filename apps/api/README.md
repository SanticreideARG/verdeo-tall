# Verdeo API

Primer corte de la migración incremental de Laravel a TypeScript.

## Endpoints

- `GET /v1/health`: liveness público.
- `GET /v1/ready`: conexión con la base actual.
- `GET /v1/conversations`: lectura paginada por cursor. Requiere `Authorization: Bearer <INTERNAL_API_TOKEN>`.

La API lee la tabla `conversaciones` de MySQL sin seleccionar la columna JSON `mensajes`. El repositorio es reemplazable para mover el almacenamiento a PostgreSQL sin cambiar el contrato HTTP.

## Desarrollo

```bash
npm ci
npm run typecheck
npm test
npm run build
```
