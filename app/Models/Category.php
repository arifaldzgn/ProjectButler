<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'icon',
        'color',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOrdered($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /**
     * Default categories to seed for every new user.
     */
    public static function defaultDefinitions(): array
    {
        return [
            ['name' => 'Makanan & Minuman', 'icon' => '🍽️', 'color' => 'var(--orange)', 'sort_order' => 10],
            ['name' => 'Transport',          'icon' => '🚗', 'color' => 'var(--blue)',   'sort_order' => 20],
            ['name' => 'Belanja',            'icon' => '🛍️', 'color' => 'var(--purple)', 'sort_order' => 30],
            ['name' => 'Hiburan',            'icon' => '🎮', 'color' => 'var(--green)',  'sort_order' => 40],
            ['name' => 'Kesehatan',          'icon' => '💊', 'color' => 'var(--red)',    'sort_order' => 50],
            ['name' => 'Pendidikan',         'icon' => '📚', 'color' => 'var(--blue)',   'sort_order' => 60],
            ['name' => 'Tagihan & Utilitas', 'icon' => '🔌', 'color' => 'var(--yellow)', 'sort_order' => 70],
            ['name' => 'Investasi',          'icon' => '📈', 'color' => 'var(--green)',  'sort_order' => 80],
            ['name' => 'Lainnya',            'icon' => '📦', 'color' => 'var(--text-muted)', 'sort_order' => 90],
        ];
    }

    /**
     * Seed default categories for a user (idempotent).
     */
    public static function seedDefaults(User $user): void
    {
        if (self::forUser($user->id)->where('is_default', true)->exists()) {
            return;
        }

        foreach (self::defaultDefinitions() as $def) {
            self::create(array_merge($def, [
                'user_id'    => $user->id,
                'is_default' => true,
            ]));
        }
    }
}
