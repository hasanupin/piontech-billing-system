<?php

namespace Tests\Feature;

use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kontrak tema panel. Bukan menguji CSS (itu diverifikasi mata), tapi menjaga
 * konfigurasi yang kalau lepas bikin panel tampil tanpa styling di produksi.
 */
class PanelThemeTest extends TestCase
{
    use RefreshDatabase;

    public function testPanelRegistersCustomViteTheme(): void
    {
        $this->assertSame(
            'resources/css/filament/admin/theme.css',
            Filament::getPanel('admin')->getViteTheme(),
        );
    }

    public function testPanelDefaultsToDarkMode(): void
    {
        $this->assertSame(ThemeMode::Dark, Filament::getPanel('admin')->getDefaultThemeMode());
        // Toggle light/dark tetap tersedia — dark hanya default, bukan paksaan.
        $this->assertTrue(Filament::getPanel('admin')->hasDarkMode());
        $this->assertFalse(Filament::getPanel('admin')->hasDarkModeForced());
    }

    public function testPanelUsesMonoFontForNumerals(): void
    {
        $this->assertTrue(Filament::getPanel('admin')->hasCustomMonoFontFamily());
    }

    public function testPanelDropsFilamentPromoAndAccountWidgets(): void
    {
        $widgets = Filament::getPanel('admin')->getWidgets();

        // Keduanya dead code (Dashboard::getWidgets() menimpanya) sekaligus
        // menampilkan branding Filament — dibuang supaya panel terasa milik sendiri.
        $this->assertNotContains(FilamentInfoWidget::class, $widgets);
        $this->assertNotContains(AccountWidget::class, $widgets);
    }

    public function testLoginPageShowsBrandWordmark(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('PIONTECH');
    }
}
