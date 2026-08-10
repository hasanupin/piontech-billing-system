<?php

namespace Tests\Feature;

use App\Filament\Widgets\NetworkStatusStrip;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Strip status jaringan di puncak dashboard (TASK theming). Yang diuji adalah
 * angka yang dihitung — tampilannya (chip, meter, dot) urusan CSS.
 */
class NetworkStatusStripTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function metrics(array $params = []): array
    {
        return Livewire::test(NetworkStatusStrip::class, $params)->instance()->metrics();
    }

    public function testStripCountsActiveAndSuspendedSeparately(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Customer::factory()->count(2)->create();
        Customer::factory()->suspended()->create();
        // Terminated bukan pelanggan ditagih — tidak boleh masuk hitungan mana pun.
        Customer::factory()->terminated()->create();

        $metrics = $this->metrics();

        $this->assertSame(2, $metrics['active']);
        $this->assertSame(1, $metrics['suspended']);
        $this->assertSame(3, $metrics['billed']);
    }

    public function testStripCountsDueTodayExcludingPaid(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $due = Customer::factory()->count(3)->create(['billing_day' => now()->day]);
        Customer::factory()->create(['billing_day' => now()->day === 1 ? 28 : 1]);

        // Satu dari yang jatuh tempo sudah lunas bulan ini → sisa 2.
        Transaction::factory()->create([
            'customer_id' => $due->first()->id,
            'period' => now()->startOfMonth(),
        ]);

        $this->assertSame(2, $this->metrics()['due_today']);
    }

    public function testStripShowsHeldByOfficers(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $officer = User::factory()->fieldOfficer()->create();

        // Tunai 2jt + transfer 1jt − setor 500rb → di petugas 1,5jt (transfer tidak masuk).
        Transaction::factory()->count(2)->create([
            'officer_id' => $officer->id,
            'paid_amount' => 1_000_000,
        ]);
        Transaction::factory()->transfer()->create(['paid_amount' => 1_000_000]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 500_000,
            'period' => now()->startOfMonth(),
        ]);

        $this->assertSame(1_500_000.0, $this->metrics()['held_by_officers']);
    }

    public function testCollectionRateIsZeroWhenNoBillableCustomers(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // DB kosong: pembagian lunas/ditagih harus dijaga, bukan DivisionByZeroError.
        $metrics = $this->metrics();

        $this->assertSame(0, $metrics['billed']);
        $this->assertSame(0.0, $metrics['collection_rate']);
    }

    public function testCollectionRateReflectsPaidCustomers(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $customers = Customer::factory()->count(4)->create();
        Transaction::factory()->create([
            'customer_id' => $customers->first()->id,
            'period' => now()->startOfMonth(),
        ]);

        $this->assertSame(25.0, $this->metrics()['collection_rate']);
    }

    public function testFieldOfficerSeesOnlyOwnClusterAndCash(): void
    {
        $mine = User::factory()->fieldOfficer()->create();
        $other = User::factory()->fieldOfficer()->create();

        Customer::factory()->count(2)->create([
            'cluster_id' => Cluster::factory()->create(['officer_id' => $mine->id])->id,
        ]);
        Customer::factory()->count(3)->create([
            'cluster_id' => Cluster::factory()->create(['officer_id' => $other->id])->id,
        ]);

        Transaction::factory()->create(['officer_id' => $mine->id, 'paid_amount' => 750_000]);
        Transaction::factory()->create(['officer_id' => $other->id, 'paid_amount' => 250_000]);

        $this->actingAs($mine);
        $metrics = $this->metrics();

        $this->assertSame(2, $metrics['billed']);
        $this->assertSame(750_000.0, $metrics['held_by_officers']);
    }

    public function testStripFollowsPeriodFilter(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Transaction::factory()->create(['paid_amount' => 1_000_000]);
        Transaction::factory()->create([
            'paid_amount' => 400_000,
            'period' => now()->startOfMonth()->subMonth(),
            'paid_at' => now()->startOfMonth()->subMonth(),
        ]);

        $metrics = $this->metrics([
            'pageFilters' => ['period' => now()->startOfMonth()->subMonth()->format('Y-m')],
        ]);

        $this->assertSame(400_000.0, $metrics['held_by_officers']);
    }

    public function testStripRendersWithoutError(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Customer::factory()->count(2)->create();

        Livewire::test(NetworkStatusStrip::class)->assertOk();
    }
}
