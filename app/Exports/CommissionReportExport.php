<?php

namespace App\Exports;

use App\Models\User;
use App\Services\BillingService;

/**
 * Komisi per petugas dalam rentang tanggal (basis paid_at, beda dari halaman
 * Komisi yang berbasis periode tagihan). Semua petugas tampil, termasuk yang
 * belum menghasilkan komisi.
 */
class CommissionReportExport extends BaseExport
{
    public function rows(): array
    {
        $rows = [[
            __('Officer'),
            __('Paid Customers'),
            __('Commission Per Customer'),
            __('Commission Amount'),
        ]];

        $officers = app(BillingService::class)
            ->commissionRangeQuery($this->from, $this->until)
            ->get()
            ->sortByDesc('commission_amount');

        foreach ($officers as $officer) {
            $rows[] = [
                $officer->name,
                (int) $officer->paid_customers,
                $this->rupiah((float) $officer->commission_per_customer),
                $this->rupiah($officer->commission_amount),
            ];
        }

        return $rows;
    }

    /** Total komisi rentang ini — dipakai subjudul halaman. */
    public function total(): float
    {
        return (float) app(BillingService::class)
            ->commissionRangeQuery($this->from, $this->until)
            ->get()
            ->sum(fn (User $officer): float => $officer->commission_amount);
    }
}
