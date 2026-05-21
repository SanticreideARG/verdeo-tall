# Contexto para la Sesión 2 — Verdeo TALL

> Handoff técnico para la próxima instancia de Claude.
> Generado al cierre del primer long run · 2026-05-20 · App en v0.2.0
>
> **Este archivo NO repite lo que ya está en memoria.** El stack, los credenciales,
> los contenedores, el listado de features y los fixes conocidos viven en
> `MEMORY.md` / `project_verdeo_tall.md` / `feedback_docker_windows.md`.
> Leer esos primero. Acá va únicamente lo que cambió, el estado real de cada
> pieza, y lo accionable.

---

## 1. Qué cambió en esta sesión (2026-05-18 a 2026-05-20)

Cinco bloques de trabajo concretos. Todo se aplicó dentro de WSL en
`/home/screide/proyectos/verdeo-tall/` y/o vía `docker exec` en `verdeo_laravel`.

### 1.1 — Datos de prueba multicanal
- Se ejecutó `TestConversacionesMulticanalSeeder` (vía `docker exec` en `verdeo_laravel`).
- Cargó **24 conversaciones**: 12 WhatsApp + 6 Messenger + 6 Instagram.
- Estas conversaciones existen en la DB **Alice** (la de prueba). Es el dato que ve
  el inbox `/conversaciones` ahora mismo.

### 1.2 — Reescritura de la página de Ayuda
- Se reemplazó la vieja "Guía de uso" de 5 secciones por un acordeón
  **"Funcionalidades" de 19 secciones** (una por área del sistema).
- La pestaña "Acerca de" se actualizó a **v0.2.0**.
- Se sumaron al detalle de stack en esa pestaña: **MySQL 8, Redis, Claude API**.
- Archivo afectado: la vista Volt de Ayuda (buscar en `resources/views/livewire/`
  o `resources/views/pages/` el componente de la ruta de ayuda).

### 1.3 — Integración del logo y favicons
- Origen: `Logo Verdeo.png` (diseño circular de hoja tropical, 1024×1024, 1.6 MB).
- Se copió como `verdeo-logo.png` y se **optimizó a 256×256 PNG / 62 KB** usando
  PHP GD **dentro del contenedor** (reducción del 96 %).
- Se generaron `favicon-32.png` (2.4 KB) y `favicon-192.png` (38 KB).
- Se agregaron etiquetas `<link>` de favicon en **ambos** layouts:
  - `resources/views/layouts/app.blade.php`
  - `resources/views/components/layouts/app.blade.php`
  (recordar: este proyecto mantiene el layout duplicado en las dos rutas a propósito).
- Se agregó cache-buster `?v=2` a los `<img>` del logo en el sidebar para romper
  el 404 cacheado del navegador. **Gotcha:** si se vuelve a tocar el logo, hay que
  subir el `?v=` o el browser sigue sirviendo la versión vieja.

### 1.4 — Configuración de correo electrónico en Ajustes
- Nueva tarjeta **"Correo electrónico"** en la pantalla de Ajustes.
- Contiene:
  - 4 presets de proveedor: **SMTP / Gmail / Outlook / Resend** con auto-fill de
    host y puerto al elegir.
  - Campos: host, port, encryption.
  - Contraseña **segura** — mismo patrón write-only que las API keys: se escribe
    pero nunca se devuelve; la UI muestra `●●●●` si ya hay valor guardado.
  - `from_address` + `from_name`.
  - Botón de **prueba de conectividad TCP** vía `fsockopen` (abre socket al
    host:port, no envía mail real).
- Persiste en el modelo `Setting` con el mismo patrón de claves seguras que el
  resto de credenciales.

### 1.5 — Datos estadísticos sembrados
- Se ejecutó `EstadisticasTestSeeder`.
- Insertó **142 órdenes** repartidas en 30 días, escribiendo `created_at`
  histórico **directamente vía `DB::table()`** (Eloquent no permite setear fechas
  pasadas con timestamps automáticos — por eso usa `DB::table()`).
- Estado resultante de Alice: 168 órdenes totales, $7.5M facturación (solo
  entregadas), 192 order items, ~20 % de órdenes con 2 items.
