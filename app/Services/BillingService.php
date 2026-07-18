<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use Carbon\Carbon;

/**
 * Formula keuangan billing. Kunci: uang transfer langsung settled ke rekening
 * Pion Tech — TIDAK pernah masuk perhitungan "uang di petugas".
 */
class BillingService
{
    /**
     * @return array{cash: float, transfer: float, total_collected: float, total_deposited: float, held_by_officers: float}
     */
    public function monthlySummary(Carbon $period): array
    {
        $p = $period->copy()->startOfMonth();

        $cash = (float) Transaction::forPeriod($p)->cash()->sum('paid_amount');
        $transfer = (float) Transaction::forPeriod($p)
            ->where('payment_method', PaymentMethod::Transfer)
            ->sum('paid_amount');
        $deposited = (float) OfficerDeposit::whereDate('period', $p)->sum('amount');

        return [
            'cash' => $cash,
            'transfer' => $transfer,
            'total_collected' => $cash + $transfer,
            'total_deposited' => $deposited,
            // Formula kunci: hanya tunai yang lewat petugas, dikurangi setoran.
            'held_by_officers' => $cash - $deposited,
        ];
    }

    public function officerRemainingBalance(int $officerId, Carbon $period): float
    {
        $p = $period->copy()->startOfMonth();

        $cash = (float) Transaction::where('officer_id', $officerId)
            ->forPeriod($p)->cash()->sum('paid_amount');
        $deposited = (float) OfficerDeposit::where('officer_id', $officerId)
            ->whereDate('period', $p)->sum('amount');

        return $cash - $deposited;
    }
}
