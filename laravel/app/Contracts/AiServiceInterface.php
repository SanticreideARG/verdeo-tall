<?php

namespace App\Contracts;

interface AiServiceInterface
{
    /**
     * Multi-turn chat. $messages = [['role'=>'system|user|assistant','content'=>'...']]
     * System messages are handled internally per-provider.
     */
    public function chat(array $messages, array $options = []): string;

    /** Whether this provider is configured and ready (API key present, etc.) */
    public function isAvailable(): bool;

    /** Human-readable model identifier, e.g. "claude-sonnet-4-6" */
    public function getModel(): string;

    /** Short slug: 'claude' | 'gpt' | 'gemini' | 'ollama' */
    public function getProviderName(): string;
}
