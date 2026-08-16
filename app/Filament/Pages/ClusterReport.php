<?php

namespace App\Filament\Pages;

use App\Exports\BaseExport;
use App\Exports\ClusterReportExport;
use BackedEnum;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;

class ClusterReport extends AbstractRangeReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('Cluster Report');
    }

    public function getTitle(): string
    {
        return __('Cluster Report');
    }

    public function description(): string
    {
        return __('Per cluster: customers, billed vs collected, collection rate, arrears');
    }

    protected function makeExport(Carbon $from, Carbon $until): BaseExport
    {
        return new ClusterReportExport($from, $until);
    }

    protected function filenamePrefix(): string
    {
        return 'cluster';
    }
}
