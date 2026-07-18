<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\CustomerStatus;
use App\Models\Package;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Identity'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required(),
                        TextInput::make('full_name')
                            ->label(__('Full Name')),
                        TextInput::make('whatsapp_number')
                            ->label(__('WhatsApp Number'))
                            ->tel()
                            ->prefix('+62')
                            ->helperText(__('Without leading 0, e.g. 81234567890')),
                    ])->columns(),
                Section::make(__('Assignment & Package'))
                    ->schema([
                        Select::make('cluster_id')
                            ->label(__('Cluster'))
                            ->relationship('cluster', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText(__('Assignment reference for the field officer')),
                        Select::make('package_id')
                            ->label(__('Package'))
                            ->relationship('package', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                // Prefill nominal dari paket berharga; paket custom biarkan user isi.
                                $package = Package::find($state);
                                if ($package && ! $package->is_custom) {
                                    $set('billing_amount', number_format((float) $package->default_price, 2, ',', '.'));
                                }
                            })
                            ->helperText(__('Selecting a package fills the billing amount')),
                        TextInput::make('billing_amount')
                            ->label(__('Billing Amount'))
                            ->required()
                            ->prefix('Rp')
                            ->mask(RawJs::make("\$money(\$input, ',', '.')"))
                            ->formatStateUsing(fn ($state) => filled($state) ? number_format((float) $state, 2, ',', '.') : $state)
                            ->dehydrateStateUsing(fn ($state) => (float) str_replace(['.', ','], ['', '.'], (string) $state))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                // Nominal diubah dari harga paket → otomatis jadi paket custom.
                                $package = Package::find($get('package_id'));
                                if (! $package || $package->is_custom) {
                                    return;
                                }
                                $entered = (float) str_replace(['.', ','], ['', '.'], (string) $state);
                                if ($entered !== (float) $package->default_price) {
                                    $custom = Package::where('is_custom', true)->first();
                                    if ($custom) {
                                        $set('package_id', $custom->getKey());
                                    }
                                }
                            })
                            ->helperText(__('Pre-filled from package — editing it switches to a custom package')),
                        TextInput::make('billing_day')
                            ->label(__('Billing Day'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->required(),
                    ])->columns(),
                Section::make(__('Location (Reference)'))
                    ->schema([
                        TextInput::make('address')
                            ->label(__('Address'))
                            ->helperText(__('Village name — reference only')),
                        TextInput::make('maps_url')
                            ->label(__('Maps URL'))
                            ->url(),
                        FileUpload::make('house_photo_url')
                            ->label(__('House Photo'))
                            ->image()
                            ->directory('foto-rumah'),
                    ])->columns(),
                Section::make(__('Status'))
                    ->schema([
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(CustomerStatus::class)
                            ->default(CustomerStatus::Active)
                            ->required(),
                        DatePicker::make('registered_at')
                            ->label(__('Registered At'))
                            ->default(now())
                            ->required(),
                    ])->columns(),
            ]);
    }
}
