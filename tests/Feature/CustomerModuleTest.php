<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
}
