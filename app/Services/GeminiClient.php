<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiClient
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('butler.gemini.api_key');
        $this->model = config('butler.gemini.model');
        $this->baseUrl = config('butler.gemini.base_url');
    }

    /**
     * Send a message to Gemini and get a structured JSON response.
     *
     * Uses max_tokens: 250 for parsing calls (per spec — enough, prevents cost waste).
     *
     * @param string $systemPrompt The system instruction for the AI
     * @param string $userMessage The user's message to parse
     * @return array|null Parsed JSON response, or null on failure
     */
    public function generateJson(string $systemPrompt, string $userMessage): ?array
    {
        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'contents' => [
                [
                    'parts' => [
                        ['text' => $userMessage],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.2, // Low temperature for consistent parsing
                'maxOutputTokens' => 250, // Per spec: enough for parsing, prevents cost waste
            ],
        ];

        try {
            $response = Http::timeout(30)
                ->post($url, $payload);

            if (!$response->successful()) {
                $errorBody = $response->body();
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                ]);
                throw new \Exception("Gemini API Error (HTTP {$response->status()}): " . $errorBody);
            }

            $data = $response->json();

            // Extract the text content from Gemini's response structure
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$text) {
                Log::error('Gemini response missing text content', ['data' => $data]);
                throw new \Exception("Gemini API Error: Response missing text content.");
            }

            $parsed = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Gemini JSON response', [
                    'text' => $text,
                    'error' => json_last_error_msg(),
                ]);
                throw new \Exception("Gemini API Error: Failed to parse JSON. Error: " . json_last_error_msg());
            }

            return $parsed;

        } catch (\Exception $e) {
            Log::error('Gemini API exception', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send a message and get a plain text response.
     * Used for Prompt B (summary) and general chat.
     */
    public function generateText(string $systemPrompt, string $userMessage): ?string
    {
        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'contents' => [
                [
                    'parts' => [
                        ['text' => $userMessage],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 300,
            ],
        ];

        try {
            $response = Http::timeout(30)->post($url, $payload);

            if (!$response->successful()) {
                throw new \Exception("Gemini API Text Error (HTTP {$response->status()}): " . $response->body());
            }

            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        } catch (\Exception $e) {
            Log::error('Gemini text API exception', ['message' => $e->getMessage()]);
            throw $e;
        }
    }
}
