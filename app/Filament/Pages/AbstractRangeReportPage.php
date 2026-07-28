<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Exports\BaseExport;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dasar halaman laporan ber-filter rentang tanggal (default bulan berjalan).
 * Abstract → dilewati auto-discovery panel; tiap laporan jadi sub-menu
 * sendiri di grup "Reports". Preview on-screen = tabel Filament dari
 * previewRows() (baris 0 heading, sisanya data — kolom seragam).
 */
abstract class AbstractRangeReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.report-range';

    // Rentang tanggal terpilih (Y-m-d); default awal–akhir bulan ini.
    public ?string $from = null;

    public ?string $until = null;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->until = now()->endOfMonth()->toDateString();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Reports');
    }

    public static function canAccess(): bool
    {
        // Hanya CEO & admin; petugas forbidden.
        return auth()->user()?->isRole(Role::SuperAdmin, Role::Admin) ?? false;
    }

    /** Deskripsi singkat isi laporan (ditampilkan di halaman). */
    abstract public function description(): string;

    abstract protected function makeExport(Carbon $from, Carbon $until): BaseExport;

    /** Prefix nama file, mis. "rekap" → rekap_2026-07-01_2026-07-31.xlsx */
    abstract protected function filenamePrefix(): string;

    /** @return array{Carbon, Carbon} rentang ternormalisasi (terbalik → ditukar) */
    protected function resolvedRange(): array
    {
        $from = Carbon::parse($this->from ?: now()->startOfMonth());
        $until = Carbon::parse($this->until ?: now()->endOfMonth());

        return $from->greaterThan($until) ? [$until, $from] : [$from, $until];
    }

    /**
     * Baris preview kolom-seragam (baris 0 heading, sisanya data).
     * Default = rows() export (rekap & tunggakan sudah kolom seragam);
     * laporan ragged (setoran) meng-override.
     *
     * @return array<int, array<int, string|int|float>>
     */
    public function previewRows(): array
    {
        [$from, $until] = $this->resolvedRange();

        return $this->makeExport($from, $until)->rows();
    }

    public function table(Table $table): Table
    {
        $rows = $this->previewRows();
        $headers = $rows[0] ?? [];

        $columns = [];
        foreach ($headers as $i => $label) {
            $columns[] = TextColumn::make("c{$i}")->label((string) $label)->wrap();
        }

        $records = [];
        foreach (array_slice($rows, 1) as $idx => $row) {
            $record = [];
            foreach ($row as $i => $cell) {
                $record["c{$i}"] = $cell;
            }
            $records[$idx] = $record;
        }

        return $table
            ->records(fn (): array => $records)
            ->columns($columns)
            ->paginated([25, 50, 100, 'all']);
    }

    public function download(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        [$from, $until] = $this->resolvedRange();
        $filename = sprintf('%s_%s_%s.xlsx', $this->filenamePrefix(), $from->toDateString(), $until->toDateString());

        return $this->makeExport($from, $until)->download($filename);
    }
}
