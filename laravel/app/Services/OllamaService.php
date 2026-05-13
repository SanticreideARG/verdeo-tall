<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OllamaService
{
    use BuildsSystemPrompt;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ollama.url', 'http://ollama:11434'), '/');
        $this->model   = config('services.ollama.model', 'llama3.2');
    }

    /**
     * Single-turn completion. Returns the response text.
     */
    public function generate(string $prompt, array $options = []): string
    {
        $response = Http::timeout(300)->post("{$this->baseUrl}/api/generate", array_merge([
            'model'  => $this->model,
            'prompt' => $prompt,
            'stream' => false,
        ], $options));

        return $response->json('response', '');
    }

    /**
     * Multi-turn chat. $messages = [['role'=>'user','content'=>'...'], ...]
     */
    public function chat(array $messages, array $options = []): string
    {
        $response = Http::timeout(300)->post("{$this->baseUrl}/api/chat", array_merge([
            'model'    => $this->model,
            'messages' => $messages,
            'stream'   => false,
        ], $options));

        return $response->json('message.content', '');
    }

    /**
     * Streaming chat — calls $onChunk($text) for each token received.
     */
    public function stream(array $messages, callable $onChunk): void
    {
        $body = Http::timeout(180)
            ->post("{$this->baseUrl}/api/chat", [
                'model'    => $this->model,
                'messages' => $messages,
                'stream'   => true,
            ]);

        foreach (explode("\n", $body->body()) as $line) {
            $line = trim($line);
            if (! $line) continue;
            $data = json_decode($line, true);
            if (isset($data['message']['content'])) {
                $onChunk($data['message']['content']);
            }
        }
    }

    /**
     * Classify text into one of the given categories.
     * Returns the category key or 'unknown'.
     */
    public function classify(string $text, array $categories): string
    {
        $list   = implode(', ', array_keys($categories));
        $prompt = "Classify the following message into exactly one of these categories: {$list}.\n"
                . "Respond with only the category name, nothing else.\n\n"
                . "Message: {$text}";

        $result = strtolower(trim($this->generate($prompt)));

        foreach (array_keys($categories) as $key) {
            if (str_contains($result, strtolower($key))) {
                return $key;
            }
        }
        return 'unknown';
    }

    /**
     * Extract structured order data from a free-text message.
     * Returns ['menu'=>'...', 'tamano'=>'250g|400g', 'forma_pago'=>'...'] or null.
     */
    public function extractOrderFromText(string $text): ?array
    {
        $menus     = implode(', ', array_values(\App\Models\Producto::tipos()));
        $prompt    = "Extract order information from this message. Available menus: {$menus}. "
                   . "Sizes: 250g, 400g. Payment: no_definido, en_destino, transferencia.\n"
                   . "Respond ONLY with valid JSON: {\"menu\": \"tipo\", \"tamano\": \"250g\", \"forma_pago\": \"en_destino\"}\n"
                   . "Message: {$text}";

        $raw = trim($this->generate($prompt));
        preg_match('/\{.*\}/s', $raw, $matches);
        if (! isset($matches[0])) return null;

        $data = json_decode($matches[0], true);
        return is_array($data) ? $data : null;
    }

    /**
     * Suggest a reply to a customer message given context.
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

    public function isAvailable(): bool
    {
        try {
            if (! Http::timeout(3)->get("{$this->baseUrl}/api/tags")->successful()) {
                return false;
            }
            // Keep model loaded in memory indefinitely
            Http::timeout(5)->post("{$this->baseUrl}/api/generate", [
                'model'      => $this->model,
                'prompt'     => '',
                'keep_alive' => -1,
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
