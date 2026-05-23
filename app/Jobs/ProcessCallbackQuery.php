<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\MessageRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCallbackQuery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;
    public string $chatId;
    public string $callbackQueryId;
    public string $data;
    public int $messageId;

    public function __construct(int $userId, string $chatId, string $callbackQueryId, string $data, int $messageId)
    {
        $this->userId = $userId;
        $this->chatId = $chatId;
        $this->callbackQueryId = $callbackQueryId;
        $this->data = $data;
        $this->messageId = $messageId;
    }

    public function handle(MessageRouter $router): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('ProcessCallbackQuery: user not found', ['user_id' => $this->userId]);
            return;
        }

        try {
            $router->handleCallbackQuery($user, $this->chatId, $this->callbackQueryId, $this->data, $this->messageId);
        } catch (\Exception $e) {
            Log::error('ProcessCallbackQuery failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public int $tries = 2;
}
