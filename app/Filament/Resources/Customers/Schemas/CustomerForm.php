<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\CustomerStatus;
use App\Enums\Role;
use App\Models\Cluster;
use App\Models\Package;
use App\Services\ScopeService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Js;

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
                            ->relationship('cluster', 'name', fn (Builder $query): Builder => app(ScopeService::class)
                                ->scopeClustersForUser($query, auth()->user()))
                            ->searchable()
                            ->preload()
                            ->required()
                            // Petugas dengan satu cluster → langsung terisi; kalau pegang beberapa,
                            // dia pilih sendiri (opsi tetap terbatas pada clusternya).
                            ->default(function (): ?string {
                                $user = auth()->user();

                                if (! $user?->isRole(Role::FieldOfficer)) {
                                    return null;
                                }

                                $clusters = Cluster::where('officer_id', $user->id)->pluck('id');

                                return $clusters->count() === 1 ? $clusters->first() : null;
                            })
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
                            ->url()
                            ->helperText(__('Paste a Google Maps link, or tap the pin to capture GPS coordinates'))
                            // Ambil titik GPS via browser (mobile petugas) — koordinat
                            // disimpan sebagai link maps biasa, tanpa kolom baru.
                            ->suffixAction(
                                Action::make('take_coordinates')
                                    ->label(__('Take Coordinates'))
                                    ->icon('heroicon-o-map-pin')
                                    ->alpineClickHandler(function (Component $component): string {
                                        $statePath = Js::from($component->getStatePath());
                                        $error = Js::from(__('Location unavailable. Allow location access in your browser, or paste the link manually.'));

                                        return <<<JS
                                            navigator.geolocation
                                                ? navigator.geolocation.getCurrentPosition(
                                                    (position) => \$wire.\$set({$statePath}, 'https://maps.google.com/?q=' + position.coords.latitude.toFixed(6) + ',' + position.coords.longitude.toFixed(6)),
                                                    () => alert({$error}),
                                                  )
                                                : alert({$error})
                                            JS;
                                    }),
                            ),
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
                            ->required()
                            // Isolir/Pulihkan tetap wewenang admin (quick action di CustomersTable).
                            // Tanpa ->dehydrated(): field disabled tidak ikut ter-submit, jadi saat
                            // create jatuh ke default kolom (active) dan saat edit nilainya tak berubah.
                            ->disabled(fn (): bool => auth()->user()?->isRole(Role::FieldOfficer) ?? false),
                        DatePicker::make('registered_at')
                            ->label(__('Registered At'))
                            ->default(now())
                            ->required(),
                    ])->columns(),
            ]);
    }
}
