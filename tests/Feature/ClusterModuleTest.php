<?php

namespace Tests\Feature;

use App\Filament\Resources\Clusters\ClusterResource;
use App\Filament\Resources\Clusters\Pages\CreateCluster;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClusterModuleTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanCreateCluster(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateCluster::class)
            ->fillForm([
                'name' => 'Cluster Turen Timur',
                'officer_id' => $officer->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Cluster::where('name', 'Cluster Turen Timur')->exists());
    }

    public function testFieldOfficerCannotAccessClusterResource(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(ClusterResource::getUrl('index'))->assertForbidden();
    }

    public function testSuperAdminCanAccessClusterResource(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(ClusterResource::getUrl('index'))->assertOk();
    }

    public function testChangingClusterOfficerMovesCustomerVisibility(): void
    {
        $budi = User::factory()->fieldOfficer()->create();
        $andi = User::factory()->fieldOfficer()->create();
        $cluster = Cluster::factory()->create(['officer_id' => $budi->id]);
        Customer::factory()->count(5)->create(['cluster_id' => $cluster->id]);

        // Before: Budi sees 5 (global scope filters by cluster officer), Andi sees 0.
        $this->actingAs($budi);
        $this->assertSame(5, Customer::count());
        $this->actingAs($andi);
        $this->assertSame(0, Customer::count());

        // Change PIC to Andi.
        $cluster->update(['officer_id' => $andi->id]);

        // After: Andi sees 5, Budi sees 0.
        $this->actingAs($andi);
        $this->assertSame(5, Customer::count());
        $this->actingAs($budi);
        $this->assertSame(0, Customer::count());
    }

    /**
     * Satu petugas boleh memegang lebih dari satu daerah: `clusters.officer_id`
     * sengaja tidak unique. Pelanggan, komisi, dan setoran menggabungkan
     * seluruh daerah yang dipegangnya.
     */
    public function testOneOfficerCanHoldSeveralDaerah(): void
    {
        $officer = User::factory()->fieldOfficer()->create(['commission_per_customer' => 4000]);
        $a = Cluster::factory()->create(['name' => 'Daerah Utara', 'officer_id' => $officer->id]);
        $b = Cluster::factory()->create(['name' => 'Daerah Selatan', 'officer_id' => $officer->id]);

        foreach ([$a, $b] as $daerah) {
            $customer = Customer::factory()->create([
                'cluster_id' => $daerah->id,
                'billing_amount' => 100_000,
            ]);
            Transaction::factory()->create([
                'customer_id' => $customer->id,
                'officer_id' => $officer->id,
                'payment_method' => 'cash',
                'period' => now()->startOfMonth(),
                'billed_amount' => 100_000,
                'paid_amount' => 100_000,
            ]);
        }

        $this->assertSame(2, $officer->clusters()->count());

        // Petugas melihat pelanggan dari kedua daerah.
        $this->actingAs($officer);
        $this->assertSame(2, Customer::query()->count());

        // Komisi & setoran menjumlahkan kedua daerah.
        $billing = app(BillingService::class);
        $this->assertSame(8_000.0, $billing->commissionQuery(now()->startOfMonth())
            ->find($officer->getKey())->commission_amount);
        $this->assertSame(200_000.0, $billing->officerProgress($officer->id, now())['target']);
        $this->assertSame(200_000.0, $billing->officerProgress($officer->id, now())['collected']);
    }
}
