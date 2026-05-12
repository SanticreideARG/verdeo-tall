<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app', ['title' => 'Instagram'])] class extends Component {};

?>

<div>

    {{-- Header --}}
    <div class="flex items-start justify-between mb-8">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0"
                 style="background: rgba(225,48,108,0.12); border: 1px solid rgba(225,48,108,0.25);">
                <x-icon-instagram class="w-5 h-5" style="color: #e1306c;"/>
            </div>
            <div>
                <h2 class="font-condensed font-bold text-xl" style="color: var(--vd-text); letter-spacing: 0.5px;">Instagram</h2>
                <p class="text-sm mt-0.5" style="color: var(--vd-muted);">Feed, stories, reels y métricas de la cuenta.</p>
            </div>
        </div>
        <button class="btn-secondary opacity-60 cursor-not-allowed" disabled>
            Conectar cuenta
        </button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        @foreach([
            ['label' => 'Seguidores', 'value' => '—'],
            ['label' => 'Publicaciones', 'value' => '—'],
            ['label' => 'Alcance semanal', 'value' => '—'],
            ['label' => 'Me gusta promedio', 'value' => '—'],
        ] as $stat)
        <div class="card text-center">
            <p class="text-2xl font-bold font-condensed" style="color: #e1306c;">{{ $stat['value'] }}</p>
            <p class="text-xs font-semibold mt-1" style="color: var(--vd-muted);">{{ $stat['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Connect card --}}
    <div class="card py-16 text-center">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
             style="background: rgba(225,48,108,0.08); border: 1px solid rgba(225,48,108,0.2);">
            <x-icon-instagram class="w-6 h-6" style="color: #e1306c;"/>
        </div>
        <p class="font-condensed font-bold text-base mb-2" style="color: var(--vd-text);">Cuenta de Instagram no conectada</p>
        <p class="text-sm max-w-sm mx-auto mb-6" style="color: var(--vd-muted);">
            Vinculá tu cuenta de Instagram Business para ver estadísticas y programar publicaciones desde el panel.
        </p>
        <button class="btn-secondary opacity-60 cursor-not-allowed" disabled title="Próximamente">
            Conectar con Instagram
        </button>
    </div>

</div>
