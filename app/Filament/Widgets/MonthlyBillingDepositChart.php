<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ReadsMonthlyBillingFilters;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Rekap uang periode & cluster terpilih di halaman Tagihan Bulanan, 8 batang
 * dalam 3 kelompok: Tagihan (nominal/terbayar/belum), Metode (tunai/transfer),
 * Setoran (harus/sudah/belum disetor).
 *
 * "Harus disetor" = seluruh tagihan yang tidak dibayar transfer, jadi "belum
 * disetor" = uang yang belum sampai ke admin — termasuk yang belum ditarik
 * petugas. Lihat BillingService::billingProgress().
 */
class MonthlyBillingDepositChart extends ChartWidget
{
    use ReadsMonthlyBillingFilters;

    protected ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 3;

    public function getHeading(): ?string
    {
        return __('Billing & Deposit Recap');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Satu sumber untuk batang: [key billingProgress(), label, kelompok, warna palet].
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private function bars(): array
    {
        return [
            ['billed_amount', __('Billed Amount'), __('Bill'), '#0891b2'],
            ['paid_amount', __('Paid'), __('Bill'), '#12b76a'],
            ['outstanding', __('Unpaid'), __('Bill'), '#f04438'],
            ['cash', __('Cash'), __('Method'), '#22d3ee'],
            ['transfer', __('Transfer'), __('Method'), '#0ba5ec'],
            ['must_deposit', __('Must Deposit'), __('Deposit'), '#f79009'],
            ['deposited', __('Deposited'), __('Deposit'), '#12b76a'],
            ['not_deposited', __('Not Deposited'), __('Deposit'), '#f04438'],
        ];
    }

    protected function getData(): array
    {
        $progress = $this->progress();
        $bars = $this->bars();

        return [
            'datasets' => [
                [
                    'label' => __('Amount'),
                    'data' => array_map(fn (array $bar): float => $progress[$bar[0]], $bars),
                    'backgroundColor' => array_column($bars, 3),
                    'borderWidth' => 0,
                ],
            ],
            // Label 2 baris: nama angka di atas, kelompoknya di bawah.
            'labels' => array_map(fn (array $bar): array => [$bar[1], $bar[2]], $bars),
        ];
    }

    protected function getOptions(): RawJs
    {
        // RawJs, bukan array: tick & tooltip perlu formatter rupiah — tanpa ini
        // sumbu Y menampilkan 5000000 mentah.
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
