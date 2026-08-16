<?php

namespace App\Filament\Pages;

use App\Exports\BaseExport;
use App\Exports\CustomerGrowthExport;
use BackedEnum;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;

class CustomerGrowthReport extends AbstractRangeReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('Customer Report');
    }

    public function getTitle(): string
    {
        return __('Customer Report');
    }

    public function description(): string
    {
        return __('New, suspended, and terminated customers per month; package breakdown in Excel');
    }

    /** Blok per-paket ragged → preview hanya blok bulanan. */
    public function previewRows(): array
    {
        [$from, $until] = $this->resolvedRange();

        return (new CustomerGrowthExport($from, $until))->monthlyRows();
    }

    protected function makeExport(Carbon $from, Carbon $until): BaseExport
    {
        return new CustomerGrowthExport($from, $until);
    }

    protected function filenamePrefix(): string
    {
        return 'pelanggan';
    }
}
