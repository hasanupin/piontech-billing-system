<?php

namespace App\Filament\Pages;

use App\Exports\ArrearsExport;
use App\Exports\BaseExport;
use BackedEnum;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;

class ArrearsReport extends AbstractRangeReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('Arrears Report');
    }

    public function getTitle(): string
    {
        return __('Arrears Report');
    }

    public function description(): string
    {
        return __('Unpaid & suspended customers with WhatsApp and cluster for follow-up');
    }

    protected function makeExport(Carbon $from, Carbon $until): BaseExport
    {
        return new ArrearsExport($from, $until);
    }

    protected function filenamePrefix(): string
    {
        return 'tunggakan';
    }
}
