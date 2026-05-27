<?php

namespace App\Services;

use App\Contracts\AiServiceInterface;
use App\Models\Producto;
use App\Models\User;
use App\Services\BotPermissions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AiRouter — orquestador de proveedores IA con fallback en cascada.
 *
 * Prioridad: Claude (primario) → GPT (secundario) → Gemini (terciario)
 *
 * Todos los call sites deben inyectar AiRouter en lugar de llamar
 * directamente a un servicio específico.
 */
class AiRouter
{
    use BuildsSystemPrompt;

    /** @var AiServiceInterface[] Ordered priority list */
    private array $cascade;

    public function __construct(
        private readonly ClaudeService  $claude,
        private readonly GptService     $gpt,
        private readonly GeminiService  $gemini,
    ) {
        $this->cascade = [$claude, $gpt, $gemini];
    }

    /* ── Acceso a proveedores ──────────────────────────────────────────────── */

    /**
     * Returns the first available provider in priority order.
     * Throws if none is configured.
     */
    public function primary(): AiServiceInterface
    {
        foreach ($this->cascade as $provider) {
            if ($provider->isAvailable()) {
                return $provider;
            }
        }

        throw new \RuntimeException(
            'No hay proveedor IA configurado. '
            . 'Definí ANTHROPIC_API_KEY, OPENAI_API_KEY o GEMINI_API_KEY en el .env.'
        );
    }

    /**
     * Returns a specific provider by slug ('claude' | 'gpt' | 'gemini').
     * Falls back to primary() if the slug is unknown or unavailable.
     */
    public function provider(string $slug): AiServiceInterface
    {
        foreach ($this->cascade as $p) {
            if ($p->getProviderName() === $slug && $p->isAvailable()) {
                return $p;
            }
        }

        return $this->primary();
    }

    /**
     * Returns all available providers in priority order.
     * @return AiServiceInterface[]
     */
    public function available(): array
    {
        return array_values(array_filter($this->cascade, fn ($p) => $p->isAvailable()));
    }

    /**
     * Status map for UI display.
     * ['claude' => true, 'gpt' => false, 'gemini' => false]
     */
    public function status(): array
    {
        return [
            'claude' => $this->claude->isAvailable(),
            'gpt'    => $this->gpt->isAvailable(),
            'gemini' => $this->gemini->isAvailable(),
        ];
    }

    /* ── Métodos de alto nivel (con fallback cascade) ─────────────────────── */

    /**
     * Chat with automatic cascade fallback.
     * Tries each provider in order; logs warnings on failure.
     */
    public function chat(array $messages, array $options = []): string
    {
        $lastError = null;

        foreach ($this->cascade as $provider) {
            if (! $provider->isAvailable()) {
                continue;
            }

            try {
                return $provider->chat($messages, $options);
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning("AiRouter: proveedor [{$provider->getProviderName()}] falló, intentando siguiente.", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new \RuntimeException(
            'Todos los proveedores IA fallaron. Último error: ' . ($lastError?->getMessage() ?? 'desconocido')
        );
    }

    /**
     * Chat using a specific provider slug, with cascade fallback on error.
     */
    public function chatWith(string $slug, array $messages, array $options = []): string
    {
        $provider = $this->provider($slug);

        try {
            return $provider->chat($messages, $options);
        } catch (\Throwable $e) {
            Log::warning("AiRouter: proveedor [{$slug}] falló en chatWith, usando cascade.", [
                'error' => $e->getMessage(),
            ]);
            return $this->chat($messages, $options);
        }
    }

    /**
     * Suggest a reply to a customer message given conversation context.
     */
    public function suggestReply(string $customerMessage, string $context = ''): string
    {
        $system = $this->buildSystemPrompt();
        if ($context) {
            $system .= "\n\nContexto adicional del cliente: {$context}";
        }

        return $this->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $customerMessage],
        ]);
    }

    /* ── Bot helpers ──────────────────────────────────────────────────────────── */

    /**
     * Check whether the bot is allowed to perform a specific capability.
     * Delegates to BotPermissions::can() which handles planificado guard + Setting lookup.
     */
    public static function botCanDo(string $capability): bool
    {
        return BotPermissions::can($capability);
    }

    /**
     * Return the ID of the bot user (role='bot'), cached for 60 seconds.
     * Returns null if the bot user doesn't exist yet.
     */
    public static function botUserId(): ?int
    {
        return Cache::remember('bot_user_id', 60, function () {
            return User::where('role', 'bot')->value('id');
        });
    }

    /**
     * Extract structured order data from a free-text message.
     * Returns ['menu'=>'...', 'tamano'=>'250kcal|400kcal', 'forma_pago'=>'...'] or null.
     */
    public function extractOrderFromText(string $text): ?array
    {
        $menus = implode(', ', array_values(Producto::tipos()));

        $messages = [
            ['role' => 'system', 'content' =>
                'Sos un extractor de datos de pedidos. '
                . 'Respondés ÚNICAMENTE con JSON válido, sin texto adicional.'],
            ['role' => 'user', 'content' =>
                "Extraé la información del pedido de este mensaje. "
                . "Menús disponibles: {$menus}. "
                . "Tamaños: 250kcal, 400kcal. "
                . "Forma de pago: no_definido, en_destino, transferencia.\n"
                . "Respondé SOLO con JSON: {\"menu\": \"tipo\", \"tamano\": \"250kcal\", \"forma_pago\": \"en_destino\"}\n"
                . "Mensaje: {$text}"],
        ];

        $raw = trim($this->chat($messages));
        preg_match('/\{.*\}/s', $raw, $matches);
        if (! isset($matches[0])) {
            return null;
        }

        $data = json_decode($matches[0], true);
        return is_array($data) ? $data : null;
    }
}
