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

                if ($isJson) {
                    $parsed = json_decode($content, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception("OpenRouter API Error: Invalid JSON from {$model}");
                    }
                    return ['data' => $parsed, 'model_used' => $model];
                }

                return ['text' => $content, 'model_used' => $model];

            } catch (\Exception $e) {
                $lastException = $e;
                // If it's the first model and we have a fallback, it will continue the loop
            }
        }

        Log::error('OpenRouter all models failed', ['exception' => $lastException->getMessage()]);
        throw $lastException;
    }
}
