<?php

namespace App\Filament\Widgets;

/**
 * Pie Total Tagihan: nominal terbayar vs belum dibayar pada periode & cluster
 * terpilih di halaman Tagihan Bulanan.
 */
class MonthlyBillingChart extends MonthlyBillingPieChart
{
    public function getHeading(): ?string
    {
        return __('Total Billing');
    }

    protected function slices(): array
    {
        $progress = $this->progress();

        return [
            'labels' => [__('Paid'), __('Unpaid')],
            'data' => [$progress['paid_amount'], $progress['outstanding']],
            // Warna success & danger dari palet panel.
            'colors' => ['#12b76a', '#f04438'],
        ];
    }
}
