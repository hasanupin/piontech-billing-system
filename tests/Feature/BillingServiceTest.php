<?php

namespace Tests\Feature;

use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BillingService
    {
        return app(BillingService::class);
    }

    public function testMonthlySummaryExcludesTransferFromHeldByOfficers(): void
    {
        $period = now()->startOfMonth();
        $officer = User::factory()->fieldOfficer()->create();

        // tunai 500rb (via officer) + transfer 300rb + setor 200rb
        Transaction::factory()->create([
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'billed_amount' => 500000,
            'paid_amount' => 500000,
            'period' => $period,
        ]);
        Transaction::factory()->transfer()->create([
            'billed_amount' => 300000,
            'paid_amount' => 300000,
            'period' => $period,
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 200000,
            'period' => $period,
        ]);

        $summary = $this->service()->monthlySummary($period);

        $this->assertEquals(500000, $summary['cash']);
        $this->assertEquals(300000, $summary['transfer']);
        $this->assertEquals(800000, $summary['total_collected']);
        $this->assertEquals(200000, $summary['total_deposited']);
        // di petugas = tunai - setor = 300rb, BUKAN 600rb (transfer tidak ikut)
        $this->assertEquals(300000, $summary['held_by_officers']);
    }

    public function testOfficerRemainingBalanceIsCashMinusDeposits(): void
    {
        $period = now()->startOfMonth();
        $officer = User::factory()->fieldOfficer()->create();
        $other = User::factory()->fieldOfficer()->create();

        Transaction::factory()->create([
            'officer_id' => $officer->id,
            'payment_method' => 'cash',
            'billed_amount' => 400000,
            'paid_amount' => 400000,
            'period' => $period,
        ]);
        Transaction::factory()->create([
            'officer_id' => $other->id,
            'payment_method' => 'cash',
            'billed_amount' => 999000,
            'paid_amount' => 999000,
            'period' => $period,
        ]);
        OfficerDeposit::factory()->create([
            'officer_id' => $officer->id,
            'amount' => 150000,
            'period' => $period,
        ]);

        $this->assertEquals(
            250000,
            $this->service()->officerRemainingBalance($officer->id, $period),
        );
    }

    public function testMonthlySummaryIgnoresOtherPeriods(): void
    {
        $period = now()->startOfMonth();

        Transaction::factory()->create([
            'payment_method' => 'cash',
            'billed_amount' => 100000,
            'paid_amount' => 100000,
            'period' => $period->copy()->subMonth(),
        ]);

        $summary = $this->service()->monthlySummary($period);

        $this->assertEquals(0, $summary['cash']);
    }
}
