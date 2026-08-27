<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Filament\Actions\ExportTableAction;
use App\Filament\Widgets\CommissionSummary;
use App\Models\User;
use App\Services\BillingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Komisi per petugas untuk satu periode: jumlah pelanggan LUNAS di cluster
 * yang dipegangnya x tarif komisi petugas itu (BillingService::commissionQuery()).
 * Petugas tanpa pelanggan lunas tetap tampil dengan komisi 0.
 */
class Commission extends Page implements HasTable
{
    // Sama seperti MonthlyBilling: dua trait membawa method bernama sama.
    use HasFiltersForm, InteractsWithTable {
        HasFiltersForm::normalizeTableFilterValuesFromQueryString insteadof InteractsWithTable;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('Commission');
    }

    public function getTitle(): string
    {
        return __('Commission');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Billing');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isRole(Role::SuperAdmin, Role::Admin) ?? false;
    }

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

    /** Urutan konten: filter → kartu ringkasan → tabel. */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('filtersForm'),
            Grid::make(3)->schema(fn (): array => $this->getWidgetsSchemaComponents([
                CommissionSummary::class,
            ])),
            EmbeddedTable::make(),
        ]);
    }

    /** Export mengikuti filter periode halaman + filter/search/sort tabel. */
    protected function getHeaderActions(): array
    {
        return [
            ExportTableAction::make(
                'komisi_'.$this->periodStart()->format('Y_m'),
                [
                    __('Officer'),
                    __('Paid Customers'),
                    __('Commission Per Customer'),
                    __('Commission Amount'),
                    __('Estimated Commission'),
                ],
                fn (User $record): array => [
                    $record->name,
                    (int) $record->paid_customers,
                    (float) $record->commission_per_customer,
                    $record->commission_amount,
                    $record->estimated_commission_amount,
                ],
            ),
        ];
    }

    public function periodStart(): Carbon
    {
        return Carbon::parse(($this->filters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(BillingService::class)->commissionQuery($this->periodStart()))
            ->defaultSort('commission_amount', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Officer'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('clusters_count')
                    ->label(__('Clusters'))
                    ->counts('clusters')
                    ->toggleable(),
                TextColumn::make('paid_customers')
                    ->label(__('Paid Customers'))
                    ->sortable(),
                TextColumn::make('commission_per_customer')
                    ->label(__('Commission Per Customer'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('commission_amount')
                    ->label(__('Commission Amount'))
                    ->state(fn (User $record): float => $record->commission_amount)
                    ->money('IDR')
                    ->weight('bold')
                    // Komisi bukan kolom tabel — urutkan lewat jumlah x tarif.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("COALESCE(paid_customers, 0) * commission_per_customer {$direction}")),
                TextColumn::make('estimated_commission_amount')
                    ->label(__('Estimated Commission'))
                    ->state(fn (User $record): float => $record->estimated_commission_amount)
                    ->money('IDR')
                    ->color('warning')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("COALESCE(estimated_customers, 0) * commission_per_customer {$direction}")),
            ])
            ->filters([
                // Semua petugas tampil secara default; filter ini cara membatasi
                // ke yang aktif saja.
                TernaryFilter::make('is_active')
                    ->label(__('Active')),
            ]);
    }
}
