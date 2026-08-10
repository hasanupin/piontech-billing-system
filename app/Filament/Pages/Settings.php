<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Nilai default yang dipakai lintas menu. Page ber-form (bukan Resource):
 * hanya ada satu "record" konseptual, tidak butuh list/create/delete.
 */
class Settings extends Page
{
    use InteractsWithFormActions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Settings');
    }

    public function getTitle(): string
    {
        return __('Settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Master');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isRole(Role::SuperAdmin) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            Setting::DEFAULT_COMMISSION_PERCENT => Setting::defaultCommissionPercent(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Commission'))
                    ->description(__('Default values used across the app'))
                    ->schema([
                        TextInput::make(Setting::DEFAULT_COMMISSION_PERCENT)
                            ->label(__('Default Commission'))
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        // Pola EditRecord::getFormContentComponent() — form + tombol simpan di footer.
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make($this->getFormActions()),
                ]),
        ]);
    }

    /**
     * @return array<Action>
     */
    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save'))
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        // Guard server-side; tombolnya tersembunyi tapi jangan cuma andalkan itu.
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        Setting::set(
            Setting::DEFAULT_COMMISSION_PERCENT,
            (string) $data[Setting::DEFAULT_COMMISSION_PERCENT],
        );

        Notification::make()
            ->title(__('Settings Saved'))
            ->success()
            ->send();
    }
}
