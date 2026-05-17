<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app', ['title' => 'Ayuda'])] class extends Component {

    public string $tab = 'guia';

}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-condensed font-bold" style="color: var(--vd-text);">Ayuda</h1>
        <p class="text-sm mt-1" style="color: var(--vd-muted);">Guía de uso y créditos del sistema.</p>
    </div>

    {{-- Tab pills --}}
    <div class="flex gap-2 mb-6">
        <button wire:click="$set('tab', 'guia')"
                class="px-5 py-2 rounded-full text-sm font-semibold transition-all"
                style="{{ $tab === 'guia'
                    ? 'background: var(--vd-green); color: #fff; box-shadow: 0 2px 8px rgba(78,158,90,0.3);'
                    : 'background: var(--vd-bg-2); color: var(--vd-text-soft); border: 1px solid var(--vd-bdr);' }}">
            Guía de uso
        </button>
        <button wire:click="$set('tab', 'acercade')"
                class="px-5 py-2 rounded-full text-sm font-semibold transition-all"
                style="{{ $tab === 'acercade'
                    ? 'background: var(--vd-green); color: #fff; box-shadow: 0 2px 8px rgba(78,158,90,0.3);'
                    : 'background: var(--vd-bg-2); color: var(--vd-text-soft); border: 1px solid var(--vd-bdr);' }}">
            Acerca de
        </button>
    </div>

    {{-- ── Guía de uso ── --}}
    @if($tab === 'guia')
    <div class="space-y-3 max-w-2xl" x-data="{ open: null }">

        @php
        $secciones = [
            [
                'titulo' => 'Conversaciones',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>',
                'items'  => [
                    'Muestra todas las conversaciones de WhatsApp organizadas por zona.',
                    'Hacé clic en una conversación para ver el historial completo.',
                    'Podés filtrar por zona y estado (Pendiente, Consulta, Entregado).',
                    'Desde el historial podés cambiar el estado y agregar notas.',
                ],
            ],
            [
                'titulo' => 'Órdenes',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>',
                'items'  => [
                    'Creá nuevas órdenes con "+ Nueva orden" — elegí cliente, zona, menús y forma de pago.',
                    'Filtrá por estado, zona o número de pedido para encontrar órdenes rápidamente.',
                    'Cambiá el estado desde el selector en la tabla (para admin y responsable de zona).',
                    'Usá los checkboxes para confirmar un bloque de órdenes pendientes a cocina.',
                    '"Lista de ventas" (visible con zona filtrada) genera un resumen imprimible del día.',
                ],
            ],
            [
                'titulo' => 'Cocina',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1A3.75 3.75 0 0012 18z"/>',
                'items'  => [
                    'La vista de Cocina muestra solo las órdenes en estado "Aprobada".',
                    'Se actualiza automáticamente cada 10 segundos — no es necesario recargar.',
                    'Si llega un nuevo bloque, suena un beep y aparece un banner verde.',
                    'Al terminar de preparar un pedido, presioná "Lista para entregar".',
                    'Las órdenes con más de 30 minutos en cocina muestran un badge rojo.',
                ],
            ],
            [
                'titulo' => 'Entregas',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/>',
                'items'  => [
                    'Filtrá por fecha y zona para ver las entregas del día.',
                    'El mapa muestra los marcadores con las coordenadas de cada cliente.',
                    'Si una orden no tiene coordenadas, se muestra en el centro de la zona.',
                    'Usá "Geocodificar" para obtener automáticamente las coordenadas desde la dirección.',
                    '"Imprimir" genera una hoja de ruta imprimible solo con la tabla.',
                ],
            ],
            [
                'titulo' => 'Redacción (mensajes masivos)',
                'icono'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>',
                'items'  => [
                    'Seleccioná la zona y filtrá destinatarios por estado de conversación.',
                    'Usá variables {{nombre}}, {{zona}} y {{ultimo_pedido}} para personalizar el mensaje.',
                    'El preview en tiempo real muestra cómo verá el mensaje el primer destinatario.',
                    'Elegí la API de envío (Evolution API o META WhatsApp Business) según tu configuración.',
                    'El botón de envío estará disponible cuando se configure la integración de WhatsApp.',
                ],
            ],
        ];
        @endphp

        @foreach($secciones as $i => $sec)
        <div class="card p-0 overflow-hidden" x-data="{ open: false }">
            <button @click="open = !open"
                    class="flex items-center gap-3 w-full px-5 py-4 text-left transition-colors"
                    style="background: var(--vd-bg-2);"
                    :style="open ? 'background: rgba(78,158,90,0.06);' : ''">
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
            <div class="w-20 h-20 rounded-full mx-auto mb-4 flex items-center justify-center"
                 style="background: linear-gradient(135deg, #2d6633, #4e9e5a); box-shadow: 0 4px 20px rgba(78,158,90,0.35);">
                <svg width="40" height="40" fill="none" stroke="#fff" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c-4.444 4-6 7.5-4.5 10.5S12 21 12 21s4.5-4.5 4.5-7.5S16.444 7 12 3z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21V10"/>
                </svg>
            </div>
            <h2 class="font-condensed font-bold text-2xl mb-1" style="color: var(--vd-text);">Verdeo</h2>
            <p class="text-xs font-mono mb-3" style="color: var(--vd-muted);">v0.1.0 — Mayo 2026</p>
            <p class="text-sm leading-relaxed" style="color: var(--vd-text-soft);">
                Sistema de gestión de ventas y logística para Verdeo. Gestión de pedidos, conversaciones con clientes, logística de entregas y comunicación masiva.
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
                    ['Docker',          '#2496ed'],
                    ['n8n',             '#ea4b71'],
                    ['Evolution API',   '#25d366'],
                    ['Ollama',          '#7c3aed'],
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
