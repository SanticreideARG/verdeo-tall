<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Services\OllamaService;
use App\Services\ClaudeService;

new #[Layout('layouts.app', ['title' => 'Asistente IA'])] class extends Component {

    public array  $messages       = [];
    public string $input          = '';
    public bool   $loading        = false;
    public string $provider       = 'ollama'; // 'ollama' | 'claude'
    public bool   $ollamaOk       = false;
    public bool   $claudeOk       = false;
    public string $model          = '';

    public function mount(OllamaService $ollama, ClaudeService $claude): void
    {
        if (! auth()->user()->isAdmin() && ! auth()->user()->isResponsableZona()) {
            $this->redirect(route('dashboard'), navigate: true);
            return;
        }
        $this->ollamaOk = $ollama->isAvailable();
        $this->claudeOk = $claude->isAvailable();
        $this->provider = $this->claudeOk ? 'claude' : 'ollama';
        $this->model    = $this->provider === 'claude' ? $claude->getModel() : $ollama->getModel();
    }

    public function switchProvider(string $p, OllamaService $ollama, ClaudeService $claude): void
    {
        if ($p === 'claude' && ! $this->claudeOk) return;
        if ($p === 'ollama' && ! $this->ollamaOk) return;
        $this->provider = $p;
        $this->model    = $p === 'claude' ? $claude->getModel() : $ollama->getModel();
        $this->messages = [];
    }

    public function send(OllamaService $ollama, ClaudeService $claude, string $text = ''): void
    {
        $text = trim($text ?: $this->input);
        if (! $text || $this->loading) return;

        $this->messages[] = ['role' => 'user', 'content' => $text];
        $this->input      = '';
        $this->loading    = true;

        $service = $this->provider === 'claude' ? $claude : $ollama;

        try {
            $history = array_merge(
                [['role' => 'system', 'content' => $service->buildSystemPrompt()]],
                $this->messages
            );
            $reply = $service->chat($history);
            $this->messages[] = ['role' => 'assistant', 'content' => $reply ?: '_(sin respuesta)_'];
        } catch (\Throwable $e) {
            $this->messages[] = ['role' => 'assistant', 'content' => 'Error: ' . $e->getMessage()];
        } finally {
            $this->loading = false;
        }
    }

    public function clear(): void
    {
        $this->messages = [];
        $this->input    = '';
        $this->loading  = false;
    }

}; ?>

