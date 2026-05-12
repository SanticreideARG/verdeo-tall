<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app', ['title' => 'Facebook'])] class extends Component {};

?>

<div>

    {{-- Header --}}
    <div class="flex items-start justify-between mb-8">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0"
                 style="background: rgba(24,119,242,0.12); border: 1px solid rgba(24,119,242,0.25);">
                <x-icon-facebook class="w-5 h-5" style="color: #1877f2;"/>
            </div>
            <div>
                <h2 class="font-condensed font-bold text-xl" style="color: var(--vd-text); letter-spacing: 0.5px;">Facebook</h2>
                <p class="text-sm mt-0.5" style="color: var(--vd-muted);">Gestión de página, publicaciones y anuncios.</p>
            </div>
        </div>
        <button class="btn-secondary opacity-60 cursor-not-allowed" disabled>
            Conectar página
        </button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        @foreach([
            ['label' => 'Seguidores', 'value' => '—'],
            ['label' => 'Alcance este mes', 'value' => '—'],
            ['label' => 'Publicaciones', 'value' => '—'],
            ['label' => 'Interacciones', 'value' => '—'],
        ] as $stat)
        <div class="card text-center">
            <p class="text-2xl font-bold font-condensed" style="color: #1877f2;">{{ $stat['value'] }}</p>
            <p class="text-xs font-semibold mt-1" style="color: var(--vd-muted);">{{ $stat['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Connect card --}}
    <div class="card py-16 text-center">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
             style="background: rgba(24,119,242,0.08); border: 1px solid rgba(24,119,242,0.2);">
            <x-icon-facebook class="w-6 h-6" style="color: #1877f2;"/>
        </div>
        <p class="font-condensed font-bold text-base mb-2" style="color: var(--vd-text);">Página de Facebook no conectada</p>
        <p class="text-sm max-w-sm mx-auto mb-6" style="color: var(--vd-muted);">
            Conectá tu página de Facebook para publicar contenido, ver métricas y gestionar anuncios desde el panel.
        </p>
        <button class="btn-secondary opacity-60 cursor-not-allowed" disabled title="Próximamente">
            Conectar con Facebook
        </button>
    </div>

</div>
