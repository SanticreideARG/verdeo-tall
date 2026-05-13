# Verdeo — Panel de Administración

Panel interno para la gestión de pedidos, clientes y automatización WhatsApp de Verdeo, empresa argentina de comida saludable.

## Stack

- **Laravel 10** + **Livewire v4.3.0 Volt** (SFCs) + **Alpine.js** + **Tailwind v3**
- **MySQL 8** · **Redis** · **Laravel Horizon** (queue worker)
- **Ollama** (llama3.2) — IA para sugerencias de respuesta y chat interno
- **n8n** + **Evolution API** — automatización WhatsApp

## Levantar el entorno

```bash
# En WSL Ubuntu-22.04
cd ~/proyectos/verdeo-tall
docker compose up -d
```

- App: http://localhost:8888
- Admin: `admin@verdeo.com.ar` / `verdeo2026`
- n8n: http://localhost:5678
- Horizon: http://localhost:8888/horizon

## Arquitectura

Ver [`docs/arquitectura.md`](../docs/arquitectura.md) para la estructura completa de archivos, rutas, modelos y el módulo de IA.

## Módulos

| Módulo | Estado |
|--------|--------|
| Auth (login/logout) | ✅ |
| Dashboard | ✅ |
| Conversaciones + Sugerir respuesta IA | ✅ |
| Productos (Menús + Precios por tamaño) | ✅ |
| Órdenes (tamaño / forma de pago / precio auto) | ✅ |
| Usuarios (gestión de roles, fichas) | ✅ |
| Asistente IA `/ai` (chat interno Ollama) | ✅ |
| Zonas / Estadísticas / Marketing | ✅ estructura, ⏳ datos reales |
| Evolution API — instancias WhatsApp | ⏳ pendiente |
| n8n — flujos de clasificación y respuesta | ⏳ pendiente |
| SSL / producción | ⏳ pendiente |

## Comandos útiles

```bash
# Artisan dentro del contenedor
docker exec verdeo_laravel php artisan migrate
docker exec verdeo_laravel php artisan db:seed
docker exec verdeo_laravel php artisan view:clear
docker exec verdeo_laravel php artisan config:clear

# Ollama
docker exec verdeo_ollama ollama list
docker exec verdeo_ollama ollama run llama3.2

# Logs
docker logs verdeo_laravel -f
docker logs verdeo_queue -f
```

## Notas de desarrollo

- **Blade complejos**: usar scripts Python en `/tmp/` para escribirlos (evita conflictos de quoting con `{{ }}`, `@if`, `<?php` en heredocs bash).
- **No usar `route:cache`**: Volt no soporta route caching.
- **`make:livewire` crea stubs**: borrar `⚡*.blade.php` en `resources/views/components/` si aparecen componentes en blanco.
