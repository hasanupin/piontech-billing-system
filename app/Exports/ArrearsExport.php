<?php

namespace App\Exports;

use App\Enums\TransactionStatus;
use App\Models\Customer;

/**
 * Daftar Tunggakan / ISOLIR — pelanggan billable tanpa pembayaran lunas
 * dalam rentang tanggal, dengan durasi isolir, WA, dan cluster untuk follow-up.
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
                ->whereBetween('paid_at', [$this->from, $this->until])
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
                $customer->cluster?->name ?? '',
                $this->rupiah((float) $customer->billing_amount),
            ];
        }

        return $rows;
    }
}
