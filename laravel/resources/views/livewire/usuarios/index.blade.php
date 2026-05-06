<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\User;

new #[Layout('layouts.app', ['title' => 'Usuarios'])] class extends Component {

    public ?int $deletingId = null;

    public function with(): array
    {
        return ['usuarios' => User::orderBy('name')->get()];
    }

    public function cambiarRol(int $id, string $role): void
    {
        if (!array_key_exists($role, User::rolesLabels())) return;
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) return;
        $user->update(['role' => $role]);
    }

    public function confirmarEliminar(int $id): void
    {
        $this->deletingId = $id;
    }

    public function eliminar(): void
    {
        if (!$this->deletingId || $this->deletingId === auth()->id()) {
            $this->deletingId = null;
            return;
        }
        $user = User::findOrFail($this->deletingId);
        if ($user->foto) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->foto);
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

    {{-- Flash --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-6 badge-green px-4 py-3 rounded-xl text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex justify-end mb-6">
        <a href="{{ route('usuarios.crear') }}" wire:navigate class="btn-primary">
            + Nuevo usuario
        </a>
    </div>

    {{-- Confirm delete modal --}}
    @if($deletingId)
    <div class="fixed inset-0 z-50 flex items-center justify-center"
         style="background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);">
        <div class="w-full max-w-sm rounded-2xl p-6"
             style="background: var(--vd-surface-2); border: 1px solid rgba(220,68,68,0.35);
                    box-shadow: 0 24px 60px rgba(0,0,0,0.4);">
            <h3 class="font-condensed font-bold text-lg mb-2" style="color: var(--vd-text);">¿Eliminar usuario?</h3>
            <p class="text-sm mb-6" style="color: var(--vd-muted);">Esta acción no se puede deshacer.</p>
            <div class="flex justify-end gap-3">
                <button wire:click="cancelarEliminar" class="btn-secondary">Cancelar</button>
                <button wire:click="eliminar" class="btn-danger">Eliminar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Table --}}
    <div class="card p-0 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th class="text-left px-6 py-3">Usuario</th>
                    <th class="text-left px-6 py-3">WhatsApp</th>
                    <th class="text-left px-6 py-3">Ciudad</th>
                    <th class="text-left px-6 py-3">Rol</th>
                    <th class="text-left px-6 py-3">Desde</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($usuarios as $u)
                <tr>
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
                                <p class="text-xs" style="color: var(--vd-muted);">{{ $u->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @if($u->whatsapp)
                            <span class="text-xs font-mono" style="color: var(--vd-text-soft);">{{ $u->whatsapp }}</span>
                        @else
                            <span class="text-xs" style="color: var(--vd-muted-2);">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($u->ciudad)
                            <span class="text-sm" style="color: var(--vd-text-soft);">{{ $u->ciudad }}</span>
                        @else
                            <span class="text-xs" style="color: var(--vd-muted-2);">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($u->id === auth()->id())
                            @php
                                $badgeClass = match($u->role) {
                                    'admin'            => 'badge-green',
                                    'responsable_zona' => 'badge-blue',
                                    'cliente'          => 'badge-yellow',
                                    default            => 'badge-gray',
                                };
                            @endphp
                            <span class="{{ $badgeClass }}">{{ $u->rolLabel() }}</span>
                        @else
                            <select wire:change="cambiarRol({{ $u->id }}, $event.target.value)"
                                    class="text-xs rounded-full border-0 font-bold cursor-pointer focus:outline-none py-1 pl-2 pr-6 font-condensed tracking-wide uppercase"
                                    style="background: rgba(58,125,68,0.18); color: var(--vd-green-xlt);
                                           border: 1px solid rgba(78,158,90,0.35);">
                                @foreach(\App\Models\User::rolesLabels() as $val => $label)
                                    <option value="{{ $val }}" @selected($u->role === $val)
                                            style="background: var(--vd-bg-2); color: var(--vd-text);">{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm" style="color: var(--vd-muted);">
                        {{ $u->created_at->format('d/m/Y') }}
                    </td>
                    <td class="px-6 py-4 text-right">
                        @if($u->id !== auth()->id())
                            <button wire:click="confirmarEliminar({{ $u->id }})"
                                    class="btn-secondary text-xs px-3 py-1.5"
                                    style="color: #fca5a5; border-color: rgba(220,68,68,0.25);"
                                    onmouseover="this.style.background='rgba(220,68,68,0.12)'"
                                    onmouseout="this.style.background=''">
                                Eliminar
                            </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-16 text-center" style="color: var(--vd-muted);">
                        No hay usuarios registrados.
                        <br>
                        <a href="{{ route('usuarios.crear') }}" wire:navigate
                           class="inline-block mt-3 btn-primary text-xs">+ Crear el primero</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
