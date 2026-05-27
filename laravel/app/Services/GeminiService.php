<?php

namespace App\Services;

use App\Contracts\AiServiceInterface;
use Illuminate\Support\Facades\Http;

class GeminiService implements AiServiceInterface
{
    use BuildsSystemPrompt;

    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
        $this->model  = config('services.gemini.model', 'gemini-2.0-flash');
    }

    /**
     * Multi-turn chat using Google Gemini API.
     * Converts OpenAI-style messages to Gemini's 'contents' format.
     * System messages map to systemInstruction.
     */
    public function chat(array $messages, array $options = []): string
    {
        $systemInstruction = null;
        $contents          = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemInstruction = ['parts' => [['text' => $msg['content']]]];
            } else {
                // Gemini uses 'model' instead of 'assistant'
                $contents[] = [
                    'role'  => $msg['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $msg['content']]],
                ];
            }
        }

        $payload = ['contents' => $contents];
        if ($systemInstruction) {
            $payload['systemInstruction'] = $systemInstruction;
        }

        $url      = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";
        $response = Http::timeout(60)->post($url, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Gemini API error ' . $response->status() . ': ' . $response->body()
            );
        }

        return $response->json('candidates.0.content.parts.0.text', '');
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }
}
