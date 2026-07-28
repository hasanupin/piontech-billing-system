<?php

namespace App\Exports;

use App\Enums\Role;
use App\Models\OfficerDeposit;
use App\Models\User;
use App\Services\BillingService;

/**
 * Setoran per Petugas — total tagih tunai, riwayat titip (timestamp), sisa;
 * semua dalam rentang tanggal (paid_at / deposited_at).
 */
class OfficerDepositReportExport extends BaseExport
{
    public function rows(): array
    {
        $service = app(BillingService::class);

        $rows = [[__('Officer'), __('Cash Collected'), __('Total Deposited'), __('Remaining')]];

        foreach (User::where('role', Role::FieldOfficer)->orderBy('name')->get() as $officer) {
            $summary = $service->rangeSummary($this->from, $this->until, $officer->id);

            $rows[] = [
                $officer->name,
                $this->rupiah($summary['cash']),
                $this->rupiah($summary['total_deposited']),
                $this->rupiah($summary['held_by_officers']),
            ];
        }

        // Riwayat titip dengan timestamp (setara TITIP 1/2/3 di Excel lama).
        $rows[] = [''];
        $rows[] = [__('Deposit History')];
        $rows[] = [__('Officer'), __('Deposited At'), __('Amount'), __('Received By')];

        $deposits = OfficerDeposit::with(['officer', 'receiver'])
            ->whereBetween('deposited_at', [$this->from, $this->until])
            ->orderBy('deposited_at')
            ->get();

        foreach ($deposits as $deposit) {
            $rows[] = [
                $deposit->officer->name,
                $deposit->deposited_at?->format('d/m/Y H:i') ?? '',
                $this->rupiah((float) $deposit->amount),
                $deposit->receiver?->name ?? '',
            ];
        }

        return $rows;
    }
}
