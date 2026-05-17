# Verdeo TALL — Backlog del agente

> Agente autónomo: leer el primer `- [ ]`, implementarlo, marcarlo `- [x]`.

---

## Dashboard (`resources/views/livewire/dashboard.blade.php`)

- [x] Widget "Hoy": mostrar en una fila de 3 tarjetas — órdenes creadas hoy, conversaciones nuevas hoy, ingresos del día (suma de órdenes `entregada` de hoy).
- [x] Widget "Ventas por zona": tabla compacta con las 4 zonas, total de órdenes y suma de ingresos en los últimos 30 días por zona.
- [x] Widget "Últimas conversaciones": lista de las 5 conversaciones más recientes con link a `/conversaciones/{id}`.
- [x] Quick actions: fila de 3 botones — "Nueva orden", "Ver pendientes", "Ir a estadísticas".

## Estadísticas (`resources/views/livewire/estadisticas/index.blade.php`)

- [x] Gráfico de barras SVG puro (sin librería): órdenes por día en el período seleccionado. Calcular con `Orden::selectRaw('DATE(created_at) as dia, count(*) as total')->whereBetween('created_at', [$desde, $hasta])->groupBy('dia')->orderBy('dia')->get()`. Renderizar como barras SVG proporcionales al máximo del período.
- [x] Tabla "Productos más vendidos": `OrdenItem::selectRaw('producto_id, tamano, sum(cantidad) as total_unidades, sum(subtotal) as total_pesos')->groupBy('producto_id','tamano')->orderByDesc('total_unidades')->with('producto:id,tipo')->get()`. Mostrar tipo + tamaño + unidades + total $, en el período seleccionado.
- [x] Fila de ingresos por zona: misma estructura que el widget del dashboard pero usando el período seleccionado del selector 7d/30d/mes_actual.

---

## Sistema — Backups (`resources/views/livewire/sistema/index.blade.php`)

- [x] Agregar sección "Backups de base de datos" a la página de Sistema (admin only). UI: tabla vacía con columnas Fecha, Tipo, Tamaño, Estado, Acciones. Botones de acción futura: "Exportar usuarios y teléfonos" (badge `próximamente`), "Exportar historial de mensajes" (badge `próximamente`), "Backup completo" (badge `próximamente`). Mostrar texto explicativo: "Los backups incluirán datos de clientes, números de WhatsApp e historial de conversaciones." No implementar la lógica de backup todavía — solo el panel de UI con estado vacío y estructura preparada.

## Ajustes — Múltiples números por zona (`resources/views/livewire/ajustes/index.blade.php`)

- [x] En la sección "Números de WhatsApp por zona", reemplazar el campo único por zona por una lista dinámica: cada zona muestra sus números actuales como chips/tags con botón ✕ para eliminar, y un input + botón "Agregar" para añadir números adicionales. Los números se guardan en Setting como JSON array (clave: `wa_bsas`, `wa_valle_nqn`, `wa_cordoba`, `wa_mendoza`). Ejemplo: `Setting::set('wa_bsas', json_encode(['5491158393179', '5491167890123']))`. En el PHP del componente: cambiar los 4 campos string a arrays, actualizar `mount()` para decodificar JSON, agregar métodos `agregarNumero(string $zona)` y `quitarNumero(string $zona, int $idx)`.

## Redacción — Mensajes masivos (nueva página)

- [x] Crear ruta en `routes/web.php`: `Volt::route('/redaccion', 'redaccion.index')->name('redaccion');`. Agregar link en sidebar (layouts/app.blade.php) con ícono de lápiz, visible para admin y responsable_zona.
- [x] Crear componente Volt SFC en `resources/views/livewire/redaccion/index.blade.php`. Secciones: (1) **Destinatarios**: selector de zona + filtro por categoría (Todos / Pendiente / Consulta / Entregado) que carga lista de contactos de Conversacion con su nombre y teléfono; (2) **Redacción**: textarea con variables disponibles: `{{nombre}}`, `{{ultimo_pedido}}`, `{{zona}}`; preview en vivo que reemplaza las variables con datos del primer destinatario de la lista; (3) **API de envío**: selector pill entre "Evolution API" y "META (WhatsApp Business)"; (4) **Botón enviar**: muestra confirmación con conteo de destinatarios antes de enviar. No implementar el envío real todavía (botón con estado `próximamente`). Implementar solo la UI completa con preview funcional.

## Cocina — Flujo de confirmación y vista en tiempo real

