<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app', ['title' => 'Ayuda'])] class extends Component {

    public string $tab = 'funcionalidades';

}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-condensed font-bold" style="color: var(--vd-text);">Ayuda</h1>
        <p class="text-sm mt-1" style="color: var(--vd-muted);">Referencia completa de funcionalidades del sistema.</p>
    </div>

    {{-- Tab pills --}}
    <div class="flex gap-2 mb-6">
        <button wire:click="$set('tab', 'funcionalidades')"
                class="px-5 py-2 rounded-full text-sm font-semibold transition-all"
                style="{{ $tab === 'funcionalidades'
                    ? 'background: var(--vd-green); color: #fff; box-shadow: 0 2px 8px rgba(78,158,90,0.3);'
                    : 'background: var(--vd-bg-2); color: var(--vd-text-soft); border: 1px solid var(--vd-bdr);' }}">
            Funcionalidades
        </button>
        <button wire:click="$set('tab', 'acercade')"
                class="px-5 py-2 rounded-full text-sm font-semibold transition-all"
                style="{{ $tab === 'acercade'
                    ? 'background: var(--vd-green); color: #fff; box-shadow: 0 2px 8px rgba(78,158,90,0.3);'
                    : 'background: var(--vd-bg-2); color: var(--vd-text-soft); border: 1px solid var(--vd-bdr);' }}">
            Acerca de
        </button>
    </div>

    {{-- ── Funcionalidades ── --}}
    @if($tab === 'funcionalidades')
    <div class="space-y-2 max-w-2xl" x-data>

        @php
        $secciones = [

            [
                'titulo' => 'Dashboard',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>',
                'items'  => [
                    'Panel central con métricas clave del día: conversaciones activas, órdenes pendientes, entregas y ventas.',
                    'Acceso rápido a las secciones más utilizadas del sistema.',
                    'Los datos se actualizan en tiempo real con Livewire sin necesidad de recargar la página.',
                    'La vista se adapta al rol: Admin ve métricas globales de todas las zonas; Responsable ve su zona.',
                ],
            ],

            [
                'titulo' => 'Conversaciones multicanal',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>',
                'items'  => [
                    'Bandeja unificada con mensajes de WhatsApp, Messenger e Instagram en una sola vista.',
                    'Identificación visual por canal: verde para WhatsApp, azul para Messenger, rosa para Instagram.',
                    'Filtros por canal, zona (Buenos Aires, Valle NQN/Roca, Córdoba, Mendoza) y estado (abierta, esperando, cerrada).',
                    'La vista de detalle muestra el historial completo de mensajes con fecha y hora de cada uno.',
                    'WhatsApp muestra el número de teléfono; Messenger e Instagram muestran el ID de usuario (PSID/IGSID).',
                    'Si el contacto tiene usuario registrado en el sistema, la ficha lo indica con un vínculo directo.',
                ],
            ],

            [
                'titulo' => 'Chat interno',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>',
                'items'  => [
                    'Mensajería directa entre los miembros del equipo (usuarios internos del sistema).',
                    'Badge con contador de mensajes no leídos visible en el menú lateral.',
                    'Los mensajes persisten en sesión y permiten coordinación sin salir del sistema.',
                    'Accesible para Colaborador, Responsable de Zona y Administrador.',
                ],
            ],

            [
                'titulo' => 'Asistente IA',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>',
                'items'  => [
                    'Chat con modelos de IA para consultas operativas, redacción de respuestas y análisis.',
                    'Soporta múltiples proveedores: Claude (Anthropic) y modelos locales vía Ollama.',
                    'Proveedor y modelo configurables desde Ajustes → Inteligencia Artificial.',
                    'Historial de conversación activo durante la sesión.',
                    'Acceso restringido a Administrador y Responsable de Zona.',
                ],
            ],

            [
                'titulo' => 'Zonas geográficas',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>',
                'items'  => [
                    'Gestión de las zonas de distribución activas: Buenos Aires, Valle NQN/Roca, Córdoba, Mendoza.',
                    'Cada zona almacena su menú semanal independiente (sincronizable desde Productos).',
                    'Configuración de dirección de referencia, coordenadas GPS y días de entrega por zona.',
                    'Las conversaciones y órdenes se asocian a una zona para filtrado y logística.',
                    'Lectura para todos los roles; edición para Admin y Responsable de Zona.',
                ],
            ],

            [
                'titulo' => 'Productos y menús semanales',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>',
                'items'  => [
                    'Catálogo de 5 tipos de menú semanal: Clásico, Vegano, Keto, Familiar y Diabético.',
                    'Cada menú tiene platos configurables de lunes a viernes con precios para porciones de 250g y 400g.',
                    '"Sincronizar menús en Zonas" copia el menú completo de la semana a las zonas seleccionadas.',
                    '"Sincronizar precios en Zonas" actualiza solo los precios sin tocar el contenido de los platos.',
                    'Las zonas destinatarias de la sincronización se configuran en Ajustes → Zonas para sincronización.',
                    'Edición restringida a Admin y Responsable de Zona.',
                ],
            ],

            [
                'titulo' => 'Órdenes',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>',
                'items'  => [
                    'Creá nuevas órdenes con "+ Nueva orden": cliente, zona, tipo de menú, porción y forma de pago.',
                    'Flujo de estados: Pendiente → Aprobada → En cocina → Lista para entregar → Entregada (o Cancelada).',
                    'Filtros por estado, zona, fecha o número de pedido para localizar órdenes rápidamente.',
                    'Admin y Responsable pueden cambiar el estado directamente desde el selector en la tabla.',
                    'Selección múltiple con checkboxes para confirmar un bloque de órdenes a cocina de una vez.',
                    '"Lista de ventas" genera un resumen imprimible del día cuando hay una zona filtrada activa.',
                ],
            ],

            [
                'titulo' => 'Cocina',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1A3.75 3.75 0 0012 18z"/>',
                'items'  => [
                    'Vista dedicada para el equipo de cocina: muestra únicamente las órdenes en estado "Aprobada".',
                    'Se actualiza automáticamente cada 10 segundos sin necesidad de recargar la página.',
                    'Notificación sonora (beep) y banner verde cuando llega un nuevo bloque de pedidos.',
                    'El botón "Lista para entregar" avanza la orden al siguiente estado del flujo.',
                    'Badge rojo en órdenes con más de 30 minutos en cocina sin ser procesadas.',
                    'Acceso principal para el rol "Cocina"; también visible para Admin y Responsable.',
                ],
            ],

            [
                'titulo' => 'Entregas y logística',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>',
                'items'  => [
                    'Filtrá por fecha y zona para ver las entregas programadas del día.',
                    'Mapa interactivo con marcadores en las coordenadas GPS de cada cliente.',
                    'Si una orden no tiene coordenadas, se ubica en el punto de referencia de la zona.',
                    'Geocodificación automática: obtiene coordenadas desde la dirección registrada del cliente.',
                    '"Imprimir" genera una hoja de ruta imprimible con la tabla de entregas ordenada.',
                    'Visible para Admin y Responsable de Zona.',
                ],
            ],

            [
                'titulo' => 'Redacción (mensajes masivos)',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>',
                'items'  => [
                    'Envío de mensajes a múltiples contactos de WhatsApp según zona y estado de conversación.',
                    'Variables de personalización: {{nombre}}, {{zona}} y {{ultimo_pedido}}.',
                    'Preview en tiempo real con los datos del primer destinatario de la lista seleccionada.',
                    'Selección de API de envío: Evolution API o META WhatsApp Business.',
                    'El botón de envío se activa cuando la integración de WhatsApp está configurada.',
                    'Acceso para Admin y Responsable de Zona.',
                ],
            ],

            [
                'titulo' => 'Estadísticas',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',
                'items'  => [
                    'Reportes de ventas, órdenes y conversaciones con filtros por período y zona.',
                    'Gráficos de evolución de pedidos, ingresos y canales de contacto activos.',
                    'Métricas de frecuencia de compra y retención de clientes por zona.',
                    'Acceso para Admin y Responsable de Zona.',
                ],
            ],

            [
                'titulo' => 'Clientes',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>',
                'items'  => [
                    'Directorio completo de clientes registrados con número de cliente, zona y datos de contacto.',
                    'Búsqueda por nombre, teléfono o zona.',
                    'Ficha individual con historial de pedidos y conversaciones vinculadas al contacto.',
                    'Alta de nuevos clientes desde la vista o vía portal de auto-registro (/unirme).',
                    'Acceso para Admin y Responsable de Zona.',
                ],
            ],

            [
                'titulo' => 'CRM de clientes',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6"/>',
                'items'  => [
                    'Dashboard de inteligencia comercial con métricas de retención y frecuencia de compra.',
                    'Segmentación de clientes por zona, actividad reciente y volumen de pedidos.',
                    'Identificación de clientes frecuentes, en riesgo de abandono y nuevos ingresos.',
                    'Enlace directo a la conversación activa de cada cliente.',
                    'Acceso para Admin y Responsable de Zona.',
                ],
            ],

            [
                'titulo' => 'Usuarios',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>',
                'items'  => [
                    'Gestión del equipo con 5 roles: Administrador, Responsable de Zona, Colaborador, Cocina y Cliente.',
                    'Alta con nombre, apellido, email, WhatsApp, ciudad, foto de perfil y contraseña.',
                    'Cambio de rol directo desde el selector en la tabla (exclusivo para Administrador).',
                    'Eliminación de usuario con borrado de foto asociada y confirmación modal.',
                    'Flujo especial de registro de colaboradores vía /usuarios/crear/colaborador.',
                    'Administración exclusiva para Administrador; visualización para Responsable de Zona.',
                ],
            ],

            [
                'titulo' => 'Marketing de redes',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/>',
                'items'  => [
                    'Módulos dedicados por canal: Email, WhatsApp, Facebook, Instagram y Otros.',
                    'Email: composición y envío de campañas de email marketing con plantillas.',
                    'WhatsApp: gestión de mensajes y templates para WhatsApp Business.',
                    'Facebook e Instagram: herramientas para publicaciones y seguimiento de campañas en redes sociales.',
                    'Acceso para Admin y Responsable de Zona.',
                ],
            ],

            [
                'titulo' => 'Ajustes del sistema',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
                'items'  => [
                    'Configuración de IA: proveedor (Claude / Ollama), modelo, y contexto para el chatbot y el asistente.',
                    'Selector de servidor activo: Alice (base de datos de pruebas) o Betty (base de datos de producción).',
                    'Sincronización completa Alice → Betty: zonas, productos y platos en un solo paso.',
                    'Selección de zonas para sincronización: define cuáles zonas reciben actualizaciones de menús y precios.',
                    'El badge ALICE/BETTY en la barra superior indica el servidor activo en todo momento.',
                    'Acceso exclusivo para Administrador y Responsable de Zona.',
                ],
            ],

            [
                'titulo' => 'Portal del cliente',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016 2.993 2.993 0 002.25-1.016 3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/>',
                'items'  => [
                    'Acceso exclusivo para clientes: visualización de sus pedidos y del menú de la semana.',
                    'Auto-registro de nuevos clientes vía /unirme sin intervención del equipo interno.',
                    'Historial de órdenes propias con estado actualizado de cada pedido.',
                    'Navegación simplificada con dock inferior específico para el rol Cliente.',
                ],
            ],

            [
                'titulo' => 'Mi cuenta',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/>',
                'items'  => [
                    'Edición del perfil personal: nombre, apellido, ciudad, WhatsApp y foto de perfil.',
                    'Cambio de contraseña con validación de la contraseña actual.',
                    'Accesible para todos los roles autenticados.',
                ],
            ],

            [
                'titulo' => 'Rastreador de enlaces',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>',
                'items'  => [
                    'Creá URLs cortas rastreables para incluir en mensajes y campañas de comunicación.',
                    'Cada enlace registra la cantidad de clics con fecha y hora de cada visita.',
                    'El acceso vía /ir/{enlace} redirige al destino y registra la visita automáticamente.',
                    'Útil para medir el alcance real de mensajes masivos, publicaciones y campañas.',
                    'Acceso para Admin y Responsable de Zona.',
                ],
            ],

            [
                'titulo' => 'Log de actividad',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                'items'  => [
                    'Registro cronológico de todas las acciones realizadas en el sistema.',
                    'Muestra usuario responsable, acción ejecutada, módulo afectado y marca de tiempo.',
                    'Filtros por usuario y tipo de acción para tareas de auditoría.',
                    'Visible exclusivamente para Administradores.',
                ],
            ],

        ];
        @endphp

        @foreach($secciones as $sec)
        <div class="card p-0 overflow-hidden" x-data="{ open: false }">
            <button @click="open = !open"
                    class="flex items-center gap-3 w-full px-5 py-4 text-left transition-colors"
                    :style="open ? 'background: rgba(78,158,90,0.06);' : 'background: var(--vd-bg-2);'">
                <svg width="18" height="18" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24" class="flex-shrink-0">
                    {!! $sec['icono'] !!}
                </svg>
                <span class="font-condensed font-bold text-base flex-1" style="color: var(--vd-text);">
                    {{ $sec['titulo'] }}
                </span>
                <svg :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                     style="width:14px;height:14px;flex-shrink:0;transition:transform 0.2s;color:var(--vd-muted-2);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-collapse>
                <ul class="px-5 py-4 space-y-2" style="border-top: 1px solid var(--vd-bdr-soft);">
                    @foreach($sec['items'] as $item)
                    <li class="flex items-start gap-2.5 text-sm" style="color: var(--vd-text-soft);">
                        <svg width="14" height="14" fill="none" stroke="#4e9e5a" stroke-width="2.5" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                        {{ $item }}
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endforeach

    </div>
    @endif

    {{-- ── Acerca de ── --}}
    @if($tab === 'acercade')
    <div class="max-w-xl space-y-5">

        {{-- Logo card --}}
        <div class="card text-center py-8 px-6">
            <div class="mx-auto mb-4" style="width: 96px; height: 96px;">
                <img src="/images/verdeo-logo.png" alt="Verdeo"
                     class="w-24 h-24 rounded-full object-cover"
                     style="filter: drop-shadow(0 4px 20px rgba(58,125,68,0.45));">
            </div>
            <h2 class="font-condensed font-bold text-2xl mb-1" style="color: var(--vd-text);">Verdeo</h2>
            <p class="text-xs font-mono mb-3" style="color: var(--vd-muted);">v0.2.0 — Mayo 2026</p>
            <p class="text-sm leading-relaxed" style="color: var(--vd-text-soft);">
                Sistema integral de gestión comercial y logística para Verdeo. Centraliza pedidos, conversaciones multicanal, entregas, comunicación masiva e inteligencia de clientes en una sola plataforma.
            </p>
        </div>

        {{-- Stack --}}
        <div class="card">
            <h3 class="font-condensed font-bold text-base mb-3" style="color: var(--vd-text);">Stack tecnológico</h3>
            <div class="grid grid-cols-2 gap-2">
                @foreach([
                    ['Laravel 10',      '#f05340'],
                    ['Livewire 4.3',    '#fb70a9'],
                    ['Alpine.js',       '#77c1d2'],
                    ['Tailwind CSS',    '#38bdf8'],
                    ['MySQL 8',         '#4479a1'],
                    ['Redis',           '#dc382d'],
                    ['Docker',          '#2496ed'],
                    ['n8n',             '#ea4b71'],
                    ['Evolution API',   '#25d366'],
                    ['Claude API',      '#7c3aed'],
                    ['Ollama',          '#6b7280'],
                ] as [$tech, $color])
                <div class="flex items-center gap-2 text-sm px-3 py-2 rounded-lg"
                     style="background: var(--vd-bg-2); border: 1px solid var(--vd-bdr-soft);">
                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background: {{ $color }};"></span>
                    <span style="color: var(--vd-text-soft);">{{ $tech }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Credits --}}
        <div class="card">
            <h3 class="font-condensed font-bold text-base mb-3" style="color: var(--vd-text);">Créditos</h3>
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-sm flex-shrink-0"
                         style="background: linear-gradient(135deg, #3a7d44, #4e9e5a);">S</div>
                    <div>
                        <p class="font-semibold text-sm" style="color: var(--vd-text);">Santiago Creide</p>
                        <p class="text-xs" style="color: var(--vd-muted);">Desarrollador principal</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-sm flex-shrink-0"
                         style="background: linear-gradient(135deg, #7c3aed, #a855f7);">A</div>
                    <div>
                        <p class="font-semibold text-sm" style="color: var(--vd-text);">Claude (Anthropic)</p>
                        <p class="text-xs" style="color: var(--vd-muted);">Co-desarrollador IA</p>
                    </div>
                </div>
            </div>
            <p class="mt-4 text-xs" style="color: var(--vd-muted-2);">
                Desarrollado con ❤️ en Argentina · 2026
            </p>
        </div>

    </div>
    @endif

</div>
