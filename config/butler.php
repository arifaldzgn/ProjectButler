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

    /*
    |--------------------------------------------------------------------------
    | Smart Fallback — Suggestion Engine
    |--------------------------------------------------------------------------
    |
    | Stage-1 similarity threshold (0.0–1.0). Higher = stricter matches.
    | Default 0.55 works well empirically. Tune via BUTLER_SUGGESTION_THRESHOLD.
    |
    */
    'suggestion_threshold' => (float) env('BUTLER_SUGGESTION_THRESHOLD', 0.55),

    /*
    |--------------------------------------------------------------------------
    | Undo Window
    |--------------------------------------------------------------------------
    |
    | How many minutes after confirmation the undo button stays active.
    | Default 5 minutes. Configure via BUTLER_UNDO_WINDOW_MINUTES.
    |
    */
    'undo_window_minutes' => (int) env('BUTLER_UNDO_WINDOW_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Shortcut API
    |--------------------------------------------------------------------------
    |
    | Configuration for the public Shortcut API used by iPhone Shortcuts,
    | Android HTTP clients, and other non-Telegram entry points.
    |
    | admin_secret  — Header value required to issue API tokens (POST /api/shortcut/token).
    |                 Set SHORTCUT_ADMIN_SECRET in .env. Never commit this value.
    | rate_limit    — Max requests per minute per authenticated user.
    | sync_timeout  — Seconds to wait for an AI response in sync mode.
    |
    */
    'shortcut' => [
        'admin_secret'         => env('SHORTCUT_ADMIN_SECRET'),
        'rate_limit'           => (int) env('SHORTCUT_RATE_LIMIT', 30),
        'sync_timeout'         => (int) env('SHORTCUT_SYNC_TIMEOUT', 12),
        'pairing_ttl_minutes'  => (int) env('SHORTCUT_PAIRING_TTL_MINUTES', 15),
        'idempotency_ttl_seconds' => (int) env('SHORTCUT_IDEMPOTENCY_TTL', 600),
    ],

];