- Distribución por zona: bsas 54 %, valle_nqn 28 %, cordoba 13 %, mendoza 6 %.
- Tendencia creciente en los últimos 7 días (intencional, para que el panel de
  Estadísticas muestre variación % positiva).

---

## 2. Settings keys nuevas (modelo `Setting`)

Las claves de correo agregadas esta sesión. Siguen el patrón clave-valor del
modelo `Setting`. La de password es del tipo **secure / write-only**.

| Clave (aprox.) | Tipo | Notas |
|---|---|---|
| `mail_host` | texto | host SMTP |
| `mail_port` | texto/int | puerto SMTP |
| `mail_encryption` | texto | tls / ssl / none |
| `mail_username` | texto | usuario SMTP |
| `mail_password` | **secure** | write-only, devuelve `●●●●` si existe |
| `mail_from_address` | texto | remitente |
| `mail_from_name` | texto | nombre del remitente |

> Verificar los nombres exactos abriendo la vista Volt de Ajustes — los nombres de
> arriba son la convención esperada, no confirmados al carácter. El **patrón** sí
> es seguro: idéntico al de las API keys de IA y los tokens de WhatsApp/Meta.

---

## 3. Estado real de cada feature: cableado vs placeholder

Esto es lo más importante del handoff. Muchas pantallas **se ven completas pero no
están conectadas a un proveedor externo.** No asumir que algo funciona end-to-end
solo porque la UI existe.

### Cableado de extremo a extremo (funciona de verdad)
- **Órdenes** — alta, cambio de estado, máquina de estados completa, confirmación
  masiva a cocina, lista de ventas. Todo sobre DB real.
- **Cocina** — vista filtrada, autorefresco 10s, beep, badge de 30 min. Funciona.
- **Zonas / Productos / Menús** — CRUD real, sincronización menús/precios real.
- **Clientes / CRM** — leen datos reales de la DB.
- **Estadísticas** — calcula sobre las órdenes reales (las sembradas).
- **Usuarios** — CRUD real, cambio de rol, borrado con limpieza de foto.
- **Ajustes** — guarda todo en `Setting`. Las pruebas de conexión (WhatsApp API,
  canales, SMTP) **sí golpean la red de verdad**.
- **Asistente IA `/ai`** — chatea de verdad contra Ollama (si el contenedor está
  arriba) o Claude (si hay API key configurada).
- **Conversaciones** — el inbox **muestra** datos reales (las 24 sembradas) y los
  filtros funcionan. Ver abajo lo que NO funciona.
- **Temas, log de actividad, rastreador de enlaces, portal cliente, mi cuenta** —
  funcionan.

### UI lista pero NO conectada (placeholder funcional)
- **Responder una conversación** — el detalle de conversación muestra el historial
  pero **no hay forma de enviar una respuesta**. No existe `MessageDispatcher`.
  El botón de responder (si está en la UI) no hace nada real.
- **Mensajes entrantes de Meta** — no hay `ProcessMetaWebhookEvent`. Messenger e
  Instagram nunca van a recibir mensajes nuevos automáticamente; las 6+6
  conversaciones de esos canales son 100 % sembradas.
- **Redacción (envío masivo)** — la UI arma el mensaje y la preview, pero el envío
  real depende de Evolution API / Meta API, que no están operativas.
- **Marketing (todos los canales)** — composición de campañas y plantillas son UI;
  no hay envío real.
- **Badges de salud del dashboard** (Horizon, Evolution, n8n, Ollama) — verifican
  estado real, pero **Evolution siempre dará rojo** por el crash loop.

### Roto / degradado
- **Evolution API** — `verdeo_evolution` en `Restarting(1)` desde hace semanas.
  Prisma intenta migrar sobre DB no vacía. No está conectado a ninguna instancia
  de WhatsApp. **Cualquier feature de WhatsApp real está bloqueada por esto.**

---

## 4. Trabajo pendiente exacto — con pistas de implementación

### PRIORIDAD ALTA — habilita mensajería real

**4.1 — `ProcessMetaWebhookEvent` (job)**
- Crear en `app/Jobs/`. Es un Job que recibe el payload de un webhook de Meta.
- Debe parsear el evento (mensaje de Messenger o Instagram) y crear/actualizar un
  registro `Conversacion`.
