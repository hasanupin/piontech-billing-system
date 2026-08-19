<?php

namespace App\Filament\Field\Pages;

use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Filament\Field\Resources\Transactions\FieldTransactionResource;
use App\Filament\Pages\Dashboard as AdminDashboard;
use App\Models\Customer;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menu Transaksi versi petugas: daftar tagihan per periode (pola halaman
 * Tagihan Bulanan) — pelanggan billable + status LUNAS/BELUM pada periode
 * terpilih, bukan sekadar daftar transaksi tercatat, supaya "belum bayar"
 * bisa difilter.
 */
class Transactions extends Page implements HasTable
{
    // Keduanya membawa normalizeTableFilterValuesFromQueryString() identik.
    use HasFiltersForm, InteractsWithTable {
        HasFiltersForm::normalizeTableFilterValuesFromQueryString insteadof InteractsWithTable;
    }

    protected static ?string $slug = 'transaksi';

    public static function getNavigationLabel(): string
    {
        return __('Transactions');
    }

    public function getTitle(): string
    {
        return __('Transactions');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isRole(Role::FieldOfficer) ?? false;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('period')
                    ->label(__('Period'))
                    ->options(AdminDashboard::periodOptions())
                    ->default(now()->format('Y-m'))
                    ->selectablePlaceholder(false),
                Select::make('payment_status')
                    ->label(__('Payment Status'))
                    ->placeholder(__('All'))
                    ->options([
                        'paid' => __('Paid'),
                        'unpaid' => __('Unpaid'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.field.billing-tabs'),
            EmbeddedSchema::make('filtersForm'),
            EmbeddedTable::make(),
        ]);
    }

    public function periodStart(): Carbon
    {
        $value = $this->filters['period'] ?? now()->format('Y-m');

        return Carbon::parse($value.'-01')->startOfMonth();
    }

    public function periodParam(): string
    {
        return $this->periodStart()->format('Y-m');
    }

    protected function paymentStatusOf(Customer $record): TransactionStatus
    {
        return $record->transactions()
            ->forPeriod($this->periodStart())
            ->where('status', TransactionStatus::Paid)
            ->exists()
            ? TransactionStatus::Paid
            : TransactionStatus::Unpaid;
    }

    protected function paidInPeriod(Builder $query): Builder
    {
        return $query->forPeriod($this->periodStart())->where('status', TransactionStatus::Paid);
    }

    public function table(Table $table): Table
    {
        return $table
            // Global scope cluster membatasi ke pelanggan petugas ini.
            ->query(fn (): Builder => Customer::query()
                ->billable()
                ->when(
                    $this->filters['payment_status'] ?? null,
                    fn (Builder $query, string $status): Builder => $status === 'paid'
                        ? $query->whereHas('transactions', $this->paidInPeriod(...))
                        : $query->whereDoesntHave('transactions', $this->paidInPeriod(...)),
                ))
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('name')
                            ->label(__('Name'))
                            ->weight(FontWeight::SemiBold)
                            ->searchable(),
                        TextColumn::make('billing_amount')
                            ->label(__('Amount'))
                            ->money('IDR')
                            ->grow(false),
                    ]),
                    Split::make([
                        TextColumn::make('payment_status')
                            ->label(__('Payment Status'))
                            ->badge()
                            ->state(fn (Customer $record): TransactionStatus => $this->paymentStatusOf($record))
                            ->grow(false),
                        TextColumn::make('status')
                            ->label(__('Customer Status'))
                            ->badge()
                            ->grow(false),
                    ]),
                ])->space(2),
            ])
            ->contentGrid(['default' => 1])
            ->recordActions([
                Action::make('whatsapp')
                    ->label(__('WhatsApp'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->iconButton()
                    ->url(fn (Customer $record): ?string => $record->whatsapp_number
                        ? 'https://wa.me/'.ltrim($record->whatsapp_number, '+')
                        : null)
                    ->openUrlInNewTab()
                    ->disabled(fn (Customer $record): bool => blank($record->whatsapp_number)),
                Action::make('record_payment')
                    ->label(__('Record Payment'))
                    ->icon('heroicon-o-banknotes')
                    ->button()
                    // Yang sudah lunas tidak bisa dicatat dua kali.
                    ->visible(fn (Customer $record): bool => $this->paymentStatusOf($record) === TransactionStatus::Unpaid)
                    ->url(fn (Customer $record): string => FieldTransactionResource::getUrl('create', [
                        'customer_id' => $record->getKey(),
                        'period' => $this->periodParam(),
                    ])),
            ]);
    }
}
