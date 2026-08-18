<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * Satu-satunya penulis audit_logs.
 *
 * Sadapannya wildcard event Eloquent, bukan trait per model: semua jalur tulis
 * (form Filament, quick action ISOLIR, import Excel, update langsung) lewat
 * Model::save(), jadi tidak ada jalur yang bisa lupa dipasangi.
 */
class AuditService
{
    /** Halaman yang sama tidak dicatat ulang selama jendela ini. */
    public const DEDUPE_MINUTES = 5;

    /**
     * ponytail: hanya AuditLog yang dikecualikan (kalau ikut, satu tulisan
     * berlipat tanpa batas). Model baru otomatis teraudit — itu yang diinginkan;
     * tambahkan ke sini kalau nanti ada model ber-write-volume tinggi.
     */
    private const IGNORED = [AuditLog::class];

    /**
     * Jaring kedua di atas $model->getHidden() — untuk model yang tidak
     * mendeklarasikan #[Hidden], dan untuk membuang timestamp yang tak berarti.
     */
    private const SKIP_FIELDS = ['created_at', 'updated_at', 'password', 'remember_token'];

    public function listen(): void
    {
        $events = [
            'created' => AuditEvent::Created,
            'updated' => AuditEvent::Updated,
            'deleted' => AuditEvent::Deleted,
        ];

        foreach ($events as $name => $event) {
            Event::listen("eloquent.{$name}: *", function (string $eventName, array $payload) use ($event): void {
                $model = $payload[0] ?? null;

                if (! $model instanceof Model || in_array($model::class, self::IGNORED, true)) {
                    return;
                }

                $this->record($event, $model, $this->diff($event, $model));
            });
        }

        Event::listen(Login::class, fn (Login $e) => $this->record(
            AuditEvent::LoggedIn,
            userId: $e->user->getAuthIdentifier(),
        ));

        // auth()->id() sudah null di titik ini — aktornya harus diambil dari event.
        Event::listen(Logout::class, fn (Logout $e) => $this->record(
            AuditEvent::LoggedOut,
            userId: $e->user?->getAuthIdentifier(),
        ));
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     */
    public function record(
        AuditEvent $event,
        ?Model $subject = null,
        array $changes = [],
        ?string $label = null,
        ?int $userId = null,
    ): void {
        $userId ??= auth()->id();

        // Tanpa aktor tidak ada yang bisa diaudit: seeder, artisan, factory di test.
        if ($userId === null) {
            return;
        }

        // Save yang cuma menyentuh timestamp bukan aktivitas.
        if ($event === AuditEvent::Updated && $changes === []) {
            return;
        }

        AuditLog::create([
            'user_id' => $userId,
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject ? (string) $subject->getKey() : null,
            'subject_label' => $label ?? $this->label($subject),
            'changed_values' => $changes ?: null,
            // url sengaja tidak diisi: perubahan data datang dari endpoint Livewire
            // (livewire/update), bukan halaman — hanya akses menu punya URL berarti.
            'ip_address' => request()->ip(),
        ]);
    }

    public function recordPageVisit(Request $request): void
    {
        $userId = auth()->id();
        $path = $request->path();

        if ($userId === null) {
            return;
        }

        // Cache::add atomik: hanya request pertama dalam jendela yang lolos.
        $fresh = Cache::add(
            "audit:visit:{$userId}:{$path}",
            true,
            now()->addMinutes(self::DEDUPE_MINUTES),
        );

        if (! $fresh) {
            return;
        }

        $class = $request->route()?->getAction('controller');

        // subject_type sengaja dibiarkan null: isinya class Page, bukan model —
        // basename-nya ("ListCustomers") tidak bisa diterjemahkan dan hanya
        // mengotori kolom & filter Objek. Nama menu sudah ada di subject_label.
        AuditLog::create([
            'user_id' => $userId,
            'event' => AuditEvent::Accessed,
            'subject_label' => $this->pageLabel($class, $path),
            'url' => $path,
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * Filament mendaftarkan class Page sebagai route action, jadi label menu
     * bisa diambil dari sana — jatuh ke path kalau bentuknya di luar dugaan.
     */
    private function pageLabel(mixed $class, string $path): string
    {
        if (! is_string($class) || ! class_exists($class)) {
            return $path;
        }

        return match (true) {
            method_exists($class, 'getResource') => ($class::getResource())::getPluralModelLabel(),
            method_exists($class, 'getNavigationLabel') => $class::getNavigationLabel(),
            default => $path,
        };
    }

    /**
     * ponytail: label generik. Transaksi/setoran tampil sebagai ULID —
     * changed_values sudah memuat nominal & customer_id. Tambahkan auditLabel()
     * per model kalau nanti perlu lebih terbaca.
     */
    private function label(?Model $subject): ?string
    {
        return $subject?->getAttribute('name')
            ?? $subject?->getAttribute('key');
    }

    /**
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function diff(AuditEvent $event, Model $model): array
    {
        $skip = array_flip([...self::SKIP_FIELDS, ...$model->getHidden()]);

        return match ($event) {
            AuditEvent::Created => collect(array_diff_key($model->getAttributes(), $skip))
                ->map(fn (mixed $value): array => [null, $value])
                ->all(),

            AuditEvent::Updated => collect(array_diff_key($model->getChanges(), $skip))
                ->map(fn (mixed $new, string $field): array => [$model->getOriginal($field), $new])
                ->all(),

            default => [],
        };
    }
}
