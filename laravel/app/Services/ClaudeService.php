<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ClaudeService
{
    use BuildsSystemPrompt;

    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key', '');
        $this->model  = config('services.anthropic.model', 'claude-haiku-4-5-20251001');
    }

    /**
     * Multi-turn chat. Accepts the same message format as OllamaService.
     * System messages are extracted and sent as the top-level 'system' field.
     */
    public function chat(array $messages, array $options = []): string
    {
        $system   = '';
        $filtered = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $system = $msg['content'];
            } else {
                $filtered[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        $payload = array_merge([
            'model'      => $this->model,
            'max_tokens' => 1024,
            'messages'   => $filtered,
        ], $options);

        if ($system) {
            $payload['system'] = $system;
        }

        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post("{$this->baseUrl}/messages", $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Anthropic API error ' . $response->status() . ': ' . $response->json('error.message', $response->body())
            );
        }

        return $response->json('content.0.text', '');
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
