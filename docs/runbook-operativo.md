# Runbook técnico de Verdeo

Esta guía técnica describe cómo levantar, comprobar, operar y respaldar el entorno local de Verdeo, conectar una cuenta de WhatsApp mediante Evolution API y verificar la migración incremental de mensajería hacia TypeScript y PostgreSQL. Para una visión breve del proyecto, consultar el [manual de Verdeo](instructivo-operativo.md).

> Estado actual: Laravel y MySQL siguen siendo el sistema productivo legado. La API TypeScript recibe eventos nuevos de Evolution en PostgreSQL y el importador replica el histórico. PostgreSQL todavía es una base *shadow*: no debe retirarse MySQL ni cambiarse el tráfico de lectura sin completar los criterios de corte de esta guía.

## 1. Arquitectura actual

| Componente | Función | Acceso desde Windows |
| --- | --- | --- |
| Nginx | Entrada web y enrutamiento Laravel/TypeScript | `http://localhost:8888` |
| Laravel | Aplicación legado, sesiones y panel actual | A través de Nginx |
| API TypeScript | Salud, conversaciones e ingesta Evolution | `http://localhost:3000` |
| MySQL 8 | Fuente de verdad actual y base de Evolution | `127.0.0.1:3306` |
| PostgreSQL 17 | Destino de mensajería normalizada | `127.0.0.1:5432` |
| Redis | Sesiones, caché y estado de Evolution | `127.0.0.1:6379` |
| Evolution API | Conexión con WhatsApp | `http://localhost:8080` |
| n8n | Automatizaciones | `http://localhost:5678` |

Los puertos de bases de datos, Redis, n8n, Evolution y la API TypeScript están enlazados únicamente a `127.0.0.1`. Sólo Nginx queda expuesto en todas las interfaces locales mediante los puertos `8888` y `443`.

La mensajería de PostgreSQL vive en el esquema `messaging`:

- `channels`, `participants` y `conversations` modelan la identidad y el hilo omnicanal;
- `messages` conserva mensajes entrantes y salientes de forma idempotente;
- `attachments` contiene referencias, nunca los archivos binarios;
- `ingestion_events` registra cada webhook y su resultado;
- `outbox_events` deja trabajo confiable para procesos posteriores;
- `migration_runs` audita las importaciones desde MySQL.

## 2. Requisitos

- Windows con WSL 2 y Ubuntu 22.04.
- Docker Desktop configurado para usar WSL 2 e integrado con Ubuntu 22.04.
- Docker Compose v2.
- Git.
- El repositorio en `/home/screide/proyectos/verdeo-tall` dentro de WSL.
- Los directorios persistentes `D:\verdeo-docker\evolution`, `D:\verdeo-docker\n8n` y `D:\verdeo-docker\certbot`.
- Una cuenta de WhatsApp que pueda escanear un código QR.

Todos los ejemplos siguientes se ejecutan en PowerShell. Primero inicializar las variables de trabajo:

```powershell
$repo = '\\wsl.localhost\Ubuntu-22.04\home\screide\proyectos\verdeo-tall'
$compose = Join-Path $repo 'docker-compose.yml'
$dotenv = Join-Path $repo '.env'
```

## 3. Preparar la configuración

Si `.env` todavía no existe:

```powershell
Copy-Item -LiteralPath (Join-Path $repo '.env.example') -Destination $dotenv
```

Completar como mínimo estas variables con valores propios y largos:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8888
APP_KEY=base64:VALOR_GENERADO_POR_LARAVEL

DB_ROOT_PASSWORD=VALOR_UNICO
DB_DATABASE=verdeo_db
DB_USERNAME=verdeo_user
DB_PASSWORD=VALOR_UNICO

POSTGRES_DATABASE=verdeo_messaging
POSTGRES_USER=verdeo_user
POSTGRES_PASSWORD=VALOR_UNICO
CONVERSATION_READ_SOURCE=mysql

INTERNAL_API_TOKEN=VALOR_ALEATORIO_DE_32_O_MAS_CARACTERES
EVOLUTION_API_KEY=VALOR_ALEATORIO_DE_32_O_MAS_CARACTERES

