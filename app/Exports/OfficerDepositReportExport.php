<?php

namespace App\Exports;

use App\Enums\Role;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BillingService;

/**
 * Setoran per Petugas — total tagih tunai, riwayat titip (timestamp), sisa.
 */
class OfficerDepositReportExport extends BaseExport
{
    public function rows(): array
    {
        $service = app(BillingService::class);

        $rows = [[__('Officer'), __('Cash Collected'), __('Total Deposited'), __('Remaining')]];

        foreach (User::where('role', Role::FieldOfficer)->orderBy('name')->get() as $officer) {
            $cash = (float) Transaction::where('officer_id', $officer->id)
                ->forPeriod($this->period)->cash()->sum('paid_amount');
            $deposited = (float) OfficerDeposit::where('officer_id', $officer->id)
                ->whereDate('period', $this->period)->sum('amount');

            $rows[] = [
                $officer->name,
                $this->rupiah($cash),
                $this->rupiah($deposited),
                $this->rupiah($service->officerRemainingBalance($officer->id, $this->period)),
            ];
        }

        // Riwayat titip dengan timestamp (setara TITIP 1/2/3 di Excel lama).
        $rows[] = [''];
        $rows[] = [__('Deposit History')];
        $rows[] = [__('Officer'), __('Deposited At'), __('Amount'), __('Received By')];

        $deposits = OfficerDeposit::with(['officer', 'receiver'])
            ->whereDate('period', $this->period)
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
