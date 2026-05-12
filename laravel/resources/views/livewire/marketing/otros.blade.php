<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app', ['title' => 'Otros Canales'])] class extends Component {};

?>

<div>

    {{-- Header --}}
    <div class="flex items-start justify-between mb-8">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0"
                 style="background: rgba(78,158,90,0.12); border: 1px solid rgba(78,158,90,0.25);">
                <x-icon-megaphone class="w-5 h-5" style="color: var(--vd-green-lt);"/>
            </div>
            <div>
                <h2 class="font-condensed font-bold text-xl" style="color: var(--vd-text); letter-spacing: 0.5px;">Otros Canales</h2>
                <p class="text-sm mt-0.5" style="color: var(--vd-muted);">Canales adicionales de comunicación y marketing.</p>
            </div>
        </div>
    </div>

    {{-- Channel cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        @foreach([
            [
                'nombre'  => 'Google Ads',
                'desc'    => 'Campañas de búsqueda y display en Google.',
                'color'   => '#4285f4',
                'estado'  => 'Próximamente',
            ],
            [
                'nombre'  => 'TikTok',
                'desc'    => 'Videos cortos y campañas en TikTok.',
                'color'   => '#ff0050',
                'estado'  => 'Próximamente',
            ],
            [
                'nombre'  => 'Telegram',
                'desc'    => 'Canal de Telegram para clientes y difusiones.',
                'color'   => '#29b6f6',
                'estado'  => 'Próximamente',
            ],
            [
                'nombre'  => 'SMS',
                'desc'    => 'Mensajes de texto masivos vía Twilio o similar.',
                'color'   => '#c8a030',
                'estado'  => 'Próximamente',
            ],
            [
                'nombre'  => 'YouTube',
                'desc'    => 'Canal de YouTube y anuncios en video.',
                'color'   => '#ff0000',
                'estado'  => 'Próximamente',
            ],
            [
                'nombre'  => 'Personalizado',
                'desc'    => 'Integrá cualquier canal vía webhook o API.',
                'color'   => 'var(--vd-green-lt)',
                'estado'  => 'Configurable',
            ],
        ] as $canal)
        <div class="card flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <p class="font-condensed font-bold text-sm" style="color: var(--vd-text);">{{ $canal['nombre'] }}</p>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                      style="background: rgba({{ $canal['color'] === 'var(--vd-green-lt)' ? '78,158,90' : '128,128,128' }},0.12);
                             color: var(--vd-muted); border: 1px solid var(--vd-bdr-soft);">
                    {{ $canal['estado'] }}
                </span>
            </div>
            <p class="text-xs leading-relaxed" style="color: var(--vd-muted);">{{ $canal['desc'] }}</p>
            <div class="mt-auto pt-2" style="border-top: 1px solid var(--vd-bdr-soft);">
                <div class="w-full h-1 rounded-full" style="background: var(--vd-bdr-soft);">
                    <div class="h-1 rounded-full w-0" style="background: {{ $canal['color'] }};"></div>
                </div>
            </div>
        </div>
        @endforeach

    </div>

</div>
