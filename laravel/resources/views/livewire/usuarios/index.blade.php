<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app', ['title' => 'Usuarios'])] class extends Component {

    public ?int $deletingId = null;

    public function mount(): void
    {
        $user = auth()->user();
        if ($user->isColaborador() || $user->isCliente()) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function with(): array
    {
        return ['usuarios' => User::orderBy('name')->get()];
    }

    public function cambiarRol(int $id, string $role): void
    {
        if (! auth()->user()->isAdmin()) return;
        if ($role === '__eliminar__') {
            $this->confirmarEliminar($id);
            return;
        }
        if (! array_key_exists($role, User::rolesLabels())) return;
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) return;
        $user->update(['role' => $role]);
    }

    public function confirmarEliminar(int $id): void
    {
        if (! auth()->user()->isAdmin()) return;
        $this->deletingId = $id;
    }

    public function eliminar(): void
    {
        if (! auth()->user()->isAdmin()) return;
        if (! $this->deletingId || $this->deletingId === auth()->id()) {
            $this->deletingId = null;
            return;
        }
        $user = User::findOrFail($this->deletingId);
        if ($user->foto) {
            Storage::disk('public')->delete($user->foto);
        }
        $user->delete();
        $this->deletingId = null;
        session()->flash('success', 'Usuario eliminado.');
    }

    public function cancelarEliminar(): void
    {
        $this->deletingId = null;
    }

}; ?>

