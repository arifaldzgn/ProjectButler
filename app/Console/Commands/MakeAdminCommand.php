<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin {telegram_chat_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a user an admin using their Telegram Chat ID';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $telegramId = $this->argument('telegram_chat_id');
        $user = User::where('telegram_chat_id', $telegramId)->first();

        if (!$user) {
            $this->error("User with Telegram ID {$telegramId} not found.");
            return Command::FAILURE;
        }

        $user->update(['is_admin' => true]);
        $this->info("User {$user->name} ({$telegramId}) is now an admin.");
        return Command::SUCCESS;
    }
}
