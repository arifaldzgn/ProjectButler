<?php

use Illuminate\Support\Facades\Route;

// Dashboard is out of scope for v1 — per spec: "DON'T build the dashboard yet"
Route::get('/', function () {
    return response()->json([
        'app' => 'Project Butler',
        'version' => 'v1',
        'status' => 'running',
        'note' => 'Butler is a Telegram-only assistant. No web dashboard for v1.',
    ]);
});
