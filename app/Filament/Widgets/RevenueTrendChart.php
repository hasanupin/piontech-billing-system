<?php

namespace App\Filament\Widgets;

use App\Enums\Role;
use App\Services\BillingService;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Tren 12 bulan uang masuk, dipecah tunai vs transfer (sumber sama dengan
 * sparkline BillingStatsOverview). Sengaja BUKAN "tagihan vs terbayar":
 * billing_amount tersimpan di pelanggan (nilai hari ini), jadi sisi tagihan
 * akan datar dan menyesatkan untuk bulan-bulan lampau.
 */
class RevenueTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->isRole(Role::SuperAdmin, Role::Admin) ?? false;
    }

    public function getHeading(): ?string
    {
        return __('12-Month Revenue Trend');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * 12 bulan berakhir di periode terpilih.
     *
     * @return array{labels: array<int, string>, cash: array<int, float>, transfer: array<int, float>}
     */
    public function trend(): array
    {
        $billing = app(BillingService::class);
        $cursor = $this->periodStart()->subMonths(11);

        $labels = $cash = $transfer = [];

        for ($i = 0; $i < 12; $i++) {
            $summary = $billing->monthlySummary($cursor->copy());

            $labels[] = $cursor->translatedFormat('M y');
            $cash[] = $summary['cash'];
            $transfer[] = $summary['transfer'];

            $cursor->addMonth();
        }

        return ['labels' => $labels, 'cash' => $cash, 'transfer' => $transfer];
    }

    protected function getData(): array
    {
        $trend = $this->trend();

        return [
            'datasets' => [
                [
                    'label' => __('Cash'),
                    'data' => $trend['cash'],
                    'backgroundColor' => '#12b76a',
                    'borderColor' => 'transparent',
                ],
                [
                    'label' => __('Transfer'),
                    'data' => $trend['transfer'],
                    'backgroundColor' => '#06aed4',
                    'borderColor' => 'transparent',
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }

    protected function getOptions(): RawJs
    {
        // Ditumpuk: tinggi batang = total uang masuk bulan itu.
        return RawJs::make(<<<'JS'
            {
                scales: {
                    x: { stacked: true },
                    y: {
                        stacked: true,
                        ticks: {
                            callback: (value) => 'Rp ' + new Intl.NumberFormat('id-ID').format(value),
                        },
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ctx.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(ctx.parsed.y),
                        },
                    },
                },
            }
        JS);
    }

    private function periodStart(): Carbon
    {
        return Carbon::parse(($this->pageFilters['period'] ?? now()->format('Y-m')).'-01')->startOfMonth();
    }
}
