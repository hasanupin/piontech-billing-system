<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Model;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label(__('Customer'))
                    // Hanya pelanggan yang ditagih (AKTIF + ISOLIR).
                    ->relationship('customer', 'name', fn ($query) => $query->billable())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        // Pre-fill nominal dari billing_amount pelanggan — tetap editable.
                        // Set sudah dalam format tampilan (rupiah) agar konsisten dengan dehydrate mask.
                        $amount = Customer::find($state)?->billing_amount;
                        $set('billed_amount', filled($amount) ? number_format((float) $amount, 2, ',', '.') : null);
                    })
                    // Tolak duplikat pelanggan+periode (unique constraint TASK-02) dengan pesan jelas.
                    ->rule(fn (Get $get, ?Model $record) => function (string $attribute, $value, Closure $fail) use ($get, $record): void {
                        $exists = Transaction::where('customer_id', $value)
                            ->whereDate('period', Carbon::parse($get('period'))->startOfMonth())
                            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($exists) {
                            $fail(__('A transaction for this customer and period already exists.'));
                        }
                    }),
                Select::make('payment_method')
                    ->label(__('Payment Method'))
                    ->live()
                    // Petugas tidak melihat opsi transfer.
                    ->options(fn (): array => auth()->user()?->isRole(Role::SuperAdmin, Role::Admin)
                        ? [PaymentMethod::Cash->value => __('Cash (via Officer)'), PaymentMethod::Transfer->value => __('Direct Transfer')]
                        : [PaymentMethod::Cash->value => __('Cash')])
                    ->default(PaymentMethod::Cash->value)
                    ->required(),
                Select::make('officer_id')
                    ->label(__('Officer'))
                    ->relationship('officer', 'name', fn ($query) => $query->where('role', Role::FieldOfficer))
                    ->searchable()
                    ->preload()
                    ->hidden(fn (Get $get): bool => $get('payment_method') === PaymentMethod::Transfer->value)
                    ->default(fn () => auth()->user()?->isRole(Role::FieldOfficer) ? auth()->id() : null),
                DatePicker::make('period')
                    ->label(__('Period'))
                    ->displayFormat('F Y')
                    ->default(now()->startOfMonth())
                    ->required(),
                TextInput::make('billed_amount')
                    ->label(__('Billed Amount'))
                    ->required()
                    ->prefix('Rp')
                    ->mask(RawJs::make("\$money(\$input, ',', '.')"))
                    ->formatStateUsing(fn ($state) => filled($state) ? number_format((float) $state, 2, ',', '.') : $state)
                    ->dehydrateStateUsing(fn ($state) => (float) str_replace(['.', ','], ['', '.'], (string) $state))
                    ->helperText(__('Pre-filled from package — can be edited')),
                TextInput::make('paid_amount')
                    ->label(__('Paid Amount'))
                    ->required()
                    ->prefix('Rp')
                    ->mask(RawJs::make("\$money(\$input, ',', '.')"))
                    ->formatStateUsing(fn ($state) => filled($state) ? number_format((float) $state, 2, ',', '.') : $state)
                    ->dehydrateStateUsing(fn ($state) => (float) str_replace(['.', ','], ['', '.'], (string) $state)),
                DateTimePicker::make('paid_at')
                    ->label(__('Paid At'))
                    ->default(now())
                    ->required(),
            ]);
    }
}
