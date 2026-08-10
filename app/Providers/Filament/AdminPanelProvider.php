<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\SetLocale;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
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
            ->brandLogo(fn (): View => view('filament.brand'))
            ->darkModeBrandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.svg'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Dark jadi default (tema "NOC console"), tapi toggle light tetap ada.
            ->defaultThemeMode(ThemeMode::Dark)
            ->darkMode()
            ->sidebarCollapsibleOnDesktop()
            ->font('Outfit')
            // Mono khusus angka uang/counter — tabular-nums diatur di theme.css.
            ->monoFont('JetBrains Mono')
            // Palet "NOC": primary cyan (metafora fiber), gray bernuansa navy
            // supaya surface dark jadi near-black-navy, bukan abu netral.
            ->colors([
                'primary' => [
                    50 => '#ecfeff',
                    100 => '#cffafe',
                    200 => '#a5f3fc',
                    300 => '#67e8f9',
                    400 => '#22d3ee',
                    500 => '#06b6d4',
                    600 => '#0891b2',
                    700 => '#0e7490',
                    800 => '#155e75',
                    900 => '#164e63',
                    950 => '#083344',
                ],
                'gray' => [
                    50 => '#f8fafc',
                    100 => '#f1f5f9',
                    200 => '#e2e8f0',
                    300 => '#cbd5e1',
                    400 => '#94a3b8',
                    500 => '#64748b',
                    600 => '#475569',
                    700 => '#334155',
                    800 => '#1e293b',
                    900 => '#0f172a',
                    950 => '#070b14',
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
            // Urutan grup nav: Penagihan → Laporan → Master.
            ->navigationGroups([
                __('Billing'),
                __('Reports'),
                __('Master'),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Sengaja kosong: AccountWidget & FilamentInfoWidget dead code
            // (Dashboard::getWidgets() menimpanya) sekaligus branding Filament.
            ->widgets([])
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
            // Backdrop grid + glow di halaman login (layout simple).
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn (): string => view('filament.login-backdrop')->render(),
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