### Contexto del flujo
`pendiente` → responsable_zona confirma bloque por zona → `aprobada` → cocina prepara → `lista_para_entrega` → se entrega → `entregada`.
La cocina solo ve órdenes en estado `aprobada`. El responsable confirma en bloque (todas las pendientes de su zona, o una selección).

- [x] **Rol cocina en User**: En `app/Models/User.php`, agregar `'cocina' => 'Cocina'` al array `rolesLabels()`. Agregar método `isCocina(): bool { return $this->role === 'cocina'; }`. En `resources/views/livewire/usuarios/crear-colaborador.blade.php`, agregar la opción `cocina` al select de roles.

- [x] **Confirmación de bloque en órdenes**: En `resources/views/livewire/ordenes/index.blade.php`, para usuarios `isResponsableZona()` o `isAdmin()`: agregar columna de checkboxes al inicio de la tabla, una propiedad `$seleccionados = []` (array de IDs), y un botón "Confirmar bloque para cocina" que aparece cuando hay al menos uno seleccionado. Al confirmar, ejecutar `Orden::whereIn('id', $this->seleccionados)->where('estado', 'pendiente')->update(['estado' => 'aprobada'])` y limpiar selección. Agregar también un botón secundario "Confirmar todos los pendientes de [zona]" que selecciona y confirma todos los `pendiente` de la zona del usuario en un click.

- [x] **Ruta y componente cocina**: Agregar en `routes/web.php`: `Volt::route('/cocina', 'cocina.index')->name('cocina');`. Agregar link en sidebar de `layouts/app.blade.php` visible para `isCocina()`, `isAdmin()` y `isResponsableZona()`, con ícono de fuego/chef. Crear `resources/views/livewire/cocina/index.blade.php` como Volt SFC con `wire:poll.10s` para actualización automática cada 10 segundos. El componente muestra las órdenes en estado `aprobada`, agrupadas por zona. Cada orden muestra: número de orden, cliente (nombre + teléfono), productos con tamaño y cantidad, notas, tiempo desde que fue aprobada (badge rojo si > 30 min). Botón "Lista para entregar" por orden que cambia estado a `lista_para_entrega`.

- [x] **Sidebar restringido para cocina**: En `layouts/app.blade.php`, para usuarios cuyo único rol es `cocina`, ocultar los links de Conversaciones, Usuarios, Estadísticas, Ajustes, Sistema. Mostrar solo: Cocina (principal) y Mi cuenta. Usar `@if(!auth()->user()->isCocina())` para envolver los links que no corresponden.

- [x] **Indicador de nuevas órdenes en cocina**: En la vista cocina, usar Alpine para comparar el count de órdenes entre polls. Si el count aumentó respecto al último valor conocido, mostrar un banner verde "¡Nuevo bloque recibido!" con animación y reproducir un sonido corto (Web Audio API: `new AudioContext().createOscillator()` — beep de 200ms). Guardar el count anterior en `x-data="{ prevCount: {{ $ordenes->count() }}, checkNew(newCount) { if(newCount > this.prevCount) { this.showBanner = true; this.playBeep(); setTimeout(()=>this.showBanner=false, 4000); } this.prevCount = newCount; }, showBanner: false, playBeep() { ... } }"`.

## Hojas de ruta — Entregas y documentos de cierre

- [x] **Lista de Ventas (cierre de pedidos)**: En `ordenes/index.blade.php`, agregar botón "Lista de ventas" (visible con filtro de zona activo, para admin/responsable). Abre modal Alpine con tabla imprimible: encabezado "Lista de Ventas — Zona X — Fecha DD/MM/YYYY", filas con N° Pedido + Menús (tipo + tamaño), total de unidades al pie. Datos: `Orden::whereDate('created_at', $fecha)->where('zona', $zona)->with(['items.producto','cliente'])->get()`. Botón "Imprimir" → `window.print()` con `@media print` que oculta todo excepto el modal.

- [x] **Hoja de Ruta (entregas)**: Crear ruta `Volt::route('/entregas', 'entregas.index')->name('entregas')` y link en sidebar con ícono de mapa. Crear `resources/views/livewire/entregas/index.blade.php`. Selectores: fecha (default hoy) + zona. Tabla: N° Pedido, Cliente (nombre + teléfono), Dirección, Coordenadas, Menús, Precio, Forma de pago. Mapa Leaflet CDN con marcadores: si la orden tiene `latitud`/`longitud` usarlos; si no, usar coords de referencia por zona (BSAS:-34.60,-58.38; Valle NQN:-38.95,-68.07; Córdoba:-31.42,-64.18; Mendoza:-32.89,-68.84). Botón "Imprimir" → `window.print()` con tabla sola.

