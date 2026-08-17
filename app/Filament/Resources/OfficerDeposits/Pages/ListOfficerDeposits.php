<?php

namespace App\Filament\Resources\OfficerDeposits\Pages;

use App\Filament\Actions\ExportTableAction;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\OfficerDeposits\OfficerDepositResource;
use App\Filament\Resources\OfficerDeposits\Widgets\OfficerDepositSummary;
use App\Filament\Widgets\OfficerDepositWidget;
use App\Models\OfficerDeposit;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ListOfficerDeposits extends ListRecords
{
    // InteractsWithTable dipakai di ListRecords (parent), bukan di kelas ini,
    // jadi method senama dari trait ini menang tanpa perlu insteadof.
    use HasFiltersForm;

    protected static string $resource = OfficerDepositResource::class;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('period')
                    ->label(__('Period'))
                    ->options(Dashboard::periodOptions())
                    ->default(now()->format('Y-m'))
                    ->selectablePlaceholder(false),
            ]);
    }

    /** Urutan konten: filter → ringkasan & panel per petugas → tabel setoran. */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('filtersForm'),
            Grid::make(1)->schema(fn (): array => $this->getWidgetsSchemaComponents([
                OfficerDepositSummary::class,
                OfficerDepositWidget::class,
            ])),
            EmbeddedTable::make(),
        ]);
    }

    public function periodStart(): Carbon
    {
        return Carbon::parse(($this->filters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();
    }

    /** Baris tabel ikut periode terpilih; ListRecords memanggil method ini di makeTable(). */
    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->whereDate('period', $this->periodStart())
            // Record yang baru dibuat dipin ke baris pertama (sort tabel jadi
            // tie-breaker). ?created= hanya ada di GET awal, jadi pin otomatis
            // lepas begitu user menyentuh tabel.
            ->when(request()->query('created'), fn (Builder $query, string $id) => $query->orderByRaw('id = ? desc', [$id]));
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportTableAction::make(
                'setoran_petugas',
                [
                    __('Officer'),
                    __('Period'),
                    __('Amount'),
                    __('Must Collect'),
                    __('Received By'),
                    __('Deposited At'),
                ],
                fn (OfficerDeposit $record): array => [
                    $record->officer?->name ?? '',
                    $record->period->translatedFormat('F Y'),
                    (float) $record->amount,
                    $record->mustCollect(),
                    $record->receiver?->name ?? '',
                    $record->deposited_at?->format('d/m/Y H:i') ?? '',
                ],
            ),
            CreateAction::make(),
        ];
    }
}