<div class="flex flex-col" style="height: calc(100vh - 8rem);" x-data>

    {{-- Header bar --}}
    <div class="flex items-center justify-between mb-4 flex-shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: linear-gradient(135deg, rgba(58,125,68,0.25), rgba(78,158,90,0.15)); border: 1px solid rgba(78,158,90,0.3);">
                <svg width="18" height="18" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                </svg>
            </div>
            <div>
                <h2 class="font-condensed font-bold text-base" style="color: var(--vd-text);">Chat Interno</h2>
                <p class="text-xs" style="color: var(--vd-muted);">
                    <span style="color: #4e9e5a;">●</span> {{ $model }}
                </p>
            </div>
        </div>

        {{-- Provider toggle + clear --}}
        <div class="flex items-center gap-3">
            <div class="flex items-center rounded-xl overflow-hidden"
                 style="border: 1px solid var(--vd-bdr-soft); background: var(--vd-input-bg);">
                <button wire:click="switchProvider('ollama')"
                        {{ ! $ollamaOk ? 'disabled' : '' }}
                        class="px-3 py-1.5 text-xs font-condensed font-bold uppercase tracking-wide transition-all"
                        style="{{ $provider === 'ollama'
                            ? 'background: rgba(58,125,68,0.25); color: #4e9e5a;'
                            : 'color: var(--vd-muted); ' . (! $ollamaOk ? 'opacity:0.4; cursor:not-allowed;' : '') }}">
                    Ollama
                </button>
                <button wire:click="switchProvider('claude')"
                        {{ ! $claudeOk ? 'disabled' : '' }}
                        class="px-3 py-1.5 text-xs font-condensed font-bold uppercase tracking-wide transition-all"
                        style="{{ $provider === 'claude'
                            ? 'background: rgba(139,92,246,0.25); color: #a78bfa;'
                            : 'color: var(--vd-muted); ' . (! $claudeOk ? 'opacity:0.4; cursor:not-allowed;' : '') }}">
                    Claude
                </button>
            </div>
            @if(count($messages) > 0)
            <button wire:click="clear" class="btn-secondary text-xs px-3 py-1.5"
                    style="color: var(--vd-muted);">Nueva conversación</button>
            @endif
        </div>
    </div>

    {{-- Messages --}}
    <div class="flex-1 overflow-y-auto rounded-2xl p-4 space-y-4 mb-4"
         style="background: var(--vd-surface-2); border: 1px solid var(--vd-bdr-soft);"
         x-ref="chatbox"
         x-init="$watch('$wire.messages', () => { $nextTick(() => { $el.scrollTop = $el.scrollHeight; }) })">

        @if(count($messages) === 0)
        <div class="flex flex-col items-center justify-center h-full text-center py-12">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-4"
                 style="background: linear-gradient(135deg, rgba(58,125,68,0.2), rgba(78,158,90,0.1)); border: 1px solid rgba(78,158,90,0.2);">
                <svg width="28" height="28" fill="none" stroke="#4e9e5a" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                </svg>
            </div>
            <p class="font-condensed font-bold text-lg mb-1" style="color: var(--vd-text);">Asistente Verdeo</p>
            <p class="text-sm max-w-sm" style="color: var(--vd-muted);">
                Preguntame sobre menús, redacción de mensajes, análisis de órdenes o cualquier consulta interna.
            </p>
            <div class="flex flex-wrap gap-2 mt-6 justify-center">
                @foreach([
                    '¿Cuál es el menú más vendido?',
                    'Redactá un mensaje de bienvenida para clientes nuevos',
                    '¿Qué diferencia hay entre el menú Keto y el Real?',
                    'Sugerí una descripción para el menú Anti-Age',
                ] as $hint)
                <button wire:click="$set('input', '{{ addslashes($hint) }}')"
                        class="text-xs px-3 py-1.5 rounded-full"
                        style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft); color: var(--vd-muted);"
                        onmouseover="this.style.borderColor='rgba(78,158,90,0.4)'; this.style.color='var(--vd-green-xlt)'"
                        onmouseout="this.style.borderColor='var(--vd-bdr-soft)'; this.style.color='var(--vd-muted)'">
                    {{ $hint }}
                </button>
                @endforeach
            </div>
        </div>
        @endif

        @foreach($messages as $msg)
        <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }} gap-3">
            @if($msg['role'] === 'assistant')
            <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5"
                 style="background: linear-gradient(135deg, rgba(58,125,68,0.3), rgba(78,158,90,0.2)); border: 1px solid rgba(78,158,90,0.3);">
                <svg width="14" height="14" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                </svg>
            </div>
            @endif
            <div class="max-w-xl rounded-2xl px-4 py-3 text-sm leading-relaxed"
                 style="{{ $msg['role'] === 'user'
                     ? 'background: rgba(58,125,68,0.22); border: 1px solid rgba(78,158,90,0.3); color: var(--vd-text); border-bottom-right-radius: 4px;'
                     : 'background: var(--vd-surface-3, var(--vd-bg-2)); border: 1px solid var(--vd-bdr-soft); color: var(--vd-text); border-bottom-left-radius: 4px;'
                 }}">
                {!! nl2br(e($msg['content'])) !!}
            </div>
            @if($msg['role'] === 'user')
            <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5 font-bold text-white text-xs"
                 style="background: linear-gradient(135deg, #3a7d44, #4e9e5a); flex-shrink: 0;">
                {{ strtoupper(substr(auth()->user()->name ?? 'V', 0, 1)) }}
            </div>
            @endif
        </div>
        @endforeach

        @if($loading)
        <div class="flex justify-start gap-3">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                 style="background: linear-gradient(135deg, rgba(58,125,68,0.3), rgba(78,158,90,0.2)); border: 1px solid rgba(78,158,90,0.3);">
                <svg width="14" height="14" fill="none" stroke="#4e9e5a" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                </svg>
            </div>
            <div class="rounded-2xl px-4 py-3 flex flex-col gap-2"
                 style="background: var(--vd-surface-3, var(--vd-bg-2)); border: 1px solid var(--vd-bdr-soft); border-bottom-left-radius: 4px;">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full animate-bounce" style="background: #4e9e5a; animation-delay: 0ms;"></span>
                    <span class="w-2 h-2 rounded-full animate-bounce" style="background: #4e9e5a; animation-delay: 150ms;"></span>
                    <span class="w-2 h-2 rounded-full animate-bounce" style="background: #4e9e5a; animation-delay: 300ms;"></span>
                </div>
                @if(count($messages) <= 1)
                <p class="text-xs" style="color: var(--vd-muted);">Primera respuesta: puede tardar hasta 2-3 min mientras carga el modelo.</p>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- Input --}}
    @php $ready = ($provider === 'claude' ? $claudeOk : $ollamaOk); @endphp
    <div class="flex-shrink-0">
        @if(! $ready)
        <div class="rounded-xl px-4 py-3 text-sm text-center mb-3"
             style="background: rgba(220,68,68,0.1); border: 1px solid rgba(220,68,68,0.3); color: #fca5a5;">
            @if($provider === 'claude')
                API key de Claude no configurada. Agregá <code>ANTHROPIC_API_KEY</code> en el <code>.env</code>.
            @else
                Ollama no está disponible. Verificá que el contenedor esté corriendo.
            @endif
        </div>
        @endif
        <form x-data @submit.prevent="$wire.send($refs.input.value); $refs.input.value = ''; $refs.input.style.height = 'auto';" class="flex gap-3">
            <div class="flex-1 relative">
                <textarea x-ref="input"
                          rows="1"
                          placeholder="{{ $ready ? 'Escribí tu mensaje...' : 'No disponible' }}"
                          {{ $ready ? '' : 'disabled' }}
                          class="w-full rounded-xl px-4 py-3 text-sm resize-none"
                          style="background: var(--vd-input-bg); border: 1px solid var(--vd-bdr-soft); color: var(--vd-text); outline: none; min-height: 48px; max-height: 160px;"
                          @keydown.enter.prevent="if (!$event.shiftKey) { $wire.send($el.value); $el.value = ''; $el.style.height = 'auto'; }"
                          x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                          onfocus="this.style.borderColor='rgba(78,158,90,0.5)'"
                          onblur="this.style.borderColor='var(--vd-bdr-soft)'"></textarea>
            </div>
            <button type="submit"
                    {{ $loading || ! $ready ? 'disabled' : '' }}
                    class="flex-shrink-0 h-12 w-12 rounded-xl flex items-center justify-center transition-all"
                    style="{{ $loading || ! $ready
                        ? 'background: rgba(78,158,90,0.12); border: 1px solid rgba(78,158,90,0.15); cursor: not-allowed; color: var(--vd-muted-2);'
                        : 'background: linear-gradient(135deg, #3a7d44, #4e9e5a); border: 1px solid rgba(78,158,90,0.5); color: white; cursor: pointer;' }}">
                @if($loading)
                <svg width="18" height="18" class="animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 2a10 10 0 110 20A10 10 0 0112 2zm0 0v4"/>
                </svg>
                @else
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                </svg>
                @endif
            </button>
        </form>
        <p class="text-xs mt-2 text-center" style="color: var(--vd-muted-2);">
            Enter para enviar · Shift+Enter para nueva línea · Modelo: {{ $model }}
        </p>
    </div>

</div>
