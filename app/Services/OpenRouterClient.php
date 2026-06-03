<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterClient
{
    private string $apiKey;
    private string $primaryModel;
    private string $fallbackModel;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('butler.ai.api_key');
        $this->primaryModel = config('butler.ai.primary_model');
        $this->fallbackModel = config('butler.ai.fallback_model');
        $this->baseUrl = config('butler.ai.base_url', 'https://openrouter.ai/api/v1/chat/completions');
    }

    /**
     * Send a message and get a structured JSON response.
     * Tries primary model first, falls back to secondary if it fails.
     *
     * @return array|null Associative array containing ['data' => parsedJson, 'model_used' => string]
     */
    public function generateJson(string $systemPrompt, string $userMessage): ?array
    {
        $payload = [
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
            'max_tokens' => 250,
        ];

        return $this->executeWithFallback($payload, true);
    }

    /**
     * Send a message and get a plain text response.
     *
     * @return array|null Associative array containing ['text' => string, 'model_used' => string]
     */
    public function generateText(string $systemPrompt, string $userMessage): ?array
    {
        $payload = [
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'temperature' => 0.7,
            'max_tokens' => 300,
        ];

        return $this->executeWithFallback($payload, false);
    }

    /**
     * Send a vision (image + text) request to a multimodal model and get JSON.
     * Uses a vision-capable model (google/gemini-2.0-flash-001 supports images).
     *
     * @param  string $systemPrompt
     * @param  string $imageBase64    Base64-encoded image data
     * @param  string $mimeType       e.g. 'image/jpeg'
     * @param  string $userText       Optional text alongside the image
     * @return array|null             ['data' => parsedJson, 'model_used' => string, 'token_usage' => ...]
     */
    public function generateVisionJson(string $systemPrompt, string $imageBase64, string $mimeType = 'image/jpeg', string $userText = ''): ?array
    {
        $payload = [
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => array_filter([
                    ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageBase64}"]],
                    $userText ? ['type' => 'text', 'text' => $userText] : null,
                ])],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1,
            'max_tokens' => 400,
        ];

        return $this->executeWithFallback($payload, true);
    }

    /**
     * Executes the request with fallback logic.
     */
    private function executeWithFallback(array $payload, bool $isJson): ?array
    {
        $models = [$this->primaryModel];
        if ($this->fallbackModel && $this->fallbackModel !== $this->primaryModel) {
            $models[] = $this->fallbackModel;
        }

        $lastException = null;

        foreach ($models as $model) {
            try {
                $payload['model'] = $model;

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'HTTP-Referer' => config('app.url'), // Optional, for OpenRouter rankings
                    'X-Title' => config('app.name'), // Optional, for OpenRouter rankings
                ])->timeout(30)->post($this->baseUrl, $payload);

                if (!$response->successful()) {
                    $errorBody = $response->body();
                    Log::warning("OpenRouter API error for model {$model}", [
                        'status' => $response->status(),
                        'body' => $errorBody,
                    ]);
                    throw new \Exception("OpenRouter API Error ({$model}): " . $errorBody);
                }

                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? null;

                if (!$content) {
                    throw new \Exception("OpenRouter API Error: Empty content from {$model}");
                }

                // Extract token usage from OpenRouter response
                $tokenUsage = [
                    'input'  => $data['usage']['prompt_tokens'] ?? null,
                    'output' => $data['usage']['completion_tokens'] ?? null,
                ];

                if ($isJson) {
                    $parsed = json_decode($content, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception("OpenRouter API Error: Invalid JSON from {$model}");
                    }
                    return ['data' => $parsed, 'model_used' => $model, 'token_usage' => $tokenUsage];
                }

                return ['text' => $content, 'model_used' => $model, 'token_usage' => $tokenUsage];

            } catch (\Exception $e) {
                $lastException = $e;
                // If it's the first model and we have a fallback, it will continue the loop
            }
        }

        Log::error('OpenRouter all models failed', ['exception' => $lastException->getMessage()]);
        throw $lastException;
    }
}
