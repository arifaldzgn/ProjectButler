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

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
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
