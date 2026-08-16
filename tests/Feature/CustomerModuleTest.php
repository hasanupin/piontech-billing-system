<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Cluster;
use App\Models\CommissionRecipient;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Services\ScopeService;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function testFieldOfficerCanAccessCustomerResource(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(CustomerResource::getUrl('index'))->assertOk();
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

    public function testFieldOfficerCanCreateCustomerInOwnCluster(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);
        $this->actingAs($officer);

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Pelanggan Lapangan',
                'cluster_id' => $cluster->id,
                'billing_amount' => '150000',
                'billing_day' => 5,
                'registered_at' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $customer = Customer::where('name', 'Pelanggan Lapangan')->firstOrFail();
        $this->assertSame($cluster->id, $customer->cluster_id);
        // Field status disabled untuk petugas → jatuh ke default kolom.
        $this->assertSame(CustomerStatus::Active, $customer->status);
    }

    public function testFieldOfficerCannotCreateCustomerInOtherOfficerCluster(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        Cluster::factory()->create(['officer_id' => $officer->id]);
        $otherCluster = Cluster::factory()->create([
            'officer_id' => User::factory()->fieldOfficer()->create()->id,
        ]);
        $this->actingAs($officer);

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Nyelonong',
                'cluster_id' => $otherCluster->id,
                'billing_amount' => '150000',
                'billing_day' => 5,
                'registered_at' => now()->toDateString(),
            ])
            ->call('create')
            ->assertHasFormErrors(['cluster_id']);

        $this->assertDatabaseMissing('customers', ['name' => 'Nyelonong']);
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

    public function testFieldOfficerCanEditCustomerInOwnCluster(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);
        $customer = Customer::factory()->create(['cluster_id' => $cluster->id]);
        $this->actingAs($officer);

        Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->fillForm(['whatsapp_number' => '81200000000'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('81200000000', $customer->refresh()->whatsapp_number);
    }

    public function testFieldOfficerCannotEditCustomerOutsideOwnCluster(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        Cluster::factory()->create(['officer_id' => $officer->id]);
        $customer = Customer::factory()->create();

        $this->actingAs($officer);
        $this->assertFalse($officer->can('update', $customer));

        // Global scope cluster ikut berlaku di route-model binding → 404 sebelum policy.
        $this->get(CustomerResource::getUrl('edit', ['record' => $customer]))->assertNotFound();
    }

    public function testFieldOfficerCannotChangeCustomerStatus(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $officer->id]);
        $customer = Customer::factory()->suspended()->create(['cluster_id' => $cluster->id]);
        $this->actingAs($officer);

        Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->fillForm(['status' => CustomerStatus::Active->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(CustomerStatus::Suspended, $customer->refresh()->status);
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

    public function testCustomerCanBeSavedWithoutReferral(): void
    {
        $cluster = Cluster::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Tanpa Referal',
                'cluster_id' => $cluster->id,
                'billing_amount' => '100000',
                'billing_day' => 5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Customer::firstWhere('name', 'Tanpa Referal')->referral_id);
    }

    public function testAdminCanSetCustomerReferral(): void
    {
        $recipient = CommissionRecipient::factory()->create();
        $cluster = Cluster::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Dengan Referal',
                'cluster_id' => $cluster->id,
                'billing_amount' => '100000',
                'billing_day' => 5,
                'referral_id' => $recipient->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            $recipient->id,
            Customer::firstWhere('name', 'Dengan Referal')->referral_id,
        );
    }

    public function testSelfReferralAndInactiveRecipientsAreNotOfferedAsOptions(): void
    {
        $customer = Customer::factory()->create();
        // Penerima yang mirror ke pelanggan ini sendiri → tidak boleh jadi referalnya.
        $self = CommissionRecipient::factory()->customerType($customer)->create();
        $inactive = CommissionRecipient::factory()->inactive()->create();
        $valid = CommissionRecipient::factory()->create();
        $mirrored = CommissionRecipient::factory()
            ->customerType(Customer::factory()->create(['name' => 'Pelanggan Referal']))
            ->create();
        $this->actingAs(User::factory()->admin()->create());

        $options = $this->referralOptions($customer);

        $this->assertArrayHasKey($valid->id, $options);
        $this->assertArrayNotHasKey($self->id, $options);
        $this->assertArrayNotHasKey($inactive->id, $options);
        // Label penerima tipe Pelanggan ikut mirror ke nama pelanggannya.
        $this->assertSame('Pelanggan Referal', $options[$mirrored->id]);
    }

    /**
     * Opsi select Referal pada form edit pelanggan.
     *
     * @return array<string, string>
     */
    private function referralOptions(Customer $customer): array
    {
        $components = Livewire::test(EditCustomer::class, ['record' => $customer->getKey()])
            ->instance()
            ->getSchema('form')
            ->getFlatComponents();

        $select = collect($components)->first(
            fn ($component): bool => $component instanceof Select && $component->getName() === 'referral_id',
        );

        return $select->getOptions();
    }
}
