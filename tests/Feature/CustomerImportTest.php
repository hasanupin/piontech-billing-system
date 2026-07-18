<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Imports\CustomerImport;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeImport(): CustomerImport
    {
        return new CustomerImport(Cluster::factory()->create()->id);
    }

    public function testConvertsScientificNotationWaToProperString(): void
    {
        // Nilai float persis seperti hasil baca Excel (6.28226E+12).
        $wa = $this->makeImport()->cleanWhatsappNumber(6.28226E+12);

        $this->assertIsString($wa);
        $this->assertStringStartsWith('62', $wa);
        $this->assertStringNotContainsString('E', $wa);
    }

    public function testNormalizesLeadingZeroWaTo62(): void
    {
        $this->assertSame('6281234567890', $this->makeImport()->cleanWhatsappNumber('081234567890'));
        $this->assertNull($this->makeImport()->cleanWhatsappNumber(null));
    }

    public function testMapsPaket110ToMasterPaket110000(): void
    {
        $package = Package::factory()->create(['default_price' => 110_000]);

        $this->assertSame($package->id, $this->makeImport()->lookupPackage(110));
        // Tidak ketemu → null (warning masuk log).
        $this->assertNull($this->makeImport()->lookupPackage(999));
    }

    public function testDefaultsInvalidStatusToActive(): void
    {
        $import = $this->makeImport();

        $this->assertSame(CustomerStatus::Active, $import->cleanStatus('ngawur'));
        $this->assertSame(CustomerStatus::Suspended, $import->cleanStatus(' ISOLIR '));
        $this->assertSame(CustomerStatus::Active, $import->cleanStatus('AKTIF'));
        $this->assertSame(CustomerStatus::Terminated, $import->cleanStatus('OFF'));
    }

    public function testRejectsInvalidMapsUrl(): void
    {
        $import = $this->makeImport();

        $this->assertSame('https://maps.app.goo.gl/abc', $import->validUrl('https://maps.app.goo.gl/abc'));
        $this->assertNull($import->validUrl('bukan-url'));
    }

    public function testImportsFullSampleFile(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Package::factory()->create(['default_price' => 110_000]);
        $cluster = Cluster::factory()->create();

        $import = new CustomerImport($cluster->id);
        $import->import(base_path('tests/fixtures/sample_pelanggan.xlsx'));

        $this->assertSame(10, Customer::count());
        $this->assertSame(10, $import->imported);
        // Semua masuk cluster tujuan.
        $this->assertSame(10, Customer::where('cluster_id', $cluster->id)->count());
        // Baris WA scientific ter-normalisasi.
        $this->assertSame(0, Customer::where('whatsapp_number', 'like', '%E%')->count());
    }

    public function testAdminSeesImportActionPetugasNot(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(\App\Filament\Resources\Customers\Pages\ListCustomers::class)
            ->assertActionExists('import');

        $this->actingAs(User::factory()->fieldOfficer()->create());
        Livewire::test(\App\Filament\Resources\Customers\Pages\ListCustomers::class)
            ->assertActionHidden('import');
    }
}
