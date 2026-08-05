# Migración incremental a TypeScript

Guía complementaria: [instructivo operativo completo](instructivo-operativo.md).

## Decisión

Verdeo migrará por módulos usando el patrón strangler. Laravel permanece operativo durante la transición; Nginx dirige únicamente las rutas ya migradas al servicio TypeScript.

El backend objetivo será TypeScript sobre Node.js, Fastify, PostgreSQL y Redis. MySQL funciona temporalmente como fuente de compatibilidad para lecturas. No se incorpora MongoDB: pedidos, contactos, conversaciones, asignaciones y estados necesitan integridad relacional y transacciones. PostgreSQL permite conservar payloads variables de los canales en `jsonb`, particionar mensajes por fecha y agregar índices especializados sin separar el modelo operativo en dos bases.

Los adjuntos no se almacenarán en la base: irán a almacenamiento compatible con S3 y los mensajes guardarán metadatos y referencias.

## Fases

1. **Compatibilidad:** servicio TypeScript, observabilidad, healthchecks y lectura paginada de conversaciones desde MySQL.
2. **Mensajería:** esquema PostgreSQL normalizado para canales, conversaciones, participantes y mensajes; ingesta idempotente desde Evolution/email; outbox transaccional.
3. **Pedidos:** mover el dominio de órdenes y su vínculo con conversaciones, preservando auditoría e idempotencia.
4. **Identidad y frontend:** sustituir autenticación Laravel y trasladar las pantallas restantes.
5. **Retiro:** congelar escrituras Laravel, reconciliar datos, cambiar tráfico y retirar MySQL/PHP.

## Primer corte implementado

`apps/api` publica salud, readiness y `GET /v1/conversations` con autenticación interna, filtros y paginación por cursor. El endpoint evita cargar el JSON histórico de mensajes y establece un contrato independiente del motor de persistencia.

## Segundo corte implementado

PostgreSQL se ejecuta en un volumen independiente y contiene el esquema normalizado `messaging`: canales, participantes, conversaciones, mensajes, adjuntos, eventos de ingesta y ejecuciones de migración. Las migraciones SQL tienen checksum y un lock de PostgreSQL evita que dos réplicas las apliquen en paralelo.

El importador MySQL → PostgreSQL funciona en dos modos:

- `dry-run`: recorre y valida el origen sin escribir;
- `--apply`: hace upsert por identificadores de linaje, registra la ejecución y reconcilia conteos.

La validación local importó 142 conversaciones, 142 contactos y 528 mensajes. Dos ejecuciones consecutivas conservaron exactamente esos conteos. MySQL continúa siendo la única fuente de verdad; PostgreSQL todavía no recibe tráfico de escritura productivo.

## Tercer corte implementado

Evolution API 2.2.3 envía por la red privada de Docker los eventos `MESSAGES_UPSERT`, `MESSAGES_UPDATE` y `MESSAGES_DELETE` a `POST /v1/webhooks/evolution`. El receptor:

- valida la API key sin comparaciones temporales variables y no la persiste;
- registra el evento crudo sanitizado con una clave idempotente;
- normaliza JID, contacto, cuerpo, tipo, dirección y timestamp;
- crea o actualiza conversación y mensaje dentro de una transacción;
- conserva estados terminales y evita regresiones ante eventos desordenados;
- agrega un evento transaccional pendiente a `messaging.outbox_events`.

Los replays integrados comprobaron `401` para secretos inválidos, `202` para eventos nuevos y `200` para duplicados. También se verificó el recorrido real Evolution → red Docker → API → PostgreSQL. Los datos sintéticos fueron eliminados al terminar la prueba. No hay instancias de WhatsApp activas en el entorno local, por lo que la ingesta permanece en modo sombra hasta conectar una.

## Cuarto corte implementado

`GET /v1/conversations` puede leer desde MySQL o PostgreSQL mediante `CONVERSATION_READ_SOURCE`, con MySQL como valor seguro predeterminado. El repositorio PostgreSQL conserva filtros, estados del contrato legado y paginación por cursor, y obtiene contacto y último mensaje desde el modelo normalizado. El contrato amplía sus canales válidos con `email` e `internal`.

La validación local activó PostgreSQL temporalmente, comparó las primeras 100 conversaciones contra MySQL y obtuvo igualdad semántica completa. Los 142 identificadores importados también coinciden actualmente. La API quedó restaurada en modo MySQL al finalizar; la feature flag no autoriza por sí sola el corte productivo.

## Reglas de transición

- Una sola fuente de verdad por entidad y fase.
- Nada de doble escritura sin outbox y reconciliación.
- Webhooks idempotentes mediante identificador externo único.
- Los secretos recibidos en webhooks se validan y eliminan antes de persistir payloads.
- Backfill repetible y verificable por conteos y checksums.
- Los historiales JSON heredados solo pueden crecer por append hasta el cutover; reordenarlos invalidaría sus referencias posicionales.
- Cambios de tráfico reversibles desde Nginx.
- Cambios de repositorio reversibles con `CONVERSATION_READ_SOURCE`.

## Deuda de seguridad durante la transición

Las dependencias compatibles se actualizaron y redujeron el resultado de `composer audit` de 26 a 3 avisos. Los tres restantes pertenecen a Laravel 10 y exigen una actualización mayor del framework para quedar corregidos. El código actual no implementa envío de correo ni URLs firmadas temporales, por lo que no se encontraron rutas activas que alcancen esas vulnerabilidades; de todos modos, la exposición debe revisarse cada vez que se habilite una de esas funciones.

Mientras Laravel permanezca activo:

- ejecutar `composer audit` en CI y bloquear nuevas vulnerabilidades;
- no habilitar correo a direcciones ingresadas por usuarios sin validar caracteres CR/LF;
- no incorporar URLs firmadas temporales sobre Laravel 10;
- retirar o actualizar Laravel antes de habilitar esas capacidades.
