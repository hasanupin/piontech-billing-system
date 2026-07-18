<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionRulesTest extends TestCase
{
    use RefreshDatabase;

    public function testSetsStatusPaidWhenPaidCoversBilled(): void
    {
        $t = Transaction::factory()->create([
            'billed_amount' => 110000,
            'paid_amount' => 110000,
        ]);

        $this->assertSame(TransactionStatus::Paid, $t->status);
    }

    public function testSetsStatusPartialWhenPaidBelowBilled(): void
    {
        $t = Transaction::factory()->create([
            'billed_amount' => 110000,
            'paid_amount' => 50000,
        ]);

        $this->assertSame(TransactionStatus::Partial, $t->status);
    }

    public function testSetsStatusUnpaidWhenNothingPaid(): void
    {
        $t = Transaction::factory()->create([
            'billed_amount' => 110000,
            'paid_amount' => 0,
        ]);

        $this->assertSame(TransactionStatus::Unpaid, $t->status);
    }

    public function testNullifiesOfficerOnTransfer(): void
    {
        $t = Transaction::factory()->create([
            'payment_method' => 'transfer',
            'officer_id' => User::factory()->fieldOfficer()->create()->id,
        ]);

        $this->assertNull($t->officer_id);
    }

    public function testKeepsOfficerOnCash(): void
    {
        $officer = User::factory()->fieldOfficer()->create();

        $t = Transaction::factory()->create([
            'payment_method' => 'cash',
            'officer_id' => $officer->id,
        ]);

        $this->assertSame($officer->id, $t->officer_id);
    }
}
