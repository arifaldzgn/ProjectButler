<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiLog extends Model
{
    /**
     * Only created_at is stored (no updated_at column).
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'entry_id',
        'call_type',
        'prompt_version',
        'raw_input',
        'token_count_input',
        'raw_output',
        'token_count_output',
        'intent_detected',
        'confidence_score',
        'latency_ms',
        'was_successful',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:3',
            'was_successful' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
