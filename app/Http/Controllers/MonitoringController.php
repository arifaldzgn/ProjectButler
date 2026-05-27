<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonitoringController extends Controller
{
    public function index()
    {
        $telegramToken = config('butler.telegram.bot_token');
        $webhookInfo = null;
        $botInfo = null;
        $aiStatus = 'Checking...';

        try {
            // Get Webhook Info
            $webhookResponse = Http::timeout(3)->get("https://api.telegram.org/bot{$telegramToken}/getWebhookInfo");
            if ($webhookResponse->successful()) {
                $webhookInfo = $webhookResponse->json('result');
            }

            // Get Bot Info
            $botResponse = Http::timeout(3)->get("https://api.telegram.org/bot{$telegramToken}/getMe");
            if ($botResponse->successful()) {
                $botInfo = $botResponse->json('result');
            }

            // Simple AI Config Check
            $aiBaseUrl = config('butler.ai.base_url');
            if (!empty($aiBaseUrl)) {
                $aiStatus = 'Configured';
            } else {
                $aiStatus = 'Missing Base URL';
            }

        } catch (\Exception $e) {
            Log::error('Monitoring API Check Failed', ['error' => $e->getMessage()]);
            $webhookInfo = ['error' => 'Failed to reach Telegram API: ' . $e->getMessage()];
        }

        return view('monitoring', [
            'webhookInfo' => $webhookInfo,
            'botInfo' => $botInfo,
            'aiStatus' => $aiStatus,
            'aiConfig' => [
                'primary_model' => config('butler.ai.primary_model'),
                'fallback_model' => config('butler.ai.fallback_model'),
                'base_url' => config('butler.ai.base_url'),
            ],
            'appUrl' => config('app.url'),
            'timezone' => config('butler.timezone'),
        ]);
    }
}
