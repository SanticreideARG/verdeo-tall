<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app', ['title' => 'Email Marketing'])] class extends Component {

    public string $tab = 'campanas';

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
                 style="background: rgba(78,158,90,0.12); border: 1px solid rgba(78,158,90,0.25);">
                <x-icon-email class="w-5 h-5" style="color: var(--vd-green-lt);"/>
            </div>
            <div>
                <h2 class="font-condensed font-bold text-xl" style="color: var(--vd-text); letter-spacing: 0.5px;">Email Marketing</h2>
                <p class="text-sm mt-0.5" style="color: var(--vd-muted);">Campañas, suscriptores y métricas de email.</p>
            </div>
        </div>
        <button class="btn-primary opacity-60 cursor-not-allowed" disabled title="Próximamente">
            + Nueva campaña
        </button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        @foreach([
            ['label' => 'Suscriptores', 'value' => '—', 'sub' => 'lista activa'],
            ['label' => 'Enviados este mes', 'value' => '—', 'sub' => 'emails'],
            ['label' => 'Tasa de apertura', 'value' => '—', 'sub' => 'promedio'],
            ['label' => 'Clics', 'value' => '—', 'sub' => 'últimas 30 días'],
        ] as $stat)
        <div class="card text-center">
            <p class="text-2xl font-bold font-condensed" style="color: var(--vd-text);">{{ $stat['value'] }}</p>
            <p class="text-xs font-semibold mt-1" style="color: var(--vd-muted);">{{ $stat['label'] }}</p>
            <p class="text-xs mt-0.5" style="color: var(--vd-muted-2);">{{ $stat['sub'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 mb-5" style="border-bottom: 1px solid var(--vd-bdr-soft);">
        @foreach(['campanas' => 'Campañas', 'suscriptores' => 'Suscriptores', 'plantillas' => 'Plantillas'] as $key => $label)
        <button wire:click="setTab('{{ $key }}')"
                class="px-4 py-2.5 text-sm font-medium transition-colors duration-150 -mb-px border-b-2"
                style="{{ $tab === $key
                    ? 'color: var(--vd-green-lt); border-color: var(--vd-green-lt);'
                    : 'color: var(--vd-muted); border-color: transparent;' }}"
                onmouseover="{{ $tab !== $key ? "this.style.color='var(--vd-text)'" : '' }}"
                onmouseout="{{ $tab !== $key ? "this.style.color='var(--vd-muted)'" : '' }}">
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- Tab content --}}
    <div class="card py-16 text-center">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
             style="background: rgba(78,158,90,0.08); border: 1px solid rgba(78,158,90,0.18);">
            <x-icon-email class="w-6 h-6" style="color: var(--vd-green-lt);"/>
        </div>
        <p class="font-condensed font-bold text-base mb-2" style="color: var(--vd-text);">
            @if($tab === 'campanas') Sin campañas creadas
            @elseif($tab === 'suscriptores') Sin suscriptores cargados
            @else Sin plantillas
            @endif
        </p>
        <p class="text-sm max-w-sm mx-auto" style="color: var(--vd-muted);">
            Conectá tu proveedor de email (MailerLite, Brevo, etc.) para gestionar campañas desde acá.
        </p>
    </div>

</div>
