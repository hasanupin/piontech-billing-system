<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai default aplikasi (key-value). Satu-satunya pintu baca/tulis setting —
 * jangan query tabel ini langsung dari resource/service lain.
 */
#[Fillable([
    'key',
    'value',
    'updated_by',
])]
class Setting extends Model
{
    use HasUlids;

    public const DEFAULT_COMMISSION_PERCENT = 'default_commission_percent';

    /** Fallback saat barisnya belum ada — app tetap benar di DB fresh tanpa seeder. */
    public const DEFAULTS = [
        self::DEFAULT_COMMISSION_PERCENT => '4',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    /**
     * ponytail: query per pembacaan; kalau nanti setting dibaca banyak widget per
     * request, bungkus Cache::rememberForever + forget di set(). Sekarang tidak
     * berguna — cache driver proyek ini database, jadi cuma tukar query.
     */
    public static function get(string $key): ?string
    {
        return static::query()->where('key', $key)->value('value')
            ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => auth()->id()],
        );
    }

    public static function defaultCommissionPercent(): float
    {
        return (float) static::get(self::DEFAULT_COMMISSION_PERCENT);
    }
}