- [x] **Geocoding Nominatim**: crear `app/Services/GeocodingService.php` con `geocodificar(string $direccion): ?array`. Botón "Geocodificar" por orden en la vista entregas.

---

## Portal de Clientes

- [x] **Registro externo de clientes** (`/unirme`): Volt SFC con layout.guest. Formulario: nombre, apellido, email, contraseña, WhatsApp, zona, dirección habitual. Crea User role=cliente con numero_cliente. Auto-login + redirect a /portal.
- [x] **Portal de clientes** (`/portal`): Vista mobile-first para rol cliente. Muestra catálogo de productos con selector de tamaño y botón "Agregar al pedido". Formulario de pedido inline (dirección, forma de pago, notas). Lista de mis pedidos con estado. Acceso rápido al asistente IA. Mobile dock actualizado para mostrar Portal en slot 1.
- [x] **Post-pedido**: Enviar notificación WhatsApp al responsable de zona cuando un cliente hace un pedido desde el portal.
- [x] **Estado en tiempo real**: En portal, usar wire:poll.30s para actualizar estado de pedidos activos sin recargar página.

## Ajustes — Configuración de API WhatsApp

- [x] **Sección WhatsApp API en Ajustes**: agregar nueva sección "API de WhatsApp" en `ajustes/index.blade.php`. Dos proveedores con UI de selector pill: **Evolution API** y **META (WhatsApp Business)**. Para Evolution API: campo URL de instancia (`wa_evolution_url`, ej: `http://localhost:8080`), campo API Key (`wa_evolution_key`, manejo seguro igual que las API keys de IA — no se devuelve al frontend, botón "Cambiar"), selector de instancia por zona (los nombres de instancia de Evolution corresponden a cada zona). Para META: campo App ID (`wa_meta_app_id`), campo Token (`wa_meta_token`, secure), campo Phone Number ID por zona (`wa_meta_phone_id_bsas`, etc). Guardar proveedor activo en `wa_proveedor` (evolution/meta). Testear conexión: botón "Verificar conexión" por proveedor que llame a un método Livewire con `Http::get($url.'/instance/fetchInstances', ['apikey'=>$key])` para Evolution o `Http::get('https://graph.facebook.com/v19.0/me', ['access_token'=>$token])` para META, y muestre badge verde/rojo con el resultado.

## Ajustes — Selector de tema visual

- [x] **Tema visual en Ajustes**: agregar sección "Apariencia" antes del formulario principal. Mostrar 5 opciones como cards clicables de 60x40px mostrando un preview de colores: **Bosque** (actual — dark green), **Carbón** (dark neutral gray), **Aurora** (dark purple), **Cielo** (light blue), **Natural** (light green). Guardar en `Setting::set('tema', $tema)`. En `layouts/app.blade.php`, agregar `data-theme="{{ Setting::get('tema', 'bosque') }}"` al `<html>`. Definir las variables CSS de cada tema en `<style>` dentro del layout usando el selector `[data-theme="bosque"]`, `[data-theme="carbon"]`, etc. Para Carbón: verde → gris azulado (#6b7280). Para Aurora: verde → violeta (#a855f7). Para Cielo: fondo claro (#f0f4f8), texto oscuro. Para Natural: fondo claro (#f0f5f0), acento verde pero claro.

## Sistema — Sección Ayuda y Acerca de

- [x] **Ruta y link**: agregar `Volt::route('/ayuda', 'ayuda.index')->name('ayuda')` en `routes/web.php`. Agregar link en sidebar al pie, con ícono de interrogación, visible para todos.
- [x] **Componente ayuda**: crear `resources/views/livewire/ayuda/index.blade.php`. Dos tabs: "Guía de uso" y "Acerca de". **Guía de uso**: secciones plegables (Alpine x-show) con instrucciones básicas para cada módulo (Conversaciones, Órdenes, Cocina, Entregas, Redacción). **Acerca de**: card con logo Verdeo, versión del sistema (v0.1.0 — Mayo 2026), descripción del proyecto ("Sistema de gestión de ventas y logística para Verdeo"), y sección de créditos: "Desarrollado por Santiago Creide · Co-desarrollado con Claude (Anthropic) · Stack: Laravel 10 + Livewire 4.3 + Tailwind CSS + Alpine.js · Infraestructura: Docker, n8n, Evolution API, Ollama".
