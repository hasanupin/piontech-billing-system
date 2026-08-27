<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Services\ScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CustomerModuleTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanAccessCustomerResource(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(CustomerResource::getUrl('index'))->assertOk();
    }

    /** Petugas dilayani panel mobile /petugas — lihat FieldPanelTest. */
    public function testFieldOfficerCannotAccessAdminCustomerResource(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(CustomerResource::getUrl('index'))->assertForbidden();
    }

    public function testAdminCanMarkCustomerAsSuspendedWithSuspendedAt(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListCustomers::class)
            ->callTableAction('suspend', $customer);

        $customer->refresh();
        $this->assertSame(CustomerStatus::Suspended, $customer->status);
        $this->assertNotNull($customer->suspended_at);
    }

    public function testAdminCanRestoreSuspendedCustomerToActive(): void
    {
        $customer = Customer::factory()->suspended()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListCustomers::class)
            ->callTableAction('restore_active', $customer);

        $customer->refresh();
        $this->assertSame(CustomerStatus::Active, $customer->status);
        $this->assertNull($customer->suspended_at);
    }

    public function testFieldOfficerCannotSeeSuspendAction(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);
        $customer = Customer::factory()->create([
            'cluster_id' => $cluster->id,
            'status' => CustomerStatus::Active,
        ]);
        $this->actingAs($officer);

        Livewire::test(ListCustomers::class)
            ->assertTableActionHidden('suspend', $customer);
    }

    public function testStatusChangeStampsSuspendedAndTerminatedDates(): void
    {
        $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

        // Dicatat di model, bukan di quick action — form & import ikut terpakai.
        $customer->update(['status' => CustomerStatus::Suspended]);
        $this->assertNotNull($customer->fresh()->suspended_at);

        $customer->update(['status' => CustomerStatus::Terminated]);
        $customer->refresh();
        $this->assertNull($customer->suspended_at);
        $this->assertNotNull($customer->terminated_at);

        $customer->update(['status' => CustomerStatus::Active]);
        $this->assertNull($customer->fresh()->terminated_at);
    }

    public function testExplicitStatusDatesAreNotOverwritten(): void
    {
        // Impor & seeder mengirim tanggal aslinya — jangan ditimpa now().
        $customer = Customer::factory()->create([
            'status' => CustomerStatus::Suspended,
            'suspended_at' => now()->subMonths(2)->toDateString(),
        ]);

        $this->assertSame(
            now()->subMonths(2)->toDateString(),
            $customer->fresh()->suspended_at->toDateString(),
        );
    }

    public function testSuspendedCustomerStaysBillableButTerminatedDoesNot(): void
    {
        Customer::factory()->suspended()->create();
        Customer::factory()->terminated()->create();
        Customer::factory()->create(['status' => CustomerStatus::Active]);

        // billable() = active + suspended (isolir), terminated excluded.
        $this->assertSame(2, Customer::billable()->count());
    }

    public function testFieldOfficerOnlySeesOwnClusterCustomers(): void
    {
        $mine = User::factory()->fieldOfficer()->create();
        $other = User::factory()->fieldOfficer()->create();
        $myCluster = Cluster::factory()->create(['officer_id' => $mine->id]);
        $otherCluster = Cluster::factory()->create(['officer_id' => $other->id]);
        Customer::factory()->count(3)->create(['cluster_id' => $myCluster->id]);
        Customer::factory()->count(2)->create(['cluster_id' => $otherCluster->id]);

        $this->actingAs($mine);
        $this->assertSame(3, Customer::count());
    }

    public function testHidesCreateAnotherOnCreatePage(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Pilihan "lanjut atau berhenti" dipindah ke popup setelah simpan.
        $this->assertFalse(
            Livewire::test(CreateCustomer::class)->instance()->canCreateAnother(),
        );
    }

    public function testShowsPromptModalAfterCreate(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->createCustomer()
            // assertHasNoFormErrors() tidak bisa dipakai setelah popup pasca-simpan
            // termount: helper-nya mencari schema milik action, yang memang tidak ada.
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertActionMounted('createdPrompt');

        // Record tetap tersimpan walau redirect ditahan.
        $this->assertSame(1, Customer::count());
    }

    public function testPromptBackToListRedirectsToPinnedList(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->createCustomer()
            ->callMountedAction()
            ->assertRedirect(CustomerResource::getUrl('index', [
                'created' => Customer::first()->getKey(),
            ]));
    }

    public function testPromptCreateAnotherRedirectsToBlankForm(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->createCustomer()
            ->callMountedAction(['another' => true])
            ->assertRedirect(CustomerResource::getUrl('create'));
    }

    public function testPinsCreatedCustomerToFirstRow(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Tanpa defaultSort tabel ini urut ULID naik — record baru justru di bawah.
        $first = Customer::factory()->create();
        $pinned = Customer::factory()->create();

        Livewire::withQueryParams(['created' => $pinned->getKey()])
            ->test(ListCustomers::class)
            ->assertCanSeeTableRecords([$pinned, $first], inOrder: true);
    }

    private function createCustomer(): Testable
    {
        $cluster = Cluster::factory()->create();

        return Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Pelanggan Baru',
                'cluster_id' => $cluster->id,
                'billing_amount' => '150000',
                'billing_day' => 5,
            ])
            ->call('create');
    }

    public function testAuthorizeCustomerClusterRejectsForeignCluster(): void
    {
        // Lapisan kedua: validasi form bisa dilewati payload palsu, guard ini tidak.
        $officer = User::factory()->fieldOfficer()->create();
        $otherCluster = Cluster::factory()->create([
            'officer_id' => User::factory()->fieldOfficer()->create()->id,
        ]);

        $this->expectException(HttpException::class);

        app(ScopeService::class)->authorizeCustomerCluster($officer, $otherCluster->id);
    }

    /** Halaman edit-nya ada di panel mobile — assertion HTTP-nya di FieldPanelTest. */
    public function testFieldOfficerCannotUpdateCustomerOutsideOwnCluster(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        Cluster::factory()->create(['officer_id' => $officer->id]);
        $customer = Customer::factory()->create();

        $this->assertFalse($officer->can('update', $customer));
    }

    public function testFieldOfficerOnlySeesOwnClustersAsOptions(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        Cluster::factory()->create(['officer_id' => $officer->id]);
        Cluster::factory()->create([
            'officer_id' => User::factory()->fieldOfficer()->create()->id,
        ]);

        $scoped = app(ScopeService::class)->scopeClustersForUser(Cluster::query(), $officer);

        $this->assertSame(1, $scoped->count());
    }

    public function testFieldOfficerCannotDeleteCustomer(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);
        $customer = Customer::factory()->create(['cluster_id' => $cluster->id]);

        $this->assertFalse($officer->can('delete', $customer));
    }

    public function testSelectingPackagePrefillsBillingAmount(): void
    {
        $package = Package::factory()->create(['default_price' => 110000]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateCustomer::class)
            ->set('data.package_id', $package->id)
            ->assertSet('data.billing_amount', '110.000,00');
    }

    public function testChangingBillingAmountSwitchesToCustomPackage(): void
    {
        $package = Package::factory()->create(['default_price' => 110000]);
        $custom = Package::factory()->custom()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateCustomer::class)
            ->set('data.package_id', $package->id)
            ->set('data.billing_amount', '95000') // beda dari harga paket
            ->assertSet('data.package_id', $custom->id);
    }

    public function testAdminCanExportCustomersToExcel(): void
    {
        Customer::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListCustomers::class)
            ->callAction('export')
            ->assertFileDownloaded();
    }

    public function testFieldOfficerCannotExportCustomers(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        Livewire::test(ListCustomers::class)
            ->assertActionHidden('export');
    }

    /** Petugas read-only atas data pelanggan (PRD §6) — policy, bukan sekadar UI. */
    public function testFieldOfficerCannotCreateOrUpdateCustomer(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);
        $ownCustomer = Customer::factory()->create(['cluster_id' => $cluster->id]);

        $this->assertFalse($officer->can('create', Customer::class));
        // Bahkan di daerahnya sendiri.
        $this->assertFalse($officer->can('update', $ownCustomer));
        $this->assertFalse($officer->can('delete', $ownCustomer));
    }
}
