<?php

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerScopeTest extends TestCase
{
    use RefreshDatabase;

    public function testFieldOfficerOnlySeesOwnClusterCustomers(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $ownCluster = Cluster::factory()->create(['officer_id' => $officer->id]);
        Customer::factory()->count(3)->create(['cluster_id' => $ownCluster->id]);
        Customer::factory()->count(5)->create(); // clusters lain

        $this->actingAs($officer);

        $this->assertSame(3, Customer::count());
    }

    public function testAdminSeesAllCustomers(): void
    {
        Customer::factory()->count(4)->create();

        $this->actingAs(User::factory()->admin()->create());

        $this->assertSame(4, Customer::count());
    }

    public function testBillableScopeExcludesTerminated(): void
    {
        Customer::factory()->count(2)->create(); // active
        Customer::factory()->suspended()->create();
        Customer::factory()->terminated()->count(2)->create();

        $this->assertSame(3, Customer::billable()->count());
    }

    public function testDueTodayScopeMatchesBillingDay(): void
    {
        $today = now()->day;
        Customer::factory()->create(['billing_day' => $today]);
        Customer::factory()->create(['billing_day' => $today === 1 ? 2 : 1]);

        $this->assertSame(1, Customer::dueToday()->count());
    }
}
