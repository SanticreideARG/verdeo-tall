# Manual breve de Verdeo

**Estado al 4 de agosto de 2026.**

Este documento es para orientar el proyecto sin entrar en detalles técnicos innecesarios. Para comandos avanzados, backups o solución de errores, consultar el [runbook técnico](runbook-operativo.md).

## Qué es Verdeo

Verdeo es el sistema de gestión de una empresa de viandas saludables. Su eje es conectar en un mismo lugar:

- conversaciones con clientes;
- pedidos y producción;
- clientes, zonas y entregas;
- campañas y seguimiento comercial;
- automatizaciones e inteligencia artificial.

La meta es que una conversación de WhatsApp, email u otro canal pueda terminar en un pedido trazable sin copiar información manualmente entre sistemas.

## Decisión tecnológica

El destino del proyecto es **TypeScript + Node.js + Fastify + PostgreSQL + Redis**.

Laravel no se elimina de inmediato. Se mantiene funcionando mientras cada módulo se reemplaza de forma gradual y reversible. Esto evita una reescritura total con meses sin entregar valor.

PostgreSQL será la base principal. No necesitamos MongoDB por ahora: conversaciones, clientes y pedidos tienen relaciones y transacciones importantes. PostgreSQL puede almacenar datos variables de cada canal mediante `jsonb` y escalar el historial de mensajes con índices y particiones.

Los archivos adjuntos deberán guardarse fuera de la base, en almacenamiento de objetos compatible con S3.

## Cómo está compuesto hoy

```text
Usuarios
   ↓
Nginx
   ├── Laravel → panel actual → MySQL
   └── API TypeScript → mensajería nueva → PostgreSQL

WhatsApp → Evolution API → API TypeScript → PostgreSQL → Outbox
                                      └── futuras automatizaciones
```

| Componente | Para qué se usa |
| --- | --- |
| Laravel + MySQL | Aplicación actual y fuente de verdad durante la transición |
| API TypeScript | Backend nuevo y recepción de eventos de mensajería |
| PostgreSQL | Conversaciones y mensajes normalizados |
| Redis | Sesiones, caché y coordinación |
| Evolution API | Conexión con WhatsApp |
| n8n | Automatizaciones futuras |
| Nginx | Entrada única al sistema |

## Estado actual

| Área | Estado |
| --- | --- |
| Entorno Docker y WSL | Funciona |
| Login Laravel | Funciona; el error 419 fue corregido |
| API TypeScript | Funciona y tiene controles de salud |
| Esquema PostgreSQL de mensajería | Implementado |
| Migración histórica | 142 conversaciones, 142 contactos y 528 mensajes reconciliados |
| Webhook de Evolution | Implementado e idempotente |
| Lectura desde PostgreSQL | Implementada detrás de una configuración reversible |
| Línea real de WhatsApp | Pendiente de conectar |
| Procesamiento de outbox | Pendiente |
| Migración de pedidos | Pendiente |
| VPS productivo | Pendiente |

Actualmente las conversaciones se siguen leyendo desde MySQL. PostgreSQL está preparado y probado, pero se mantiene como sistema sombra hasta completar las pruebas reales.

## Accesos locales

| Servicio | Dirección |
| --- | --- |
| Aplicación | `http://localhost:8888` |
| API TypeScript | `http://localhost:3000` |
| Evolution API | `http://localhost:8080` |
| n8n | `http://localhost:5678` |

El código está en:

```text
/home/screide/proyectos/verdeo-tall
```

La rama de trabajo actual es:

```text
codex/migrate-typescript
```

## Uso cotidiano

Desde PowerShell:

```powershell
$repo = '\\wsl.localhost\Ubuntu-22.04\home\screide\proyectos\verdeo-tall'
$compose = Join-Path $repo 'docker-compose.yml'
$dotenv = Join-Path $repo '.env'
```

Arrancar el sistema:

```powershell
docker compose -f $compose --env-file $dotenv up -d
```

Ver el estado:

```powershell
docker compose -f $compose --env-file $dotenv ps
Invoke-RestMethod 'http://localhost:3000/v1/ready'
```

Detenerlo sin borrar datos:

```powershell
docker compose -f $compose --env-file $dotenv stop
```

Si algo falla, consultar primero:

```powershell
docker compose -f $compose --env-file $dotenv logs --tail 100
```

## Reglas importantes

- No ejecutar `docker compose down -v`: elimina volúmenes de datos.
- No regenerar `APP_KEY` para corregir un error de login.
- No guardar `.env` ni credenciales en Git.
- No retirar MySQL todavía.
- No activar PostgreSQL como fuente definitiva sólo porque el backfill coincide.
- No exponer públicamente MySQL, PostgreSQL, Redis, Evolution o n8n.
- Antes de cambios de infraestructura, crear y comprobar un backup.

## Qué sigue

El orden recomendado es:

1. Completar la API de conversaciones: detalle, historial paginado, participantes y asignaciones.
2. Agregar reconciliación automática, métricas y alertas.
3. Implementar el procesador de outbox con reintentos.
4. Conectar la línea real de WhatsApp y probar mensajes y adjuntos.
5. Preparar CI/CD y el despliegue seguro al VPS.
6. Migrar pedidos y su vínculo con las conversaciones.
7. Migrar autenticación y pantallas restantes.
8. Retirar Laravel y MySQL cuando exista paridad y rollback probado.

Mientras la línea telefónica no esté disponible, se puede avanzar con los puntos 1, 2, 3 y parte del 5.

## Cuando esté disponible la línea de WhatsApp

El objetivo de la prueba será confirmar:

- conexión estable de la instancia;
- mensajes entrantes y salientes;
- estados enviado, entregado y leído;
- mensajes duplicados o fuera de orden;
- imágenes, audios y documentos;
- creación correcta de conversación, participante, mensaje y evento de outbox;
- ausencia de credenciales dentro de los eventos almacenados.

La conexión por QR y las consultas de verificación están detalladas en el [runbook técnico](runbook-operativo.md#5-crear-y-conectar-la-instancia-de-whatsapp).

## Decisiones que todavía debemos tomar

- Proveedor, capacidad y dominio del VPS.
- Proveedor de email para el canal omnicanal.
- Almacenamiento de adjuntos.
- Política de retención de mensajes y datos personales.
- Alcance exacto de roles y permisos en la nueva aplicación.
- Momento en que PostgreSQL pasará de sombra a fuente principal.

## Referencias del proyecto

- [Roadmap técnico de la migración](migracion-typescript.md)
- [Arquitectura detallada](arquitectura.md)
- [Runbook técnico y solución de problemas](runbook-operativo.md)

Este manual debe reflejar decisiones y estado general. Los comandos extensos, procedimientos de recuperación y detalles internos pertenecen al runbook.
