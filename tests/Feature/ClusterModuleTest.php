<?php

namespace Tests\Feature;

use App\Filament\Resources\Clusters\ClusterResource;
use App\Filament\Resources\Clusters\Pages\CreateCluster;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\User;
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
}
