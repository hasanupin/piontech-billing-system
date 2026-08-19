<?php

namespace App\Filament\Field\Pages;

use App\Enums\Role;
use App\Enums\TransactionStatus;
use App\Filament\Field\Resources\Transactions\FieldTransactionResource;
use App\Models\Customer;
use App\Services\BillingService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Panel;
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
use Illuminate\Support\Facades\Storage;

/**
 * Beranda petugas: 3 angka utama + daftar pelanggan jatuh tempo hari ini
 * dengan tombol Tagih menuju form transaksi terprefill.
 */
class Dashboard extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }

    public function getTitle(): string
    {
        return __('Dashboard');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isRole(Role::FieldOfficer) ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.field.dashboard-stats')
                ->viewData(fn (): array => ['stats' => $this->stats()]),
            EmbeddedTable::make(),
        ]);
    }

    /**
     * @return array{due_today: int, cash_on_hand: float, unpaid: int, total: int}
     */
    public function stats(): array
    {
        // Global scope cluster membatasi query Customer ke cluster petugas.
        return [
            'due_today' => Customer::query()->dueToday()->count(),
            'cash_on_hand' => app(BillingService::class)
                ->officerProgress((int) auth()->id(), now())['remaining'],
            'unpaid' => Customer::query()->billable()
                ->whereDoesntHave('transactions', $this->paidThisMonth(...))
                ->count(),
            'total' => Customer::query()->billable()->count(),
        ];
    }

    protected function paidThisMonth(Builder $query): Builder
    {
        return $query->forPeriod(now())->where('status', TransactionStatus::Paid);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Due Today'))
            // Belum lunas bulan ini saja — yang sudah bayar tak perlu ditagih lagi.
            ->query(fn (): Builder => Customer::query()
                ->dueToday()
                ->whereDoesntHave('transactions', $this->paidThisMonth(...)))
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('name')
                            ->label(__('Name'))
                            ->weight(FontWeight::SemiBold),
                        TextColumn::make('billing_amount')
                            ->label(__('Amount'))
                            ->money('IDR')
                            ->grow(false),
                    ]),
                    TextColumn::make('address')
                        ->label(__('Address'))
                        ->color('gray'),
                    TextColumn::make('status')
                        ->label(__('Status'))
                        ->badge(),
                ])->space(2),
            ])
            ->contentGrid(['default' => 1])
            ->paginated(false)
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
                Action::make('check_location')
                    ->label(__('Check Location'))
                    ->icon('heroicon-o-map-pin')
                    ->iconButton()
                    ->url(fn (Customer $record): ?string => $record->maps_url)
                    ->openUrlInNewTab()
                    ->disabled(fn (Customer $record): bool => blank($record->maps_url)),
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
                Action::make('collect')
                    ->label(__('Collect'))
                    ->icon('heroicon-o-banknotes')
                    ->button()
                    ->url(fn (Customer $record): string => FieldTransactionResource::getUrl('create', [
                        'customer_id' => $record->getKey(),
                    ])),
            ]);
    }
}
