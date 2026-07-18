<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionStatus;
use App\Models\Customer;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Doughnut sudah vs belum bayar — setara chart prosentase Excel (TASK-10).
 */
class PaymentChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $maxHeight = '250px';

    public function getHeading(): ?string
    {
        return __('Payment Percentage');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $period = Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();

        $ditagih = Customer::billable()->count();
        $lunas = Customer::billable()->whereHas(
            'transactions',
            fn ($q) => $q->forPeriod($period)->where('status', TransactionStatus::Paid),
        )->count();

        return [
            'datasets' => [
                [
                    'data' => [$lunas, max(0, $ditagih - $lunas)],
                    // Warna success & danger dari palet panel.
                    'backgroundColor' => ['#12b76a', '#f04438'],
                ],
            ],
            'labels' => [__('Paid'), __('Unpaid')],
        ];
    }
}
