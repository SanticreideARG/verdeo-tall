<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app', ['title' => 'WhatsApp Marketing'])] class extends Component {

    public string $tab = 'difusion';

    public function setTab(string $t): void
    {
        $this->tab = $t;
    }

}; ?>

<div>

    {{-- Header --}}
    <div class="flex items-start justify-between mb-8">
        <div class="flex items-center gap-4">
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0"
                 style="background: rgba(37,211,102,0.12); border: 1px solid rgba(37,211,102,0.25);">
                <x-icon-whatsapp class="w-5 h-5" style="color: #25d366;"/>
            </div>
            <div>
                <h2 class="font-condensed font-bold text-xl" style="color: var(--vd-text); letter-spacing: 0.5px;">WhatsApp Marketing</h2>
                <p class="text-sm mt-0.5" style="color: var(--vd-muted);">Difusiones, plantillas y métricas de mensajes masivos.</p>
            </div>
        </div>
        <button class="btn-primary opacity-60 cursor-not-allowed" disabled title="Próximamente">
            + Nueva difusión
        </button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        @foreach([
            ['label' => 'Listas de difusión', 'value' => '—', 'color' => '#25d366'],
            ['label' => 'Mensajes enviados', 'value' => '—', 'color' => '#25d366'],
            ['label' => 'Entregados', 'value' => '—', 'color' => '#25d366'],
            ['label' => 'Respuestas recibidas', 'value' => '—', 'color' => '#25d366'],
        ] as $stat)
        <div class="card text-center">
            <p class="text-2xl font-bold font-condensed" style="color: {{ $stat['color'] }};">{{ $stat['value'] }}</p>
            <p class="text-xs font-semibold mt-1" style="color: var(--vd-muted);">{{ $stat['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 mb-5" style="border-bottom: 1px solid var(--vd-bdr-soft);">
        @foreach(['difusion' => 'Difusiones', 'plantillas' => 'Plantillas', 'contactos' => 'Contactos'] as $key => $label)
        <button wire:click="setTab('{{ $key }}')"
                class="px-4 py-2.5 text-sm font-medium transition-colors duration-150 -mb-px border-b-2"
                style="{{ $tab === $key
                    ? 'color: #25d366; border-color: #25d366;'
                    : 'color: var(--vd-muted); border-color: transparent;' }}">
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- Tab content --}}
    <div class="card py-16 text-center">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
             style="background: rgba(37,211,102,0.08); border: 1px solid rgba(37,211,102,0.2);">
            <x-icon-whatsapp class="w-6 h-6" style="color: #25d366;"/>
        </div>
        <p class="font-condensed font-bold text-base mb-2" style="color: var(--vd-text);">
            @if($tab === 'difusion') Sin listas de difusión
            @elseif($tab === 'plantillas') Sin plantillas de mensajes
            @else Sin contactos de marketing
            @endif
        </p>
        <p class="text-sm max-w-sm mx-auto" style="color: var(--vd-muted);">
            Las listas de difusión usan las instancias de WhatsApp configuradas en <a href="{{ route('zonas') }}" wire:navigate style="color: var(--vd-green-lt); text-decoration: underline;">Zonas</a>.
        </p>
    </div>

</div>
