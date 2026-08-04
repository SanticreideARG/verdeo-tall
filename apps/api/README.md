# Verdeo API

Servicio de migración incremental de Laravel a TypeScript.

## Endpoints

- `GET /v1/health`: liveness público.
- `GET /v1/ready`: conexión con MySQL y PostgreSQL.
- `GET /v1/conversations`: lectura paginada por cursor. Requiere `Authorization: Bearer <INTERNAL_API_TOKEN>`.

La API lee la tabla `conversaciones` de MySQL sin seleccionar la columna JSON `mensajes`. El repositorio es reemplazable para mover el almacenamiento a PostgreSQL sin cambiar el contrato HTTP.

## Persistencia de mensajería

PostgreSQL contiene el modelo normalizado de canales, conversaciones, participantes, mensajes, adjuntos y eventos de ingesta. La API aplica automáticamente los archivos de `migrations/` al arrancar y verifica sus checksums.

MySQL continúa siendo la fuente de verdad. El backfill no activa doble escritura.

```bash
# Inspección sin escrituras
docker compose exec typescript-api node dist/scripts/backfill-messaging.js

# Importación idempotente y reconciliación
docker compose exec typescript-api node dist/scripts/backfill-messaging.js --apply
```

El reporte compara conversaciones, contactos y mensajes esperados con los persistidos. Un segundo `--apply` debe producir los mismos conteos sin duplicados. Mientras MySQL sea la fuente, los arrays históricos de mensajes solo deben crecer por append; no deben reordenarse.

## Desarrollo

```bash
npm ci
npm run typecheck
npm test
npm run build
```

Con las variables de entorno de ambas bases disponibles también se puede ejecutar `npm run db:migrate`, `npm run db:backfill:dry` y `npm run db:backfill`.
