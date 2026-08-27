<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Models\Customer;
use App\Models\Transaction;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Model;

class TransactionForm
{
    /**
     * Pelanggan yang dibawa lewat ?customer_id=... (tombol "Catat Pembayaran"
     * di halaman Tagihan Bulanan). Prefill dipasang sebagai ->default() per
     * field, BUKAN dengan override fillForm() + form->fill([...]): fill()
     * dengan argumen array melewatkan seluruh default komponen lain, sehingga
     * payment_method/period/paid_at/officer_id justru ikut kosong.
     *
     * Sumber ID-nya CreateTransaction::$customerId (bukan request()->query()
     * langsung) supaya cuma ada satu tempat yang membaca query string.
     *
     * Query tunduk pada global scope cluster — petugas yang menebak ID
     * pelanggan cluster lain tidak mendapat prefill apa pun.
     */
    private static function prefillCustomer(mixed $livewire): ?Customer
    {
        $id = $livewire instanceof CreateTransaction ? $livewire->customerId : null;

        return filled($id) ? Customer::billable()->find($id) : null;
    }

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
                    ->default(fn ($livewire): mixed => self::prefillCustomer($livewire)?->getKey())
                    ->afterStateUpdated(function ($state, Set $set): void {
                        // Pre-fill nominal dari billing_amount pelanggan — tetap editable.
                        // Set sudah dalam format tampilan (rupiah) agar konsisten dengan dehydrate mask.
                        $amount = Customer::find($state)?->billing_amount;
                        $formatted = filled($amount) ? number_format((float) $amount, 2, ',', '.') : null;

                        $set('billed_amount', $formatted);
                        // Nominal bayar ikut terisi: mayoritas pembayaran lunas penuh,
                        // dan petugas cukup mengubahnya saat pembayaran sebagian.
                        $set('paid_amount', $formatted);
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
                // Alamat pelanggan supaya petugas tahu ke mana harus datang;
                // customer_id sudah live(), jadi ikut berubah tanpa apa-apa lagi.
                Placeholder::make('customer_address')
                    ->label(__('Address'))
                    ->content(fn (Get $get): string => filled($get('customer_id'))
                        ? (Customer::find($get('customer_id'))?->address ?: '—')
                        : '—'),
                Select::make('payment_method')
                    ->label(__('Payment Method'))
                    ->live()
                    // Petugas ikut boleh mencatat transfer (menyimpang dari PRD §6 atas
                    // permintaan pemilik produk). Aturan uangnya tidak berubah:
                    // Transaction::booted() tetap menge-null-kan officer_id untuk transfer.
                    ->options([
                        PaymentMethod::Cash->value => __('Cash (via Officer)'),
                        PaymentMethod::Transfer->value => __('Direct Transfer'),
                    ])
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
                    // ?period=Y-m dari daftar tagihan per periode; tetap bisa diganti.
                    ->default(fn ($livewire): Carbon => $livewire instanceof CreateTransaction && filled($livewire->period)
                        ? Carbon::parse($livewire->period.'-01')->startOfMonth()
                        : now()->startOfMonth())
                    ->required(),
                TextInput::make('billed_amount')
                    ->label(__('Billed Amount'))
                    ->required()
                    ->default(fn ($livewire): mixed => self::prefillCustomer($livewire)?->billing_amount)
                    ->prefix('Rp')
                    ->mask(RawJs::make("\$money(\$input, ',', '.')"))
                    ->formatStateUsing(fn ($state) => filled($state) ? number_format((float) $state, 2, ',', '.') : $state)
                    ->dehydrateStateUsing(fn ($state) => (float) str_replace(['.', ','], ['', '.'], (string) $state))
                    ->helperText(__('Pre-filled from package — can be edited')),
                TextInput::make('paid_amount')
                    ->label(__('Paid Amount'))
                    ->required()
                    ->default(fn ($livewire): mixed => self::prefillCustomer($livewire)?->billing_amount)
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
