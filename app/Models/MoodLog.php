<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoodLog extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'mood',
        'energy_level',
        'note',
        'log_date',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
        ];
    }

    /**
     * Valid mood values (matches DB enum).
     */
    public static array $moods = ['great', 'good', 'okay', 'bad', 'terrible'];

    /**
     * Map Indonesian casual keywords to mood values.
     */
    public static function parseMood(string $text): ?string
    {
        $text = strtolower(trim($text));
        return match (true) {
            in_array($text, ['great', 'sangat baik', 'luar biasa', 'excellent']) => 'great',
            in_array($text, ['good', 'baik', 'bagus', 'oke', 'ok'])             => 'good',
            in_array($text, ['okay', 'lumayan', 'biasa', 'so-so', 'biasa aja']) => 'okay',
            in_array($text, ['bad', 'buruk', 'tidak baik', 'jelek', 'kurang']) => 'bad',
            in_array($text, ['terrible', 'sangat buruk', 'parah', 'hancur'])   => 'terrible',
            default => null,
        };
    }
}
