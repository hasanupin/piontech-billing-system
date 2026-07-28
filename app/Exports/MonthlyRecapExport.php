<?php

namespace App\Exports;

use App\Enums\CustomerStatus;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Services\BillingService;

/**
 * Rekap Pembayaran — angka bersumber BillingService::rangeSummary agar
 * konsisten dengan dashboard; filter berbasis paid_at dalam rentang.
 */
class MonthlyRecapExport extends BaseExport
{
    public function rows(): array
    {
        $summary = app(BillingService::class)->rangeSummary($this->from, $this->until);

        $aktif = Customer::billable()->where('status', CustomerStatus::Active)->count();
        $isolir = Customer::billable()->where('status', CustomerStatus::Suspended)->count();
        $ditagih = $aktif + $isolir;
        $lunas = Customer::billable()->whereHas(
            'transactions',
            fn ($q) => $q->whereBetween('paid_at', [$this->from, $this->until])
                ->where('status', TransactionStatus::Paid),
        )->count();

        return [
            [__('Metric'), __('Value')],
            [__('Billed Customers'), $ditagih],
            [__('Paid Customers'), $lunas],
            [__('Success Rate'), round($lunas / max(1, $ditagih) * 100, 1).'%'],
            [__('Cash Collected'), $this->rupiah($summary['cash'])],
            [__('Via Transfer'), $this->rupiah($summary['transfer'])],
            [__('Total Collected'), $this->rupiah($summary['total_collected'])],
            [__('Total Deposited'), $this->rupiah($summary['total_deposited'])],
            [__('Held By Officers'), $this->rupiah($summary['held_by_officers'])],
        ];
    }
}
