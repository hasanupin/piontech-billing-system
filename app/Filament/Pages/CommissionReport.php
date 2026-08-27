<?php

namespace App\Filament\Pages;

use App\Exports\BaseExport;
use App\Exports\CommissionReportExport;
use App\Filament\Widgets\BillingStatsOverview;
use BackedEnum;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;

class CommissionReport extends AbstractRangeReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('Commission Report');
    }

    public function getTitle(): string
    {
        return __('Commission Report');
    }

    public function description(): string
    {
        [$from, $until] = $this->resolvedRange();
        $total = (new CommissionReportExport($from, $until))->total();

        return __('Commission per officer based on paid customers').' — '
            .__('Total Commission').': '.BillingStatsOverview::rupiah($total);
    }

    protected function makeExport(Carbon $from, Carbon $until): BaseExport
    {
        return new CommissionReportExport($from, $until);
    }

    protected function filenamePrefix(): string
    {
        return 'komisi';
    }
}
