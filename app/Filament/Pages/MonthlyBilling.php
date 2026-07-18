<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Customer;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MonthlyBilling extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected string $view = 'filament.pages.monthly-billing';

    // Periode terpilih (format Y-m); default bulan & tahun ini.
    public ?string $period = null;

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
    }

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

    /**
     * Awal bulan dari periode terpilih.
     */
    public function periodStart(): Carbon
    {
        return Carbon::parse(($this->period ?: now()->format('Y-m')).'-01')->startOfMonth();
    }

    /**
     * Opsi periode: 12 bulan terakhir.
     *
     * @return array<string, string>
     */
    public function periodOptions(): array
    {
        $options = [];
        $cursor = now()->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $options[$cursor->format('Y-m')] = $cursor->translatedFormat('F Y');
            $cursor->subMonth();
        }

        return $options;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Customer::query()->billable())
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('cluster.name')
                    ->label(__('Cluster')),
                TextColumn::make('billing_day')
                    ->label(__('Billing Day'))
                    ->sortable(),
                TextColumn::make('billing_amount')
                    ->label(__('Amount'))
                    ->money('IDR'),
                TextColumn::make('payment_status')
                    ->label(__('Payment Status'))
                    ->badge()
                    ->state(fn (Customer $record): TransactionStatus => $record->transactions()
                        ->forPeriod($this->periodStart())
                        ->where('status', TransactionStatus::Paid)
                        ->exists()
                        ? TransactionStatus::Paid
                        : TransactionStatus::Unpaid),
            ])
            ->filters([
                Filter::make('unpaid')
                    ->label(__('Unpaid Only'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave(
                        'transactions',
                        fn (Builder $q) => $q->forPeriod($this->periodStart())->where('status', TransactionStatus::Paid),
                    )),
            ])
            ->recordActions([
                Action::make('record_payment')
                    ->label(__('Record Payment'))
                    ->icon('heroicon-o-banknotes')
                    ->url(fn (Model $record): string => TransactionResource::getUrl('create', [
                        'customer_id' => $record->getKey(),
                    ])),
            ]);
    }
}
