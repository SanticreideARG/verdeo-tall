<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app', ['title' => 'Ficha de usuario'])] class extends Component {

    public int $usuarioId;

    public function mount(User $user): void
    {
        $viewer = auth()->user();
        if ($viewer->isColaborador() || $viewer->isCliente()) {
            $this->redirect(route('dashboard'), navigate: true);
            return;
        }
        $this->usuarioId = $user->id;
    }

    public function with(): array
    {
        return ['usuario' => User::findOrFail($this->usuarioId)];
    }

}; ?>

<div>

    <div class="mb-6">
        <a href="{{ route('usuarios') }}" wire:navigate
           class="inline-flex items-center gap-2 text-sm transition-colors duration-150"
           style="color: var(--vd-muted);"
           onmouseover="this.style.color='var(--vd-green-lt)'"
           onmouseout="this.style.color='var(--vd-muted)'">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver a Usuarios
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Avatar --}}
        <div class="card flex flex-col items-center gap-4 py-8">
            @if($usuario->foto)
                <img src="{{ Storage::url($usuario->foto) }}" alt="{{ $usuario->nombreCompleto() }}"
                     class="w-28 h-28 rounded-2xl object-cover"
                     style="border: 2px solid rgba(78,158,90,0.4); box-shadow: 0 0 30px rgba(58,125,68,0.2);">
            @else
                <div class="w-28 h-28 rounded-2xl flex items-center justify-center text-white font-bold font-condensed"
                     style="background: linear-gradient(135deg, #3a7d44, #4e9e5a); font-size: 44px; line-height:1;
                            border: 2px solid rgba(78,158,90,0.4); box-shadow: 0 0 30px rgba(58,125,68,0.2);">
                    {{ strtoupper(substr($usuario->name, 0, 1)) }}
                </div>
            @endif

            <div class="text-center">
                <h2 class="font-condensed font-bold text-xl" style="color: var(--vd-text);">
                    {{ $usuario->nombreCompleto() }}
                </h2>
                @php
                    $bs = match($usuario->role) {
                        'admin'            => 'background:rgba(78,158,90,0.15);color:#4e9e5a;border:1px solid rgba(78,158,90,0.3)',
                        'responsable_zona' => 'background:rgba(96,165,250,0.15);color:#60a5fa;border:1px solid rgba(96,165,250,0.3)',
                        'colaborador'      => 'background:rgba(167,139,250,0.15);color:#a78bfa;border:1px solid rgba(167,139,250,0.3)',
                        'cliente'          => 'background:rgba(200,160,48,0.15);color:#c8a030;border:1px solid rgba(200,160,48,0.3)',
                        default            => 'background:rgba(128,128,128,0.15);color:var(--vd-muted)',
                    };
                @endphp
                <span class="inline-block mt-2 text-xs font-condensed font-bold uppercase px-3 py-1 rounded-full"
                      style="{{ $bs }}">
                    {{ $usuario->rolLabel() }}
                </span>
            </div>

            @if(auth()->user()->isAdmin())
                <p class="text-xs text-center" style="color: var(--vd-muted);">
                    Miembro desde {{ $usuario->created_at->format('d/m/Y') }}
                </p>
            @endif
        </div>

        {{-- Info --}}
        <div class="lg:col-span-2 card">
            <h3 class="font-condensed font-bold tracking-wide mb-5"
                style="color: var(--vd-muted); letter-spacing: 1px; text-transform: uppercase; font-size: 11px;
                       border-bottom: 1px solid var(--vd-bdr-soft); padding-bottom: 12px;">
                Información
            </h3>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">

                <div>
                    <dt class="font-condensed text-xs uppercase tracking-wide mb-1"
                        style="color: var(--vd-muted-2); letter-spacing: 1px;">Nombre</dt>
                    <dd class="font-semibold" style="color: var(--vd-text);">{{ $usuario->name }}</dd>
                </div>

                <div>
                    <dt class="font-condensed text-xs uppercase tracking-wide mb-1"
                        style="color: var(--vd-muted-2); letter-spacing: 1px;">Apellido</dt>
                    <dd class="font-semibold" style="color: var(--vd-text);">{{ $usuario->apellido ?: '—' }}</dd>
                </div>

                <div>
                    <dt class="font-condensed text-xs uppercase tracking-wide mb-1"
                        style="color: var(--vd-muted-2); letter-spacing: 1px;">Rol</dt>
                    <dd>
                        <span class="text-xs font-condensed font-bold uppercase px-2 py-0.5 rounded-full"
                              style="{{ $bs }}">{{ $usuario->rolLabel() }}</span>
                    </dd>
                </div>

                @if(auth()->user()->isAdmin())
                <div>
                    <dt class="font-condensed text-xs uppercase tracking-wide mb-1"
                        style="color: var(--vd-muted-2); letter-spacing: 1px;">Email</dt>
                    <dd class="text-sm" style="color: var(--vd-text-soft);">{{ $usuario->email }}</dd>
                </div>

                <div>
                    <dt class="font-condensed text-xs uppercase tracking-wide mb-1"
                        style="color: var(--vd-muted-2); letter-spacing: 1px;">WhatsApp</dt>
                    <dd class="text-sm font-mono" style="color: var(--vd-text-soft);">{{ $usuario->whatsapp ?: '—' }}</dd>
                </div>

                <div>
                    <dt class="font-condensed text-xs uppercase tracking-wide mb-1"
                        style="color: var(--vd-muted-2); letter-spacing: 1px;">Ciudad</dt>
                    <dd class="text-sm" style="color: var(--vd-text-soft);">{{ $usuario->ciudad ?: '—' }}</dd>
                </div>
                @endif

            </dl>
        </div>
    </div>

</div>
