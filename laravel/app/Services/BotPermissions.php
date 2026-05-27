<?php

namespace App\Services;

use App\Models\Setting;

/**
 * BotPermissions — catálogo canónico de capacidades del agente IA.
 *
 * Fuente única de verdad. Todos los lugares que necesiten saber qué puede
 * hacer el bot deben consultar esta clase, no leer Settings directamente.
 *
 * Claves de Setting: "bot_{capability}" → "1" | "0"
 */
class BotPermissions
{
    /* ── Status constants ──────────────────────────────────────────────────── */

    /** Funciona hoy, sin dependencias externas pendientes. */
    const STATUS_OPERATIVO      = 'operativo';

    /** Funciona cuando las dependencias listadas estén configuradas. */
    const STATUS_REQUIERE       = 'requiere_config';

    /** Planificado — no implementado aún, el toggle está deshabilitado en UI. */
    const STATUS_PLANIFICADO    = 'planificado';

    /* ── Catálogo ──────────────────────────────────────────────────────────── */

    /**
     * Returns the full capability catalog, grouped by category.
     *
     * Each item:
     *   key         → short slug used for Setting key and $botPermisos array
     *   label       → display name
     *   descripcion → one-sentence explanation shown in UI
     *   status      → operativo | requiere_config | planificado
     *   requiere    → string[] list of dependencies (shown in UI)
     */
    public static function catalog(): array
    {
        return [

            'conversaciones' => [
                'label' => 'Conversaciones',
                'color' => '#4e9e5a',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>',
                'items' => [
                    'sugerir_respuestas' => [
                        'label'       => 'Sugerir respuestas',
                        'descripcion' => 'En el panel de conversaciones, el bot propone respuestas al último mensaje del cliente con un solo clic.',
                        'status'      => self::STATUS_OPERATIVO,
                        'requiere'    => ['API key IA configurada'],
                    ],
                    'clasificar_conversaciones' => [
                        'label'       => 'Clasificar conversaciones',
                        'descripcion' => 'Analiza cada mensaje entrante y asigna una categoría automática: pedido, consulta, queja o cancelación.',
                        'status'      => self::STATUS_REQUIERE,
                        'requiere'    => ['Webhook Evolution API activo'],
                    ],
                    'responder_automaticamente' => [
                        'label'       => 'Responder automáticamente',
                        'descripcion' => 'El bot responde sin intervención humana a consultas frecuentes: menú semanal, precios, zonas de entrega y horarios.',
                        'status'      => self::STATUS_REQUIERE,
                        'requiere'    => ['Webhook Evolution API activo', 'Flujo n8n configurado'],
                    ],
                ],
            ],

            'pedidos' => [
                'label' => 'Pedidos',
                'color' => '#f59e0b',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z"/>',
                'items' => [
                    'extraer_pedidos' => [
                        'label'       => 'Extraer pedidos de texto libre',
                        'descripcion' => 'Interpreta mensajes en lenguaje natural y extrae menú, tamaño y forma de pago estructurados.',
                        'status'      => self::STATUS_REQUIERE,
                        'requiere'    => ['Webhook Evolution API activo'],
                    ],
                    'crear_ordenes' => [
                        'label'       => 'Crear órdenes automáticamente',
                        'descripcion' => 'Registra la orden en el sistema directamente desde el chat, sin intervención humana.',
                        'status'      => self::STATUS_PLANIFICADO,
                        'requiere'    => ['Extracción de pedidos activa', 'Webhook Evolution API activo'],
                    ],
                    'confirmar_ordenes' => [
                        'label'       => 'Confirmar órdenes por WhatsApp',
                        'descripcion' => 'Envía un mensaje de confirmación al cliente con detalle del pedido y número de orden.',
                        'status'      => self::STATUS_PLANIFICADO,
                        'requiere'    => ['Creación de órdenes activa', 'MessageDispatcher configurado'],
                    ],
                ],
            ],

            'contenido' => [
                'label' => 'Contenido y redacción',
                'color' => '#a855f7',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>',
                'items' => [
                    'redactar_marketing' => [
                        'label'       => 'Asistir redacción de campañas',
                        'descripcion' => 'En /marketing, genera textos de campañas según el producto, zona, tono y canal seleccionados.',
                        'status'      => self::STATUS_OPERATIVO,
                        'requiere'    => ['API key IA configurada'],
                    ],
                    'sugerir_precios' => [
                        'label'       => 'Sugerir precios de menús',
                        'descripcion' => 'Al crear o editar un producto, propone precios de 250kcal y 400kcal basados en el contexto del menú.',
                        'status'      => self::STATUS_OPERATIVO,
                        'requiere'    => ['API key IA configurada'],
                    ],
                    'generar_descripciones' => [
                        'label'       => 'Generar descripciones de menús',
                        'descripcion' => 'Redacta descripciones atractivas para cada menú basándose en sus ingredientes y beneficios.',
                        'status'      => self::STATUS_PLANIFICADO,
                        'requiere'    => ['API key IA configurada'],
                    ],
                ],
            ],

            'analisis' => [
                'label' => 'Análisis e inteligencia',
                'color' => '#60a5fa',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>',
                'items' => [
                    'analizar_estadisticas' => [
                        'label'       => 'Análisis de estadísticas en lenguaje natural',
                        'descripcion' => 'En /estadisticas genera un párrafo de análisis: ventas, tendencias, zona más activa y menú estrella.',
                        'status'      => self::STATUS_OPERATIVO,
                        'requiere'    => ['API key IA configurada'],
                    ],
                    'detectar_anomalias' => [
                        'label'       => 'Detectar pedidos anómalos',
                        'descripcion' => 'Marca automáticamente órdenes con datos sospechosos (precio, zona, menú) para revisión del equipo.',
                        'status'      => self::STATUS_PLANIFICADO,
                        'requiere'    => ['API key IA configurada'],
                    ],
                    'predecir_demanda' => [
                        'label'       => 'Predecir demanda semanal',
                        'descripcion' => 'Basado en historial de órdenes, estima cuántos pedidos se esperan por zona la próxima semana.',
                        'status'      => self::STATUS_PLANIFICADO,
                        'requiere'    => ['Mínimo 30 días de datos históricos', 'API key IA configurada'],
                    ],
                ],
            ],

            'automatizacion' => [
                'label' => 'Automatización programada',
                'color' => '#f97316',
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
                'items' => [
                    'resumen_diario' => [
                        'label'       => 'Resumen diario automático',
                        'descripcion' => 'A las 20hs ejecuta un análisis del día y lo muestra como notificación en el dashboard.',
                        'status'      => self::STATUS_PLANIFICADO,
                        'requiere'    => ['Scheduler activo', 'API key IA configurada'],
                    ],
                    'reporte_semanal' => [
                        'label'       => 'Reporte semanal',
                        'descripcion' => 'Cada lunes genera un informe comparativo de la semana anterior vs. la anterior.',
                        'status'      => self::STATUS_PLANIFICADO,
                        'requiere'    => ['Scheduler activo', 'API key IA configurada'],
                    ],
                    'seguimiento_clientes' => [
                        'label'       => 'Seguimiento automático de clientes',
                        'descripcion' => 'Detecta clientes sin actividad en 14+ días y notifica al equipo para hacer seguimiento.',
                        'status'      => self::STATUS_PLANIFICADO,
                        'requiere'    => ['Scheduler activo', 'MessageDispatcher configurado'],
                    ],
                ],
            ],

        ];
    }

