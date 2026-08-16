<?php

namespace App\Exports;

use App\Models\CommissionRecipient;
use App\Services\BillingService;

/**
 * Komisi per penerima dalam rentang tanggal (basis paid_at, beda dari halaman
 * Komisi yang berbasis periode tagihan). Semua penerima tampil, termasuk yang
 * belum menghasilkan komisi.
 */
class CommissionReportExport extends BaseExport
{
    public function rows(): array
    {
        $rows = [[
            __('Name'),
            __('Type'),
            __('WhatsApp'),
            __('Commission Percentage'),
            __('Paid Transactions'),
            __('Commission Base'),
            __('Commission Amount'),
        ]];

        $recipients = app(BillingService::class)
            ->commissionRangeQuery($this->from, $this->until)
            ->get()
            ->sortByDesc('commission_amount');

        foreach ($recipients as $recipient) {
            $rows[] = [
                $recipient->display_name ?? '',
                $recipient->type->getLabel(),
                $recipient->display_whatsapp ?? '',
                (float) $recipient->commission_percent.'%',
                (int) $recipient->paid_count,
                $this->rupiah((float) ($recipient->paid_base ?? 0)),
                $this->rupiah($recipient->commission_amount),
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
            ->sum(fn (CommissionRecipient $recipient): float => $recipient->commission_amount);
    }
}
