<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Filament\Actions\ExportTableAction;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Widgets\MonthlyBillingChart;
use App\Filament\Widgets\MonthlyBillingDepositChart;
use App\Filament\Widgets\MonthlyBillingMethodChart;
use App\Filament\Widgets\MonthlyBillingSummary;
use App\Models\Cluster;
use App\Models\Customer;
use App\Services\ScopeService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MonthlyBilling extends Page implements HasTable
{
    // Dua-duanya membawa normalizeTableFilterValuesFromQueryString() dengan isi
    // identik — ambil salah satu agar tidak bentrok.
    use HasFiltersForm, InteractsWithTable {
        HasFiltersForm::normalizeTableFilterValuesFromQueryString insteadof InteractsWithTable;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('Monthly Billing');
    }

    public function getTitle(): string
    {
        return __('Monthly Billing');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Billing');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isRole(Role::SuperAdmin, Role::Admin, Role::FieldOfficer) ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportTableAction::make(
                'tagihan_bulanan',
                [
                    __('Name'),
                    __('Cluster'),
                    __('Address'),
                    __('WhatsApp'),
                    __('Billing Day'),
                    __('Amount'),
                    __('Customer Status'),
                    __('Payment Status'),
                ],
                fn (Customer $record): array => [
                    $record->name,
                    $record->cluster?->name ?? '',
                    $record->address ?? '',
                    $record->whatsapp_number ?? '',
                    $record->billing_day,
                    (float) $record->billing_amount,
                    $record->status->getLabel(),
                    $this->paymentStatusOf($record)->getLabel(),
                ],
            ),
        ];
    }

    /**
     * Filter dipasang di level halaman (bukan tabel) supaya letaknya di atas
     * chart: satu filter menggerakkan widget dan tabel sekaligus.
     */
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
                Select::make('cluster_id')
                    ->label(__('Cluster'))
                    ->placeholder(__('All'))
                    // Petugas hanya melihat cluster yang dia pegang.
                    ->options(fn (): array => app(ScopeService::class)
                        ->scopeClustersForUser(Cluster::query(), auth()->user())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                Select::make('payment_status')
                    ->label(__('Payment Status'))
                    ->placeholder(__('All'))
                    ->options([
                        'paid' => __('Paid'),
                        'unpaid' => __('Unpaid'),
                    ]),
            ]);
    }

    /**
     * Urutan konten: filter → kartu ringkasan → 3 pie → tabel.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('filtersForm'),
            Grid::make(3)->schema(fn (): array => $this->getWidgetsSchemaComponents([
                MonthlyBillingSummary::class,
                MonthlyBillingChart::class,
                MonthlyBillingMethodChart::class,
                MonthlyBillingDepositChart::class,
            ])),
            EmbeddedTable::make(),
        ]);
    }

    /**
     * Awal bulan dari filter periode; default bulan berjalan.
     */
    public function periodStart(): Carbon
    {
        $value = $this->filters['period'] ?? now()->format('Y-m');

        return Carbon::parse($value.'-01')->startOfMonth();
    }

    /** Lunas atau belum pada periode terpilih — dipakai kolom & export. */
    protected function paymentStatusOf(Customer $record): TransactionStatus
    {
        return $record->transactions()
            ->forPeriod($this->periodStart())
            ->where('status', TransactionStatus::Paid)
            ->exists()
            ? TransactionStatus::Paid
            : TransactionStatus::Unpaid;
    }

    /** Transaksi lunas pada periode terpilih — dipakai filter status bayar. */
    protected function paidInPeriod(Builder $query): Builder
    {
        return $query->forPeriod($this->periodStart())->where('status', TransactionStatus::Paid);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Customer::query()
                ->billable()
                ->when(
                    $this->filters['cluster_id'] ?? null,
                    fn (Builder $query, string $clusterId): Builder => $query->where('cluster_id', $clusterId),
                )
                ->when(
                    $this->filters['payment_status'] ?? null,
                    fn (Builder $query, string $status): Builder => $status === 'paid'
                        ? $query->whereHas('transactions', $this->paidInPeriod(...))
                        : $query->whereDoesntHave('transactions', $this->paidInPeriod(...)),
                ))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('cluster.name')
                    ->label(__('Cluster')),
                TextColumn::make('address')
                    ->label(__('Address'))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('whatsapp_number')
                    ->label(__('WhatsApp'))
                    ->url(fn (Customer $record): ?string => $record->whatsapp_number
                        ? 'https://wa.me/'.ltrim($record->whatsapp_number, '+')
                        : null)
                    ->openUrlInNewTab(),
                TextColumn::make('billing_day')
                    ->label(__('Billing Day'))
                    ->sortable(),
                TextColumn::make('billing_amount')
                    ->label(__('Amount'))
                    ->money('IDR'),
                TextColumn::make('status')
                    ->label(__('Customer Status'))
                    ->badge(),
                TextColumn::make('payment_status')
                    ->label(__('Payment Status'))
                    ->badge()
                    ->state(fn (Customer $record): TransactionStatus => $this->paymentStatusOf($record)),
            ])
            ->recordActions([
                // Foto & lokasi: tetap tampil tapi mati bila datanya kosong,
                // supaya petugas tahu pelanggan itu memang belum punya datanya.
                Action::make('house_photo')
                    ->label(__('House Photo'))
                    ->icon('heroicon-o-photo')
                    ->iconButton()
                    ->disabled(fn (Customer $record): bool => blank($record->house_photo_url))
                    ->modalHeading(fn (Customer $record): string => $record->name)
                    ->modalContent(fn (Customer $record) => view('filament.modals.house-photo', [
                        'url' => Storage::disk('public')->url($record->house_photo_url),
                        'name' => $record->name,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close')),
                Action::make('check_location')
                    ->label(__('Check Location'))
                    ->icon('heroicon-o-map-pin')
                    ->iconButton()
                    ->url(fn (Customer $record): ?string => $record->maps_url)
                    ->openUrlInNewTab()
                    ->disabled(fn (Customer $record): bool => blank($record->maps_url)),
                Action::make('record_payment')
                    ->label(__('Record Payment'))
                    ->icon('heroicon-o-banknotes')
                    ->url(fn (Model $record): string => TransactionResource::getUrl('create', [
                        'customer_id' => $record->getKey(),
                    ])),
            ]);
    }
}