- El modelo `Conversacion` ya tiene los campos necesarios: `canal`, `zona`,
  `telefono`, `nombre`, `estado`, `ultimo_mensaje`, `ultimo_mensaje_at`.
- Necesita una ruta de webhook pública (controlador) que reciba el POST de Meta,
  valide el `Verify Token` (ya hay campo para eso en Ajustes → Canales) y despache
  el job.
- **Bloqueante:** Meta exige HTTPS para registrar el webhook. Sin SSL no se puede
  ni probar contra Meta real.

**4.2 — `MessageDispatcher` (service)**
- Crear en `app/Services/` (junto a `OllamaService.php`).
- Debe poder enviar un mensaje saliente por **dos rutas según config**:
  - Evolution API (cuando el proveedor configurado en Ajustes es Evolution).
  - Meta Graph API (cuando el proveedor es Meta Business API).
- Leer la config desde `Setting` (las claves de "API de WhatsApp" y "Canales").
- Usar `app/Services/OllamaService.php` como **plantilla de estilo** — mismo patrón
  de servicio: constructor con config, métodos públicos claros, `isAvailable()`.

**4.3 — UI de respuesta en conversaciones**
- Archivo: `conversaciones/ver.blade.php` (la vista de detalle de conversación).
- Agregar un textarea + botón que llame a `MessageDispatcher`.
- Tras enviar, registrar el mensaje en el historial y actualizar
  `ultimo_mensaje` / `ultimo_mensaje_at`.

### PRIORIDAD MEDIA — automatización n8n
- Configurar credencial de Ollama en la UI de n8n: Base URL `http://ollama:11434`.
- Flujo autorespuesta: webhook Evolution → clasificar intención → IA → responder.
- Flujo extracción de órdenes: texto libre de WhatsApp → registros `Orden`.
  `OllamaService` ya tiene `classify()` y `extractOrderFromText()` — se pueden
  exponer como endpoints internos para que n8n los consuma, o replicar la lógica
  en n8n.
- Evolution API: configurar 4 instancias de WhatsApp (una por zona). Primero hay
  que resolver el crash loop (ver 4.4).

### PRIORIDAD CRÍTICA — antes de producción
- **4.4 — Crash loop de Evolution:** hacer baseline de las migraciones de Prisma.
  `verdeo_evolution` corre `atendai/evolution-api`. El problema es que Prisma
  encuentra la DB ya poblada y quiere migrar. Solución: `prisma migrate resolve`
  marcando las migraciones como aplicadas, o limpiar y dejar que migre desde cero.
- `APP_DEBUG=false` y `APP_ENV=production`.
- Endurecer TODAS las contraseñas (las `*_cambiar` de la memoria).
- HTTPS: certbot + config nginx + cambiar puerto 8888→80 (deshabilitar Apache WSL).
- `php artisan config:cache` + `view:cache`. **NUNCA `route:cache`** (rompe Volt).

---

## 5. Deuda técnica y gotchas para una instancia fresca

Cosas que pueden sorprender a un Claude nuevo:

1. **El layout está duplicado a propósito.** `resources/views/layouts/app.blade.php`
   Y `resources/views/components/layouts/app.blade.php` deben existir ambos. Si se
   edita uno hay que editar el otro. (Documentado en `project_verdeo_tall.md`,
   se repite acá porque es trampa fácil.)

2. **Nunca `route:cache`.** Las rutas Volt no son cacheables. Si la app empieza a
   tirar errores raros de rutas, lo primero es `php artisan route:clear`.

3. **Stubs de Volt.** `make:livewire` deja stubs `⚡*.blade.php` en
   `resources/views/components/` que pisan los SFCs reales. Si un componente "no
   actualiza" o muestra contenido viejo: borrar el stub y limpiar el cache de Volt
   en `storage/framework/views/livewire/`.

4. **Archivos Blade complejos vía script.** El quoting de `<?php`, `{{ }}` y
   `@directivas` rompe en la shell. Para vistas complejas, escribir el archivo con
   un script intermedio en `/tmp/` y moverlo, no con heredocs directos.

5. **Cache-buster del logo.** Si se reemplaza cualquier imagen del logo, subir el
   `?v=N` en los `<img>` o el browser sirve la vieja por el 404 cacheado.

