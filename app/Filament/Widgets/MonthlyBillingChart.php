<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsMonthlyBillingFilters;
use Filament\Widgets\ChartWidget;

/**
 * Doughnut lunas vs belum bayar untuk periode & cluster terpilih
 * di halaman Tagihan Bulanan.
 */
class MonthlyBillingChart extends ChartWidget
{
    use ReadsMonthlyBillingFilters;

    protected ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = 1;

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
        $progress = $this->progress();

        return [
            'datasets' => [
                [
                    'data' => [$progress['paid'], $progress['unpaid']],
                    // Warna success & danger dari palet panel.
                    'backgroundColor' => ['#12b76a', '#f04438'],
                    // Transparan, bukan warna surface: jeda antar segmen tanpa
                    // jadi cincin gelap saat light mode.
                    'borderColor' => 'transparent',
                    'borderWidth' => 3,
                ],
            ],
            'labels' => [__('Paid'), __('Unpaid')],
        ];
    }
}
