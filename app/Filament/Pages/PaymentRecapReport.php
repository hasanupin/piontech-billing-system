<?php

namespace App\Filament\Pages;

use App\Exports\BaseExport;
use App\Exports\MonthlyRecapExport;
use BackedEnum;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;

class PaymentRecapReport extends AbstractRangeReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('Payment Recap');
    }

    public function getTitle(): string
    {
        return __('Payment Recap');
    }

    public function description(): string
    {
        return __('Billed, collected (cash/transfer), success rate');
    }

    /** Ringkasan layar saja — angka detailnya sudah ada di laporan lain. */
    public function hasExport(): bool
    {
        return false;
    }

    /** Isinya 8 baris metrik tetap. */
    public function isPaginated(): bool
    {
        return false;
    }

    protected function makeExport(Carbon $from, Carbon $until): BaseExport
    {
        return new MonthlyRecapExport($from, $until);
    }

    protected function filenamePrefix(): string
    {
        return 'rekap';
    }
}
