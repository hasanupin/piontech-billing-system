<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BillingStatsOverview;
use App\Filament\Widgets\DueTodayWidget;
use App\Filament\Widgets\OfficerDepositWidget;
use App\Filament\Widgets\PaymentChartWidget;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

/**
 * Dashboard dengan filter periode global — semua widget membaca
 * $this->pageFilters['period'] via InteractsWithPageFilters (TASK-10).
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label(__('Period'))
                ->options(self::periodOptions())
                ->default(now()->format('Y-m'))
                ->selectablePlaceholder(false),
        ]);
    }

    /**
     * Opsi periode: 12 bulan terakhir (pola sama dengan MonthlyBilling).
     *
     * @return array<string, string>
     */
    public static function periodOptions(): array
    {
        $options = [];
        $cursor = now()->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $options[$cursor->format('Y-m')] = $cursor->translatedFormat('F Y');
            $cursor->subMonth();
        }

        return $options;
    }

    public function getWidgets(): array
    {
        return [
            BillingStatsOverview::class,
            PaymentChartWidget::class,
            OfficerDepositWidget::class,
            DueTodayWidget::class,
        ];
    }
}
