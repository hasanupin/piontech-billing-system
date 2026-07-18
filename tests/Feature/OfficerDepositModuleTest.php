<?php

namespace Tests\Feature;

use App\Filament\Resources\OfficerDeposits\OfficerDepositResource;
use App\Filament\Resources\OfficerDeposits\Pages\CreateOfficerDeposit;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OfficerDepositModuleTest extends TestCase
{
    use RefreshDatabase;

    public function testCalculatesRemainingBalanceAfterMultipleDeposits(): void
    {
        $officer = User::factory()->fieldOfficer()->create();

        // Tunai terkumpul: 3.000.000 (3 x 1jt).
        Transaction::factory()->count(3)->create([
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'paid_amount' => 1_000_000,
            'period' => now()->startOfMonth(),
        ]);
        // TITIP 1: 1jt, TITIP 2: 500rb.
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 1_000_000,
            'period' => now()->startOfMonth(),
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 500_000,
            'period' => now()->startOfMonth(),
        ]);

        // Sisa = 3jt - 1,5jt = 1,5jt.
        $this->assertSame(
            1_500_000.0,
            app(BillingService::class)->officerRemainingBalance($officer->id, now()),
        );
    }

    public function testFieldOfficerCanCreateDepositForThemselves(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($officer);

        Livewire::test(CreateOfficerDeposit::class)
            ->fillForm([
                'amount' => '500000',
                'received_by' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $deposit = OfficerDeposit::first();
        $this->assertSame($officer->id, $deposit->officer_id);
    }

    public function testFieldOfficerCanAccessDepositResource(): void
    {
        $this->actingAs(User::factory()->fieldOfficer()->create());

        $this->get(OfficerDepositResource::getUrl('index'))->assertOk();
    }

    public function testFieldOfficerOnlySeesOwnDeposits(): void
    {
        $mine = User::factory()->fieldOfficer()->create();
        $other = User::factory()->fieldOfficer()->create();
        OfficerDeposit::factory()->count(2)->create(['officer_id' => $mine->id]);
        OfficerDeposit::factory()->count(3)->create(['officer_id' => $other->id]);

        $this->actingAs($mine);

        $query = OfficerDepositResource::getEloquentQuery();
        $this->assertSame(2, $query->count());
    }
}
