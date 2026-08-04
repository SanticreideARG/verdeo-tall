# Arquitectura — Verdeo TALL

**Stack actual:** Tailwind v3 · Alpine.js · Laravel 10 · Livewire v4.3.0 Volt
**Migración:** TypeScript · Node.js 22 · Fastify · PostgreSQL 17
**Patrón:** Single-File Components (Volt SFCs) — PHP class + Blade template en un solo archivo  
**Fecha de última actualización:** 2026-08-04

La migración se realiza por módulos. Laravel continúa operativo y `apps/api` incorpora los primeros contratos TypeScript. Ver [migracion-typescript.md](migracion-typescript.md).

---

## Contenedores Docker

```
Host Windows
└── WSL2 (Ubuntu-22.04)
    └── Docker Desktop
        ├── verdeo_nginx      :8888 → nginx → verdeo_laravel:9000
        ├── verdeo_laravel    php-fpm (Laravel 10)
        ├── verdeo_typescript_api :3000 (Fastify, migración incremental)
        ├── verdeo_mysql      :3306  (volumen Docker verdeo_mysql_data)
        ├── verdeo_postgres   :5432  (mensajería normalizada, destino de migración)
        ├── verdeo_redis      :6379  (volumen Docker verdeo_redis_data)
        ├── verdeo_n8n        :5678  (D:\verdeo-docker\n8n)
        ├── verdeo_evolution  :8080  (D:\verdeo-docker\evolution)
        ├── verdeo_queue      (Laravel queue worker)
        └── verdeo_scheduler  (Laravel scheduler)
```

Todos los contenedores comparten la red Compose `verdeo_net`. Se comunican por nombre de servicio.

La API TypeScript comprueba MySQL y PostgreSQL en readiness. Al iniciar aplica las migraciones SQL pendientes. MySQL sigue atendiendo las lecturas del contrato `/v1/conversations`; PostgreSQL conserva el backfill reconciliado hasta que se implemente la ingesta idempotente y se autorice el cambio de fuente.

Evolution API entrega eventos de mensajes directamente a la API TypeScript dentro de `verdeo_net`. Los eventos se escriben en `messaging.ingestion_events`, actualizan el modelo normalizado y generan `messaging.outbox_events` en una única transacción. La outbox todavía no tiene publicador: permanece como frontera durable para el siguiente corte de automatizaciones.

## Estructura de archivos Laravel

```
laravel/
├── app/
│   ├── Models/
│   │   ├── User.php          # roles: admin | responsable_zona | colaborador | cliente
│   │   ├── Conversacion.php  # mensajes WhatsApp entrantes
│   │   ├── Producto.php      # menús con precios 250g/400g
│   │   ├── Plato.php         # items dentro de un producto
│   │   ├── Orden.php         # pedido de un cliente
│   │   └── OrdenItem.php     # línea de orden: menu + tamaño + forma_pago + precio
│   └── Services/
│       └── OllamaService.php # wrapper para la API REST de Ollama
├── resources/views/
│   ├── layouts/
│   │   ├── app.blade.php     # layout principal (sidebar + topbar + canvas bg)
│   │   └── guest.blade.php   # layout para login
│   └── livewire/             # Volt SFCs (PHP class + Blade en un archivo)
│       ├── dashboard.blade.php
│       ├── ai/
│       │   └── chat.blade.php          # Chat interno con Ollama
│       ├── conversaciones/
│       │   └── index.blade.php         # Lista + Sugerir respuesta (IA)
│       ├── ordenes/
│       │   └── index.blade.php         # Lista + nueva orden (menu/tamaño/forma_pago/precio auto)
│       ├── productos/
│       │   └── index.blade.php         # Tabs: Menús | Precios
│       ├── usuarios/
│       │   ├── index.blade.php         # Lista clickeable + rol dropdown + eliminar
│       │   ├── ver.blade.php           # Ficha de usuario
│       │   ├── crear.blade.php
│       │   ├── crear-cliente.blade.php
│       │   └── crear-colaborador.blade.php
│       ├── zonas/index.blade.php
│       ├── estadisticas/index.blade.php
│       ├── enlaces/index.blade.php
│       ├── ajustes/index.blade.php
│       ├── mi-cuenta/index.blade.php
│       └── marketing/
│           ├── email.blade.php
│           ├── whatsapp.blade.php
│           ├── facebook.blade.php
│           ├── instagram.blade.php
│           └── otros.blade.php
├── routes/web.php             # Volt::route() para todas las rutas
└── config/services.php        # ollama.url + ollama.model
```

