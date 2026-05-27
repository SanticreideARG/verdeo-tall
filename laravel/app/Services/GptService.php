<?php

namespace App\Services;

use App\Contracts\AiServiceInterface;
use Illuminate\Support\Facades\Http;

class GptService implements AiServiceInterface
{
    use BuildsSystemPrompt;

    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key', '');
        $this->model  = config('services.openai.model', 'gpt-4o-mini');
    }

    /**
     * Multi-turn chat using OpenAI Chat Completions API.
     * System messages are passed natively in the messages array.
     */
    public function chat(array $messages, array $options = []): string
    {
        $response = Http::timeout(60)
            ->withToken($this->apiKey)
            ->post('https://api.openai.com/v1/chat/completions', array_merge([
                'model'      => $this->model,
                'messages'   => $messages,
                'max_tokens' => 1024,
            ], $options));

        if (! $response->successful()) {
            throw new \RuntimeException(
                'OpenAI API error ' . $response->status() . ': ' . $response->json('error.message', $response->body())
            );
        }

        return $response->json('choices.0.message.content', '');
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
        return 'gpt';
    }
}