SESSION_DRIVER=redis
SESSION_CONNECTION=session
REDIS_SESSION_DB=2
SESSION_COOKIE=verdeo_session
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
```

Después de guardar `.env`, cargar sus valores para el resto de los ejemplos:

```powershell
$envMap = @{}
Get-Content -LiteralPath $dotenv |
  Where-Object { $_ -match '^[A-Z0-9_]+=' } |
  ForEach-Object {
    $key, $value = $_ -split '=', 2
    $envMap[$key] = $value.Trim().Trim('"').Trim("'")
  }
```

Volver a ejecutar este bloque después de modificar `.env`. No imprimir `$envMap` completo ni copiarlo en tickets o chats: contiene credenciales.

Para generar secretos sin instaladores adicionales:

```powershell
[Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLower()
```

Para generar `APP_KEY` con el contenedor de Laravel, levantar primero sus dependencias y ejecutar:

```powershell
docker compose -f $compose --env-file $dotenv up -d mysql redis
docker compose -f $compose --env-file $dotenv run --rm laravel-app php artisan key:generate --show
```

Copiar el valor mostrado a `APP_KEY`. No ejecutar `php artisan key:generate` sin `--show` sobre un entorno que ya tenga datos cifrados.

Validar la sintaxis del Compose antes de levantar servicios:

```powershell
docker compose -f $compose --env-file $dotenv config --quiet
```

## 4. Levantar y verificar el entorno

En un volumen MySQL nuevo hay que crear una base independiente para Evolution y conceder acceso al usuario de aplicación. Esta operación es idempotente:

```powershell
docker compose -f $compose --env-file $dotenv up -d mysql

$mysqlRootPassword = $envMap['DB_ROOT_PASSWORD']
$mysqlUser = $envMap['DB_USERNAME']

docker exec -e "MYSQL_PWD=$mysqlRootPassword" verdeo_mysql mysql -uroot -e `
  "CREATE DATABASE IF NOT EXISTS verdeo_evolution CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON verdeo_evolution.* TO '$mysqlUser'@'%'; FLUSH PRIVILEGES;"
```

Construir y arrancar:

```powershell
docker compose -f $compose --env-file $dotenv up -d --build
docker compose -f $compose --env-file $dotenv ps
```

Todos los servicios con *healthcheck* deben terminar en `healthy`. Durante el primer arranque MySQL, PostgreSQL, Evolution y n8n pueden tardar uno o dos minutos.

Comprobar los puntos principales:

```powershell
Invoke-RestMethod 'http://localhost:3000/v1/health'
Invoke-RestMethod 'http://localhost:3000/v1/ready'
(Invoke-WebRequest 'http://localhost:8888/login').StatusCode
(Invoke-WebRequest 'http://localhost:8080').StatusCode
```

Resultados esperados:

- `health` responde `status = ok`;
- `ready` responde `status = ready` después de comprobar MySQL y PostgreSQL;
- `/login` responde HTTP 200;
- Evolution responde sin error de conexión.

Si un servicio no está sano, revisar primero sus últimas líneas:

```powershell
docker logs --tail 150 verdeo_typescript_api
docker logs --tail 150 verdeo_laravel
docker logs --tail 150 verdeo_postgres
docker logs --tail 150 verdeo_evolution
```

## 5. Crear y conectar la instancia de WhatsApp

Evolution exige su API key en el encabezado `apikey`. Cargarla desde `.env` y elegir un nombre estable para la instancia:

```powershell
$headers = @{ apikey = $envMap['EVOLUTION_API_KEY'] }
$instanceName = 'verdeo-principal'
```

### 5.1 Ver si la instancia ya existe

```powershell
$instances = Invoke-RestMethod `
  -Uri 'http://localhost:8080/instance/fetchInstances' `
  -Headers $headers

$instances | Select-Object name, connectionStatus
```

No crear una segunda instancia si `verdeo-principal` ya aparece. Continuar directamente con la consulta de estado o la obtención del QR.

### 5.2 Crear la instancia

```powershell
$body = @{
  instanceName = $instanceName
  qrcode = $true
  integration = 'WHATSAPP-BAILEYS'
} | ConvertTo-Json

$created = Invoke-RestMethod `
  -Method Post `
  -Uri 'http://localhost:8080/instance/create' `
  -Headers $headers `
  -ContentType 'application/json' `
  -Body ([Text.Encoding]::UTF8.GetBytes($body))

$created
```

### 5.3 Obtener y abrir el QR

```powershell
$qr = Invoke-RestMethod `
  -Uri "http://localhost:8080/instance/connect/$instanceName" `
  -Headers $headers

$base64 = $qr.base64 -replace '^data:image/[^;]+;base64,', ''
$qrPath = Join-Path $env:TEMP "$instanceName-qr.png"
[IO.File]::WriteAllBytes($qrPath, [Convert]::FromBase64String($base64))
Start-Process $qrPath
```

En el teléfono: **WhatsApp → Dispositivos vinculados → Vincular un dispositivo** y escanear el QR. El código caduca; si ocurre, volver a ejecutar sólo este paso.

### 5.4 Confirmar la conexión

```powershell
$state = Invoke-RestMethod `
  -Uri "http://localhost:8080/instance/connectionState/$instanceName" `
  -Headers $headers

$state
```

El estado esperado es `open`. La sesión de Evolution persiste en `D:\verdeo-docker\evolution`; reiniciar el contenedor no debería exigir un QR nuevo.

## 6. Verificar la ingesta real de mensajes

El Compose configura un webhook global interno:

```text
Evolution API
  → POST http://typescript-api:3000/v1/webhooks/evolution
  → PostgreSQL: ingestion_events + conversations + messages + outbox_events
```

Sólo están habilitados estos eventos:

- `MESSAGES_UPSERT`;
- `MESSAGES_UPDATE`;
- `MESSAGES_DELETE`.

Comprobar la configuración efectiva sin mostrar la API key:

```powershell
docker exec verdeo_evolution sh -lc 'printenv | grep -E "^(WEBHOOK_GLOBAL|WEBHOOK_EVENTS_MESSAGES_)" | sort'
```

Abrir los logs de la API en una terminal:

```powershell
docker logs --follow --tail 50 verdeo_typescript_api
```

Desde otro teléfono, enviar un mensaje al número conectado. Luego consultar PostgreSQL:

```powershell
$pgUser = $envMap['POSTGRES_USER']
$pgDb = $envMap['POSTGRES_DATABASE']

docker exec verdeo_postgres psql "-U$pgUser" "-d$pgDb" -c @'
SELECT id, event_type, status, received_at, processed_at
FROM messaging.ingestion_events
WHERE provider = 'evolution'
ORDER BY id DESC
LIMIT 20;
'@

docker exec verdeo_postgres psql "-U$pgUser" "-d$pgDb" -c @'
SELECT id, source_ref, direction, type, status, occurred_at
FROM messaging.messages
WHERE source_system = 'evolution'
ORDER BY id DESC
LIMIT 20;
'@

docker exec verdeo_postgres psql "-U$pgUser" "-d$pgDb" -c @'
SELECT id, aggregate_type, event_type, status, created_at
FROM messaging.outbox_events
ORDER BY id DESC
LIMIT 20;
'@
```

La prueba se considera correcta cuando:

1. aparece un `ingestion_event` procesado;
2. existe exactamente un mensaje con el identificador del proveedor;
3. la conversación y sus participantes se crearon o actualizaron;
4. existe un evento de outbox pendiente;
5. reenviar el mismo webhook no duplica el mensaje.

La API elimina `apikey` del payload antes de persistirlo. Esta comprobación debe devolver cero:

```powershell
docker exec verdeo_postgres psql "-U$pgUser" "-d$pgDb" -c @'
SELECT count(*) AS eventos_con_apikey
FROM messaging.ingestion_events
WHERE payload ? 'apikey';
'@
```

Los identificadores de WhatsApp terminados en `@lid` son válidos. El normalizador usa `remoteJidAlt` o `senderPn` cuando están disponibles; si Evolution no entrega un número alternativo, conserva el identificador sin inventar un teléfono.

## 7. Importar el histórico MySQL → PostgreSQL

La importación es idempotente y tiene dos modos. Ejecutar siempre primero el ensayo sin escrituras:

```powershell
docker compose -f $compose --env-file $dotenv exec typescript-api `
  node dist/scripts/backfill-messaging.js
```

Si no informa errores, aplicar:

```powershell
docker compose -f $compose --env-file $dotenv exec typescript-api `
  node dist/scripts/backfill-messaging.js --apply
```

Repetir `db:backfill` no debe aumentar los totales. Verificar el último resultado:

```powershell
docker exec verdeo_postgres psql "-U$pgUser" "-d$pgDb" -c @'
SELECT id, source_system, mode, status, stats, error_message,
       started_at, finished_at
FROM messaging.migration_runs
ORDER BY id DESC
LIMIT 5;
'@
```

En la validación inicial del repositorio se reconciliaron 142 conversaciones, 142 contactos y 528 mensajes. Estos números son una referencia histórica, no una constante: deben crecer si MySQL recibe datos nuevos.

### 7.1 Conmutar la lectura de conversaciones

`GET /v1/conversations` dispone de dos repositorios intercambiables:

- `CONVERSATION_READ_SOURCE=mysql`: comportamiento predeterminado y rollback inmediato;
- `CONVERSATION_READ_SOURCE=postgres`: consulta el esquema normalizado, participantes y último mensaje sin leer el JSON histórico.

Después de reconciliar el backfill, habilitar PostgreSQL en `.env` y recrear sólo la API:

```powershell
# Editar .env: CONVERSATION_READ_SOURCE=postgres
docker compose -f $compose --env-file $dotenv up -d --force-recreate typescript-api
Invoke-RestMethod 'http://localhost:3000/v1/ready'
docker logs --tail 30 verdeo_typescript_api
```

El log debe incluir `conversation read repository selected` con `source=postgres`. Probar paginación, filtros y consumidores antes de mantener el cambio.

Para volver inmediatamente a MySQL:

```powershell
# Editar .env: CONVERSATION_READ_SOURCE=mysql
docker compose -f $compose --env-file $dotenv up -d --force-recreate typescript-api
Invoke-RestMethod 'http://localhost:3000/v1/ready'
```

La validación del cuarto corte comparó 100 conversaciones de ambos repositorios, excluyendo el identificador interno, y obtuvo igualdad semántica completa. Los 142 registros importados conservan además el mismo identificador actual. Esto valida el conjunto local, pero no reemplaza una ventana de observación antes del corte productivo.

## 8. Pruebas antes de cada entrega

Ejecutar las pruebas TypeScript en un contenedor temporal de Node.js 22. El código se monta como sólo lectura y se copia dentro del contenedor para que `npm ci` no mezcle módulos de Windows y Linux en el repositorio:

```powershell
wsl.exe -d Ubuntu-22.04 -- docker run --rm `
  -v /home/screide/proyectos/verdeo-tall/apps/api:/source:ro `
  -w /work node:22-alpine sh -lc `
  'cp -R /source/. /work && npm ci && npm test && npm run typecheck && npm run build'

docker compose -f $compose --env-file $dotenv exec laravel-app php artisan test
docker compose -f $compose --env-file $dotenv config --quiet
```

Además:

```powershell
Invoke-RestMethod 'http://localhost:3000/v1/ready'
docker compose -f $compose --env-file $dotenv ps
git -C $repo status --short
```

No aprobar un despliegue si `ready` falla, hay migraciones fallidas, el webhook registra errores o los conteos del backfill no reconcilian.

## 9. Operaciones cotidianas

### Arrancar

```powershell
docker compose -f $compose --env-file $dotenv up -d
```

### Detener sin eliminar datos

```powershell
docker compose -f $compose --env-file $dotenv stop
```

### Reiniciar un servicio

```powershell
docker compose -f $compose --env-file $dotenv restart typescript-api
docker compose -f $compose --env-file $dotenv restart evolution-api
```

### Reconstruir después de cambiar código o configuración

```powershell
docker compose -f $compose --env-file $dotenv up -d --build typescript-api nginx
```

### Ver logs conjuntos

```powershell
docker compose -f $compose --env-file $dotenv logs --tail 200 `
  nginx laravel-app typescript-api mysql postgres redis evolution-api
```

No usar `docker compose down -v`: la opción `-v` elimina los volúmenes nombrados de MySQL, PostgreSQL y Redis. Un `down` sin `-v` conserva esos volúmenes, pero tampoco es necesario para la operación habitual.

## 10. Backup manual

Crear una carpeta con fecha:

```powershell
$backupRoot = "D:\verdeo-backups\manual-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
New-Item -ItemType Directory -Path $backupRoot | Out-Null
```

### MySQL

```powershell
$mysqlUser = $envMap['DB_USERNAME']
$mysqlDb = $envMap['DB_DATABASE']
$mysqlPassword = $envMap['DB_PASSWORD']

docker exec -e "MYSQL_PWD=$mysqlPassword" verdeo_mysql `
  mysqldump "-u$mysqlUser" --single-transaction --routines --triggers `
  --result-file=/tmp/verdeo-mysql.sql $mysqlDb

docker cp verdeo_mysql:/tmp/verdeo-mysql.sql (Join-Path $backupRoot 'mysql.sql')
docker exec verdeo_mysql rm -f /tmp/verdeo-mysql.sql
```

Respaldar también la base `verdeo_evolution`, que contiene metadatos propios de Evolution:

```powershell
docker exec -e "MYSQL_PWD=$mysqlPassword" verdeo_mysql `
  mysqldump "-u$mysqlUser" --single-transaction `
  --result-file=/tmp/verdeo-evolution.sql verdeo_evolution

docker cp verdeo_mysql:/tmp/verdeo-evolution.sql `
  (Join-Path $backupRoot 'evolution-mysql.sql')

docker exec verdeo_mysql rm -f /tmp/verdeo-evolution.sql
```

### PostgreSQL

```powershell
$pgPassword = $envMap['POSTGRES_PASSWORD']

docker exec -e "PGPASSWORD=$pgPassword" verdeo_postgres `
  pg_dump -h 127.0.0.1 "-U$pgUser" "-d$pgDb" `
  --no-owner --no-privileges -f /tmp/verdeo-postgres.sql

docker cp verdeo_postgres:/tmp/verdeo-postgres.sql `
  (Join-Path $backupRoot 'postgres.sql')

docker exec verdeo_postgres rm -f /tmp/verdeo-postgres.sql
```

### Sesión de Evolution y n8n

```powershell
Copy-Item -LiteralPath 'D:\verdeo-docker\evolution' `
  -Destination (Join-Path $backupRoot 'evolution') -Recurse

Copy-Item -LiteralPath 'D:\verdeo-docker\n8n' `
  -Destination (Join-Path $backupRoot 'n8n') -Recurse
```

Guardar una copia cifrada de `.env` fuera del servidor, nunca dentro de Git. Validar cada backup restaurándolo en un entorno aislado; la existencia del archivo no garantiza que sea recuperable.

## 11. Rotación de credenciales

### API key de Evolution

1. Generar un secreto nuevo.
2. Actualizar `EVOLUTION_API_KEY` en `.env`.
3. Recrear juntos Evolution y la API TypeScript, porque ambos deben compartir el valor:

```powershell
docker compose -f $compose --env-file $dotenv up -d --force-recreate `
  evolution-api typescript-api
```

4. Consultar el estado de la instancia con la nueva key.
5. Enviar un mensaje de prueba y verificar la ingesta.

### Contraseña de PostgreSQL

Cambiar primero el rol dentro de PostgreSQL, después `.env` y finalmente recrear los consumidores. No basta con modificar `.env`: la imagen oficial sólo aplica `POSTGRES_PASSWORD` al inicializar un volumen vacío.

```powershell
docker exec -it verdeo_postgres psql "-U$pgUser" "-d$pgDb"
```

Dentro de `psql`, ejecutar `\password`; el cliente cambiará la contraseña del usuario conectado y pedirá la clave nueva sin dejarla en el historial. Salir con `\q`, actualizar `POSTGRES_PASSWORD` en `.env` y luego:

```powershell
docker compose -f $compose --env-file $dotenv up -d --force-recreate typescript-api
```

Aplicar el mismo principio a MySQL: modificar el usuario en la base antes de recrear Laravel, Evolution y la API TypeScript.

## 12. Solución de problemas

### Laravel muestra `419 Page Expired` al iniciar sesión

Causas habituales: cookie antigua, `APP_KEY` distinta entre reinicios, Redis inaccesible o configuración de sesión cacheada.

```powershell
docker exec verdeo_redis redis-cli -n 2 PING
docker exec verdeo_laravel php artisan optimize:clear
docker compose -f $compose --env-file $dotenv up -d --force-recreate laravel-app nginx
```

Después borrar únicamente las cookies de `localhost` en el navegador y recargar `/login`. Comprobar que `APP_URL=http://localhost:8888`, `SESSION_SECURE_COOKIE=false` en local y que `APP_KEY` no cambió. No regenerar la key para intentar resolver un 419 existente.

### Nginx responde `502 Bad Gateway`

```powershell
docker exec verdeo_nginx nginx -t
docker exec verdeo_nginx nginx -s reload
docker logs --tail 100 verdeo_nginx
docker logs --tail 100 verdeo_typescript_api
docker logs --tail 100 verdeo_laravel
```

La configuración resuelve nuevamente el DNS interno de Docker, pero un contenedor recreado puede requerir la recarga de Nginx si había una conexión anterior abierta.

### `/v1/ready` responde 503

```powershell
docker exec verdeo_postgres pg_isready "-U$pgUser" "-d$pgDb"
docker exec verdeo_mysql mysqladmin ping -h localhost "-u$mysqlUser" "-p$mysqlPassword"
docker logs --tail 150 verdeo_typescript_api
```

Revisar contraseñas y nombres de bases en `.env`; no cambiar datos dentro del contenedor para hacerlos coincidir a ciegas.

### Evolution no muestra QR

1. Consultar `fetchInstances` y `connectionState`.
2. Si la instancia existe pero está cerrada, volver a pedir `/instance/connect/{instance}`.
3. Revisar `docker logs verdeo_evolution`.
4. Verificar que `D:\verdeo-docker\evolution` sea accesible desde Docker Desktop.

No borrar la carpeta de Evolution: contiene la sesión de WhatsApp.

### El webhook responde 401

`EVOLUTION_API_KEY` y `EVOLUTION_WEBHOOK_SECRET` efectivo no coinciden. En este Compose ambos derivan de la misma variable. Recrear simultáneamente `evolution-api` y `typescript-api`, sin imprimir el secreto en los logs.

### Hay evento de ingesta pero no mensaje

```powershell
docker exec verdeo_postgres psql "-U$pgUser" "-d$pgDb" -c @'
SELECT id, event_type, status, error_message, payload
FROM messaging.ingestion_events
WHERE provider = 'evolution'
ORDER BY id DESC
LIMIT 5;
'@
```

Revisar `status`, `error`, `event_type`, el identificador remoto y el tipo de mensaje. Un evento no soportado puede registrarse sin crear un mensaje. Evitar compartir el payload completo si contiene datos personales.

### Parecen existir mensajes duplicados

Buscar por `source_system` y `source_ref`. Reintentos del proveedor pueden producir más de una entrega, pero las restricciones de idempotencia deben conservar un solo mensaje. No eliminar duplicados manualmente antes de identificar si tienen realmente el mismo ID del proveedor.

## 13. Preparación del VPS

El VPS debe usar Linux estable, Docker Engine y Compose v2. Antes de copiar datos:

- asignar al menos 4 vCPU, 8 GB de RAM y almacenamiento SSD para el conjunto completo;
- usar un dominio y TLS válido;
- exponer públicamente sólo `80/443` mediante Nginx;
- no publicar `3000`, `3306`, `5432`, `5678`, `6379` ni `8080`;
- establecer `APP_ENV=production`, `APP_DEBUG=false`, `NODE_ENV=production` y `SESSION_SECURE_COOKIE=true`;
- usar secretos nuevos y distintos a los del entorno local;
- restringir SSH por clave, habilitar firewall y actualizaciones de seguridad;
- automatizar backups cifrados fuera del VPS y probar restauraciones;
- fijar versiones o *digests* de todas las imágenes;
- agregar monitoreo de disco, memoria, reinicios, HTTP readiness, fallos de ingesta y antigüedad del outbox;
- definir retención de logs y de payloads con datos personales.

La URL pública no debe apuntar directamente a Evolution. La comunicación Evolution → TypeScript permanece en la red privada de Docker. Si en el futuro un proveedor externo necesita invocar el webhook, se debe publicar una ruta específica con TLS, rate limiting y un secreto independiente.

### Secuencia sugerida de despliegue

1. Crear backups verificados de MySQL, PostgreSQL, Evolution, n8n y secretos.
2. Preparar el VPS y copiar el repositorio sin `.git` si el servidor no realizará despliegues Git.
3. Crear el `.env` de producción en el VPS con permisos `600`.
4. Levantar MySQL, PostgreSQL y Redis; restaurar y verificar datos.
5. Levantar Laravel y TypeScript; ejecutar migraciones y readiness.
6. Restaurar Evolution y comprobar la sesión sin conectar dos servidores simultáneamente a la misma cuenta.
7. Levantar Nginx con TLS.
8. Ejecutar pruebas de login, conversaciones, mensaje entrante, idempotencia y backup.
9. Cambiar DNS sólo después de las verificaciones.
10. Mantener el entorno anterior apagado pero recuperable durante la ventana de rollback.

## 14. Criterios para avanzar con la migración

No cambiar las lecturas de mensajería a PostgreSQL solamente porque el backfill finaliza. Antes del corte deben cumplirse como mínimo:

- una instancia real de WhatsApp conectada y estable;
- varios días de observación sin eventos fallidos o perdidos;
- reintentos comprobados sin duplicados;
- reconciliación automatizada MySQL/PostgreSQL;
- métricas y alertas para ingesta y outbox;
- un consumidor del outbox con reintentos y *dead-letter handling*;
- repositorios de lectura PostgreSQL detrás de una *feature flag* (implementado; falta observación productiva);
- pruebas de carga con el volumen esperado de conversaciones;
- backup y restauración ensayados;
- procedimiento de rollback escrito y probado.

PostgreSQL es adecuado para este dominio. Conversaciones, participantes, pedidos y estados se benefician de integridad relacional y transacciones; `jsonb`, índices y particionado cubren los payloads variables y el volumen de mensajes. MongoDB agregaría consistencia y operación distribuida sin resolver una necesidad actual. Los adjuntos deben ir a almacenamiento de objetos y no a ninguna de las dos bases.

## 15. Lista de aceptación del entorno

- [ ] `docker compose config --quiet` termina sin errores.
- [ ] Todos los contenedores esperados están en ejecución y los comprobables están `healthy`.
- [ ] Laravel abre `/login` y permite iniciar sesión sin 419.
- [ ] `/v1/health` responde `ok` y `/v1/ready` responde `ready`.
- [ ] Evolution informa la instancia en estado `open`.
- [ ] Un mensaje real crea ingesta, conversación, mensaje y outbox.
- [ ] Repetir la entrega no duplica el mensaje.
- [ ] No hay API keys persistidas en los eventos.
- [ ] El backfill seco y aplicado finalizan y reconcilian.
- [ ] Las pruebas Laravel y TypeScript pasan.
- [ ] Existe un backup reciente y restaurable.
- [ ] `.env` no está versionado y los secretos no aparecen en logs.

## 16. Referencias oficiales

- [Instalación de Evolution API](https://docs.evolutionfoundation.com.br/evolution-api/installation)
- [Crear una instancia](https://docs.evolutionfoundation.com.br/en/evolution-api/create-instance)
- [Conectar una instancia y obtener el QR](https://docs.evolutionfoundation.com.br/evolution-api/connect-instance)
- [Consultar el estado de conexión](https://docs.evolutionfoundation.com.br/evolution-api/get-connection-state)
- [Configurar webhooks](https://docs.evolutionfoundation.com.br/en/evolution-api/configuration/webhooks)
- [Consultar la configuración de una instancia](https://docs.evolutionfoundation.com.br/en/evolution-api/get-settings)

La documentación oficial puede describir una versión más nueva que la imagen fijada por el proyecto. Antes de actualizar Evolution, hacerlo en una rama y un entorno aislado, leer las notas entre versiones, respaldar la sesión y repetir toda la lista de aceptación.
