<?php

namespace App\Filament\Widgets;

/**
 * Pie Setoran: sudah vs belum disetor pada periode & cluster terpilih.
 *
 * "Belum disetor" = harus disetor − sudah disetor, dan "harus disetor" =
 * seluruh tagihan yang tidak dibayar transfer — termasuk yang belum ditarik
 * petugas. Beda dari held_by_officers (tunai − setor) milik Dashboard.
 * Lihat BillingService::billingProgress().
 */
class MonthlyBillingDepositChart extends MonthlyBillingPieChart
{
    public function getHeading(): ?string
    {
        return __('Deposit');
    }

    protected function slices(): array
    {
        $progress = $this->progress();

        return [
            'labels' => [__('Deposited'), __('Not Deposited')],
            'data' => [$progress['deposited'], $progress['not_deposited']],
            'colors' => ['#12b76a', '#f04438'],
        ];
    }
}
