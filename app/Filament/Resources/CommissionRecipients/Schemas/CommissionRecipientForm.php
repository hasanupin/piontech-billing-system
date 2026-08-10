<?php

namespace App\Filament\Resources\CommissionRecipients\Schemas;

use App\Enums\RecipientType;
use App\Models\Customer;
use App\Models\Setting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CommissionRecipientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Identity'))
                    ->schema([
                        Select::make('type')
                            ->label(__('Type'))
                            ->options(RecipientType::class)
                            ->default(RecipientType::External->value)
                            ->required()
                            ->live()
                            // Ganti tipe → bersihkan sisa isian tipe sebelumnya.
                            ->afterStateUpdated(function (Set $set): void {
                                $set('customer_id', null);
                                $set('name', null);
                                $set('address', null);
                                $set('whatsapp_number', null);
                            }),
                        Select::make('customer_id')
                            ->label(__('Customer'))
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(self::isCustomerType(...))
                            ->required(self::isCustomerType(...))
                            ->live()
                            // Isi field read-only di bawah untuk ditampilkan; nilainya
                            // tidak ikut tersimpan (mirror — lihat dehydrated(false)).
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $customer = Customer::find($state);

                                $set('name', $customer?->name);
                                $set('address', $customer?->address);
                                $set('whatsapp_number', $customer?->whatsapp_number);
                            }),
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required(fn (Get $get): bool => ! self::isCustomerType($get))
                            ->disabled(self::isCustomerType(...))
                            // Tipe Pelanggan: jangan disimpan, datanya mirror ke customers.
                            ->dehydrated(fn (Get $get): bool => ! self::isCustomerType($get)),
                        TextInput::make('address')
                            ->label(__('Address'))
                            ->disabled(self::isCustomerType(...))
                            ->dehydrated(fn (Get $get): bool => ! self::isCustomerType($get)),
                        TextInput::make('whatsapp_number')
                            ->label(__('WhatsApp Number'))
                            ->tel()
                            ->helperText(__('Without leading 0, e.g. 81234567890'))
                            ->disabled(self::isCustomerType(...))
                            ->dehydrated(fn (Get $get): bool => ! self::isCustomerType($get)),
                    ])->columns(),
                Section::make(__('Commission'))
                    ->schema([
                        TextInput::make('commission_percent')
                            ->label(__('Commission Percentage'))
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required()
                            // Nilai awal dari Pengaturan; tersimpan per penerima, jadi
                            // mengubah Pengaturan tidak menyentuh penerima yang sudah ada.
                            ->default(fn (): float => Setting::defaultCommissionPercent())
                            ->helperText(__('Default from Settings')),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true),
                    ])->columns(),
            ]);
    }

    /**
     * State select bisa berupa enum (hydrate dari record / default) atau string
     * (input user). Jangan bandingkan langsung ke salah satunya — kalau keliru,
     * customer_id ikut tersembunyi lalu tidak tersimpan.
     */
    private static function isCustomerType(Get $get): bool
    {
        $type = $get('type');

        return ($type instanceof RecipientType ? $type : RecipientType::tryFrom((string) $type))
            === RecipientType::Customer;
    }
}
