<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function testSuperAdminCanAccessSettingsPage(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(Settings::getUrl())->assertOk();
    }

    public function testAdminCannotAccessSettingsPage(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(Settings::getUrl())->assertForbidden();
    }

    public function testFieldOfficerCannotAccessSettingsPage(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(Settings::getUrl())->assertForbidden();
    }

    public function testShowsFourPercentAsDefaultWhenNoRowExists(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->assertSame(0, Setting::count());

        Livewire::test(Settings::class)
            ->assertSet('data.default_commission_percent', 4);
    }

    public function testSavingCommissionPersistsValueAndAuthor(): void
    {
        $ceo = User::factory()->superAdmin()->create();
        $this->actingAs($ceo);

        Livewire::test(Settings::class)
            ->fillForm(['default_commission_percent' => 5.5])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(5.5, Setting::defaultCommissionPercent());
        $this->assertSame(
            $ceo->id,
            Setting::where('key', Setting::DEFAULT_COMMISSION_PERCENT)->first()->updated_by,
        );
    }

    public function testRejectsCommissionOutsideZeroToHundred(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(Settings::class)
            ->fillForm(['default_commission_percent' => 150])
            ->call('save')
            ->assertHasFormErrors(['default_commission_percent']);

        $this->assertSame(4.0, Setting::defaultCommissionPercent());
    }

    public function testSavingIsBlockedForNonSuperAdmin(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Tombol memang tersembunyi dan halamannya 403, tapi save() tetap harus
        // menolak sendiri. Dipanggil di luar Livewire karena request 403 tidak
        // menghasilkan snapshot Livewire yang bisa di-assert.
        $this->expectException(HttpException::class);

        (new Settings)->save();
    }

    public function testDefaultCommissionPercentReadsSavedValue(): void
    {
        Setting::set(Setting::DEFAULT_COMMISSION_PERCENT, '7.25');

        $this->assertSame(7.25, Setting::defaultCommissionPercent());
    }
}