6. **`EstadisticasTestSeeder` usa `DB::table()` directo**, no Eloquent, para poder
   escribir `created_at` histórico. Si se re-ejecuta, **duplica** las 142 órdenes
   — no es idempotente. Revisar si conviene truncar antes.

7. **Doble DB Alice/Betty.** Todo el trabajo y los datos sembrados están en
   **Alice** (prueba). Betty (producción) está vacía o casi. El badge del topbar
   indica cuál está activa. Antes de tocar datos, confirmar en qué DB se está.

8. **Ollama es lento.** 60–90 s por inferencia en CPU. Si una prueba del asistente
   IA "se cuelga", probablemente solo está pensando. No es un bug.

9. **Evolution rojo es esperado.** El badge de salud de Evolution en el dashboard
   va a estar en rojo hasta que se resuelva el crash loop. No perseguir eso como
   bug nuevo.

---

## 6. Estado de los datos en la DB Alice (ahora mismo)

| Entidad | Cantidad | Origen |
|---|---|---|
| Conversaciones | 24 | `TestConversacionesMulticanalSeeder` (12 WA + 6 Messenger + 6 IG) |
| Órdenes | 168 totales | base previa + 142 de `EstadisticasTestSeeder` |
| Order items | 192 | ~20 % de órdenes con 2 items |
| Facturación (entregadas) | ~$7.5M | calculado sobre las órdenes sembradas |
| Productos | 5 tipos de menú | Clásico/Real, Vegano/Veg, Keto, Familiar/Intuitivo, Anti-Age/Diabético |
| Zonas | 4 | bsas, valle_nqn, cordoba, mendoza |
| Usuarios | equipo + clientes | incluye `admin@verdeo.com.ar` |

Distribución de órdenes por zona: bsas 54 %, valle_nqn 28 %, cordoba 13 %,
mendoza 6 %. Tendencia creciente en los últimos 7 días (intencional).

> Betty (producción): asumir vacía / sin datos de negocio. Confirmar antes de
> cualquier operación de sincronización.

---

## 7. Ubicación de archivos tocados esta sesión

Rutas relativas a `/home/screide/proyectos/verdeo-tall/laravel/` salvo indicación.

| Qué | Dónde |
|---|---|
| Vista de Ayuda (acordeón 19 secciones + Acerca de v0.2.0) | `resources/views/` — componente Volt de la ruta de ayuda |
| Vista de Ajustes (nueva tarjeta de Correo) | `resources/views/` — componente Volt de Ajustes |
| Layouts (links de favicon) | `resources/views/layouts/app.blade.php` **y** `resources/views/components/layouts/app.blade.php` |
| Logo optimizado | `public/` — `verdeo-logo.png` (256×256, 62 KB) |
| Favicons | `public/` — `favicon-32.png`, `favicon-192.png` |
| Seeder conversaciones | `database/seeders/TestConversacionesMulticanalSeeder.php` |
| Seeder estadísticas | `database/seeders/EstadisticasTestSeeder.php` |
| Modelo de config | `app/Models/Setting.php` (claves `mail_*` nuevas) |
| Servicio IA de referencia | `app/Services/OllamaService.php` (plantilla para `MessageDispatcher`) |

> Las rutas exactas de las vistas Volt dependen de cómo esté organizado
> `resources/views/` (puede ser `livewire/` o `pages/`). Localizar por la ruta:
> buscar el componente que responde a `/ajustes` y a la ruta de ayuda.

---

## 8. Arranque rápido para la próxima sesión

1. Leer memoria: `MEMORY.md`, `project_verdeo_tall.md`, `feedback_docker_windows.md`.
2. Leer este archivo.
3. Verificar contenedores: `docker ps` — esperar `verdeo_evolution` en restart.
4. App en http://localhost:8888 — login `admin@verdeo.com.ar` / `verdeo2026`.
5. Confirmar que el badge del topbar dice **Alice** antes de tocar datos.

**Primer candidato de trabajo recomendado:** `MessageDispatcher` + UI de respuesta
en conversaciones (4.2 y 4.3). Es la pieza de mayor valor que NO depende de HTTPS
ni de Meta — se puede construir y probar contra Evolution API en cuanto el crash
loop esté resuelto, y deja el inbox realmente útil. El webhook de Meta (4.1) queda
bloqueado hasta tener HTTPS.
