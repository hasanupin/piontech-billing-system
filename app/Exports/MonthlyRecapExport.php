<?php

namespace App\Exports;

use App\Enums\CustomerStatus;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Services\BillingService;

/**
 * Rekap Pembayaran Bulanan — angka bersumber BillingService agar
 * konsisten dengan dashboard.
 */
class MonthlyRecapExport extends BaseExport
{
    public function rows(): array
    {
        $service = app(BillingService::class);
        $current = $service->monthlySummary($this->period);
        $previous = $service->monthlySummary($this->period->copy()->subMonth());

        $aktif = Customer::billable()->where('status', CustomerStatus::Active)->count();
        $isolir = Customer::billable()->where('status', CustomerStatus::Suspended)->count();
        $ditagih = $aktif + $isolir;
        $lunas = Customer::billable()->whereHas(
            'transactions',
            fn ($q) => $q->forPeriod($this->period)->where('status', TransactionStatus::Paid),
        )->count();

        return [
            [__('Metric'), __('This Period'), __('Last Month')],
            [__('Billed Customers'), $ditagih, ''],
            [__('Paid Customers'), $lunas, ''],
            [__('Success Rate'), round($lunas / max(1, $ditagih) * 100, 1).'%', ''],
            [__('Cash Collected'), $this->rupiah($current['cash']), $this->rupiah($previous['cash'])],
            [__('Via Transfer'), $this->rupiah($current['transfer']), $this->rupiah($previous['transfer'])],
            [__('Total Collected'), $this->rupiah($current['total_collected']), $this->rupiah($previous['total_collected'])],
            [__('Total Deposited'), $this->rupiah($current['total_deposited']), $this->rupiah($previous['total_deposited'])],
            [__('Held By Officers'), $this->rupiah($current['held_by_officers']), $this->rupiah($previous['held_by_officers'])],
        ];
    }
}
