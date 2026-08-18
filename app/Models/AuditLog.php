<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Satu baris = satu aktivitas user. Append-only: ditulis hanya oleh AuditService,
 * tidak pernah di-update/hapus dari UI. Pembersihan lewat MassPrunable.
 */
#[Fillable([
    'user_id',
    'event',
    'subject_type',
    'subject_id',
    'subject_label',
    'changed_values',
    'url',
    'ip_address',
])]
class AuditLog extends Model
{
    use HasUlids, MassPrunable;

    /** ponytail: hardcode; pindah ke tabel settings kalau perlu diatur dari UI. */
    public const RETENTION_MONTHS = 6;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'changed_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** MassPrunable: satu DELETE, tanpa model event — jadi tidak memicu audit lagi. */
    public function prunable(): Builder
    {
        return static::query()
            ->where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Ringkas `changed_values` jadi satu baris terbaca: "Status: AKTIF → ISOLIR".
     * Nama field dilewatkan __() lewat headline supaya key lang/id.json yang
     * sudah ada ikut terterjemah; sisanya jatuh ke bahasa Inggris.
     */
    public function changesSummary(): string
    {
        return collect($this->changed_values ?? [])
            ->map(function (array $pair, string $field): string {
                [$old, $new] = $pair;

                $label = __(Str::headline($field));

                return $old === null
                    ? sprintf('%s: %s', $label, $this->stringify($new))
                    : sprintf('%s: %s → %s', $label, $this->stringify($old), $this->stringify($new));
            })
            ->implode(' · ');
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? __('Yes') : __('No'),
            is_array($value) => json_encode($value),
            default => (string) $value,
        };
    }
}
