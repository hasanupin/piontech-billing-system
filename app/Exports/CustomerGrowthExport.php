<?php

namespace App\Exports;

use App\Models\Customer;
use App\Models\Package;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pertumbuhan & churn pelanggan per bulan (registered_at / suspended_at /
 * terminated_at), plus komposisi pelanggan per paket — blok kedua khusus Excel.
 */
class CustomerGrowthExport extends BaseExport
{
    /**
     * Blok bulanan berkolom seragam — dipakai juga sebagai preview halaman.
     *
     * @return array<int, array<int, string|int|float>>
     */
    public function monthlyRows(): array
    {
        $rows = [[
            __('Month'),
            __('New Customers'),
            __('Newly Suspended'),
            __('Terminated'),
            __('Total Registered'),
        ]];

        $cursor = $this->from->copy()->startOfMonth();
        $lastMonth = $this->until->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($lastMonth)) {
            $start = $cursor->copy()->startOfMonth();
            $end = $cursor->copy()->endOfMonth();

            $rows[] = [
                $cursor->translatedFormat('F Y'),
                Customer::whereBetween('registered_at', [$start, $end])->count(),
                Customer::whereBetween('suspended_at', [$start, $end])->count(),
                Customer::whereBetween('terminated_at', [$start, $end])->count(),
                // Kumulatif: berapa pelanggan yang sudah terdaftar s/d akhir bulan itu.
                Customer::where('registered_at', '<=', $end)->count(),
            ];

            $cursor->addMonth();
        }

        return $rows;
    }

    public function rows(): array
    {
        $rows = $this->monthlyRows();

        $rows[] = [''];
        $rows[] = [__('Customers By Package')];
        $rows[] = [__('Package'), __('Speed'), __('Customers'), __('Monthly Billing')];

        $packages = Package::query()
            ->withCount(['customers as billable_count' => fn (Builder $q): Builder => $q->billable()])
            ->withSum(['customers as billing_total' => fn (Builder $q): Builder => $q->billable()], 'billing_amount')
            ->orderBy('name')
            ->get();

        foreach ($packages as $package) {
            $rows[] = [
                $package->name,
                $package->speed_mbps ? $package->speed_mbps.' Mbps' : '',
                (int) $package->billable_count,
                $this->rupiah((float) ($package->billing_total ?? 0)),
            ];
        }

        return $rows;
    }
}
