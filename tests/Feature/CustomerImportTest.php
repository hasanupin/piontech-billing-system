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

    public function testConvertsScientificNotationWaToProperString(): void
    {
        // Nilai float persis seperti hasil baca Excel (6.28226E+12).
        $wa = (new CustomerImport())->cleanWhatsappNumber(6.28226E+12);

        $this->assertIsString($wa);
        $this->assertStringStartsWith('62', $wa);
        $this->assertStringNotContainsString('E', $wa);
    }

    public function testNormalizesLeadingZeroWaTo62(): void
    {
        $this->assertSame('6281234567890', (new CustomerImport())->cleanWhatsappNumber('081234567890'));
        $this->assertNull((new CustomerImport())->cleanWhatsappNumber(null));
    }

    public function testMapsPaket110ToMasterPaket110000(): void
    {
        $package = Package::factory()->create(['default_price' => 110_000]);

        $this->assertSame($package->id, (new CustomerImport())->lookupPackage(110));
        // Tidak ketemu → null (warning masuk log).
        $this->assertNull((new CustomerImport())->lookupPackage(999));
    }

    public function testResolvesClusterByNameCaseInsensitive(): void
    {
        $cluster = Cluster::factory()->create(['name' => 'PADI']);

        $import = new CustomerImport();

        $this->assertSame($cluster->id, $import->lookupCluster(' padi '));
        // Tidak ketemu / kosong → null, admin assign belakangan via UI.
        $this->assertNull($import->lookupCluster('TIDAK ADA'));
        $this->assertNull($import->lookupCluster(null));
    }

    public function testCleansBillingDayAndAmount(): void
    {
        $import = new CustomerImport();

        $this->assertSame(15, $import->cleanBillingDay(15));
        $this->assertSame(1, $import->cleanBillingDay(null));
        $this->assertSame(1, $import->cleanBillingDay('ngawur'));
        $this->assertSame(1, $import->cleanBillingDay(45));

        // Angka gaya lama (110 = 110rb) maupun nominal penuh diterima.
        $this->assertSame(110_000.0, $import->cleanAmount(110));
        $this->assertSame(110_000.0, $import->cleanAmount(110_000));
        $this->assertNull($import->cleanAmount(null));
    }

    public function testDefaultsInvalidStatusToActive(): void
    {
        $import = new CustomerImport();

        $this->assertSame(CustomerStatus::Active, $import->cleanStatus('ngawur'));
        $this->assertSame(CustomerStatus::Suspended, $import->cleanStatus(' ISOLIR '));
        $this->assertSame(CustomerStatus::Active, $import->cleanStatus('AKTIF'));
        $this->assertSame(CustomerStatus::Terminated, $import->cleanStatus('OFF'));
    }

    public function testRejectsInvalidMapsUrl(): void
    {
        $import = new CustomerImport();

        $this->assertSame('https://maps.app.goo.gl/abc', $import->validUrl('https://maps.app.goo.gl/abc'));
        $this->assertNull($import->validUrl('bukan-url'));
    }

    public function testImportsFullSampleFile(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Package::factory()->create(['default_price' => 110_000]);
        $padi = Cluster::factory()->create(['name' => 'PADI']);
        Cluster::factory()->create(['name' => 'KAPAS']);

        $import = new CustomerImport();
        $import->import(base_path('tests/fixtures/sample_pelanggan.xlsx'));

        $this->assertSame(10, Customer::count());
        $this->assertSame(10, $import->imported);
        // Cluster ter-resolve dari kolom CLUSTER; unknown/kosong → null.
        $this->assertGreaterThan(0, Customer::where('cluster_id', $padi->id)->count());
        $this->assertGreaterThan(0, Customer::whereNull('cluster_id')->count());
        // Baris WA scientific ter-normalisasi.
        $this->assertSame(0, Customer::where('whatsapp_number', 'like', '%E%')->count());
        // Tanggal tagih terisi dari kolom TGL TAGIH.
        $this->assertGreaterThan(0, Customer::where('billing_day', '>', 1)->count());
    }

    public function testTemplateHeadingsMatchImportExpectation(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Package::factory()->create(['default_price' => 110_000]);

        // Template yang diunduh harus bisa langsung di-import kembali.
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, CustomerImport::templateContent());

        $import = new CustomerImport();
        $import->import($path);
        unlink($path);

        $this->assertGreaterThan(0, $import->imported);
        $this->assertSame([], $import->failures);
    }

    public function testImportModalMountsWithoutClusterField(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Modal import ter-mount tanpa error; cluster bukan lagi field wajib
        // di modal (pindah ke kolom CLUSTER di Excel). Tombol template ada
        // di dalam modal (SchemaActions di ListCustomers).
        Livewire::test(\App\Filament\Resources\Customers\Pages\ListCustomers::class)
            ->mountAction('import')
            ->assertActionMounted('import');
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
