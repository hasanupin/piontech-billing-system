<?php

namespace App\Filament\Actions;

use App\Enums\Role;
use App\Exports\Xlsx;
use Closure;
use Filament\Actions\Action;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;

/**
 * Header action "Export Excel" untuk halaman ber-tabel: mengekspor query yang
 * SEDANG tampil (filter, search, dan sort tabel ikut) — bukan seluruh tabel.
 * Nominal ditulis sebagai angka mentah supaya bisa di-SUM di Excel.
 */
class ExportTableAction
{
    /**
     * @param  array<int, string>  $headings
     * @param  Closure(Model): array<int, string|int|float|null>  $row
     */
    public static function make(string $filenamePrefix, array $headings, Closure $row): Action
    {
        return Action::make('export')
            ->label(__('Export Excel'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->isRole(Role::SuperAdmin, Role::Admin) ?? false)
            ->action(function (HasTable $livewire) use ($filenamePrefix, $headings, $row) {
                $rows = [$headings];

                // cursor(): export bisa ribuan baris, jangan tahan semua di memori.
                foreach ($livewire->getFilteredSortedTableQuery()->cursor() as $record) {
                    $rows[] = $row($record);
                }

                return Xlsx::stream(
                    sprintf('%s_%s.xlsx', $filenamePrefix, now()->format('Y-m-d')),
                    $rows,
                );
            });
    }
}
