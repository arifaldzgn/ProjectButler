<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Butler AI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Project Butler — personal AI assistant.
    | Per v1 spec: telegram_chat_id is your auth, goals are per-user.
    |
    */

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
    ],

    'ai' => [
        'api_key' => env('AI_API_KEY'),
        'base_url' => env('AI_BASE_URL', 'https://openrouter.ai/api/v1/chat/completions'),
        'primary_model' => env('AI_PRIMARY_MODEL', 'google/gemini-2.0-flash-lite-preview-02-05:free'),
        'fallback_model' => env('AI_FALLBACK_MODEL', 'meta-llama/llama-3.3-70b-instruct:free'),
    ],

    // Default timezone (used for scheduling; users have their own timezone)
    'timezone' => env('BUTLER_TIMEZONE', 'Asia/Jakarta'),

    // AI prompt versions (for tracking in ai_logs)
    'prompt_versions' => [
        'parser' => 'parse_v1',
        'summary' => 'summary_daily_v1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Chat IDs
    |--------------------------------------------------------------------------
    |
    | Only these Telegram chat IDs can interact with Butler.
    | Leave empty to allow all (not recommended for production).
    | Comma-separated list of chat IDs.
    |
    */
    'allowed_chat_ids' => env('ALLOWED_CHAT_IDS', ''),

];
