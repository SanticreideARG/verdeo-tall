<?php

namespace App\Jobs;

use App\Models\Campana;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnviarMensajeCampana implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Reintentos ante fallo (ej: timeout de Evolution API).
     * Con backoff exponencial: 30s, 60s, 120s.
     */
    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int    $campanaId,
        public readonly string $telefono,
        public readonly string $mensajeFinal,  // Variables ya sustituidas
        public readonly string $zona,
    ) {}

    public function handle(): void
    {
        $url = Setting::get('wa_evolution_url', '');
        $key = Setting::get('wa_evolution_key', '');

        if (! $url || ! $key) {
            Log::warning("Campana #{$this->campanaId}: Evolution API no configurada.");
            $this->incrementarFallido();
            return;
        }

        $instancias = [
            'bsas'      => Setting::get('wa_evolution_inst_bsas', ''),
            'valle_nqn' => Setting::get('wa_evolution_inst_valle_nqn', ''),
            'cordoba'   => Setting::get('wa_evolution_inst_cordoba', ''),
            'mendoza'   => Setting::get('wa_evolution_inst_mendoza', ''),
        ];

        $instancia = $instancias[$this->zona] ?? '';

        if (! $instancia) {
            Log::warning("Campana #{$this->campanaId}: instancia WA no configurada para zona '{$this->zona}'.");
            $this->incrementarFallido();
            return;
        }

        try {
            $resp = Http::timeout(15)
                ->withHeaders(['apikey' => $key])
                ->post(rtrim($url, '/') . "/message/sendText/{$instancia}", [
                    'number' => $this->telefono,
                    'text'   => $this->mensajeFinal,
                ]);

            if ($resp->successful()) {
                Campana::where('id', $this->campanaId)->increment('total_enviados');
            } else {
                Log::warning("Campana #{$this->campanaId}: envío fallido a {$this->telefono}. Status: {$resp->status()}");
                Campana::where('id', $this->campanaId)->increment('total_fallidos');
            }
        } catch (\Throwable $e) {
            Log::error("Campana #{$this->campanaId}: excepción al enviar a {$this->telefono}: {$e->getMessage()}");
            // Relanzar para que el retry de Horizon tome efecto
            throw $e;
        }

        $this->checkCompletada();
    }

    public function failed(\Throwable $e): void
    {
        // Se llama tras agotar los reintentos
        $this->incrementarFallido();
        $this->checkCompletada();
        Log::error("Campana #{$this->campanaId}: job fallido definitivamente para {$this->telefono}: {$e->getMessage()}");
    }

    /* ── Privados ──────────────────────────────────────────────────────────── */

    private function incrementarFallido(): void
    {
        Campana::where('id', $this->campanaId)->increment('total_fallidos');
    }

    private function checkCompletada(): void
    {
        $campana = Campana::find($this->campanaId);
        $campana?->checkCompletada();
    }
}
