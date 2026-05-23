<?php

namespace App\Services;

use App\Models\Reminder;
use App\Models\Streak;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class OnboardingService
{
    private TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Process a message during onboarding flow.
     *
     * State machine: new → asked_name → asked_budget → asked_calorie → complete
     *
     * @return bool True if the message was handled by onboarding
     */
    public function handle(User $user, string $chatId, string $message): bool
    {
        if ($user->isOnboardingComplete()) {
            return false; // Not in onboarding — let main router handle it
        }

        $msg = strtolower(trim($message));

        return match ($user->onboarding_step) {
            'new' => $this->handleNew($user, $chatId, $msg),
            'asked_name' => $this->handleName($user, $chatId, $message),
            'asked_budget' => $this->handleBudget($user, $chatId, $msg),
            'asked_calorie' => $this->handleCalorie($user, $chatId, $msg),
            default => false,
        };
    }

    /**
     * Step 1: User sends /start → ask for name.
     */
    private function handleNew(User $user, string $chatId, string $message): bool
    {
        $text = "Halo! Aku Butler, asisten harianmu.\n"
              . "Aku bisa bantu catat pengeluaran, kalori, dan tabungan kamu lewat chat biasa.\n\n"
              . "Boleh kenalan dulu? Nama kamu siapa?";

        $this->telegram->sendMessage($chatId, $text);

        $user->update(['onboarding_step' => 'asked_name']);
        return true;
    }

    /**
     * Step 2: User replies with name → ask for budget.
     */
    private function handleName(User $user, string $chatId, string $rawName): bool
    {
        $name = trim($rawName);
        if (empty($name) || strlen($name) > 128) {
            $this->telegram->sendMessage($chatId, "Hmm, coba ketik nama kamu ya (singkat aja).");
            return true;
        }

        $user->update([
            'name' => $name,
            'onboarding_step' => 'asked_budget',
        ]);

        $text = "Hai {$name}! 👋\n"
              . "Mau set budget harian? Ini buat aku bisa kasih tahu kalau kamu udah mendekati limit.\n"
              . "(Ketik nominalnya, atau ketik \"skip\")";

        $this->telegram->sendMessage($chatId, $text);
        return true;
    }

    /**
     * Step 3: User replies with budget or "skip" → ask for calorie goal.
     */
    private function handleBudget(User $user, string $chatId, string $message): bool
    {
        $budget = null;
        $budgetText = 'Nggak di-set';

        if ($message !== 'skip' && $message !== 'lewat') {
            $budget = $this->parseAmount($message);
            if ($budget === null || $budget <= 0) {
                $this->telegram->sendMessage($chatId,
                    "Hmm, Butler nggak nangkep nominalnya. Coba ketik angka (contoh: 200000 atau 200rb), atau \"skip\"."
                );
                return true;
            }
            $budgetText = 'Rp ' . number_format($budget, 0, ',', '.') . '/hari';
        }

        $user->update([
            'daily_budget_idr' => $budget,
            'onboarding_step' => 'asked_calorie',
        ]);

        $text = "Oke, budget {$budgetText}. ✅\n"
              . "Kalau gym atau diet — mau set target kalori harian?\n"
              . "(Ketik nominalnya, atau ketik \"skip\")";

        $this->telegram->sendMessage($chatId, $text);
        return true;
    }

    /**
     * Step 4: User replies with calorie goal or "skip" → onboarding complete.
     */
    private function handleCalorie(User $user, string $chatId, string $message): bool
    {
        $calorieGoal = null;

        if ($message !== 'skip' && $message !== 'lewat') {
            $calorieGoal = (int) preg_replace('/[^0-9]/', '', $message);
            if ($calorieGoal <= 0 || $calorieGoal > 10000) {
                $this->telegram->sendMessage($chatId,
                    "Hmm, angka kalorinya kurang pas. Coba ketik angka (contoh: 2000), atau \"skip\"."
                );
                return true;
            }
        }

        $user->update([
            'daily_calorie_goal' => $calorieGoal,
            'onboarding_step' => 'complete',
            'onboarding_complete_at' => now(),
        ]);

        // Create initial streak record
        Streak::firstOrCreate(['user_id' => $user->id]);

        // Create default behavior-based reminders
        $this->createDefaultReminders($user);

        $text = "Siap! Sekarang coba log sesuatu. 🚀\n"
              . "Contoh: \"makan nasi goreng 35k\" atau \"grab 23rb\"\n\n"
              . "Ketik apapun, Butler langsung ngerti!";

        $this->telegram->sendMessage($chatId, $text);
        return true;
    }

    /**
     * Create default behavior-based reminders (per spec).
     */
    private function createDefaultReminders(User $user): void
    {
        try {
            Reminder::create([
                'user_id' => $user->id,
                'type' => 'behavior_based',
                'trigger_condition' => ['type' => 'no_expense_log', 'by_time' => '20:00'],
                'message_template' => "Hei {name}, belum ada catatan pengeluaran hari ini. Ada yang kelewat? Coba ketik sekarang sebelum lupa.",
            ]);

            Reminder::create([
                'user_id' => $user->id,
                'type' => 'behavior_based',
                'trigger_condition' => ['type' => 'inactive_days', 'threshold' => 2],
                'message_template' => "Butler kangen, {name}. Udah 2 hari nggak ada catatan. Gimana kabar keuangan kamu?",
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create default reminders', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Parse an Indonesian-style amount string.
     * Supports: 200000, 200rb, 200ribu, 2jt, 200k
     */
    private function parseAmount(string $input): ?int
    {
        $input = strtolower(trim($input));

        // Remove "rp", dots, spaces
        $input = preg_replace('/[rp\s\.]/', '', $input);

        // Handle "jt" / "juta"
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(jt|juta)$/i', $input, $m)) {
            return (int) ((float) str_replace(',', '.', $m[1]) * 1000000);
        }

        // Handle "rb" / "ribu" / "k"
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(rb|ribu|k)$/i', $input, $m)) {
            return (int) ((float) str_replace(',', '.', $m[1]) * 1000);
        }

        // Plain number
        if (preg_match('/^(\d+)$/', $input, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
