<?php

namespace App\Filament\Resources\OfficerDeposits\Schemas;

use App\Enums\Role;
use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class OfficerDepositForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('officer_id')
                    ->label(__('Officer'))
                    ->relationship('officer', 'name', fn ($query) => $query->where('role', Role::FieldOfficer))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    // Petugas login → terkunci ke diri sendiri.
                    ->default(fn () => auth()->user()?->isRole(Role::FieldOfficer) ? auth()->id() : null)
                    ->disabled(fn () => auth()->user()?->isRole(Role::FieldOfficer) ?? false),
                DatePicker::make('period')
                    ->label(__('Period'))
                    ->displayFormat('F Y')
                    ->default(now()->startOfMonth())
                    ->live()
                    ->required(),
                Placeholder::make('info_sisa')
                    ->label(__('💰 Remaining To Deposit This Period'))
                    ->content(function (Get $get): string {
                        if (! $get('officer_id')) {
                            return '—';
                        }

                        $remaining = app(BillingService::class)->officerRemainingBalance(
                            (int) $get('officer_id'),
                            Carbon::parse($get('period') ?? now()),
                        );

                        return 'Rp '.number_format($remaining, 0, ',', '.');
                    }),
                TextInput::make('amount')
                    ->label(__('Amount'))
                    ->required()
                    ->prefix('Rp')
                    ->mask(RawJs::make("\$money(\$input, ',', '.')"))
                    ->formatStateUsing(fn ($state) => filled($state) ? number_format((float) $state, 2, ',', '.') : $state)
                    ->dehydrateStateUsing(fn ($state) => (float) str_replace(['.', ','], ['', '.'], (string) $state)),
                Select::make('received_by')
                    ->label(__('Received By'))
                    ->relationship('receiver', 'name', fn ($query) => $query->where('role', Role::Admin))
                    ->searchable()
                    ->preload()
                    ->required(),
                DateTimePicker::make('deposited_at')
                    ->label(__('Deposited At'))
                    ->default(now())
                    ->required(),
            ]);
    }
}
