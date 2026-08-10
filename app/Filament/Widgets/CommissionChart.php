<?php

namespace App\Filament\Widgets;

use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Komisi per penerima untuk periode terpilih di halaman Komisi.
 * Hanya penerima berkomisi > 0 yang jadi batang — tanpa cap jumlah, biar tidak
 * ada data yang diam-diam hilang; penerima nol tetap terlihat di tabelnya.
 */
class CommissionChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('Commission per Recipient');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $period = Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();

        $rows = app(BillingService::class)->commissionQuery($period)
            ->get()
            ->filter(fn ($recipient): bool => $recipient->commission_amount > 0)
            ->sortByDesc('commission_amount')
            ->values();

        return [
            'datasets' => [
                [
                    'label' => __('Commission Amount'),
                    'data' => $rows->map(fn ($recipient): float => $recipient->commission_amount)->all(),
                    'backgroundColor' => '#06b6d4',
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $rows->map(fn ($recipient): ?string => $recipient->display_name)->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => 'Rp ' + new Intl.NumberFormat('id-ID').format(ctx.parsed.y),
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => new Intl.NumberFormat('id-ID', {
                                notation: 'compact',
                                maximumFractionDigits: 1,
                            }).format(value),
                        },
                    },
                },
            }
        JS);
    }
}