## Rutas

Todas las rutas protegidas con `middleware('auth')`. Registradas en `routes/web.php` con `Volt::route()`.

| URL | Nombre | Acceso |
|-----|--------|--------|
| /dashboard | dashboard | todos |
| /conversaciones | conversaciones | todos |
| /zonas | zonas | todos |
| /enlaces | enlaces | todos |
| /estadisticas | estadisticas | todos |
| /productos | productos | todos |
| /ordenes | ordenes | todos |
| /usuarios | usuarios | admin, responsable_zona |
| /usuarios/crear | usuarios.crear | admin |
| /usuarios/crear/cliente | usuarios.crear-cliente | admin |
| /usuarios/crear/colaborador | usuarios.crear-colaborador | admin |
| /usuarios/{user} | usuarios.ver | admin, responsable_zona |
| /ajustes | ajustes | todos |
| /mi-cuenta | mi-cuenta | todos |
| /ai | ai.chat | admin, responsable_zona |
| /marketing/* | marketing.* | todos |
| /n8n | n8n | todos (redirect a :5678) |

## Roles de usuario

```
admin            → acceso total
responsable_zona → gestión de zona, sin edición de config global
colaborador      → operativo, sin gestión de usuarios
cliente          → redirige a dashboard (sin acceso al panel)
```

## Módulo IA (Ollama)

```
OllamaService
├── generate(prompt)          → POST /api/generate  (single-turn)
├── chat(messages)            → POST /api/chat       (multi-turn)
├── stream(messages, fn)      → POST /api/chat stream=true
├── classify(text, categories)→ prompt estructurado → categoría
├── extractOrderFromText(text)→ prompt JSON → array de orden
├── suggestReply(message, ctx)→ system prompt Verdeo → respuesta en español
├── isAvailable()             → GET /api/tags (timeout 3s)
└── getModel()                → string

Modelo: llama3.2:latest (3.2B Q4_K_M, ~2 GB)
Inferencia: CPU-only, ~60-90s por request
URL interna: http://ollama:11434
```

### Integración actual en Verdeo

- **`/ai`** — Chat multi-turno libre con contexto Verdeo
- **Conversaciones → Sugerir** — Genera respuesta sugerida al último mensaje del cliente, con botón de copiar

### Pendiente de implementar

- Análisis de órdenes (patrones, resúmenes)
- Generador de descripciones de menú
- n8n: clasificación automática de mensajes entrantes
- n8n: extracción de orden desde texto libre
- n8n: auto-respuesta WhatsApp vía Evolution API

## Flujo WhatsApp (planificado)

```
Cliente WhatsApp
    ↓ mensaje entrante
Evolution API (verdeo_evolution :8080)
    ↓ webhook
n8n (verdeo_n8n :5678)
    ├── classify(mensaje) → OllamaService
    ├── [si es consulta] → suggestReply() → OllamaService → respuesta automática
    ├── [si es pedido]   → extractOrderFromText() → crear Orden en Laravel
    └── [si es manual]   → notificar al equipo
```

## Convenciones de código

- **Sin comentarios descriptivos** — los nombres de métodos y variables son suficientes
- **Sin abstracciones prematuras** — cada componente hace lo que necesita, no más
- **Validación solo en boundaries** — inputs de usuario y respuestas de APIs externas
- **Archivos Blade complejos** — escritos via scripts Python en `/tmp/` para evitar conflictos de quoting con `<?php`, `{{ }}` y `@directives` en heredocs bash

## Gotchas conocidos

| Problema | Solución |
|----------|----------|
| `route:cache` rompe rutas Volt | Nunca ejecutar; Volt no soporta route caching |
| `make:livewire` crea stubs en `views/components/` | Borrar `⚡*.blade.php` en esa carpeta + limpiar caché Volt |
| `layouts.app` debe existir en dos lugares | `views/layouts/app.blade.php` Y `views/components/layouts/app.blade.php` |
| APP_KEY debe estar en dos .env | Root `.env` (para docker-compose) Y `laravel/.env` (para PHP) |
| MySQL en NTFS requiere flags | `--skip-ssl --sha256_password_auto_generate_rsa_keys=OFF ...` |
