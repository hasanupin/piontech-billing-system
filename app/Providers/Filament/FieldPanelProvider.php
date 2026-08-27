<?php

namespace App\Providers\Filament;

use App\Filament\Field\Pages\Dashboard;
use App\Filament\Field\Pages\Settings;
use App\Filament\Field\Pages\Transactions;
use App\Filament\Field\Resources\Customers\FieldCustomerResource;
use App\Filament\Field\Resources\Transactions\FieldTransactionResource;
use App\Http\Middleware\LogPageVisit;
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

/**
 * Panel mobile untuk petugas lapangan (field_officer). Tanpa discovery —
 * halaman & resource didaftarkan eksplisit supaya resource admin tidak
 * pernah ikut terdaftar di sini.
 */
class FieldPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('field')
            ->path('petugas')
            // Tanpa halaman login/reset sendiri: semuanya lewat satu halaman
            // login bersama di panel admin (App\Filament\Auth\Login).
            ->brandName('Piontech Billing System')
            ->brandLogo(fn (): View => view('filament.brand'))
            ->darkModeBrandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.svg'))
            ->viteTheme('resources/css/filament/field/theme.css')
            ->defaultThemeMode(ThemeMode::Dark)
            ->darkMode()
            ->font('Outfit')
            ->monoFont('JetBrains Mono')
            // Palet identik dengan panel admin (NOC console).
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
            // Navigasi bawaan dimatikan; gantinya bottom nav 3 menu di bawah.
            ->navigation(false)
            ->pages([
                Dashboard::class,
                Transactions::class,
                Settings::class,
            ])
            ->resources([
                FieldCustomerResource::class,
                FieldTransactionResource::class,
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
            ])
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn (): string => view('filament.login-backdrop')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth()->check() ? view('filament.field.bottom-nav')->render() : '',
            )
            ->authMiddleware([
                Authenticate::class,
                LogPageVisit::class,
            ]);
    }
}
