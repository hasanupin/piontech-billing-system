<?php

namespace App\Filament\Field\Resources\Customers;

use App\Enums\CustomerStatus;
use App\Enums\Role;
use App\Filament\Field\Resources\Customers\Pages\ListFieldCustomers;
use App\Filament\Resources\Customers\Schemas\CustomerForm;
use App\Models\Cluster;
use App\Models\Customer;
use App\Services\ScopeService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Data Pelanggan versi mobile petugas — READ-ONLY: petugas hanya melihat dan
 * menyaring, tidak boleh menambah atau menyunting (PRD §6). Konsekuensi yang
 * disengaja: foto rumah & titik koordinat kini hanya bisa diisi admin.
 */
class FieldCustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'pelanggan';

    protected static ?string $recordTitleAttribute = 'name';

    // Tanpa search global di topbar — pencarian ada di dalam list pelanggan.
    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return __('Customer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Customers');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isRole(Role::FieldOfficer) ?? false;
    }

    /**
     * Daerah yang dipegang petugas login — dipakai bersama filter di list ini
     * dan di halaman Transaksi. Lewat ScopeService, satu pintu scoping daerah.
     *
     * @return array<string, string>
     */
    public static function daerahOptions(): array
    {
        return app(ScopeService::class)
            ->scopeClustersForUser(Cluster::query(), auth()->user())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function getEloquentQuery(): Builder
    {
        // Global scope cluster sudah membatasi petugas; ScopeService dipasang
        // juga supaya aturannya tetap satu pintu.
        return app(ScopeService::class)->scopeCustomersForUser(
            parent::getEloquentQuery(),
            auth()->user(),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    TextColumn::make('address')
                        ->label(__('Address'))
                        ->color('gray'),
                    Split::make([
                        TextColumn::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->grow(false),
                        TextColumn::make('billing_day')
                            ->label(__('Billing Day'))
                            ->formatStateUsing(fn ($state): string => __('Billing Day').' '.$state),
                        TextColumn::make('whatsapp_number')
                            ->label(__('WhatsApp'))
                            ->url(fn (Customer $record): ?string => $record->whatsapp_number
                                ? 'https://wa.me/'.ltrim($record->whatsapp_number, '+')
                                : null)
                            ->openUrlInNewTab(),
                    ]),
                ])->space(2),
            ])
            ->contentGrid(['default' => 1])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(CustomerStatus::class),
                Filter::make('due_today')
                    ->label(__('Due Today'))
                    ->query(fn (Builder $query): Builder => $query->dueToday()),
                SelectFilter::make('cluster_id')
                    ->label(__('Cluster'))
                    ->options(fn (): array => self::daerahOptions()),
            ]);
        // Sengaja tanpa recordActions(): petugas read-only atas data pelanggan (PRD §6).
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFieldCustomers::route('/'),
        ];
    }
}
