<?php

namespace App\Exports;

use App\Enums\TransactionStatus;
use App\Models\Cluster;
use Illuminate\Database\Eloquent\Builder;

/**
 * Perbandingan antar cluster: berapa yang seharusnya tertagih vs yang benar-benar
 * masuk dalam rentang, plus jumlah penunggak — untuk melihat cluster/petugas
 * mana yang tertinggal.
 */
class ClusterReportExport extends BaseExport
{
    public function rows(): array
    {
        $rows = [[
            __('Cluster'),
            __('Officer'),
            __('Customers'),
            __('Billed'),
            __('Total Collected'),
            __('Collection Rate'),
            __('Arrears'),
        ]];

        // Tagihan tersimpan per bulan; rentang >1 bulan dikalikan jumlah bulannya.
        $months = $this->from->copy()->startOfMonth()->diffInMonths($this->until->copy()->startOfMonth()) + 1;

        $paidInRange = fn (Builder $query): Builder => $query
            ->whereBetween('transactions.paid_at', [$this->from, $this->until])
            ->where('transactions.status', TransactionStatus::Paid);

        $clusters = Cluster::query()
            ->with('officer')
            ->withCount(['customers as customers_count' => fn (Builder $q): Builder => $q->billable()])
            ->withSum(['customers as billing_total' => fn (Builder $q): Builder => $q->billable()], 'billing_amount')
            ->withSum(['transactions as collected' => $paidInRange], 'paid_amount')
            ->withCount(['customers as arrears_count' => fn (Builder $q): Builder => $q
                ->billable()
                ->whereDoesntHave('transactions', $paidInRange)])
            ->orderBy('name')
            ->get();

        foreach ($clusters as $cluster) {
            $billed = (float) ($cluster->billing_total ?? 0) * $months;
            $collected = (float) ($cluster->collected ?? 0);

            $rows[] = [
                $cluster->name,
                $cluster->officer?->name ?? '',
                (int) $cluster->customers_count,
                $this->rupiah($billed),
                $this->rupiah($collected),
                round($collected / max(1, $billed) * 100, 1).'%',
                (int) $cluster->arrears_count,
            ];
        }

        return $rows;
    }
}