<div>

    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-6 badge-green px-4 py-3 rounded-xl text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-end mb-6 gap-3 flex-wrap">
        @if(auth()->user()->isAdmin())
            <a href="{{ route('usuarios.crear-cliente') }}" wire:navigate
               class="btn-secondary text-sm flex items-center gap-2"
               style="border-color: rgba(200,160,48,0.4); color: #c8a030;"
               onmouseover="this.style.background='rgba(200,160,48,0.08)'"
               onmouseout="this.style.background=''">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Registrar cliente
            </a>
            <a href="{{ route('usuarios.crear-colaborador') }}" wire:navigate
               class="btn-secondary text-sm flex items-center gap-2"
               style="border-color: rgba(139,92,246,0.4); color: #a78bfa;"
               onmouseover="this.style.background='rgba(139,92,246,0.08)'"
               onmouseout="this.style.background=''">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Registrar colaborador
            </a>
            <a href="{{ route('usuarios.crear') }}" wire:navigate class="btn-primary text-sm">
                + Nuevo usuario
            </a>
        @endif
    </div>

    @if($deletingId)
    <div class="fixed inset-0 z-50 flex items-center justify-center"
         style="background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="w-full max-w-sm rounded-2xl p-6"
             style="background: var(--vd-surface-2); border: 1px solid rgba(220,68,68,0.35); box-shadow: 0 24px 60px rgba(0,0,0,0.4);">
            <h3 class="font-condensed font-bold text-lg mb-2" style="color: var(--vd-text);">¿Eliminar usuario?</h3>
            <p class="text-sm mb-6" style="color: var(--vd-muted);">Esta acción no se puede deshacer.</p>
            <div class="flex justify-end gap-3">
                <button wire:click="cancelarEliminar" class="btn-secondary">Cancelar</button>
                <button wire:click="eliminar" class="btn-danger">Eliminar</button>
            </div>
        </div>
    </div>
    @endif

    <div class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr style="border-bottom: 1px solid var(--vd-bdr-soft);">
                    <th class="text-left px-6 py-3 font-condensed text-xs uppercase tracking-wide" style="color: var(--vd-muted-2);">Usuario</th>
                    @if(auth()->user()->isAdmin())
                        <th class="text-left px-6 py-3 font-condensed text-xs uppercase tracking-wide" style="color: var(--vd-muted-2);">WhatsApp</th>
                        <th class="text-left px-6 py-3 font-condensed text-xs uppercase tracking-wide" style="color: var(--vd-muted-2);">Ciudad</th>
                    @endif
                    <th class="text-left px-6 py-3 font-condensed text-xs uppercase tracking-wide" style="color: var(--vd-muted-2);">Rol</th>
                    @if(auth()->user()->isAdmin())
                        <th class="text-left px-6 py-3 font-condensed text-xs uppercase tracking-wide" style="color: var(--vd-muted-2);">Desde</th>
                    @endif
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $u)
                <tr style="border-bottom: 1px solid var(--vd-bdr-soft); cursor: pointer;"
                    onclick="window.location.href='{{ route('usuarios.ver', $u) }}'"
                    onmouseover="this.style.background='var(--vd-nav-hover)'"
                    onmouseout="this.style.background=''">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            @if($u->foto)
                                <img src="{{ Storage::url($u->foto) }}" alt="{{ $u->nombreCompleto() }}"
                                     class="w-9 h-9 rounded-full object-cover flex-shrink-0"
                                     style="border: 1px solid rgba(78,158,90,0.35);">
                            @else
                                <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white flex-shrink-0"
                                     style="background: linear-gradient(135deg, #3a7d44, #4e9e5a); font-size: 15px;">
                                    {{ strtoupper(substr($u->name, 0, 1)) }}
                                </div>
                            @endif
                            <div>
                                <p class="font-semibold" style="color: var(--vd-text);">
                                    {{ $u->nombreCompleto() }}
                                    @if($u->id === auth()->id())
                                        <span class="text-xs" style="color: var(--vd-muted-2);">(vos)</span>
                                    @endif
                                </p>
                                @if(auth()->user()->isAdmin())
                                    <p class="text-xs" style="color: var(--vd-muted);">{{ $u->email }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    @if(auth()->user()->isAdmin())
                        <td class="px-6 py-4">
                            <span class="text-xs font-mono" style="color: var(--vd-text-soft);">{{ $u->whatsapp ?: '—' }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm" style="color: var(--vd-text-soft);">{{ $u->ciudad ?: '—' }}</span>
                        </td>
                    @endif
                    <td class="px-6 py-4">
                        @if(auth()->user()->isAdmin() && $u->id !== auth()->id())
                            <select wire:change="cambiarRol({{ $u->id }}, $event.target.value)"
                                    onclick="event.stopPropagation()"
                                    class="text-xs rounded-full font-bold cursor-pointer focus:outline-none py-1 pl-2 pr-6 font-condensed tracking-wide uppercase"
                                    style="background: rgba(58,125,68,0.18); color: var(--vd-green-xlt); border: 1px solid rgba(78,158,90,0.35);">
                                @foreach(User::rolesLabels() as $val => $label)
                                    <option value="{{ $val }}" @selected($u->role === $val)
                                            style="background: var(--vd-bg-2); color: var(--vd-text);">{{ $label }}</option>
                                @endforeach
                                <option disabled style="background: var(--vd-bg-2); color: var(--vd-bdr);">──────────</option>
                                <option value="__eliminar__"
                                        style="background: var(--vd-bg-2); color: #fca5a5;">Eliminar usuario</option>
                            </select>
                        @else
                            @php
                                $bs = match($u->role) {
                                    'admin'            => 'background:rgba(78,158,90,0.15);color:#4e9e5a;border:1px solid rgba(78,158,90,0.3)',
                                    'responsable_zona' => 'background:rgba(96,165,250,0.15);color:#60a5fa;border:1px solid rgba(96,165,250,0.3)',
                                    'colaborador'      => 'background:rgba(167,139,250,0.15);color:#a78bfa;border:1px solid rgba(167,139,250,0.3)',
                                    'cliente'          => 'background:rgba(200,160,48,0.15);color:#c8a030;border:1px solid rgba(200,160,48,0.3)',
                                    default            => 'background:rgba(128,128,128,0.15);color:var(--vd-muted)',
                                };
                            @endphp
                            <span class="text-xs font-condensed font-bold uppercase px-2 py-0.5 rounded-full"
                                  style="{{ $bs }}">{{ $u->rolLabel() }}</span>
                        @endif
                    </td>
                    @if(auth()->user()->isAdmin())
                        <td class="px-6 py-4 text-sm" style="color: var(--vd-muted);">
                            {{ $u->created_at->format('d/m/Y') }}
                        </td>
                    @endif
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-2">
                            <span class="btn-secondary text-xs px-3 py-1.5" style="pointer-events:none;">Ver ficha</span>
                            @if(auth()->user()->isAdmin() && $u->id !== auth()->id())
                                <button wire:click="confirmarEliminar({{ $u->id }})"
                                        onclick="event.stopPropagation()"
                                        class="btn-secondary text-xs px-3 py-1.5"
                                        style="color: #fca5a5; border-color: rgba(220,68,68,0.25);"
                                        onmouseover="this.style.background='rgba(220,68,68,0.12)'"
                                        onmouseout="this.style.background=''">Eliminar</button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ auth()->user()->isAdmin() ? 6 : 3 }}"
                        class="px-6 py-16 text-center" style="color: var(--vd-muted);">
                        No hay usuarios registrados.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
