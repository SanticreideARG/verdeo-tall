# Verdeo

Sistema de gestión para conversaciones, clientes, pedidos, producción, entregas y automatizaciones de Verdeo.

## Estado

La aplicación Laravel continúa operativa mientras el backend se migra gradualmente a TypeScript y PostgreSQL. La mensajería nueva ya dispone de esquema normalizado, backfill, webhooks de Evolution y lectura PostgreSQL reversible. La conexión con la línea real de WhatsApp está pendiente.

## Documentación

- [Manual breve del proyecto](docs/instructivo-operativo.md)
- [Roadmap de migración](docs/migracion-typescript.md)
- [Runbook técnico](docs/runbook-operativo.md)
- [Arquitectura detallada](docs/arquitectura.md)

## Acceso local

```text
Aplicación: http://localhost:8888
API:        http://localhost:3000
Evolution:  http://localhost:8080
n8n:        http://localhost:5678
```

La fuente de verdad continúa siendo MySQL hasta completar las pruebas reales, la observabilidad y el procedimiento de corte.
