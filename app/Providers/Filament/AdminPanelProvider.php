<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Piontech Billing System')
            ->font('Outfit')
            // Palet warna mengadopsi design tokens TailAdmin (tailadmin.com, free version).
            ->colors([
                'primary' => [
                    50 => '#ecf3ff',
                    100 => '#dde9ff',
                    200 => '#c2d6ff',
                    300 => '#9cb9ff',
                    400 => '#7592ff',
                    500 => '#465fff',
                    600 => '#3641f5',
                    700 => '#2a31d8',
                    800 => '#252dae',
                    900 => '#262e89',
                    950 => '#161950',
                ],
                'gray' => [
                    50 => '#f9fafb',
                    100 => '#f2f4f7',
                    200 => '#e4e7ec',
                    300 => '#d0d5dd',
                    400 => '#98a2b3',
                    500 => '#667085',
                    600 => '#475467',
                    700 => '#344054',
                    800 => '#1d2939',
                    900 => '#101828',
                    950 => '#0c111d',
                ],
                'success' => Color::hex('#12b76a'),
                'danger' => Color::hex('#f04438'),
                'warning' => Color::hex('#f79009'),
                'info' => Color::hex('#0ba5ec'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_AFTER,
                fn (): string => view('filament.locale-switcher')->render(),
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
