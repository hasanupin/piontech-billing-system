<?php

namespace App\Exports;

use App\Enums\TransactionStatus;
use App\Models\Customer;

/**
 * Daftar Tunggakan / ISOLIR — pelanggan billable yang belum bayar periode ini,
 * dengan durasi isolir, WA, dan cluster untuk aksi follow-up.
 */
class ArrearsExport extends BaseExport
{
    public function rows(): array
    {
        $rows = [[
            __('Name'),
            __('Status'),
            __('Suspended Days'),
            __('WhatsApp'),
            __('Cluster'),
            __('Amount'),
        ]];

        $customers = Customer::query()
            ->billable()
            ->whereDoesntHave('transactions', fn ($q) => $q
                ->forPeriod($this->period)
                ->where('status', TransactionStatus::Paid))
            ->with('cluster')
            ->orderBy('name')
            ->get();

        foreach ($customers as $customer) {
            $rows[] = [
                $customer->name,
                $customer->status->getLabel(),
                $customer->suspended_at ? (int) $customer->suspended_at->diffInDays(now()) : '',
                $customer->whatsapp_number ?? '',
                $customer->cluster->name,
                $this->rupiah((float) $customer->billing_amount),
            ];
        }

        return $rows;
    }
}
