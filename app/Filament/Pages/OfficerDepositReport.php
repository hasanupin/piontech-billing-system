<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Exports\BaseExport;
use App\Exports\OfficerDepositReportExport;
use App\Models\User;
use App\Services\BillingService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;

class OfficerDepositReport extends AbstractRangeReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('Officer Deposit Report');
    }

    public function getTitle(): string
    {
        return __('Officer Deposit Report');
    }

    public function description(): string
    {
        return __('Per officer: cash collected, deposit history, remaining balance');
    }

    protected function makeExport(Carbon $from, Carbon $until): BaseExport
    {
        return new OfficerDepositReportExport($from, $until);
    }

    /**
     * Preview = ringkasan per-petugas (kolom seragam); riwayat titip detail
     * hanya di Excel. rows() export ragged (ada section riwayat) → override.
     */
    public function previewRows(): array
    {
        [$from, $until] = $this->resolvedRange();
        $service = app(BillingService::class);

        $rows = [[__('Officer'), __('Cash Collected'), __('Total Deposited'), __('Remaining')]];

        foreach (User::where('role', Role::FieldOfficer)->orderBy('name')->get() as $officer) {
            $summary = $service->rangeSummary($from, $until, $officer->id);

            $rows[] = [
                $officer->name,
                'Rp '.number_format($summary['cash'], 0, ',', '.'),
                'Rp '.number_format($summary['total_deposited'], 0, ',', '.'),
                'Rp '.number_format($summary['held_by_officers'], 0, ',', '.'),
            ];
        }

        return $rows;
    }

    protected function filenamePrefix(): string
    {
        return 'setoran';
    }
}
