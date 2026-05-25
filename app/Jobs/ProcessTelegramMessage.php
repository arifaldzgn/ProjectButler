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

class ProcessTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Per spec: DON'T run AI calls synchronously inside the webhook handler.
     * This job processes the message asynchronously via Laravel Queue.
     */

    public int $userId;
    public string $chatId;
    public string $message;

    public function __construct(int $userId, string $chatId, string $message)
    {
        $this->userId = $userId;
        $this->chatId = $chatId;
        $this->message = $message;
    }

    public function handle(MessageRouter $router): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('ProcessTelegramMessage: user not found', ['user_id' => $this->userId]);
            return;
        }

        try {
            $router->handle($user, $this->chatId, $this->message);
        } catch (\Throwable $e) {
            Log::error('ProcessTelegramMessage failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Max 2 attempts per spec DON'Ts.
     */
    public int $tries = 2;
}
