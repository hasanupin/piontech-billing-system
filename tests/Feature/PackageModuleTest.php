<?php

namespace Tests\Feature;

use App\Filament\Resources\Packages\PackageResource;
use App\Filament\Resources\Packages\Pages\CreatePackage;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PackageModuleTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanCreatePackage(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreatePackage::class)
            ->fillForm([
                'name' => 'Package 110',
                'default_price' => 110000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Package::where('name', 'Package 110')->exists());
    }

    public function testAdminCanCreateCustomPackageWithoutPrice(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreatePackage::class)
            ->fillForm([
                'name' => 'Custom',
                'is_custom' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $package = Package::where('name', 'Custom')->first();
        $this->assertNotNull($package);
        $this->assertTrue($package->is_custom);
        $this->assertNull($package->default_price);
    }

    public function testFieldOfficerCannotAccessPackageResource(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(PackageResource::getUrl('index'))->assertForbidden();
    }

    public function testSuperAdminHasFullAccess(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(PackageResource::getUrl('index'))->assertOk();
        $this->get(PackageResource::getUrl('create'))->assertOk();
    }
}
