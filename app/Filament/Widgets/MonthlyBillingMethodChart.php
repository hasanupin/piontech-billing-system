<?php

namespace App\Filament\Widgets;

/**
 * Pie Metode Bayar: nominal tunai vs transfer pada periode & cluster terpilih
 * di halaman Tagihan Bulanan.
 */
class MonthlyBillingMethodChart extends MonthlyBillingPieChart
{
    public function getHeading(): ?string
    {
        return __('Payment Method');
    }

    protected function slices(): array
    {
        $progress = $this->progress();

        return [
            'labels' => [__('Cash'), __('Transfer')],
            'data' => [$progress['cash'], $progress['transfer']],
            'colors' => ['#22d3ee', '#0ba5ec'],
        ];
    }
}
