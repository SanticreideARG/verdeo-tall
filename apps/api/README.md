# Verdeo API

Servicio de migración incremental de Laravel a TypeScript.

## Endpoints

- `GET /v1/health`: liveness público.
- `GET /v1/ready`: conexión con MySQL y PostgreSQL.
- `GET /v1/conversations`: lectura paginada por cursor. Requiere `Authorization: Bearer <INTERNAL_API_TOKEN>`.
- `POST /v1/webhooks/evolution`: ingesta interna de eventos de Evolution API.

La API lee la tabla `conversaciones` de MySQL sin seleccionar la columna JSON `mensajes`. El repositorio es reemplazable para mover el almacenamiento a PostgreSQL sin cambiar el contrato HTTP.

## Persistencia de mensajería

PostgreSQL contiene el modelo normalizado de canales, conversaciones, participantes, mensajes, adjuntos y eventos de ingesta. La API aplica automáticamente los archivos de `migrations/` al arrancar y verifica sus checksums.

MySQL continúa siendo la fuente de verdad. El backfill no activa doble escritura.

## Evolution API

Evolution 2.2.3 envía globalmente `MESSAGES_UPSERT`, `MESSAGES_UPDATE` y `MESSAGES_DELETE` a la API por la red privada de Docker. El endpoint valida `apikey` en tiempo constante y elimina ese campo antes de guardar el payload.

Cada evento se inserta primero en `messaging.ingestion_events`. La clave idempotente evita replays; en la misma transacción se actualizan participantes, conversación y mensaje, y se agrega un evento pendiente a `messaging.outbox_events`. Las actualizaciones fuera de orden no pueden hacer retroceder un mensaje desde `read` a `delivered`, y `deleted` es terminal.

El endpoint no está publicado por Nginx. Para instancias configuradas individualmente también acepta el secreto en `x-verdeo-webhook-secret`.

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
