<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsMonthlyBillingFilters;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Kerangka bersama 3 pie halaman Tagihan Bulanan (Total Tagihan, Metode Bayar,
 * Setoran): doughnut nominal Rp yang mengikuti filter periode & cluster.
 * Subclass hanya mengisi heading + slices().
 */
abstract class MonthlyBillingPieChart extends ChartWidget
{
    use ReadsMonthlyBillingFilters;

    protected ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, float>, colors: array<int, string>}
     */
    abstract protected function slices(): array;

    protected function getData(): array
    {
        $slices = $this->slices();

        return [
            'datasets' => [
                [
                    'data' => $slices['data'],
                    'backgroundColor' => $slices['colors'],
                    // Transparan, bukan warna surface: jeda antar segmen tanpa
                    // jadi cincin gelap saat light mode.
                    'borderColor' => 'transparent',
                    'borderWidth' => 3,
                ],
            ],
            'labels' => $slices['labels'],
        ];
    }

    protected function getOptions(): RawJs
    {
        // RawJs, bukan array: tooltip perlu formatter rupiah — tanpa ini
        // nilainya tampil 5000000 mentah.
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ctx.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(ctx.parsed),
                        },
                    },
                },
            }
        JS);
    }
}