    /* ── Helpers ───────────────────────────────────────────────────────────── */

    /**
     * Flat list of all capability keys across all groups.
     * @return string[]
     */
    public static function allKeys(): array
    {
        $keys = [];
        foreach (static::catalog() as $group) {
            $keys = array_merge($keys, array_keys($group['items']));
        }
        return $keys;
    }

    /**
     * Default enabled state for each capability.
     * Planificado → always false (not togglable).
     * Others → false by default (admin must explicitly enable).
     */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (static::catalog() as $group) {
            foreach ($group['items'] as $key => $item) {
                $defaults[$key] = false;
            }
        }
        return $defaults;
    }

    /**
     * Check if a specific bot capability is enabled.
     * Always returns false for planificado capabilities.
     */
    public static function can(string $capability): bool
    {
        // Find the item and check status
        foreach (static::catalog() as $group) {
            if (isset($group['items'][$capability])) {
                if ($group['items'][$capability]['status'] === self::STATUS_PLANIFICADO) {
                    return false;
                }
                break;
            }
        }

        return (bool) Setting::get('bot_' . $capability, '0');
    }

    /**
     * Map of all capability keys to their current enabled state.
     */
    public static function currentState(): array
    {
        $state = [];
        foreach (static::allKeys() as $key) {
            $state[$key] = static::can($key);
        }
        return $state;
    }

    /**
     * Toggle a capability on/off and persist to Setting.
     * Planificado capabilities cannot be toggled.
     *
     * @return bool New state after toggle.
     */
    public static function toggle(string $capability): bool
    {
        foreach (static::catalog() as $group) {
            if (isset($group['items'][$capability])) {
                if ($group['items'][$capability]['status'] === self::STATUS_PLANIFICADO) {
                    return false;
                }
                break;
            }
        }

        $current = (bool) Setting::get('bot_' . $capability, '0');
        $new     = ! $current;
        Setting::set('bot_' . $capability, $new ? '1' : '0');
        return $new;
    }
}
